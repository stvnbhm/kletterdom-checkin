# Sicherheit — Checkliste

Plain-PHP-Stack ohne Framework: folgende Schutzmechanismen sind **bewusst selbst implementiert** und müssen bei Änderungen erhalten bleiben.

## CSRF

- [x] Token in Session (`Kletterdom\Http\Csrf`)
- [x] Hidden-Field `_token` in allen POST-Formularen
- [x] JSON-Requests: Header `X-CSRF-TOKEN`
- [x] Vergleich mit `hash_equals()` (`CsrfMiddleware`)
- [x] 419-Antwort bei ungültigem Token (JS lädt Seite neu)

## SQL-Injection

- [x] **Ausschließlich Prepared Statements** in allen Repositories (`PDO::prepare`)
- [x] Kein String-Concatenation von User-Input in SQL

## XSS

- [x] Template-Output mit `htmlspecialchars(..., ENT_QUOTES)` (Layout, Listen, Formulare)
- [x] JSON in `<script>` nur via `json_encode(..., JSON_HEX_TAG | JSON_HEX_QUOT)`

## Sessions

- [x] PHP file sessions (`storage/sessions`, Docker-Volume)
- [x] `session.use_strict_mode`, `session.use_only_cookies`
- [x] `session.cookie_httponly`, `session.cookie_secure` (konfigurierbar)
- [x] `session.cookie_samesite` = Lax (Standard)
- [x] Session-Regeneration beim Login (`Auth::login`)

## Passwörter

- [x] `password_hash()` / `password_verify()` (bcrypt, cost 12)
- [x] Kein Web-Passwort-Reset — nur CLI (`bin/ensure-admin`, `bin/ensure-staff`)

## HMAC (`name_birth_hash`)

- [x] `hash_hmac('sha256', ...)` mit dediziertem `HASH_KEY` in `.env`
- [x] Key **niemals** wechseln ohne members-CSV-Reimport
- [x] Keine PII in `members`-Tabelle

## Rate Limiting

- [x] Datei-basiertes Throttling (`Kletterdom\Http\Throttle`)
- [x] Registrierung und Self-Check-in geschützt

## TLS & HTTP-Header (Nginx)

- [x] HTTPS-Redirect (Port 80 → 443)
- [x] `X-Content-Type-Options: nosniff`
- [x] `X-Frame-Options: SAMEORIGIN`
- [x] `Referrer-Policy: strict-origin-when-cross-origin`
- [x] `fastcgi_param HTTPS on` für PHP

## Frontend-Dependencies (SRI)

Vendorte JS-Dateien mit festen Versionen und Subresource-Integrity:

| Datei | Version | SRI (sha384) |
| ----- | ------- | ------------ |
| `html5-qrcode.min.js` | 2.3.8 | `sha384-c9d8RFSL+u3exBOJ4Yp3HUJXS4znl9f+z66d1y54ig+ea249SpqR+w1wyvXz/lk+` |
| `chart.umd.min.js` | 4.4.7 | `sha384-zYPBGXwO4633CABX/5Spf6emCKUJCfoOkhOMYyxMsatqQZPnDblmmOewfjsIVWCM` |

Nach `npm run vendor:js` SRI neu berechnen:

```bash
php scripts/compute-sri.php
```

## Vor Go-Live

```bash
composer audit          # Backend-Deps (endroid/qr-code)
npm audit               # Build-Deps (tailwind, html5-qrcode, chart.js)
```

- [ ] `.env` mit starken `DB_PASSWORD`, `DB_ROOT_PASSWORD`, `HASH_KEY`
- [ ] `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true` in Produktion
- [ ] Backups-Verzeichnis nur für berechtigte Benutzer lesbar
- [ ] Host-Cron für Auto-Checkout und DB-Backup eingerichtet
