# Vector7

Multi-tenant, role-based portal for real-estate layout businesses: **land → approval stages → plot launch → booking → sale → registration**, with shareholder value tracking, accounting and decision dashboards.

Built with Laravel 13 (PHP 8.3+), MySQL 8, Blade + Tailwind CSS, and plain JavaScript under a strict Content-Security-Policy. Designed to run on GoDaddy cPanel or Hostinger hPanel shared hosting — no Node, Redis or Supervisor needed on the server.

- Deployment: see **[DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md)**
- Security: see **[docs/SECURITY_OWASP.md](docs/SECURITY_OWASP.md)**
- SEO spec: **[docs/seo-spec.md](docs/seo-spec.md)** · Assumptions: **[docs/ASSUMPTIONS.md](docs/ASSUMPTIONS.md)**
- Database script: **`database/sql/xaxis_schema.sql`** (61 tables, MySQL 8 / MariaDB 10.6+)

## Roles

| Role | Scope |
| --- | --- |
| Super Admin | Platform console: subscription plans, tenants, revenue, system health, logged impersonation |
| Admin | Everything inside their business: users, settings, projects, sales, shares, accounting, analytics |
| Sales | Plots, customers, bookings, sales, payments, registrations; sales income and sales dashboards |
| Shareholder | Read-only portal: own shares, share value and growth, per-sale allocations, payouts |
| Customer | Read-only portal: own plot, payment schedule, receipts |

The first user who registers a business becomes its Admin and creates the other users.

## Modules

| Module | Highlights |
| --- | --- |
| Settings | Sellable % (55), broker commission (0.5%), budget alert %, booking validity (15 days), 30/60/10 instalments, stage groups with dependencies and tasks, facilities, brokers, document writers, SRO offices, holidays, checklist templates, notification groups |
| Layout Projects | Owners (Aadhaar/PAN encrypted), survey numbers, documents, stage snapshot + scheduling, facilities, estimate (cost, production value, sellable plots), submit locks baseline |
| Operations | Start / complete stages (dependencies enforced), task ticks, expenses per stage, overdue and budget alerts → Ready to Launch |
| Launch & Sales | CSV plot import with preview, colour-coded plot map with phone bottom sheet, 15-day bookings with auto-expiry, 30/60/10 sales within 15 working days, receipts, ROR |
| Registration | Checklist from template, document writer sheet, deed upload, acknowledgement & no-dues declaration → Sold |
| Manage Shares | Admin-only allocation by contribution; value = (capital + realised profit) ÷ shares; moves only on plot sales; per-sale allocations; payouts; append-only |
| Accounting | Append-only ledger with auto-posting, reversals, CSV export |
| Analytics | Sales funnel, cash flow, overdue ageing, sales team, share value by project |
| Super Admin | Plans with limits and feature flags, tenant self-registration, subscription lifecycle, Razorpay payment links + webhook |
| Public website | Animated landing page, project list, live plot map, **online hold** with email one-time code, **Track my booking** (passwordless), purchase / call-back requests — purchases are completed only by the sales team |

## Project structure

```
app/
  Enums/                 PlotStatus (state machine), LayoutStatus, SubscriptionStatus, Role
  Http/Controllers/      Site (public), Auth, Platform (Super Admin), App (tenant), Portal, Webhook
  Http/Middleware/       SecurityHeaders, SetTenantContext, EnsurePermission, EnsureRole, ...
  Models/                TenantModel base + BelongsToTenant / Auditable / AppendOnly traits
  Services/              Business rules: Booking, Sale, Registration, Share, Ledger, Stage, Estimate, ...
  Console/Commands/      Scheduled jobs + xaxis:create-super-admin
config/xaxis.php         Roles, permission matrix, tenant defaults, seed lists
database/sql/            Standalone MySQL schema (also run by the first migration)
resources/views/         Blade UI (mobile-first; navy #1C315E, teal #227C70, sage #88A47C, cream #E6E2C3)
resources/css/app.css    Tailwind source → public/css/app.css
public/js/app.js         Drawer, confirmations, bottom sheet, charts (no inline JS)
tests/                   Unit + feature tests (tenancy isolation, access control, SEO gates)
```

## Local development

Requirements: PHP 8.3+, Composer, MySQL 8, Node 20 (only to rebuild CSS).

```bash
composer install
cp .env.example .env && php artisan key:generate
# set APP_ENV=local, APP_DEBUG=true, SESSION_SECURE_COOKIE=false and your DB_* values
php artisan migrate
php artisan xaxis:create-super-admin
php artisan db:seed --class=DemoSeeder   # optional demo tenant (prints Admin password)
php artisan serve
npm ci && npm run build                  # only when you change views/CSS
```

Tests (use a separate MySQL database `xaxis_test`):

```bash
php artisan test
```

## Key commands

| Command | Purpose |
| --- | --- |
| `php artisan xaxis:create-super-admin` | Create the platform Super Admin (interactive) |
| `php artisan xaxis:expire-bookings` | Release expired bookings (scheduled 00:30) |
| `php artisan xaxis:send-reminders` | Booking and instalment reminders (09:00) |
| `php artisan xaxis:stage-alerts` | Stage overdue and budget alerts (08:00) |
| `php artisan xaxis:subscriptions` | Trial / past-due / suspended transitions (01:00) |
| `php artisan schedule:run` | Run by the hosting cron (cPanel / hPanel) every minute |
