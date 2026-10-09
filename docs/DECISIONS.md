# Decisions

Where the build prompt left a choice open, the simplest safe option was taken and recorded here.

## Platform and hosting

1. **Laravel 13 / PHP 8.3+**, Blade, Tailwind 3 pre-built into `public/css/app.css` (committed). The server never runs Node.js.
2. **One cron entry** (`schedule:run` every minute). The scheduler runs `queue:work --stop-when-empty --max-time=50` each minute for queued email, plus the daily/hourly jobs in `routes/console.php`. No Supervisor, Redis or long-running workers.
3. **Cache, sessions and queue use the database** (works on Hostinger shared hosting).
4. **Hostinger document root:** Option A symlinks `public_html` → `vector7/public`. Option B (project inside `public_html`) uses `deploy/hostinger/root.htaccess`, which rewrites into `public/` and denies private folders.
5. **`composer.lock` is not committed** in this branch: the lock produced in the build environment pointed at local package mirrors. Run `composer install` once (it resolves from Packagist and writes a lock) and commit that lock if you want fixed versions.
6. **HTTPS** is forced by `public/.htaccess` on any host except `localhost`, `127.0.0.1` and `*.test` (so Laragon keeps working), and by `FORCE_HTTPS=true` for generated links. HSTS is sent on HTTPS requests.

## Authentication and permissions

7. **Two guards**: `web` (staff: App and tenant users, `/workspace/login`) and `customer` (`/login`). Customers are separate records owned by the App workspace.
8. **No OTP or two-factor login in this release.** The "Two-factor authentication" setting exists in App Settings → Security, defaults to Off and is disabled ("coming later"). Sensitive actions (refund decisions, settings changes) ask for the current password again.
9. **Lockout**: 5 wrong passwords lock the account for 15 minutes; counters are per account, and rate limits are applied per IP as well.
10. **Generated passwords** are 12 characters from an alphabet without look-alike characters (0/O, 1/l/I). They are shown once and only stored as a hash. Generation logs the actor and target (never the password) and logs the target out of other sessions.
11. **Permission matrix** lives in `config/permissions.php` (module × view/create/update/delete/export/approve). It is enforced by the `perm:` middleware on every workspace route; tenant isolation is a global `TenantScope` plus write guards on the `BelongsToTenant` trait, so a guessed ID from another tenant returns 404.
12. **App Manager** gets every App module except IAM, Settings and the audit log (the audit log is treated as part of IAM).
13. **Tenant custom roles** combine existing permissions only, within tenant modules.
14. **Support role** (tenant) can create, read and update tenant users (no delete) and can generate passwords for tenant users except Tenant Admins.
15. **Tenant file access**: any active user of a tenant can open that tenant's files through the permission-checked `/files/{id}` route (stored outside `public`, random file names); customers only get files attached to their own registration, payments or tickets.

## Business rules

16. **Working days** exclude Saturday, Sunday and the tenant's holidays; "within N working days" means the Nth working day after the start date.
17. **One active booking or sale per plot** is guaranteed by `SELECT … FOR UPDATE` on the plot row plus a unique `active_plot_lock` column on `bookings` and `sales`.
18. **Prices** are frozen on the booking (actual, offer, price used, promo discount, net), so later price or offer changes never affect it. A promo code applies on top of the price used.
19. **Payments are allocated** to instalments in order. A sale moves to ROR only when the balance due is zero.
20. **Refund** = paid − penalty, where the penalty row is chosen by days late after the sale window; it needs Tenant Admin approval, is recorded as an expense and releases the plot.
21. **Broker commission** is booked as an expense at sale initiation (category "Broker commission").
22. **Sold plots** leave the marketplace; their public URL redirects (301) to the project page.
23. **Enquiry → booking conversion** is recorded by entering the booking number on the enquiry (status "Converted"). A "Book plot" shortcut opens the booking form pre-filled with the enquired plot.
24. **Tenant help desk** covers tickets raised with the vector7 team. Buyers' questions to a promoter come in as enquiries.

## Masters and template

25. App-level masters (`tenant_id = NULL`) are seeded from `database/seeders/data/Vector7_Tenant_PreConfig_Template.xlsx` on install. A tenant copies them during setup ("use App defaults") or imports its own template; the import is all-or-nothing in one transaction.
26. Template roles map Admin → Tenant Admin, Manager → Tenant Manager, Sales → Tenant Sales, Accountant → Tenant Account.

## AI, marketing and integrations

27. **Claude** model defaults to `claude-sonnet-5-5` (editable in Settings). Every call is logged in `ai_usage_logs` and counts against the tenant's monthly AI credits, or against the App's monthly cap for App-level calls. Without an API key, features fall back to built-in templates or manual entry.
28. **Social publishing** uses the Meta Graph API for Facebook and Instagram (Instagram needs a launched project with a layout image). YouTube only publishes videos, so YouTube posts are prepared as title and description text to paste into YouTube Studio; channel metrics come from the YouTube Data API. Posts for platforms that are not connected stay drafts marked "Not connected".
29. **Payment gateway** (Razorpay or Swipe) is used only for tenant subscriptions, through the gateway's hosted payment-link page and a signature-verified webhook. Customer payments are recorded offline.
30. **Storage drivers** (Local, Google Drive, S3, GCS, Azure Blob) talk to each provider's REST API directly, with no SDKs, so nothing extra is needed on shared hosting.

## Demo data

31. `DemoSeeder` runs when `SEED_DEMO=true`. It drives the real services (estimate, Go decision, start, plot import, booking, sale, payments, registration), with dates spread over the last six months so dashboards and charts show history. It sends no emails. Demo logins use `Demo@2026`.
