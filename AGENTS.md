# AGENTS.md

## Cursor Cloud specific instructions

### Product overview

Kletterdom Check-in is a single Dockerized PHP app (Nginx → PHP-FPM → MySQL) for gym hall registration, QR verification, staff check-in, and admin reporting. See `ROLLOUT.md` and `scripts/deploy.sh` for the canonical deployment flow.

### Required services

| Service | Start command | URL |
|---------|---------------|-----|
| Full stack | `./scripts/deploy.sh` (first time) or `docker compose up -d` | `https://localhost` (ports 80/443) |
| MySQL, PHP-FPM, Nginx | Started together via Compose | — |

**Docker daemon:** Cloud VMs may need `dockerd` started manually with fuse-overlayfs (`/etc/docker/daemon.json`). Use `sudo docker …` if the socket is not in the `docker` group yet.

### First-time / fresh setup

```bash
npm install && npm run build
APP_URL=https://localhost \
  ADMIN_EMAIL=admin@example.com ADMIN_PASSWORD='…' \
  ./scripts/deploy.sh
```

`deploy.sh` creates `.env` from `.env.example`, generates secrets, TLS certs, runs migrations, and optionally creates admin/staff users. MySQL’s first start can take several minutes.

### Day-to-day development

- **CSS/JS changes:** `npm run build` (or `npm run watch:css` for Tailwind). Rebuild writes to `public/assets/`.
- **Restart after code changes:** Usually not needed — PHP sources are volume-mounted. Run `docker compose up -d --build` only when `docker/Dockerfile` or Compose config changes.
- **Composer deps:** Installed inside the `app` container via `deploy.sh` or `docker compose exec -T app composer install --no-dev`.
- **Staff user:** `docker compose exec -T app php bin/ensure-staff hallendienst@example.com --password='…'`

### Lint / tests

There is **no** PHPUnit, ESLint, or npm test script in this repo. Useful manual checks:

- `docker compose exec -T app composer validate`
- `docker compose exec -T app php -l public/index.php` (and other changed `.php` files)
- HTTP smoke tests from `ROLLOUT.md` §6 (`/`, `/halle-register`, `/verify/<token>`, `/login`, `/hallendienst`, `/admin`)

### Gotchas

- Registration POST requires a valid CSRF session cookie and **≥3 seconds** after loading the form (anti-spam).
- `/self-checkin` requires an authenticated staff/admin session.
- `APP_URL` and `SSL_SUBJECT_ALT_NAME` in `.env` must match how you access the site (e.g. `https://localhost`).
- To reset the database volume: `RESET_DB_VOLUME=1 ./scripts/deploy.sh`.
