# Vector7 — OWASP Top 10 (2021) controls

How each OWASP Top 10 risk is handled in the code, and what the operator must do on the server. File paths are relative to the project root.

## A01 — Broken Access Control

| Control | Where |
| --- | --- |
| Row-level tenant isolation: every tenant model is filtered by `tenant_id` through a global scope; with no tenant in context queries return **nothing** (deny by default) | `app/Models/Concerns/BelongsToTenant.php` |
| `tenant_id` is stamped from the server-side context, never mass-assignable, and immutable after create | same file, `TenantModel` |
| Tenant resolved only from the logged-in user, never from URL or form input | `app/Http/Middleware/SetTenantContext.php` |
| Cross-tenant IDs in URLs return **404** (route model binding runs through the scope); nested routes use `scopeBindings()` | `routes/web.php` |
| Deny-by-default permission matrix per role; route middleware `permission:` and `role:`; every permission is also a Gate for Blade `@can` | `config/vector7.php`, `app/Http/Middleware/EnsurePermission.php`, `AppServiceProvider` |
| Shareholder and Customer portals query only the user's own linked record — no record IDs accepted from the request (no IDOR) | `app/Http/Controllers/Portal/*` |
| Validation rules check that referenced IDs belong to the same tenant (`Rule::exists(...)->where('tenant_id', …)`) | all controllers |
| No share buy / sell / transfer routes exist; a test asserts it | `tests/Feature/AccessControlTest.php` |
| Super Admin console is hidden (404) from every other role; impersonation needs a reason, lasts 30 minutes, shows a red banner and is logged | `EnsureSuperAdmin`, `Platform/TenantController` |
| Uploaded files live in private storage and are streamed only after a tenant-prefix check | `app/Services/FileVault.php` |

## A02 — Cryptographic Failures

- Aadhaar, PAN and bank details use Laravel's `encrypted` cast (AES-256-CBC + HMAC with `APP_KEY`); screens show masked values only.
- Passwords hashed with bcrypt (cost 12); `hashed` cast prevents plain-text storage.
- HTTPS enforced (`.htaccess` redirect, `URL::forceScheme('https')`), HSTS header, `Secure` + `HttpOnly` + `SameSite=Lax` cookies, encrypted session payloads (`SESSION_ENCRYPT=true`).
- **Operator:** keep `APP_KEY` secret and backed up separately — losing it makes encrypted fields unreadable.

## A03 — Injection

- All database access through Eloquent / query builder bindings; the few `selectRaw` / `whereRaw` fragments contain **no user input**. `LIKE` searches escape `%` and `_`.
- Blade `{{ }}` escapes all output; the only raw outputs are static icon paths and JSON-LD encoded with `JSON_HEX_TAG`.
- The master-data `{type}` segment is matched against a fixed allow-list, never used to build class or table names.
- CSV import validates every cell; CSV export neutralises cells starting with `= + - @` (formula injection).

## A04 — Insecure Design

- Plot status can only change through one state machine that allows the documented transitions only (`PlotStatus::allowedNext`, `PlotStatusMachine`).
- Booking and sale operations run in DB transactions with `SELECT … FOR UPDATE` row locks — two users cannot book or sell the same plot.
- Payments cannot exceed the balance; share payouts cannot exceed allocated earnings; registration cannot complete with dues or unverified required items.
- Financial and share records are **append-only** (`AppendOnly` trait); corrections are reversal rows.
- Plan limits and feature flags enforced server-side (`PlanLimits`).
- Buttons are disabled after submit to stop double posting; confirmation dialogs on money actions.

## A05 — Security Misconfiguration

- Security headers on every response: strict **CSP** (scripts from `'self'` only — no inline scripts, no third-party CDNs), `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`, COOP/CORP, HSTS (`SecurityHeaders` middleware).
- `APP_DEBUG=false` in `.env.example`; custom error pages never show stack traces.
- `.htaccess` disables directory listing and blocks dotfiles (`.env`, `.git`) and backup/log extensions; `X-Powered-By` removed.
- Non-production environments are `noindex` and `robots.txt: Disallow: /`.
- **Operator:** app folder outside `public_html`, `.env` mode 600, PHP `display_errors=Off`, `expose_php=Off`.

## A06 — Vulnerable and Outdated Components

- Minimal dependency set: only `laravel/framework` at runtime. No jQuery, no CDN scripts. Fonts and Chart.js are self-hosted.
- **Operator:** run `composer audit` and `npm audit` before each release; apply Laravel patch releases monthly.

## A07 — Identification and Authentication Failures

- Login rate limit: 5/minute per email+IP and 20/minute per IP; registration 5/hour per IP; password reset 3/minute.
- Strong password policy (10+ chars, mixed case, number, symbol) and, in production, a breached-password check (HIBP k-anonymity).
- Session ID regenerated on login (fixation defence), invalidated on logout; 30-minute idle session lifetime.
- Generic login and reset messages (no user enumeration). Only `active` accounts can sign in; disabled users are logged out on their next request.
- Email verification required; Admins never see or set other users' passwords (users set their own via a signed reset link).

### Public booking and tracking (A04 / A07)
- Online holds need a 6-digit email one-time code: HMAC-hashed at rest, 10-minute expiry, 5 attempts, single use; codes are rate-limited per email and IP.
- Pending booking details are kept server-side in the session — the browser never re-posts them.
- "Track my booking" answers identically whether or not the email exists (no enumeration).
- Honeypot fields and per-IP limits on public forms; holds are limited to one per customer per project and auto-release after 48 hours.
- Public pages show only plot facts (size, facing, price, Available / On hold / Sold) — never customer, owner or payment data.
- No payment or purchase can be made on the website.

## A08 — Software and Data Integrity Failures

- CSRF tokens on every state-changing form (webhooks excluded and HMAC-verified instead).
- Razorpay webhooks: HMAC-SHA256 signature checked with `hash_equals`, event IDs stored with a unique key so replays are ignored, amount re-checked against the invoice.
- Only fillable attributes are mass-assigned; role, status, tenant and links are set explicitly by services.
- Append-only ledgers and share events preserve history.

## A09 — Security Logging and Monitoring Failures

- `audit_logs` records create/update/delete of business records (sensitive fields excluded) with user, IP and user agent.
- `storage/logs/security.log` (90 days): failed logins (email hashed), lockouts, permission denials, platform access attempts, impersonation start/stop, webhook signature failures.
- `plot_status_history`, `notification_logs`, `impersonation_sessions` provide operational trails.
- **Operator:** review `security.log` weekly; alert on spikes in `login_failed` or `permission_denied`.

## A10 — Server-Side Request Forgery

- The app never fetches a user-supplied URL. Outbound calls go only to fixed hosts in config: `api.razorpay.com` and `graph.facebook.com`.
- The Google Drive folder setting accepts only an ID pattern, not a URL.

## Operator checklist (every release)

- [ ] `composer audit` clean
- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] `https://vector7.in/.env` → 403/404
- [ ] securityheaders.com grade A
- [ ] Backups verified
- [ ] `security.log` reviewed
