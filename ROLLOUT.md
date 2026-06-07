# Rollout — Frischstart (Produktion)

Der Neubau ersetzt die Laravel-App als **Frischstart**: keine Migration alter verschlüsselter `registrations`. Die alte DB bleibt unberührt für Rollback.

## Voraussetzungen

- Aktuelle **Members-CSV** vom Verein (Spalten: `Mitgliedsnummer; Nachname; Status; Betrag offen; Geburtsdatum`)
- Docker + Docker Compose auf dem Server
- Ports 80/443 frei (oder Laravel-Stack vorher stoppen)

## Checkliste

### 1. Vorbereitung

- [ ] Repo klonen nach `/opt/kletterdom-checkin` (oder gewünschter Pfad)
- [ ] `.env` anlegen (deploy.sh erstellt sie aus `.env.example`)
- [ ] `HASH_KEY` einmalig generieren und sichern:
  ```bash
  php -r "echo bin2hex(random_bytes(32)).PHP_EOL;"
  ```
- [ ] `APP_URL` auf die lokale IP/URL setzen (z. B. `https://192.168.178.54`)

### 2. Erst-Deploy

```bash
ADMIN_EMAIL=admin@example.com \
ADMIN_PASSWORD='…' \
APP_URL=https://192.168.x.x \
./scripts/deploy.sh
```

Das Script:
- nutzt die **im Repo enthaltene** `public/assets/css/app.css` (kein Node/npm auf dem Pi)
- startet MySQL + PHP-FPM + Nginx
- wendet `sql/schema.sql` an (leere Tabellen)
- installiert Composer-Deps im Container
- legt optional Admin an

CSS/JS nur bei Template-Änderungen neu bauen (Entwickler-Rechner):

```bash
npm install && npm run build    # oder: ./scripts/build-assets.sh
git add public/assets/css/app.css public/assets/js/
git commit -m "Rebuild assets"
```

### 3. Members importieren

```bash
docker compose exec -T app php bin/import-members /pfad/zur/mitglieder.csv
```

Bei fehlenden Mitgliedern in der CSV (werden inaktiv gesetzt):

```bash
docker compose exec -T app php bin/import-members /pfad/zur/mitglieder.csv --confirm-missing=12
```

### 4. Staff anlegen

```bash
docker compose exec -T app php bin/ensure-staff hallendienst@example.com \
  --password='…' --name='Hallendienst'
```

### 5. Smoke-Test

- [ ] `https://<host>/` — Startseite
- [ ] `/halle-register` — Registrierung (Gast + Mitglied)
- [ ] `/verify/<token>` — QR-Code sichtbar
- [ ] `/login` — Hallendienst-Login
- [ ] `/hallendienst` — nur Eingecheckte ohne Suche; Suche findet andere
- [ ] `/hallendienst/snapshot` — JSON-Polling
- [ ] `/self-checkin` — QR-Scanner (grün/blau direkt, orange/rot → Hallendienst)
- [ ] `/admin` — KPIs, Chart, CSV-Export, Member-Import

### 6. Laravel stoppen, neuer Stack übernimmt

```bash
cd /pfad/zur/alten-app && docker compose down
```

Neuer Stack läuft bereits auf 80/443.

### 7. Cron (Host-crontab)

```cron
0,15,30,45 * * * * cd /opt/kletterdom-checkin && docker compose exec -T app php cron/auto-checkout.php >> /var/log/kletterdom-cron.log 2>&1
0 3 * * * cd /opt/kletterdom-checkin && docker compose exec -T app php cron/database-backup.php >> /var/log/kletterdom-cron.log 2>&1
```

## Updates (ohne Image-Rebuild)

```bash
./scripts/sync-from-git.sh
docker compose up -d
```

Das Sync-Script setzt bei Bedarf `storage/`/`backups/` auf deinen User zurück und macht dann `git reset --hard origin/main`.

Manuell (falls nötig):

```bash
sudo chown -R "$USER:$USER" storage/ backups/
git fetch origin && git reset --hard origin/main
```

CSS/JS-Änderungen kommen per `git pull` mit — kein Build auf dem Pi nötig.

## Rollback

```bash
cd /opt/kletterdom-checkin && docker compose down
cd /pfad/zur/alten-laravel-app && docker compose up -d
```

Die alte DB wurde **nicht** verändert.

## Hinweise

- **Registrierungen** der alten App werden nicht übernommen — Nutzer registrieren sich neu.
- **HASH_KEY** muss stabil bleiben; bei Wechsel stimmt der Member-Abgleich nicht mehr.
- **Sessions**: alle Nutzer loggen sich nach Cutover einmal neu ein.
