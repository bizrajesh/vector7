# Vector7 — Deployment Guide (GoDaddy cPanel or Hostinger hPanel)

This guide takes the Vector7 source from this package to a live, HTTPS-only site. Follow the steps in order; each ends with a check.

| Your host | Follow |
| --- | --- |
| **GoDaddy** cPanel hosting | **Part A** — sections 0–15 |
| **Hostinger** Premium / Business / Cloud web hosting (hPanel) | **Part B** — sections H0–H12 (it reuses some Part A steps and says which) |

Time needed: about 60–90 minutes for a first deployment.

> Test the package on your PC first — see *Vector7_Local_Test_Guide.docx* (Laragon).

---

# Part A — GoDaddy (cPanel)

## 0. What you need

| Item | Requirement |
| --- | --- |
| Hosting | GoDaddy **cPanel** Linux hosting (Deluxe / Ultimate / Business) or a VPS with cPanel |
| PHP | **8.3** or 8.4 (set in cPanel → *Select PHP Version* / *MultiPHP Manager*) |
| Database | MySQL 8.0 (or MariaDB 10.6+) — one database for Vector7 |
| Shell | cPanel **Terminal** or **SSH** access (needed for `composer` and `php artisan`) |
| Domain | A domain or subdomain pointed at the hosting account, with **AutoSSL** enabled |
| Your PC (optional) | PHP 8.3 + Composer, if the server cannot run `composer install` |
| Email | An SMTP mailbox (GoDaddy / Microsoft 365 / Google Workspace) for system emails |
| Payments (optional) | Razorpay account for subscription payments |

> Security rule for the whole guide: the Laravel application folder must live **outside** `public_html`. Only the `public/` folder is web-visible.

---

## 1. Prepare PHP on cPanel

1. cPanel → **Select PHP Version** → choose **8.3** (or 8.4).
2. **Extensions** tab — enable: `bcmath`, `ctype`, `curl`, `fileinfo`, `gd`, `intl`, `mbstring`, `mysqlnd`, `pdo_mysql`, `openssl`, `tokenizer`, `xml`, `zip`, `sodium`.
3. **Options** tab — set:

| Option | Value |
| --- | --- |
| `display_errors` | Off |
| `expose_php` | Off |
| `memory_limit` | 256M |
| `upload_max_filesize` | 12M |
| `post_max_size` | 16M |
| `max_execution_time` | 60 |
| `session.cookie_secure` | On |

**Check:** in Terminal run `php -v` → shows 8.3 or 8.4.

---

## 2. Create the database

1. cPanel → **MySQL® Databases**.
2. Create database, e.g. `cpuser_xaxis`.
3. Create user, e.g. `cpuser_xaxis`, with a **generated 24+ character password**.
4. Add the user to the database with **ALL PRIVILEGES** on this database only.
5. Choose ONE way to create the tables:
   - **A. phpMyAdmin (no terminal needed):** cPanel → phpMyAdmin → select the database → *Import* → `database/sql/xaxis_schema.sql` → Go.
   - **B. Artisan (after step 5):** `php artisan migrate --force` runs the same SQL file.

**Check:** phpMyAdmin shows **61 tables** and 3 rows in `plans`.

> Edit plan prices later from the Super Admin console (Subscription plans). The script seeds them at ₹0 on purpose.

---

## 3. Upload the application

Recommended layout on the server:

```
/home/CPUSER/
├── xaxis/              ← the Laravel app (this package)
│   ├── app/ bootstrap/ config/ database/ resources/ routes/ storage/ vendor/
│   └── public/         ← the ONLY web-visible folder
└── public_html/        ← domain document root (points to xaxis/public)
```

1. cPanel → **File Manager** → in your home folder create `xaxis`.
2. Upload `vector7-app.zip` into `xaxis` and **Extract**.
3. Point the web root at `xaxis/public` — choose one:
   - **Subdomain / addon domain:** cPanel → *Domains* → set the document root to `xaxis/public`.
   - **Primary domain (public_html fixed):** in Terminal:
     ```bash
     cd ~
     mv public_html public_html_old
     ln -s ~/xaxis/public ~/public_html
     ```
   - **If symlinks are not allowed:** copy the *contents* of `xaxis/public` into `public_html`, then edit `public_html/index.php` and change the two paths `__DIR__.'/../vendor/...'` and `__DIR__.'/../bootstrap/...'` to `__DIR__.'/../xaxis/vendor/...'` and `__DIR__.'/../xaxis/bootstrap/...'`. Also change the maintenance path the same way.

**Check:** `https://yourdomain/css/app.css` downloads a CSS file (not a 404).

---

## 4. Install PHP dependencies

The package ships without the `vendor/` folder. In Terminal:

```bash
cd ~/xaxis
composer install --no-dev --optimize-autoloader --no-interaction
# If "composer" is not found on the server:
/opt/cpanel/composer/bin/composer install --no-dev --optimize-autoloader --no-interaction
```

No terminal Composer? Run the same command **on your PC** inside the extracted folder, then zip and upload the generated `vendor/` folder to `~/xaxis/vendor`.

Then check for known vulnerable packages (OWASP A06):

```bash
composer audit
```

**Check:** `ls ~/xaxis/vendor/laravel/framework` lists files.

> Front-end assets are pre-built (`public/css/app.css`, self-hosted fonts and Chart.js). Node.js is **not** needed on the server. To rebuild after changing views: `npm ci && npm run build` on your PC and upload `public/css`, `public/js`, `public/fonts`.

---

## 5. Configure the environment

```bash
cd ~/xaxis
cp .env.example .env
php artisan key:generate --force
chmod 600 .env
```

Edit `.env` (File Manager → Edit):

| Key | Value |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` (never true on a live site) |
| `APP_URL` | `https://www.yourdomain.com` |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | from step 2 |
| `SESSION_SECURE_COOKIE` | `true` |
| `SESSION_ENCRYPT` | `true` |
| `MAIL_*` | your SMTP mailbox (GoDaddy: `smtpout.secureserver.net`, port 465, `ssl`) |
| `MAIL_FROM_ADDRESS` | e.g. `no-reply@yourdomain.com` |
| `TRUSTED_PROXIES` | leave empty unless you use Cloudflare (see step 10) |
| `RAZORPAY_*` | from step 9 (leave empty to run sign-ups as trial-only) |
| `WHATSAPP_*` | optional, step 9 |

Set folder permissions:

```bash
find ~/xaxis -type d -exec chmod 755 {} \;
find ~/xaxis -type f -exec chmod 644 {} \;
chmod 600 ~/xaxis/.env
chmod -R 775 ~/xaxis/storage ~/xaxis/bootstrap/cache
```

---

## 6. Initialise the application

```bash
cd ~/xaxis
php artisan migrate --force          # safe even if you imported the SQL in step 2
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan xaxis:create-super-admin # asks for name, email and a strong password
```

There are **no default accounts or passwords** anywhere in Vector7.

**Check:** open `https://yourdomain/up` → "Application up". Sign in at `/login` with the Super Admin → you land on the Platform console.

---

## 7. Scheduler and queue (cPanel Cron)

cPanel → **Cron Jobs** → add, *Once Per Minute* (`* * * * *`):

```bash
cd /home/CPUSER/xaxis && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

If `/usr/local/bin/php` is not 8.3, use the EasyApache path, e.g. `/opt/cpanel/ea-php83/root/usr/bin/php`.

This one cron entry runs everything:

| Job | When (IST) |
| --- | --- |
| Expire bookings past validity → plot back to Available | 00:30 daily |
| Subscription lifecycle (trial → past due → suspended) | 01:00 daily |
| Stage overdue + budget-threshold alerts | 08:00 daily |
| Booking expiry and instalment due/overdue reminders | 09:00 daily |
| Queue worker (emails, WhatsApp) | every minute |
| Expired password-reset tokens cleanup | daily |

**Check:** after 2 minutes, `php artisan schedule:list` shows the jobs, and `storage/logs/laravel.log` has no scheduler errors.

---

## 8. HTTPS, host and security headers

1. cPanel → **SSL/TLS Status** → run **AutoSSL** for the domain (and `www`).
2. `public/.htaccess` already forces HTTPS with one 301 redirect. To force one preferred host, uncomment the two lines under *Preferred host* and set your domain.
3. The app sends HSTS, CSP, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` and `Permissions-Policy` on every response.

**Check:**
- `http://yourdomain` → redirects once to `https://`.
- <https://securityheaders.com> → grade A.
- <https://www.ssllabs.com/ssltest/> → grade A.

---

## 9. Integrations

### Razorpay (subscription payments)
1. Razorpay Dashboard → *Settings → API Keys* → generate **live** keys → put in `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET`.
2. *Settings → Webhooks* → add `https://yourdomain/webhooks/razorpay`, event **payment_link.paid**, set a long random secret → `RAZORPAY_WEBHOOK_SECRET`.
3. `php artisan config:cache`.

Payments use Razorpay-hosted Payment Links, so no card or UPI data ever reaches Vector7. Webhooks are verified with HMAC-SHA256 and de-duplicated.

### WhatsApp Cloud API (optional)
1. Meta Business → WhatsApp → create the message template **`xaxis_alert`** (category *Utility*, language *English*) with one body variable `{{1}}`.
2. Put the permanent token and phone-number ID in `WHATSAPP_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID`; `php artisan config:cache`.
3. Enable WhatsApp on the plan (Super Admin → Plans) and on the Notification Group.

### Email deliverability
Add SPF, DKIM and DMARC DNS records for the sending domain (GoDaddy DNS → your mail provider's values).

---

## 10. Optional: Cloudflare in front of GoDaddy

Improves speed (CDN, HTTP/3, Brotli) and adds a WAF.

1. Move DNS to Cloudflare, proxy the `A` records (orange cloud).
2. SSL/TLS mode **Full (strict)** — never *Flexible* (causes redirect loops).
3. In `.env` set `TRUSTED_PROXIES` to Cloudflare's IP ranges (comma-separated, from <https://www.cloudflare.com/ips/>) so client IPs and rate limits work. Then `php artisan config:cache`.
4. WAF: keep verified bots (Googlebot/Bingbot) **allowed**; add a rate-limit rule for `/login` as a second layer.

---

## 11. Backups

Use the bundled script (keeps 14 daily database dumps plus uploaded documents):

```bash
chmod 700 ~/xaxis/scripts/backup.sh
```

cPanel → Cron Jobs → daily at 02:00:

```bash
/home/CPUSER/xaxis/scripts/backup.sh >> /home/CPUSER/backups/backup.log 2>&1
```

Backups are written to `~/backups` (outside the web root). Download a copy off-site every week, and test a restore once a month in a staging database.

---

## 12. Staging site (recommended)

1. Create `staging.yourdomain.com` with its own folder, database and `.env` (`APP_ENV=staging`, `APP_DEBUG=false`).
2. Protect it: cPanel → **Directory Privacy** on the staging document root (password prompt).
3. `robots.txt` automatically returns `Disallow: /` and every response carries `X-Robots-Tag: noindex, nofollow` when `APP_ENV` is not `production`.
4. Optional demo data (staging only): `php artisan db:seed --class=DemoSeeder` — prints a one-time Admin password.

---

## 13. Go-live checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `.env` permission 600
- [ ] App folder outside `public_html`; only `public/` is web-visible
- [ ] `https://yourdomain/.env` returns **403/404**
- [ ] Super Admin created; plan prices set in the Platform console
- [ ] Cron job running (`php artisan schedule:list`)
- [ ] Test sign-up → email verification → Admin dashboard
- [ ] Test booking → sale → payments → ROR → registration → Sold on staging
- [ ] Test website hold: publish a project → hold a plot with the email code → Sales confirms the advance → customer sees it under Track my booking
- [ ] Email delivery works (one-time codes depend on it)
- [ ] SecurityHeaders and SSL Labs grade A
- [ ] `https://yourdomain/robots.txt` shows the production rules and sitemap line
- [ ] Google Search Console + Bing Webmaster verified; sitemap `https://yourdomain/sitemap.xml` submitted
- [ ] Backups running and one restore tested
- [ ] `composer audit` clean

---

## 14. Updating to a new version

```bash
cd ~/xaxis
php artisan down --retry=60          # returns 503 + Retry-After (SEO-safe)
# upload / extract the new files over the old ones (keep .env and storage/)
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan up
```

### Upgrading an existing install to the public-booking release

`php artisan migrate --force` applies `database/sql/xaxis_update_001_public_booking.sql` automatically (adds public project fields, online holds, purchase requests and email codes). Without a terminal, import that file in phpMyAdmin **once**. Fresh installs already include it.

After upgrading, each Admin publishes a project from **Layout → Overview → Public website**, and adds *Online booking hold* and *Purchase / call-back request* events to a Notification Group so Sales is alerted.

---

## 15. Troubleshooting

| Symptom | Fix |
| --- | --- |
| HTTP 500 right after upload | Check `storage/logs/laravel.log`; usually missing `vendor/`, wrong PHP version, or `APP_KEY` empty |
| "Permission denied" in logs | Re-run the `chmod` commands in step 5 |
| Page is unstyled | Document root is not `xaxis/public`, or `public/css/app.css` missing |
| Redirect loop | Cloudflare SSL mode must be *Full (strict)* |
| 419 "Session expired" on every form | `APP_URL` must match the site's https URL; `SESSION_DOMAIN=null` |
| Emails not sent | Check `MAIL_*`; queue runs via cron — see `notification_logs` table and `failed_jobs` |
| Bookings not expiring | Cron job missing or wrong PHP path |
| Customers get "could not send the code" | SMTP settings wrong — one-time codes are sent immediately, not via the queue |
| Project not visible on /projects | It must be Launched, ticked *Show on the public website*, and the plan must include public listings |
| Changes to `.env` ignored | Run `php artisan config:cache` after every `.env` edit |

---

# Part B — Hostinger (hPanel)

Hostinger shared and cloud plans use **hPanel** (not cPanel) and the **LiteSpeed** web server, which reads the same `.htaccess` rules as Apache. Vector7 needs no code changes for Hostinger — only the paths and panel menus differ.

Throughout Part B replace:

| Placeholder | Meaning | Example |
| --- | --- | --- |
| `uXXXXXXXXX` | Your hosting username (shown on hPanel → SSH Access) | `u123456789` |
| `yourdomain.com` | The domain added to the hosting plan | `vector7.in` |
| `SERVER_IP` | Server IP from hPanel → SSH Access | `145.x.x.x` |

## H0. What you need

| Item | Requirement |
| --- | --- |
| Plan | **Premium, Business or Cloud** web hosting. *Single* has no SSH and is **not suitable**. Business/Cloud recommended (daily backups, more CPU/RAM) |
| PHP | 8.3 or 8.4 |
| Database | MySQL/MariaDB provided by Hostinger (MariaDB 10.6+ or MySQL 8 — check in H2) |
| SSH | Enabled in hPanel (port **65002**) |
| Domain | Added to the plan and pointing at Hostinger, with the free SSL active |
| Email | A Hostinger mailbox (or Google Workspace / Microsoft 365) |
| Your PC | `composer.lock` from your local test (Local Test Guide, step 7.5) |

> Security rule: the Laravel app folder must live **outside** `public_html`. Only the `public/` folder is web-visible.

## H1. Prepare PHP

1. hPanel → **Websites** → *yourdomain.com* → **Dashboard** → **Advanced** → **PHP Configuration**.
2. **PHP version** tab → choose **8.3** → Save.
3. **PHP extensions** tab — tick: `bcmath`, `ctype`, `curl`, `fileinfo`, `gd`, `intl`, `mbstring`, `mysqlnd`, `pdo_mysql`, `openssl`, `tokenizer`, `xml`, `zip`, `sodium` → Save.
4. **PHP options** tab — set the same values as Part A step 1 (`display_errors` Off, `memory_limit` 256M, `upload_max_filesize` 12M, `post_max_size` 16M, `max_execution_time` 60).

## H2. Enable SSH and check the server

1. hPanel → **Advanced** → **SSH Access** → **Enable**. Note the ready-made command, username, IP and port. The SSH password is your main FTP password (change it there if needed).
2. From Windows Terminal / PowerShell on your PC:

```bash
ssh -p 65002 uXXXXXXXXX@SERVER_IP
php -v                     # must show 8.3 or 8.4
command -v php             # note this path for cron (H7)
composer --version         # if it shows 1.x, use "composer2" everywhere below
mysql --version
```

If `php -v` still shows an old version after H1, log out and in again. If it persists, use the full path such as `/opt/alt/php83/usr/bin/php` (run `ls /opt/alt/` to see what exists) in every `php` command and in the cron job.

**Check:** PHP 8.3+ and Composer 2.x are available.

## H3. Create the database

1. hPanel → **Databases** → **Management** → *Create a new MySQL database and database user*.
2. Name e.g. `vector7` and user `vector7` — Hostinger adds your prefix, so they become **`uXXXXXXXXX_vector7`**. Use a **generated 24+ character password**.
3. Create the tables — choose ONE:
   - **A. phpMyAdmin:** Databases → Management → *Enter phpMyAdmin* → *Import* → `database/sql/xaxis_schema.sql` → Go.
   - **B. Artisan:** `php artisan migrate --force` in H6.

On Hostinger the database host is **`localhost`**.

**Check:** phpMyAdmin shows **61 tables** and 3 rows in `plans`. `SELECT VERSION();` shows MariaDB 10.6+ or MySQL 8.x.

## H4. Upload the app and point the domain at `public/`

Hostinger’s folder layout (the document root `public_html` cannot be moved in hPanel):

```
/home/uXXXXXXXXX/domains/yourdomain.com/
├── vector7/            ← the Laravel app (this package)
│   ├── app/ bootstrap/ config/ database/ resources/ routes/ storage/ vendor/
│   └── public/         ← the ONLY web-visible folder
└── public_html  →  vector7/public   (symbolic link)
```

1. hPanel → **Files** → **File Manager** → open `domains/yourdomain.com/`.
2. Create folder `vector7`, upload `vector7-app.zip` into it and **Extract**. Also upload your tested `composer.lock` into `vector7/`.
3. Over SSH, replace `public_html` with a link to `vector7/public`:

```bash
cd ~/domains/yourdomain.com
mv public_html public_html_old          # keeps Hostinger's default files, delete later
ln -s vector7/public public_html
ls -l                                   # shows: public_html -> vector7/public
```

**If the link is not served** (403/404 on every page): remove the link (`rm public_html`), create a real `public_html` folder, copy the *contents* of `vector7/public` into it, then edit `public_html/index.php` and change `__DIR__.'/../vendor/...'`, `__DIR__.'/../bootstrap/...'` and the maintenance path to `__DIR__.'/../vector7/vendor/...'`, `__DIR__.'/../vector7/bootstrap/...'` and `__DIR__.'/../vector7/storage/...'`. After every update, copy `vector7/public` into `public_html` again.

> Do **not** use the common “move everything into `public_html`” shortcut — it puts `.env`, `storage/` and source code inside the web root.

**Check:** `https://yourdomain.com/css/app.css` downloads a CSS file.

## H5. Install dependencies and configure `.env`

```bash
cd ~/domains/yourdomain.com/vector7
composer install --no-dev --optimize-autoloader --no-interaction   # or composer2
composer audit
cp .env.example .env
php artisan key:generate --force
chmod 600 .env
```

Edit `.env` (File Manager → Edit, or `nano .env`) with the same values as Part A step 5, and these Hostinger values:

| Key | Hostinger value |
| --- | --- |
| `APP_URL` | `https://yourdomain.com` (or `https://www.` — whichever you use everywhere) |
| `DB_HOST` | `localhost` |
| `DB_DATABASE` / `DB_USERNAME` | `uXXXXXXXXX_vector7` |
| `DB_PASSWORD` | from H3 |
| `MAIL_HOST` / `MAIL_PORT` / `MAIL_ENCRYPTION` | `smtp.hostinger.com` / `465` / `ssl` (Hostinger Email) |
| `MAIL_USERNAME` / `MAIL_PASSWORD` | full mailbox address and its password (hPanel → Emails) |
| `MAIL_FROM_ADDRESS` | the same mailbox, e.g. `no-reply@yourdomain.com` |

Permissions:

```bash
find ~/domains/yourdomain.com/vector7 -type d -exec chmod 755 {} \;
find ~/domains/yourdomain.com/vector7 -type f -exec chmod 644 {} \;
chmod 600 ~/domains/yourdomain.com/vector7/.env
chmod -R 775 ~/domains/yourdomain.com/vector7/storage ~/domains/yourdomain.com/vector7/bootstrap/cache
chmod 700 ~/domains/yourdomain.com/vector7/scripts/backup.sh
```

> Hostinger plans have a file-count (inode) limit. `vendor/` without dev packages is well within it; delete `public_html_old` and old zip files once the site works.

## H6. Initialise the application

Same as Part A step 6, run from `~/domains/yourdomain.com/vector7`:

```bash
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan xaxis:create-super-admin
```

**Check:** `https://yourdomain.com/up` → “Application up”; Super Admin sign-in opens the Platform console.

## H7. Scheduler and queue (Cron Jobs)

hPanel → **Advanced** → **Cron Jobs** → type **Custom** → every minute (`* * * * *`):

```bash
/usr/bin/php /home/uXXXXXXXXX/domains/yourdomain.com/vector7/artisan schedule:run >> /dev/null 2>&1
```

Use the PHP path you noted in H2 if it is not `/usr/bin/php`. This one entry runs every job in the Part A step 7 table. While testing, log the output instead: replace `/dev/null` with `/home/uXXXXXXXXX/cron.log`.

**Check:** after 2 minutes `php artisan schedule:list` shows the jobs and `storage/logs/laravel-*.log` has no scheduler errors.

## H8. SSL, HTTPS and security headers

1. hPanel → **Security** → **SSL** → confirm the free SSL is **Active** for `yourdomain.com` and `www`.
2. Hostinger’s *Force HTTPS* toggle is not needed — `public/.htaccess` already redirects to https in one hop. If you turn it on, check that `http://yourdomain.com` still redirects **once** (no loop).
3. To force one host (`www` or not), uncomment the *Preferred host* lines in `public/.htaccess` (Part A step 8).

**Check:** securityheaders.com and SSL Labs grade A.

## H9. Integrations and email

- Razorpay, WhatsApp and email DNS: exactly as Part A step 9, with webhook URL `https://yourdomain.com/webhooks/razorpay`.
- Hostinger Email: hPanel → **Emails** → create the mailbox; Hostinger adds SPF/DKIM automatically when the domain uses Hostinger DNS — confirm under **Emails → Email deliverability** (or DNS Zone) and add a DMARC record.

## H10. CDN (optional)

- **Hostinger CDN** (Business/Cloud): leave it **off** for the first deployment. If you turn it on later, purge the CDN cache after every update and re-test login, forms and the plot map.
- **Cloudflare:** follow Part A step 10 (Full (strict), `TRUSTED_PROXIES`).

## H11. Backups and staging

- Hostinger’s own backups: hPanel → **Files** → **Backups** (weekly on Premium, daily on Business/Cloud).
- Plus the bundled script — Cron Jobs → Custom → daily at 02:00 (`0 2 * * *`):

```bash
/bin/bash /home/uXXXXXXXXX/domains/yourdomain.com/vector7/scripts/backup.sh >> /home/uXXXXXXXXX/backups/backup.log 2>&1
```

- Staging: hPanel → **Domains** → **Subdomains** → `staging.yourdomain.com`, then repeat H4–H7 with its own folder `staging-vector7`, database and `.env` (`APP_ENV=staging`). Protect it with hPanel → **Advanced** → **Password Protect Directories**.

## H12. Updates and troubleshooting

Updating: same commands as Part A step 14, run from `~/domains/yourdomain.com/vector7` (use `composer2` if needed). If you used the copy fallback in H4, copy `vector7/public` into `public_html` again before `php artisan up`.

Go-live: use the Part A step 13 checklist.

| Symptom (Hostinger) | Fix |
| --- | --- |
| 403 / 404 on every page | `public_html` link wrong — `ls -l ~/domains/yourdomain.com` must show `public_html -> vector7/public`; otherwise use the H4 copy fallback |
| Hostinger default “parked” page | Old `public_html` still in place, or the domain is not pointed at Hostinger yet |
| `composer` errors about version 1 | Use `composer2` |
| “requires php ^8.3” over SSH | CLI PHP differs from website PHP — log in again or use `/opt/alt/php83/usr/bin/php` |
| Cron jobs do nothing | Wrong PHP path or domain path in the cron command; log to `cron.log` to see the error |
| `SQLSTATE[HY000] [1045] Access denied` | Use the full prefixed names `uXXXXXXXXX_…` and `DB_HOST=localhost` |
| Emails not sent | `smtp.hostinger.com`, port 465, `ssl`, full mailbox as username |
| Site slow after update | Run the four `artisan …:cache` commands; purge Hostinger CDN if enabled |
| Everything else | Part A step 15 |
