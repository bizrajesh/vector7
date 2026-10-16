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

## Feature Update 01 (spec 17)

32. **Build order.** Leads (17.6) was built before Quick Launch (17.1), because every contact flow in 17.1–17.5 writes a lead. Parts 17.1–17.6 and the shared data model (17.11/17.12) went in as one commit; later parts are committed one by one.
33. **Enquiries become leads.** On upgrade, a migration copies every tenant enquiry into `leads` (stage mapped from the old status) and keeps the `enquiries` table. App-level enquiries (from the Support page) stay in `enquiries`. When the App team assigns one to a promoter, it is copied into that promoter's Leads. `/workspace/enquiries` permanently redirects to `/workspace/leads`.
34. **Duplicate leads merge** within a tenant by mobile number (last 10 digits) or email (case-insensitive). A closed (Won/Lost) lead that comes back is reopened as New. Different tenants never share leads.
35. **Images** are stored as StorageFile ids in `listing_images` (a 1600 px WebP and a 400 px thumbnail), not as paths, so every storage driver works. The old `projects.gallery` JSON was moved into `listing_images`, and old gallery URLs redirect (301) to the new image URLs.
36. **Quick-launch plot errors** are checked before anything is saved, using an unsaved Project. On errors the form comes back with the row errors, and the photos and layout must be chosen again, because browsers cannot refill file inputs.
37. **Project contact relation** is called `contactBlock`, because `projects.contact` is an existing phone-number column. A layout with no contact block uses the promoter's default contact settings and the project's phone number.
38. **Contact gating.** Call, WhatsApp and Show number ask once per browser session for name, mobile and consent. After that the session remembers the visitor, and each later click still logs an activity on the same (merged) lead. Reveals are limited to 5 a minute and 30 a day per IP. Without JavaScript the form posts and the page comes back with the number.
39. **Search covers both** published listings and available plots in launched layouts. Plots are grouped into one "layout" card per project, showing the matching plot count and a "from" price. Searching for "plot" includes layouts. Layouts never appear in rent searches or BHK searches.
40. **Relaxation order** (when fewer than 5 results): widen the budget by 10% → add neighbouring districts from `config/districts.php` (editable) → drop free-text wishes one at a time, last first → ignore feature filters. The page lists every step that was applied.
41. **Claude never supplies results.** The AI only (a) turns the request into filters, using a strict tool schema, and (b) orders the top 30 database results with a one-line reason. Any key Claude returns that is not in the SQL results is discarded. Only key fields are sent, never contact details. Parse and rank results are cached for 1 hour. When AI is off, over its daily cap, or fails, a built-in English/Tamil keyword parser and SQL relevance order are used instead.
42. **Marketplace caches** (sitemap, category counts, known places) are cleared whenever a listing is saved or deleted. They are cached as arrays, because the database cache store does not unserialize objects.
43. **Sold or rented listings** answer **410 Gone** with similar properties, and drop out of search, the sitemap and category pages. Listings hidden by the App team, drafts and unpublished listings answer 404. Search pages with filters are `noindex,follow`. Clean category URLs (`/buy/flats/thanjavur`) are indexable and listed in the sitemap.
44. **Compare** works from a public `?keys=L:1,P:2` link (up to 3), so a shared link shows the same table. The shortlist and saved searches need a buyer account. Saved-search alerts email at most 5 new matches per run and never repeat an item (`saved_search_matches`).
45. **Chatbot leads need the visitor's own consent.** The marketplace bot's `create_lead` tool only proposes a lead. The lead is created when the visitor fills in their name and mobile and ticks consent in the chat window, through a separate endpoint that also checks the bot really proposed that property. The model never sees contact numbers.
46. **Workspace assistant scope.** It uses read-only tools, each tied to a permission (and to the plan module for Leads), and every tool runs inside the user's workspace. Claude is only offered the tools the user's role allows. Sales see their own and unassigned leads; Support sees no money data. The only write is "create follow-up", done by a separate endpoint after **Confirm**, which re-checks permission and lead visibility. "Apply" puts a draft into the text box the user last clicked; nothing is saved until they save the form.
47. **AI credits.** The workspace assistant charges the workspace's monthly AI credits. When they run out (or the plan has no AI), it still answers from the help pages and runs simple data shortcuts, and says why. The marketplace bot and AI search are charged to the App's daily caps per feature group.
48. **Conversations** are kept for 90 days for both bots (`v7:chat-purge` runs daily at 03:30). A workspace conversation continues for 12 hours, then starts fresh. Assistant tables can be downloaded as Excel by the user who asked.
