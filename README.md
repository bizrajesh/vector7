# vector7 — Realty Manage Portal

Multi-tenant SaaS for **layout promoters** (farmland → approved residential plots) and property owners: a public marketplace (layouts, plots, flats, villas, houses, farm land, commercial space), an App workspace (platform owner) and a Tenant workspace per promoter, with a Leads CRM, AI search and two AI assistants.

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
11. [Feature Update 01: listings, leads, AI search and chatbots](#11-feature-update-01-listings-leads-ai-search-and-chatbots)
12. [Marketplace in Tamil and English, menus and the App sign-in](#12-marketplace-in-tamil-and-english-menus-and-the-app-sign-in)

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
| App Admin (Super Admin) | **/app/login** (separate page, not linked from the marketplace) | value of `APP_ADMIN_EMAIL` (default `admin@vector7.in`) | value of `APP_ADMIN_PASSWORD` (default `ChangeMe@2026`) |
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

This single entry runs everything: emails (the queue), booking expiry and reminders, instalment due/overdue notices, offer expiry, subscription and usage alerts, storage checks, scheduled social posts, social metrics, saved-requirement and saved-search matching, lead follow-up reminders (9:00 digest and 15 minutes before each follow-up), the daily chatbot clean-up (conversations older than 90 days) and the daily backup.

### 4.7 HTTPS

hPanel → **Security → SSL** → install the free SSL for the domain. The app redirects every page to `https://` and sends HSTS headers.

### 4.8 Check it

- Open `https://vector7.in` (marketplace), `https://vector7.in/workspace/login` (promoters) and `https://vector7.in/app/login` (App team).
- Sign in as App Admin → **Settings → SMTP → Send test email**.
- `https://vector7.in/robots.txt` and `https://vector7.in/sitemap.xml` should open.

---

## 5. After going live: settings checklist

Sign in as App Admin and open **Settings**:

1. **Organisation** — name, logo, address, support email, social links (used in the footer, emails and PDFs).
2. **SMTP** — confirm and click **Send test email**.
3. **AI** — paste the Anthropic API key, keep the model `claude-sonnet-5-5` (or change it), set a monthly cap. Then set the three AI groups — **Marketplace AI search**, **Marketplace chatbot** and **Workspace assistant** — each with an on/off switch, a **daily cap** and an optional model (see [section 11.4](#114-ai-settings-and-caps)). Without a key, AI buttons fall back to built-in templates and search/chat fall back to keyword search.
4. **Payment gateway** — Razorpay or Swipe keys (test mode first). Webhook URL is shown on the screen. Without a gateway you can mark invoices paid manually.
5. **Storage** — Local (default), Google Drive, S3, Google Cloud Storage or Azure Blob → **Test connection**.
6. **Social accounts** — connect Instagram / Facebook / YouTube for scheduled posts and metrics.
7. **Plans** — review Starter / Professional / Enterprise limits and prices.
8. **SEO** — default title/description, AI-crawler decision, then **Rebuild now**.
10. **Chatbot** — bot name, greeting (English + Tamil) and FAQ answers (Settings → AI).
11. **Listings moderation** — check **Listings** in the App menu now and then for buyer reports; pick featured listings and layouts for the home page and approve testimonials.
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

---

## 11. Feature Update 01: listings, leads, AI search and chatbots

### 11.1 Upgrading an existing installation (Laragon or Hostinger)

The update only **adds** tables and columns; existing data is kept. Back up first, then:

```
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
php artisan seo:build
php artisan up
```

The migrations also:

- copy every promoter's enquiries into **Leads** (the old Enquiries page now redirects to Leads);
- move existing project gallery photos into the new photo manager;
- add the plan limits *Listings* and *Images per listing*, and the *Leads* module, to every plan;
- seed the features list (amenities) and the default lead stages, contact settings and WhatsApp templates for every workspace.

On Laragon, run `php artisan migrate` (no `--force`). To reload the demo data with sample listings of every type, a quick-launched layout, leads and testimonials, use `php artisan migrate:fresh --seed` and then `php artisan db:seed --class=DemoSeeder`. **This erases the database.**

### 11.2 Contact modes (per listing and per layout)

Every listing and layout has a **contact block**: contact person, mobile, WhatsApp number and email, plus three modes that can each be switched on or off:

| Mode | What the buyer sees | What happens |
|---|---|---|
| **Call** | Masked number (98XXX XX345), **Call** and **Show number** | The buyer gives their name and mobile and ticks consent once per visit. The number is then shown or dialled. |
| **WhatsApp** | **WhatsApp** button | Opens WhatsApp with the message *"Hi, I'm interested in {title} ({code}) on vector7.in – {link}"*. |
| **Email** | **Send an email enquiry** form | The message is emailed to the contact email. |

Every contact creates or updates a **lead**. Leads are merged by mobile number or email, and the source is recorded (Call, WhatsApp, Show number, Enquiry, Chatbot, AI search). Number reveals are limited to 5 a minute and 30 a day per visitor.

Default contact details and modes for new listings: **Workspace → Settings → Listings & leads**.

### 11.3 Lead assignment

**Settings → Listings & leads**:

- **Auto-assign new leads (round-robin)** — when on, each new lead goes to the next active **Sales** user in turn, and that user is emailed. When off, new leads stay **Unassigned** and the **Sales Team** notification group is emailed.
- **Lead stages** — rename, recolour and reorder the stages. *Won* and *Lost* close a lead; *Lost* asks for a reason.
- **WhatsApp templates** — messages that Sales can send from a lead with one tap. Placeholders: `{name}` (lead), `{listing}` (property they asked about), `{user}` (you), `{promoter}` (workspace name).

Who sees what: **Sales** see only leads assigned to them plus unassigned leads. **Manager** and **Admin** see all leads. **Support** can view and update leads but not delete them.

Reminders (sent by the cron job): at **9:00 every morning**, each user gets a list of their follow-ups due today or overdue, and the Management group gets a daily summary. A reminder is also sent **15 minutes before** each scheduled follow-up or site visit.

### 11.4 AI settings and caps

App Admin → **Settings → AI**:

| Setting | Default | Notes |
|---|---|---|
| Anthropic API key | — | Stored encrypted. |
| Default model | `claude-sonnet-5-5` | Used by any group that has no model of its own. |
| Monthly cap (all AI) | 0 = no cap | Counts every AI call on the platform. |
| **Marketplace AI search** — on/off, daily cap, model | on, 500/day | Turns plain words (English or Tamil) into filters and ranks the top 30 results. Results always come from the database; the AI never invents properties. Same search within 1 hour comes from the cache. When the search is off or over its cap, it falls back to keyword search. |
| **Marketplace chatbot** — on/off, daily cap, model | on, 1000/day | The floating "Ask Vee" assistant. Off hides the button. Over the cap, it answers with keyword search and the FAQ. |
| **Workspace assistant** — on/off, daily cap, model | on, 2000/day | Also uses each workspace's monthly **AI credits** from its plan. A Tenant Admin can switch it off in Settings → Listings & leads. |
| Chatbot name / greeting / FAQ | Vee | The FAQ is one question per line, then the answer, with a blank line between entries. |

Each search or chat message counts as 1 credit in **AI usage**. Searches are logged (App → **Chatbot conversations → AI search log**), and chatbot conversations can be read and exported under App → **Chatbot conversations**. Both are kept for 90 days.

### 11.5 Plan limits

| Limit | Starter | Professional | Enterprise |
|---|---|---|---|
| Live listings | 10 | 100 | unlimited |
| Images per listing / layout | 12 | 20 | 20 |
| Leads module | yes | yes | yes |

You can edit these under App → **Plans**. A listing counts once it is published (or under offer).

### 11.6 New screens

| Where | Screen |
|---|---|
| Marketplace | Home (AI search, categories, featured, districts, testimonials) · `/search` (grid / list / map) · `/buy/{type}/{city}` category pages · listing pages · compare · My account → Shortlist & searches |
| Workspace | Projects → **Quick launch layout** · **Property listings** · **Leads** (board, list, reports) · Settings → **Listings & leads** · **Assistant** (top bar) · **Help** (every screen links to its help page) |
| App | **Listings** (moderation, reports, featured, testimonials) · **Chatbot conversations** · Settings → AI groups |

Images are converted to WebP (1600 px plus a 400 px thumbnail), photo location data (EXIF) is removed, and the workspace watermark is added if it is switched on. Maps use Leaflet with OpenStreetMap tiles; no API key is needed.

All the decisions made in this update are listed in `docs/DECISIONS.md` (entries 32–48).

---

## 12. Marketplace in Tamil and English, menus and the App sign-in

### 12.1 Languages

- The **public marketplace** and the **buyer account** pages (`/account`, buyer sign-in and registration) can be shown in **English or தமிழ்**. Use the **EN | தமிழ்** switch in the top bar. The choice is remembered for the visit and in a cookie for a year. A link can also force a language with `?lang=ta` or `?lang=en`.
- **Everything else is always English**: the promoter workspace (`/workspace/...`), the App workspace (`/app/...`), staff sign-in pages, files and emails.
- The Tamil texts are in `lang/ta.json` (English text → Tamil text). Edit that file to change any wording; no code changes are needed. Tamil validation messages are in `lang/ta/validation.php`.
- Content that people type — listing titles and descriptions, project names, promoter names — is shown exactly as entered. App-seeded feature names, services and plan descriptions have Tamil versions in `lang/ta.json`.
- Search and the chatbot understand Tamil. With AI on, Claude reads Tamil directly. With AI off, common Tamil words (places, lakh/crore, BHK, plot, flat, directions, amenities) are mapped to English in `config/tamil_words.php`.
- Tamil uses the **Noto Sans Tamil** web font (in `public/fonts`). It is only downloaded when a page shows Tamil text.

### 12.2 Menu-based filters

| Menu | Opens | Filters shown |
|---|---|---|
| **Buy** (Plots & layouts, Flats, Villas, Independent houses, Farm land, Commercial) | `/buy`, `/buy/{type}` | Type pills, city / district, budget in lakhs and crores, plus filters for the type: BHK, furnishing, built-up area and facing for homes; extent in acres and water source for farm land; space type and area for commercial; plot size, facing and corner plots for plots. Also approved / RERA only and on offer. |
| **Rent** | `/rent`, `/rent/{type}` | Only types that can be rented (flats, houses, villas, shops & offices); monthly rent; BHK and furnishing. No approval or offer filters. |
| **Layouts** | `/buy/layouts` | Plot price, plot size, facing, approving authority (DTCP, CMDA, BDO, LPA, municipality, TNRERA), corner plots, with approval number only, on offer. |
| **Services** | `/services` | All / For buyers / For promoters, and a search box. |
| **For promoters** | `/for-promoters` | *Sell plots in layouts* or *Post individual properties*; the page shows the points and plan limits that matter for that choice. |
| **Support** | `/support` | Topics (buying or renting, booking & payments, documents & registration, report a listing, promoter workspace, something else). Each topic shows quick answers, and the contact form is pre-set to that topic. Messages reach the App team tagged with the topic. |

The filter panel always submits to the menu page you are on, so the address stays clean (for example `/rent/flats?bhk=2&furnishing=semi`).

### 12.3 Separate App sign-in

- **Promoters and their teams** sign in at **`/workspace/login`**. This page is linked from the marketplace ("Promoter workspace", "Post property").
- **The vector7 App team** signs in at **`/app/login`**. This page is never linked from the marketplace, is marked `noindex`, and is not listed in `robots.txt`. It has its own look ("App admin").
- Each page only accepts its own users. An App user on `/workspace/login` is told to use the App team's own page, without the address being shown. A promoter on `/app/login` gets the normal "credentials do not match" message.
- Opening any `/app/...` address while signed out goes to `/app/login`. Signing out of the App workspace returns there too. Password-reset emails and `php artisan v7:app-admin` point App users to `/app/login`.
- Tip: bookmark `/app/login` on the App team's computers.
