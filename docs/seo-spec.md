# SEO Spec — Vector7

Filled from the team template (SEO_Implementation_Guidelines_Standard_Template, §13).

## 1. Project basics
- Project owner / tech lead: Rajesh / TBD
- Production domain (preferred host): **https://vector7.in** (www → apex 301, set in `public/.htaccess`; `APP_URL=https://vector7.in`)
- Trailing slash convention: none (301 enforced in `.htaccess`)
- Framework & rendering: Laravel 11 Blade, server-rendered; portal `/app/*`, `/platform/*`, `/portal/*` are noindex
- Languages / regions: en-IN at launch; ta-IN planned (hreflang when added)
- Target launch date: TBD

## 2. Audience & keywords
- Primary audience: layout promoters, plot developers and land promoters in Tamil Nadu / South India
- Primary keywords (top 5): TBD after keyword research
- Competitor sites to benchmark: TBD

## 3. Page inventory
| Route | Page type | Title (50–60) | Index? | Schema types |
|---|---|---|---|---|
| / | Home | Plots for Sale – Live Availability & Online Booking · Vector7 | Yes | Organization, WebSite, SoftwareApplication |
| /features | Landing | Features – Layout, Sales & Shareholder Management · Vector7 | Yes | BreadcrumbList |
| /pricing | Pricing | Pricing – Plans for Real Estate Layout Developers · Vector7 | Yes | Product + Offer, BreadcrumbList |
| /about | About | About Vector7 – Built for Layout Promoters, India · Vector7 | Yes | BreadcrumbList |
| /contact | Contact | Contact Vector7 – Demo, Support & Sales Enquiries · Vector7 | Yes | BreadcrumbList |
| /privacy, /terms | Legal | (see PageController) | Yes | BreadcrumbList |
| /projects | Listing | Plots for Sale – Browse Approved Layout Projects · Vector7 | Yes | BreadcrumbList |
| /projects/{developer}/{project} | Project + plot map | {Project} – Plots in {District} · Vector7 | Yes | Place (+geo), BreadcrumbList |
| /book/*, /track | Booking hold / tracking | — | No (robots Disallow + X-Robots-Tag) | — |
| /login, /register, /forgot-password | Auth | — | No (meta + X-Robots-Tag) | — |
| /app/*, /platform/*, /portal/* | Portal | — | No (auth + X-Robots-Tag) | — |

## 4. Technical decisions
- robots.txt (production): Disallow `/app/`, `/platform/`, `/portal/`, `/login`, `/register`, `/webhooks/`; sitemap line included. Non-production: `Disallow: /`.
- AI crawler policy: TBD
- Sitemap: `/sitemap.xml`, public pages only, `lastmod` from view files
- Parameter handling: canonical tags are self-referencing clean URLs
- Default OG image: `/img/og-default.png` (1200×630)

## 5. Performance budget
- LCP ≤ 2.5 s | INP ≤ 200 ms | CLS ≤ 0.1 | TTFB ≤ 600 ms
- Public pages load one CSS file (~47 KB) and one small JS file; Chart.js only on signed-in pages
- Fonts self-hosted WOFF2 with `font-display: swap`; hashed assets cached 1 year

## 6. Environments
| Env | Protection | robots |
|---|---|---|
| Staging | cPanel Directory Privacy | noindex, Disallow: / (automatic when APP_ENV ≠ production) |
| Production | Public | index, follow |

## 7. Tracking & monitoring
- Search Console / Bing: TBD at launch
- Analytics: TBD (consent-aware; add its host to the CSP in `SecurityHeaders` when chosen)

## 8. Exceptions to the standard
| Rule | Reason | Approved by | Date |
|---|---|---|---|
| Approved frameworks (§2) | Laravel Blade chosen for GoDaddy PHP hosting; fully server-rendered | TBD | TBD |
| Lighthouse CI in pipeline (§11) | No CI configured yet; run Lighthouse manually before each release | TBD | TBD |
