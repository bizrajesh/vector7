-- =====================================================================================================
-- vector7 — remove ALL test data before going to production
--
-- KEEPS
--   * The App Admin login(s): users with no tenant whose role is "App Admin" (name, email, password).
--   * Prerequisites: App-level masters (approval stages, sub-tasks with their documents and dependencies,
--     document checklist, facilities, property features), the App roles (App Admin / App Manager /
--     App Support), permissions, plans and plan limits, top-up packs, services, the Tamil Nadu SRO list,
--     email templates, App settings (organisation, SMTP, payment gateway, AI key, sign-up switches…)
--     and the migrations table.
--
-- REMOVES
--   * Every promoter and property-lister workspace (tenants) and everything inside them: projects, plots,
--     bookings, sales, payments, receipts, registrations, leads, listings, expenses, tenant users and roles,
--     tenant copies of the masters, subscriptions, invoices, top-ups, promo codes…
--   * Every buyer (customer) account with its shortlists, requirements, saved searches and contact unlocks.
--   * Other App users (App Manager / App Support) — add them again from App → Users.
--   * Logs and runtime data: audit log, sign-in log, AI logs, chatbot conversations, help-desk tickets,
--     service requests, enquiries, testimonials, SEO page cache, sessions, cache and queued jobs.
--
-- HOW TO RUN (take a backup first!)
--   1. php artisan down
--   2. Back up:  mysqldump -u USER -p DATABASE > before-cleanup.sql
--      (or hPanel → Databases → phpMyAdmin → Export)
--   3. Run:      mysql -u USER -p DATABASE < database/sql/production-cleanup.sql
--      (or phpMyAdmin → select the database → Import → choose this file)
--   4. Delete the uploaded test files: everything inside storage/app/private/tenants,
--      storage/app/private/receipts, storage/app/private/backups and storage/app/public (keep the folders).
--   5. php artisan optimize:clear && php artisan optimize && php artisan seo:build && php artisan up
--
-- Safe to run more than once. No table is dropped. Every table in the database is listed here once;
-- tests/Feature/ProductionCleanupTest.php fails if a new table is added without being listed.
-- =====================================================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ---------- 1. Buyers (customers) and what they created ----------
TRUNCATE TABLE customer_requirement_matches;
TRUNCATE TABLE customer_requirements;
TRUNCATE TABLE customer_shortlists;
TRUNCATE TABLE customer_password_reset_tokens;
TRUNCATE TABLE customer_tenant;
TRUNCATE TABLE saved_search_matches;
TRUNCATE TABLE saved_searches;
TRUNCATE TABLE contact_unlocks;
TRUNCATE TABLE customers;

-- ---------- 2. Projects, approvals, plots ----------
TRUNCATE TABLE project_subtasks;
TRUNCATE TABLE project_stages;
TRUNCATE TABLE project_documents;
TRUNCATE TABLE estimate_lines;
TRUNCATE TABLE project_estimates;
TRUNCATE TABLE plot_status_history;
TRUNCATE TABLE plots;
TRUNCATE TABLE projects;

-- ---------- 3. Bookings, sales, money, registration ----------
TRUNCATE TABLE bookings;
TRUNCATE TABLE instalments;
TRUNCATE TABLE instalment_plans;
TRUNCATE TABLE payments;
TRUNCATE TABLE receipts;
TRUNCATE TABLE refunds;
TRUNCATE TABLE refund_penalty_rules;
TRUNCATE TABLE sales;
TRUNCATE TABLE registration_witnesses;
TRUNCATE TABLE registration_parties;
TRUNCATE TABLE registrations;
TRUNCATE TABLE brokers;
TRUNCATE TABLE expenses;
TRUNCATE TABLE expense_categories;
TRUNCATE TABLE promo_code_uses;
TRUNCATE TABLE promo_codes;

-- ---------- 4. Leads, enquiries, listings ----------
TRUNCATE TABLE lead_activities;
TRUNCATE TABLE lead_followups;
TRUNCATE TABLE lead_interests;
TRUNCATE TABLE lead_stages;
TRUNCATE TABLE leads;
TRUNCATE TABLE enquiries;
TRUNCATE TABLE listing_contacts;
TRUNCATE TABLE listing_features;
TRUNCATE TABLE listing_images;
TRUNCATE TABLE listing_reports;
TRUNCATE TABLE listings;
TRUNCATE TABLE testimonials;

-- ---------- 5. Workspaces (tenants), their plans in use and settings ----------
TRUNCATE TABLE subscription_invoices;
TRUNCATE TABLE subscriptions;
TRUNCATE TABLE tenant_topups;
TRUNCATE TABLE usage_snapshots;
TRUNCATE TABLE tenant_settings;
TRUNCATE TABLE id_sequences;
TRUNCATE TABLE holidays;
TRUNCATE TABLE notification_group_user;
TRUNCATE TABLE notification_groups;
TRUNCATE TABLE whatsapp_templates;
TRUNCATE TABLE social_metrics;
TRUNCATE TABLE social_posts;
TRUNCATE TABLE social_accounts;
TRUNCATE TABLE storage_files;
TRUNCATE TABLE tenants;

-- ---------- 6. Help desk, services, chatbot, AI, logs ----------
TRUNCATE TABLE ticket_messages;
TRUNCATE TABLE tickets;
TRUNCATE TABLE service_requests;
TRUNCATE TABLE chat_messages;
TRUNCATE TABLE chat_conversations;
TRUNCATE TABLE ai_search_logs;
TRUNCATE TABLE ai_usage_logs;
TRUNCATE TABLE audit_logs;
TRUNCATE TABLE login_events;
TRUNCATE TABLE seo_meta;

-- ---------- 7. Framework runtime tables ----------
TRUNCATE TABLE sessions;
TRUNCATE TABLE cache;
TRUNCATE TABLE cache_locks;
TRUNCATE TABLE jobs;
TRUNCATE TABLE job_batches;
TRUNCATE TABLE failed_jobs;
TRUNCATE TABLE password_reset_tokens;

-- ---------- 8. Prerequisites: keep the App-level rows, remove each workspace's copies ----------
DELETE FROM subtask_dependencies
 WHERE subtask_master_id NOT IN (SELECT id FROM subtask_masters WHERE tenant_id IS NULL)
    OR depends_on_id     NOT IN (SELECT id FROM subtask_masters WHERE tenant_id IS NULL);
DELETE FROM subtask_documents
 WHERE subtask_master_id     NOT IN (SELECT id FROM subtask_masters WHERE tenant_id IS NULL)
    OR document_checklist_id NOT IN (SELECT id FROM document_checklists WHERE tenant_id IS NULL);
DELETE FROM subtask_masters     WHERE tenant_id IS NOT NULL;
DELETE FROM stage_masters       WHERE tenant_id IS NOT NULL;
DELETE FROM document_checklists WHERE tenant_id IS NOT NULL;
DELETE FROM facility_masters    WHERE tenant_id IS NOT NULL;
DELETE FROM feature_masters     WHERE tenant_id IS NOT NULL;

-- ---------- 9. Users: keep only the App Admin login(s) ----------
DELETE FROM users
 WHERE tenant_id IS NOT NULL
    OR role_id IS NULL
    OR role_id NOT IN (SELECT id FROM roles WHERE tenant_id IS NULL AND base_role = 'app_admin');
UPDATE users SET failed_logins = 0, locked_until = NULL, remember_token = NULL, last_login_at = NULL, created_by = NULL, updated_by = NULL;
DELETE FROM roles WHERE tenant_id IS NOT NULL;

-- ---------- 10. Kept as they are (listed so every table is accounted for) ----------
-- migrations, permissions, roles (App roles), plans, plan_limits, topup_packs, services, sros,
-- email_templates, settings

SET FOREIGN_KEY_CHECKS = 1;

-- Check: should show 1 App Admin (or more if you made several) and 0 everywhere else.
SELECT (SELECT COUNT(*) FROM users) AS app_admin_users, (SELECT COUNT(*) FROM tenants) AS workspaces,
       (SELECT COUNT(*) FROM customers) AS buyers, (SELECT COUNT(*) FROM projects) AS projects,
       (SELECT COUNT(*) FROM listings) AS listings, (SELECT COUNT(*) FROM plans) AS plans,
       (SELECT COUNT(*) FROM stage_masters) AS approval_stages;
