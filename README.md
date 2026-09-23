# Kolekta

Barangay waste management web app: collection scheduling, truck dispatch alerts, and missed-pickup reporting. Built with PHP + MySQL (XAMPP), plain vanilla JS, no framework, no build step.

## What's included

- **Residents** sign up, pick their purok, and get:
  - Weekly collection calendar (per purok, per waste type)
  - Live "truck en route" dispatch alerts
  - Missed-pickup reporting (with optional photo)
  - Notifications inbox
- **Admins** get a console to:
  - Trigger dispatch alerts per purok
  - Set repeat weekly collection schedules
  - Publish barangay/zone notices and cancellations
  - Triage and resolve missed-pickup reports
- Landing page, sign-up/login with password strength + consent checkbox, legal pages (Privacy/Cookie/Terms), cookie notice, accessible reduced-motion support.

## Tech stack

- PHP 8, MariaDB/MySQL, Apache (XAMPP)
- PDO prepared statements, sessions + CSRF tokens, bcrypt passwords, locked uploads folder

---

## How to run at home

1. **Install XAMPP** (default path `C:\xampp`).
2. **Extract this project** into `C:\xampp\htdocs\` so the folder is `C:\xampp\htdocs\kolekta`.
3. **Double-click `run.bat`** inside that folder. It starts MySQL and Apache, creates/imports the database on first run, and opens the app in your browser.

Access the site at: `http://localhost/kolekta`

### Login credentials

| Role | Username | Email | Password |
|------|----------|-------|----------|
| Admin | `jayvie` | jayvie@kolekta.ph | `kolekta-admin-2026` |
| Admin | `spencer` | spencer@kolekta.ph | `kolekta-admin-2026` |
| Admin | `pau` | pau@kolekta.ph | `kolekta-admin-2026` |
| Resident | `daniel` | daniel@kolekta.ph | `kolekta-admin-2026` |
| Resident | `maria` | maria@kolekta.ph | `kolekta-admin-2026` |
| Resident | `jose` | jose@kolekta.ph | `kolekta-admin-2026` |
| Resident | `ana` | ana@kolekta.ph | `kolekta-admin-2026` |
| Resident | `pedro` | pedro@kolekta.ph | `kolekta-admin-2026` |
| Resident | `liza` | liza@kolekta.ph | `kolekta-admin-2026` |

Residents and admins use the same shared password `kolekta-admin-2026`.

## Manual setup (instead of run.bat)

1. Start **Apache** and **MySQL** from the XAMPP control panel.
2. Create a database named `kolekta` and import `db/kolekta.sql` — or from a terminal:
   ```
   php setup-db.php
   ```
3. Open `http://localhost/kolekta`.

## Useful commands

| Task | Command |
|------|---------|
| Re-seed a fresh copy of the data | `php setup-db.php --force` |
| Point to a different database | edit `api/config.php` (host/user/pass/name) |

## Notes for testing

- Photo uploads: XAMPP's default 2 MB upload limit is smaller than the app's 5 MB limit. To test photos, raise `upload_max_filesize` and `post_max_size` to `8M` in `C:\xampp\php\php.ini` and restart Apache.
- The three admin accounts can all be used interchangeably; the admin console is role-based.

## Code map

```
index.php        Landing page
login.php        Sign in         register.php    Sign up (resident)
user.php         Resident dashboard
admin.php        Admin console
api/             JSON endpoints (auth, dispatch, schedule, reports, ...)
inc/page.php     Shared page helpers, legal page layout, cookie notice
assets/js/       app (fetch/CSRF/toasts/animations), auth, user, admin, legal
assets/css/      kolekta.css (design system)
db/kolekta.sql   Schema + seed data
legal/           Privacy Policy, Cookie Policy, Terms of Service
uploads/reports/ Resident report photos (locked to images only)
setup-db.php     One-time database installer
run.bat          One-click launcher (Windows)
```