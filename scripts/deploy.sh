#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT_DIR/.env"
export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-$(basename "$ROOT_DIR")}"

cd "$ROOT_DIR"

log()  { printf '\n==> %s\n' "$*"; }
fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

on_err() {
    printf 'ERROR: Deploy abgebrochen in Zeile %s (Exit-Code %s)\n' "$1" "$2" >&2
    exit "$2"
}
trap 'on_err ${LINENO} $?' ERR

require_command() {
    command -v "$1" >/dev/null 2>&1 || fail "$1 is required but was not found."
}

env_value() {
    local key="$1"
    awk -v key="$key" '
        index($0, key "=") == 1 {
            sub(/^[^=]*=/, "", $0)
            sub(/[[:space:]]+#.*$/, "", $0)
            gsub(/^[[:space:]]+|[[:space:]]+$/, "", $0)
            gsub(/^"|"$/, "", $0)
            print
            exit
        }
    ' "$ENV_FILE"
}

set_env() {
    local key="$1" value="$2" tmp
    [[ -f "$ENV_FILE" ]] || fail ".env fehlt: $ENV_FILE"
    [[ -w "$ENV_FILE" ]] || fail ".env ist nicht beschreibbar: $ENV_FILE (evtl. sudo chown \$USER:$USER .env)"
    tmp="$(mktemp)"
    awk -v key="$key" -v value="$value" '
        BEGIN { updated = 0 }
        index($0, key "=") == 1 { print key "=" value; updated = 1; next }
        { print }
        END { if (updated == 0) print key "=" value }
    ' "$ENV_FILE" > "$tmp"
    mv "$tmp" "$ENV_FILE"
}

random_bytes_hex() {
    local bytes="$1"
    if command -v openssl >/dev/null 2>&1; then
        openssl rand -hex "$bytes"
        return 0
    fi
    if [[ -r /dev/urandom ]]; then
        dd if=/dev/urandom bs="$bytes" count=1 status=none 2>/dev/null | hexdump -ve '1/1 "%02x"'
        return 0
    fi
    if command -v php >/dev/null 2>&1; then
        php -r 'echo bin2hex(random_bytes((int) $argv[1]));' "$bytes"
        return 0
    fi
    if command -v python3 >/dev/null 2>&1; then
        python3 -c 'import secrets, sys; print(secrets.token_hex(int(sys.argv[1])))' "$bytes"
        return 0
    fi
    fail "Secrets können nicht erzeugt werden (openssl, php, python3 oder /dev/urandom nötig)."
}

ensure_env_if_empty() {
    local key="$1" bytes="$2"
    if [[ -n "$(env_value "$key")" ]]; then
        return 0
    fi
    log "Generating ${key}"
    set_env "$key" "$(random_bytes_hex "$bytes")"
}

host_from_url() {
    local url="$1" host
    host="${url#*://}"
    host="${host%%/*}"
    host="${host%%:*}"
    printf '%s' "$host"
}

subject_alt_name_for_host() {
    local host="$1"
    if [[ "$host" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        printf 'IP:%s,DNS:kletterdom.local' "$host"
    else
        printf 'DNS:%s,DNS:kletterdom.local' "$host"
    fi
}

ensure_ssl_certs() {
    local cert="$ROOT_DIR/docker/ssl/nginx.crt"
    local key="$ROOT_DIR/docker/ssl/nginx.key"
    local san
    san="$(env_value SSL_SUBJECT_ALT_NAME)"
    [[ -z "$san" ]] && san="DNS:kletterdom.local"

    if [[ -s "$cert" && -s "$key" ]]; then
        return 0
    fi

    command -v openssl >/dev/null 2>&1 || fail "openssl is required to generate TLS certificates in docker/ssl/"

    log "Generating self-signed TLS certificate (SAN: ${san})"
    if openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
        -keyout "$key" \
        -out "$cert" \
        -subj "/CN=kletterdom.local" \
        -addext "subjectAltName=${san}" 2>/dev/null; then
        return 0
    fi

    local cnf
    cnf="$(mktemp)"
    cat > "$cnf" <<EOF
[req]
distinguished_name = dn
x509_extensions = v3
prompt = no
[dn]
[v3]
subjectAltName = ${san}
EOF
    openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
        -keyout "$key" \
        -out "$cert" \
        -subj "/CN=kletterdom.local" \
        -extensions v3 -config "$cnf"
    rm -f "$cnf"
}

sql_escape() {
    printf '%s' "$1" | sed "s/'/''/g"
}

dbdata_volume_exists() {
    docker volume inspect "${COMPOSE_PROJECT_NAME}_dbdata" >/dev/null 2>&1
}

container_health_status() {
    docker inspect "$1" --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' 2>/dev/null \
        || printf 'missing'
}

mysql_exec_as_root() {
    docker compose exec -T -e MYSQL_PWD="$1" db mysql -u root "${@:2}"
}

mysql_app_user_ok() {
    docker compose exec -T -e MYSQL_PWD="$3" db \
        mysql -u "$1" "$2" -e "SELECT 1" >/dev/null 2>&1
}

require_db_secrets_in_env() {
    [[ -n "$(env_value DB_PASSWORD)" && -n "$(env_value DB_ROOT_PASSWORD)" ]] \
        || fail "DB_PASSWORD and DB_ROOT_PASSWORD must be non-empty in .env."
}

reset_db_volume_if_requested() {
    if [[ "${RESET_DB_VOLUME:-0}" != "1" ]]; then return 0; fi
    log "RESET_DB_VOLUME=1 — removing MySQL data volume"
    docker compose down --remove-orphans 2>/dev/null || true
    docker rm -f kletterdom-db 2>/dev/null || true
    docker volume rm "${COMPOSE_PROJECT_NAME}_dbdata" 2>/dev/null || true
    dbdata_volume_exists && fail "Could not remove MySQL volume. Run: docker compose down && docker volume rm ${COMPOSE_PROJECT_NAME}_dbdata"
}

wait_for_db_healthy() {
    local attempt status max="${DB_WAIT_ATTEMPTS:-90}"
    log "Waiting for MySQL (first start can take several minutes)"
    for attempt in $(seq 1 "$max"); do
        status="$(container_health_status kletterdom-db)"
        [[ "$status" == "healthy" ]] && { log "MySQL is healthy"; return 0; }
        [[ "$status" == "missing" ]] && fail "Container kletterdom-db is not running."
        if mysql_exec_as_root "$(env_value DB_ROOT_PASSWORD)" -e "SELECT 1" >/dev/null 2>&1; then
            log "MySQL accepts connections (Docker status: ${status})"
            return 0
        fi
        printf '  … database status: %s (%s/%s)\n' "$status" "$attempt" "$max"
        sleep 5
    done
    fail "MySQL did not become ready in time."
}

ensure_mysql_app_credentials() {
    local app_user app_pass root_pass db_name app_pass_sql
    app_user="$(env_value DB_USERNAME)"
    app_pass="$(env_value DB_PASSWORD)"
    root_pass="$(env_value DB_ROOT_PASSWORD)"
    db_name="$(env_value DB_DATABASE)"
    app_pass_sql="$(sql_escape "$app_pass")"

    require_db_secrets_in_env
    mysql_app_user_ok "$app_user" "$db_name" "$app_pass" && { log "MySQL app user OK"; return 0; }

    mysql_exec_as_root "$root_pass" -e "SELECT 1" >/dev/null 2>&1 \
        || fail "MySQL root password does not match existing volume. Try: RESET_DB_VOLUME=1 APP_URL=<url> ./scripts/deploy.sh"

    log "Synchronizing MySQL user ${app_user}"
    mysql_exec_as_root "$root_pass" -e "
        CREATE DATABASE IF NOT EXISTS \`${db_name}\`;
        CREATE USER IF NOT EXISTS '${app_user}'@'%' IDENTIFIED BY '${app_pass_sql}';
        ALTER USER '${app_user}'@'%' IDENTIFIED BY '${app_pass_sql}';
        GRANT ALL PRIVILEGES ON \`${db_name}\`.* TO '${app_user}'@'%';
        CREATE USER IF NOT EXISTS '${app_user}'@'localhost' IDENTIFIED BY '${app_pass_sql}';
        ALTER USER '${app_user}'@'localhost' IDENTIFIED BY '${app_pass_sql}';
        GRANT ALL PRIVILEGES ON \`${db_name}\`.* TO '${app_user}'@'localhost';
        FLUSH PRIVILEGES;
    "
    mysql_app_user_ok "$app_user" "$db_name" "$app_pass" || fail "MySQL user sync failed."
}

wait_for_app() {
    local attempt
    for attempt in $(seq 1 60); do
        if docker compose exec -T app php -r 'exit(extension_loaded("pdo_mysql") ? 0 : 1);' >/dev/null 2>&1; then
            return 0
        fi
        sleep 2
    done
    fail "PHP app container did not become ready. Check 'docker compose logs app'."
}

ensure_assets() {
    if [[ "${BUILD_ASSETS:-0}" == "1" ]]; then
        log "Building frontend assets via Docker (BUILD_ASSETS=1)"
        mkdir -p "$ROOT_DIR/public/assets/css"
        docker compose --profile build run --rm node sh -c 'npm ci && npm run build'
        [[ -s "$ROOT_DIR/public/assets/css/app.css" ]] \
            || fail "CSS-Build fehlgeschlagen: public/assets/css/app.css wurde nicht erzeugt."
        return 0
    fi

    [[ -s "$ROOT_DIR/public/assets/css/app.css" ]] \
        || fail "public/assets/css/app.css fehlt. Bitte git pull oder BUILD_ASSETS=1 ./scripts/deploy.sh ausführen."
    log "Using committed CSS (set BUILD_ASSETS=1 to rebuild)"
}

run_app_setup() {
    log "Installing Composer dependencies (if needed)"
    docker compose exec -T app composer install --no-dev --no-interaction --optimize-autoloader

    log "Applying database schema"
    docker compose exec -T app php bin/migrate

    log "Ensuring writable directories"
    docker compose exec -T app sh -c 'mkdir -p storage/sessions storage/throttle storage/imports backups && chown -R www-data:www-data storage/sessions storage/throttle 2>/dev/null || true && chmod 777 backups storage/imports 2>/dev/null || true'
    fix_storage_permissions
}

start_services() {
    local args=(-d)
    if [[ "${SKIP_BUILD:-0}" != "1" ]]; then
        args+=(--build)
    fi
    require_db_secrets_in_env
    log "Starting database"
    docker compose up "${args[@]}" db
    wait_for_db_healthy
    log "Starting application services"
    docker compose up "${args[@]}"
}

# ── Main ──────────────────────────────────────────────────────────────────────

require_command docker
docker compose version >/dev/null 2>&1 || fail "Docker Compose plugin is required."

[[ -f "$ENV_FILE" ]] || { log "Creating .env from .env.example"; cp .env.example "$ENV_FILE"; }

mkdir -p backups docker/ssl storage/sessions storage/throttle storage/imports

fix_storage_permissions() {
    mkdir -p storage/sessions storage/throttle storage/imports backups
    if [[ -w storage && -w storage/imports ]]; then
        chmod -R u+rwX storage backups 2>/dev/null || true
        chmod 777 storage/imports 2>/dev/null || true
        return 0
    fi
    log "storage/ gehört vermutlich www-data (Docker) — setze Besitzer auf $(id -un) für git/deploy"
    if command -v sudo >/dev/null 2>&1; then
        sudo chown -R "$(id -u)":"$(id -g)" storage backups
    else
        fail "storage/ ist nicht beschreibbar. Ausführen: sudo chown -R \$USER:\$USER storage/ backups/"
    fi
    chmod -R u+rwX storage backups 2>/dev/null || true
    chmod 777 storage/imports 2>/dev/null || true
}

fix_storage_permissions

if [[ -n "${APP_URL:-}" ]]; then
    set_env APP_URL "$APP_URL"
elif [[ "$(env_value APP_URL)" == *"192.168.x.x"* && -t 0 ]]; then
    read -r -p "App URL (e.g. https://192.168.178.54): " prompted
    [[ -n "$prompted" ]] && set_env APP_URL "$prompted"
fi

log "Ensuring production environment values"
set_env APP_ENV production
set_env APP_DEBUG false
set_env DB_HOST db
set_env DB_PORT 3306
set_env DB_DATABASE "${DB_DATABASE:-klettercheckin}"
set_env DB_USERNAME "${DB_USERNAME:-checkinuser}"

ensure_env_if_empty HASH_KEY 32
ensure_env_if_empty DB_PASSWORD 24
ensure_env_if_empty DB_ROOT_PASSWORD 24
log "Environment secrets OK"

app_host="$(host_from_url "$(env_value APP_URL)")"
ssl_san="$(env_value SSL_SUBJECT_ALT_NAME)"
if [[ -n "$app_host" && ( -z "$ssl_san" || "$ssl_san" == *"192.168.x.x"* ) ]]; then
    set_env SSL_SUBJECT_ALT_NAME "$(subject_alt_name_for_host "$app_host")"
fi

require_db_secrets_in_env
ensure_ssl_certs
ensure_assets
reset_db_volume_if_requested
start_services
ensure_mysql_app_credentials
wait_for_app
run_app_setup

if [[ -n "${ADMIN_EMAIL:-}" ]]; then
    admin_args=(php bin/ensure-admin "$ADMIN_EMAIL")
    [[ -n "${ADMIN_NAME:-}" ]]     && admin_args+=(--name="$ADMIN_NAME")
    [[ -n "${ADMIN_PASSWORD:-}" ]] && admin_args+=(--password="$ADMIN_PASSWORD")
    log "Ensuring admin user"
    docker compose exec -T app "${admin_args[@]}"
fi

if [[ -n "${STAFF_EMAIL:-}" ]]; then
    staff_args=(php bin/ensure-staff "$STAFF_EMAIL")
    [[ -n "${STAFF_NAME:-}" ]]     && staff_args+=(--name="$STAFF_NAME")
    [[ -n "${STAFF_PASSWORD:-}" ]] && staff_args+=(--password="$STAFF_PASSWORD")
    log "Ensuring staff user"
    docker compose exec -T app "${staff_args[@]}"
fi

log "Deployment complete"
log "Cron example (host crontab):"
log "  0,15,30,45 * * * * cd $ROOT_DIR && docker compose exec -T app php cron/auto-checkout.php"
log "  0 3 * * * cd $ROOT_DIR && docker compose exec -T app php cron/database-backup.php"
docker compose ps
