---
title: Security monitoring
audience: app
route: app.security.index
keywords: security, sessions, ip, failed login, locked, sign-in, brute force, app support
---
# Security monitoring
Open **Security** in the App menu. It is available to App Admin and **App Support**.

- **Signed-in sessions** — everyone signed in right now (App team, promoters, property listers and buyers). Each row shows their IP address, browser and device, when they signed in and when they were last active. *changed* means their IP is different from the one they signed in with. **End session** signs that person out at once; they must sign in again.
- **Sign-in activity** — every attempt on the four sign-in pages: buyer, promoter workspace, property lister and App. The result is one of: signed in, wrong password / unknown email, locked, or blocked (disabled account, suspended workspace, wrong sign-in page). Filter by email or IP, result, page or dates, and export to Excel. Records are kept for 180 days.
- **Failed attempts by IP** — IP addresses and accounts with failed sign-ins in the last hour, 24 hours or 7 days. Rows turn red at 10 or more failures, or when one IP tries 5 or more different accounts, which is a sign of password guessing.
- **Locked accounts** — accounts locked after 5 wrong passwords (they unlock themselves after 15 minutes). **Unlock** releases one early.

Passwords are never stored or shown.

## App Support role
Give someone the **App Support** role under App → IAM. They can work on:
- **Help desk** tickets, including service requests, which arrive as tickets;
- **Enquiries**;
- **Chatbot conversations**;
- **Security monitoring**;
- read-only **Services**, **Customers** and **Listings moderation**.

Their home page is a support queue: open, overdue and unassigned tickets, new enquiries and service requests, today's chats, failed sign-ins and locked accounts. They cannot see plans, tenants' subscriptions, revenue, settings, IAM or the audit log.
