# vector7 — Realty Manage Portal

Multi-tenant SaaS for **layout promoters** (farmland → approved residential plots): public marketplace, App workspace (platform owner) and a Tenant workspace per promoter.

- **Stack:** PHP 8.3+, Laravel 13, MySQL 8 / MariaDB 10.6+, Blade + Tailwind CSS 3 (pre-built), plain JavaScript, Chart.js, dompdf, OpenSpout, smalot/pdfparser, PHP GD, Anthropic Claude API.
- **No Node.js, Redis, Supervisor or workers on the server.** Everything runs from **one cron entry every minute**.

This guide is written for a non-developer. Follow the steps in order.

---

## Contents

1. [What you need](#1-what-you-need)
2. [Run it on your computer (Laragon, Windows)](#2-run-it-on-your-computer-laragon-windows)
3. [Sign in and try it](#3-sign-in-and-try-it)
4. [Put it live on Hostinger](#4-put-it-live-on-hostinger)
5. [After going live: settings checklist](#5-after-going-live-settings-checklist)
6. [Updating the live site](#6-updating-the-live-site)
7. [Backups](#7-backups)
8. [Running the tests](#8-running-the-tests)
9. [Troubleshooting](#9-troubleshooting)
10. [Where things are](#10-where-things-are)

---

## 1. What you need

| For | You need |
|---|---|
| Your computer | Windows 10/11, **Laragon** (Full edition) with **PHP 8.3 or newer** and **MySQL 8 / MariaDB**, Git (optional) |
| Live site | A Hostinger plan with **SSH access** (Business shared hosting, or Cloud). PHP 8.3+, a MySQL database, a mailbox for sending email |
| Optional | An Anthropic API key (for AI features), Razorpay or Swipe keys (tenant subscription payments) |

Required PHP extensions (already on in Laragon and Hostinger): `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `zip`, `curl`, `xml`, `intl`.

---

## 2. Run it on your computer (Laragon, Windows)

### 2.1 Install Laragon and PHP 8.3

1. Download **Laragon Full** from <https://laragon.org/download/> and install it to `C:\laragon`.
2. Open Laragon. Right-click the Laragon window → **PHP** → check the version.
   - If it is lower than **8.3**: download the PHP 8.3 (or 8.4) **Thread Safe x64 zip** from <https://windows.php.net/download/>, unzip it into `C:\laragon\bin\php\` (for example `C:\laragon\bin\php\php-8.3.x-Win32-vs16-x64`), then right-click Laragon → **PHP** → **Version** → choose it.
3. Right-click Laragon → **PHP** → **Extensions** and make sure these are ticked: `gd`, `zip`, `intl`, `fileinfo`, `openssl`, `pdo_mysql`, `mbstring`, `curl`.
4. Click **Start All** (Apache + MySQL turn green).

### 2.2 Put the project in Laragon's `www` folder

Choose **one** of these:

- **From the zip:** unzip `vector7-app.zip` so that you get `C:\laragon\www\vector7\artisan` (the `artisan` file must be directly inside `vector7`).
- **From GitHub:** click **Terminal** in Laragon and run:
  ```
  cd C:\laragon\www
  git clone -b Test https://github.com/bizrajesh/vector7.git vector7
  ```

Laragon automatically creates the address **http://vector7.test** for the folder (right-click Laragon → **Apache** → **Reload** if it doesn't open).

### 2.3 Install and set up (one time)

Click **Terminal** in Laragon and run these commands one by one:

```
cd C:\laragon\www\vector7
composer install
copy .env.example .env
php artisan key:generate
```

> If the zip already contains a `vendor` folder, `composer install` finishes in a few seconds.
> If `composer install` complains about versions, run `composer update` instead.

### 2.4 Create the database

1. Right-click Laragon → **MySQL** → **Create database** → type `vector7` → OK.
2. Open `C:\laragon\www\vector7\.env` in Notepad and check these lines (Laragon's MySQL user is `root` with no password):
   ```
   APP_URL=http://vector7.test
   DB_DATABASE=vector7
   DB_USERNAME=root
   DB_PASSWORD=
   MAIL_MAILER=log
   ```
3. Change the first App Admin login if you like:
   ```
   APP_ADMIN_EMAIL=admin@vector7.in
   APP_ADMIN_PASSWORD="ChangeMe@2026"
   ```

### 2.5 Build the database with demo data

```
php artisan migrate --seed
php artisan storage:link
```

This creates all tables, the App Admin, the plans, the approval-stage / facility / document masters from `Vector7_Tenant_PreConfig_Template.xlsx`, the Tamil Nadu SRO list and a full **demo promoter** (about 20 seconds). To start without demo data set `SEED_DEMO=false` in `.env` before running it.

To wipe everything and start again: `php artisan migrate:fresh --seed`

### 2.6 Open it

- Marketplace: **http://vector7.test**
- Staff / promoter login: **http://vector7.test/workspace/login**
- Customer login: **http://vector7.test/login**

Emails are not sent on your computer; they are written to `storage\logs\laravel-YYYY-MM-DD.log` (`MAIL_MAILER=log`).

### 2.7 Background jobs on your computer (optional)

Booking expiry, instalment reminders, offer expiry, scheduled posts and emails run from the scheduler. On your computer, keep this running in a Laragon terminal while you test:

```
php artisan schedule:work
```

---

## 3. Sign in and try it

| Who | Where | Email | Password |
|---|---|---|---|
| App Admin (Super Admin) | /workspace/login | value of `APP_ADMIN_EMAIL` (default `admin@vector7.in`) | value of `APP_ADMIN_PASSWORD` (default `ChangeMe@2026`) |
| Tenant Admin (demo) | /workspace/login | `admin@demo.vector7.in` | `Demo@2026` |
| Tenant Manager (demo) | /workspace/login | `manager@demo.vector7.in` | `Demo@2026` |
| Tenant Sales (demo) | /workspace/login | `sales@demo.vector7.in` | `Demo@2026` |
| Tenant Account (demo) | /workspace/login | `accounts@demo.vector7.in` | `Demo@2026` |
| Support (demo) | /workspace/login | `support@demo.vector7.in` | `Demo@2026` |
| Customer (demo buyer) | /login | `buyer1@demo.vector7.in` … `buyer15@demo.vector7.in` | `Demo@2026` |

The demo promoter **Demo Promoters, Thanjavur** has:
- **Vallam Green Meadows** — launched Village project, estimate, 100% progress, 40 plots on an interactive layout map (available, blocked, booked, sale-init, ROR, ROR-Init, ROR-Completed and sold), offers and the promo code `DIWALI26`.
- **Kumbakonam Temple View Nagar** — in progress (about 32%) with a blocked sub-task, for the tracking and manager dashboard.
- **Orathanadu Garden City** — draft.
- Bookings (one expiring), sales with instalments (one overdue), payments with receipts, registrations, expenses, enquiries, tickets and social-post drafts.

**Change every demo password, or set `SEED_DEMO=false`, before going live.**

Passwords: 8–32 characters, no spaces at the start or end. 5 wrong passwords lock the account for 15 minutes (App Admin / Tenant Admin can unlock it from IAM). There is no OTP or two-factor login in this release.

---

## 4. Put it live on Hostinger

The steps below use hPanel. Replace `USER` with your Hostinger username (shown in hPanel → **Advanced → SSH Access**, e.g. `u123456789`) and `vector7.in` with your domain.

### 4.1 Prepare hPanel

1. **PHP version:** hPanel → **Advanced → PHP Configuration** → choose **PHP 8.3** (or newer) → Save. On the **PHP extensions** tab make sure `gd`, `zip`, `intl`, `fileinfo` are ticked.
2. **Database:** hPanel → **Databases → Management** → create a database and user (e.g. `u123456789_vector7` / `u123456789_v7user`) with a strong password. Note the three values.
3. **Email:** hPanel → **Emails** → create `no-reply@vector7.in` with a password. The SMTP server is `smtp.hostinger.com`, port `465` (SSL).
4. **SSH:** hPanel → **Advanced → SSH Access** → enable it and note the command (e.g. `ssh -p 65002 u123456789@123.45.67.89`). On Windows you can use the Laragon Terminal or PowerShell to connect.

### 4.2 Upload the code

Connect with SSH, then:

```
cd ~
git clone -b Test https://github.com/bizrajesh/vector7.git vector7
cd vector7
composer install --no-dev --optimize-autoloader
```

(No Git? Upload `vector7-app.zip` with hPanel **File Manager** into your home folder — **not** into `public_html` — and choose **Extract**, so you get `/home/USER/vector7/artisan`.)

### 4.3 Point the domain at the `public` folder

Pick **one** option.

**Option A (recommended): link `public_html` to `vector7/public`**

```
cd ~/domains/vector7.in
mv public_html public_html_old
ln -s ~/vector7/public public_html
```

**Option B: the whole project inside `public_html`** (if you cannot use a link)

Upload/extract the project so that `artisan` is at `~/domains/vector7.in/public_html/artisan`, then:

```
cp ~/domains/vector7.in/public_html/deploy/hostinger/root.htaccess ~/domains/vector7.in/public_html/.htaccess
```

This sends every request into `public/` and blocks `.env`, `storage`, `vendor` and the other private folders. With Option B, use `~/domains/vector7.in/public_html` wherever this guide says `~/vector7`.

### 4.4 Create the live `.env`

```
cd ~/vector7
cp .env.example .env
php artisan key:generate
nano .env
```

Change these lines (Ctrl+O, Enter to save; Ctrl+X to exit):

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://vector7.in
FORCE_HTTPS=true
SESSION_SECURE_COOKIE=true

DB_HOST=localhost
DB_DATABASE=u123456789_vector7
DB_USERNAME=u123456789_v7user
DB_PASSWORD=your-database-password

MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=no-reply@vector7.in
MAIL_PASSWORD=your-mailbox-password
MAIL_FROM_ADDRESS="no-reply@vector7.in"

APP_ADMIN_EMAIL=you@yourdomain.com
APP_ADMIN_PASSWORD="a-strong-password-8-to-32"
SEED_DEMO=false
SEO_INDEXABLE=true
LOG_LEVEL=warning
```

For a **staging** copy (e.g. `staging.vector7.in`) set `SEO_INDEXABLE=false` so search engines are blocked.

### 4.5 Build the database and optimise

```
php artisan migrate --seed --force
php artisan storage:link
php artisan optimize
php artisan seo:build
```

### 4.6 Add the one cron job

hPanel → **Advanced → Cron Jobs** → **Custom** → run **every minute** (`* * * * *`) with this command:

```
/usr/bin/php /home/USER/vector7/artisan schedule:run >> /dev/null 2>&1
```

This single entry runs everything: emails (the queue), booking expiry and reminders, instalment due/overdue notices, offer expiry, subscription and usage alerts, storage checks, scheduled social posts, social metrics, saved-requirement matching and the daily backup.

### 4.7 HTTPS

hPanel → **Security → SSL** → install the free SSL for the domain. The app redirects every page to `https://` and sends HSTS headers.

### 4.8 Check it

- Open `https://vector7.in` (marketplace) and `https://vector7.in/workspace/login`.
- Sign in as App Admin → **Settings → SMTP → Send test email**.
- `https://vector7.in/robots.txt` and `https://vector7.in/sitemap.xml` should open.

---

## 5. After going live: settings checklist

Sign in as App Admin and open **Settings**:

1. **Organisation** — name, logo, address, support email, social links (used in the footer, emails and PDFs).
2. **SMTP** — confirm and click **Send test email**.
3. **AI** — paste the Anthropic API key, keep the model `claude-sonnet-5-5` (or change it), set a monthly cap. Without a key, AI buttons fall back to built-in templates.
4. **Payment gateway** — Razorpay or Swipe keys (test mode first). Webhook URL is shown on the screen. Without a gateway you can mark invoices paid manually.
5. **Storage** — Local (default), Google Drive, S3, Google Cloud Storage or Azure Blob → **Test connection**.
6. **Social accounts** — connect Instagram / Facebook / YouTube for scheduled posts and metrics.
7. **Plans** — review Starter / Professional / Enterprise limits and prices.
8. **SEO** — default title/description, AI-crawler decision, then **Rebuild now**.
9. **Prerequisites** — the App masters are already imported from the template; tenants copy them during setup.

---

## 6. Updating the live site

```
cd ~/vector7
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
php artisan up
```

CSS is pre-built and committed (`public/css/app.css`), so the server never needs Node.js. If you change Blade views on your computer, rebuild the CSS there with `npm install` then `npm run build:css`, and commit the result.

---

## 7. Backups

- The scheduler makes a **daily backup** (database dump + uploaded files, zipped) in `storage/app/private/backups` and keeps the last 7. If a cloud storage driver is selected in Settings, each backup is also copied there.
- Run one by hand: `php artisan v7:backup`
- Also turn on Hostinger's own daily backups in hPanel.

---

## 8. Running the tests

Tests use a separate MySQL database called `vector7_test`.

```
mysql -u root -e "CREATE DATABASE vector7_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
vendor\bin\phpunit
composer audit
```

(On Laragon, create `vector7_test` from **MySQL → Create database**; edit `phpunit.xml` if your MySQL user/password differ.)

The suite covers every business rule in the spec: double booking, booking expiry in working days, 30% initial payment, ROR only at zero due, refund penalty, tenant isolation (guessed IDs), offer price only while valid, Sales/Support blocked from launch actions, password 8–32 rule, generate-password permissions and forced change, plan limits and usage left-over, template import/export, registration flow, reports — plus a smoke test that opens every screen for every role with the demo data.

---

## 9. Troubleshooting

| Problem | Fix |
|---|---|
| White page / "500 Server Error" | Look in `storage/logs/laravel-*.log`. On the live site never set `APP_DEBUG=true` for long. |
| "No application encryption key" | `php artisan key:generate` |
| "Access denied for user" | Check the `DB_` lines in `.env`, then `php artisan config:clear` |
| Page looks unstyled | Make sure the domain points at the `public` folder (section 4.3). |
| Uploaded files / logos don't show | `php artisan storage:link` |
| Emails not arriving | App Admin → Settings → SMTP → Send test email; confirm the cron job runs (emails are sent by the queue each minute). |
| Bookings never expire | The cron job is missing or the path to `artisan` is wrong. |
| Changed `.env` but nothing happens | `php artisan optimize:clear` then `php artisan optimize` |
| `composer install` version errors | Run `composer update`. |
| Locked out | Another admin can unlock you from IAM, or wait 15 minutes. |
| App Admin can't sign in / forgot password | `php artisan v7:app-admin` resets the App Admin to `APP_ADMIN_EMAIL` / `APP_ADMIN_PASSWORD` from `.env` (and unlocks it). Or set your own: `php artisan v7:app-admin you@example.com --password="New@Pass2026"` |

---

## 10. Where things are

| Path | What |
|---|---|
| `routes/web.php` | Every page and its permission |
| `config/permissions.php` | Role × module × action permission matrix |
| `app/Services` | Business rules (sales, projects, plot import, template import, SEO, AI, storage drivers…) |
| `resources/views` | Screens (Blade) — `market/` public site, `account/` customers, `app/` App workspace, `ws/` tenant workspace |
| `database/seeders` | App Admin, plans, masters (from the template), SROs, demo data |
| `routes/console.php` | Scheduled jobs |
| `deploy/hostinger/root.htaccess` | Option B for Hostinger |
| `docs/DECISIONS.md` | Choices made where the spec was open |
