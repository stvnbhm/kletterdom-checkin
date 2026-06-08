#!/usr/bin/env bash
# Repo auf origin/main setzen (Pi/Server). Behebt vorher Berechtigungen,
# falls Docker storage/ oder backups/ als www-data angelegt hat.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

need_fix=0
for path in storage storage/imports backups; do
    if [[ -e "$path" ]] && [[ ! -w "$path" ]]; then
        need_fix=1
        break
    fi
done

if [[ "$need_fix" == "1" ]]; then
    printf '\n==> storage/ oder backups/ nicht beschreibbar — setze Besitzer auf %s\n' "$(id -un)"
    if ! command -v sudo >/dev/null 2>&1; then
        printf 'ERROR: sudo nötig: chown -R $USER:$USER storage/ backups/\n' >&2
        exit 1
    fi
    sudo chown -R "$(id -u)":"$(id -g)" storage backups
    chmod 777 storage/imports 2>/dev/null || true
fi

git fetch origin
git reset --hard origin/main

printf '\n==> Sync OK: '
git log -1 --oneline
printf '\n'
