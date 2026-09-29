# Assumptions made in the build (confirm before go-live)

| # | Topic | Assumption implemented | Where to change |
| --- | --- | --- | --- |
| 1 | Booking expiry | A booking lapses if it is **not converted to a sale** within the validity period (default 15 days) | `BookingService::expireDue`, Settings → booking validity |
| 2 | Minimum booking amount | None (₹1 up to plot cost) | `BookingController::store` validation |
| 3 | Expired / cancelled booking money | Not refunded automatically; Admin records any refund in Accounting | Accounting → manual entry (type *refund*) |
| 4 | Instalment due dates | 30% on working day 0, 60% on day 7, 10% on day 15 (Sundays and tenant holidays skipped) | Settings → Buy instalments |
| 5 | Past the sale window | Sale stays *Ongoing-Sale* with an Overdue flag; Admin may cancel | `SaleService::cancel` |
| 6 | Registration checklist item 12 | Seller **PAN** (the brief listed Seller Aadhaar twice) | Settings → Checklists |
| 7 | Stage group per project | Each layout project uses exactly one stage group (copied as a snapshot) | `StageService::instantiate` |
| 8 | Budget alert threshold | 80% of stage budget | Settings → Expense vs budget alert % |
| 9 | Share value recognition | When the plot reaches **Sold** (configurable to ROR) | Settings → Shares |
| 10 | Share allocation per sale | Shareholders are credited with realised **profit** × holding %; their % of gross proceeds is recorded for information | `ShareService::recordEvent` |
| 11 | Realised profit | Sale value − plot sqft × baseline cost per sellable sqft (locked at submit) − broker commission | `ShareService::recogniseSale` |
| 12 | Allocations after first sale | Issued at current share value, with a reason, so existing holders are not diluted | `ShareService::allocate` |
| 13 | Subscription payments | Razorpay Payment Links (hosted page) + webhook; GST 18% added to invoices | `BillingController`, `RazorpayGateway` |
| 14 | Trial end | 7-day grace (past due), then suspended = read-only; data kept | `vector7:subscriptions` |
| 15 | Google Drive storage | Setting saved; files stored in private server storage until the Drive add-on is enabled | Settings → Document storage |
| 16 | Email uniqueness | One email = one login across the platform | `users.email` unique |
| 18 | Website bookings | A website booking is a free **hold** (default 48 h) confirmed by an email one-time code; Sales converts it to a normal 15-day booking by recording the advance | Settings → Online booking hold, `OnlineBookingService` |
| 19 | Online purchases | Not possible by design — customers send a purchase / call-back request; Sales completes every sale | `/app/requests` |
| 20 | One online hold | One open website hold per customer per project | `OnlineBookingService::hold` |
| 21 | Public statuses | Buyers see Available / On hold / Sold only — never who holds a plot | `public/projects/show.blade.php` |
| 22 | Publishing | A launched project appears publicly only if the Admin ticks *Show on the public website* and the plan includes public listings (Growth, Enterprise) | Layout → Overview |
| 17 | PDFs | Receipts, registration details and acknowledgement are print-optimised pages ("Print / Save as PDF") — no PDF library needed on shared hosting | `resources/views/print` |
