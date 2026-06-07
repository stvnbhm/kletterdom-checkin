#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT_DIR/.env"
export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-$(basename "$ROOT_DIR")}"

cd "$ROOT_DIR"

log()  { printf '\n==> %s\n' "$*"; }
fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

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
    tmp="$(mktemp)"
    awk -v key="$key" -v value="$value" '
        BEGIN { updated = 0 }
        index($0, key "=") == 1 { print key "=" value; updated = 1; next }
        { print }
        END { if (updated == 0) print key "=" value }
    ' "$ENV_FILE" > "$tmp"
    mv "$tmp" "$ENV_FILE"
}

ensure_env() {
    local key="$1" fallback="$2"
    [[ -z "$(env_value "$key")" ]] && set_env "$key" "$fallback"
}

random_hex() {
    if command -v openssl >/dev/null 2>&1; then
        openssl rand -hex 32
    else
        docker run --rm php:8.4-cli php -r 'echo bin2hex(random_bytes(32));'
    fi
}

random_secret() {
    if command -v openssl >/dev/null 2>&1; then
        openssl rand -hex 24
    else
        docker run --rm php:8.4-cli php -r 'echo bin2hex(random_bytes(24));'
    fi
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
    openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
        -keyout "$key" \
        -out "$cert" \
        -subj "/CN=kletterdom.local" \
        -addext "subjectAltName=${san}"
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
    docker compose exec -T app sh -c 'mkdir -p storage/sessions storage/throttle backups && chown -R www-data:www-data storage backups 2>/dev/null || chmod -R 775 storage backups'
}

start_services() {
    local args=(-d)
    [[ "${SKIP_BUILD:-0}" != "1" ]] && args+=(--build)
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

mkdir -p backups docker/ssl storage/sessions storage/throttle

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

ensure_env HASH_KEY "$(random_hex)"
ensure_env DB_PASSWORD "$(random_secret)"
ensure_env DB_ROOT_PASSWORD "$(random_secret)"

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
