-- =====================================================================
-- Vector7 — Real-estate layout to plot-sales portal
-- MySQL 8.0+ schema (also runs on MariaDB 10.6+)
-- Engine InnoDB, charset utf8mb4, money DECIMAL(15,2), times in UTC.
--
-- Import options:
--   a) phpMyAdmin → Import → this file, OR
--   b) php artisan migrate  (the first migration runs this same file)
-- Every business table carries tenant_id for row-level tenant isolation.
-- Sensitive personal fields (Aadhaar, PAN, bank) are stored ENCRYPTED by
-- the application (AES-256-CBC via Laravel Crypt), never in plain text.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. Platform: plans, tenants, subscriptions
-- ---------------------------------------------------------------------
CREATE TABLE plans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(80) NOT NULL,
  description VARCHAR(255) NULL,
  badge VARCHAR(40) NULL,
  price_monthly DECIMAL(12,2) NOT NULL DEFAULT 0,
  price_yearly DECIMAL(12,2) NOT NULL DEFAULT 0,
  trial_days INT UNSIGNED NOT NULL DEFAULT 14,
  max_users INT UNSIGNED NOT NULL DEFAULT 3,
  max_layouts INT UNSIGNED NOT NULL DEFAULT 1,
  max_plots INT UNSIGNED NOT NULL DEFAULT 150,
  max_storage_mb INT UNSIGNED NOT NULL DEFAULT 2048,
  features JSON NULL,
  status ENUM('active','hidden','archived') NOT NULL DEFAULT 'active',
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY plans_code_unique (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tenants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  plan_id BIGINT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(80) NOT NULL,
  gstin VARCHAR(20) NULL,
  address VARCHAR(255) NULL,
  city VARCHAR(80) NULL,
  state VARCHAR(80) NULL,
  phone VARCHAR(20) NULL,
  email VARCHAR(190) NULL,
  logo_path VARCHAR(255) NULL,
  status ENUM('trial','active','past_due','suspended','cancelled') NOT NULL DEFAULT 'trial',
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY tenants_slug_unique (slug),
  KEY tenants_status_index (status),
  CONSTRAINT tenants_plan_fk FOREIGN KEY (plan_id) REFERENCES plans (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscriptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  plan_id BIGINT UNSIGNED NOT NULL,
  cycle ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
  status ENUM('trial','active','past_due','suspended','cancelled') NOT NULL DEFAULT 'trial',
  trial_ends_at TIMESTAMP NULL,
  current_period_start TIMESTAMP NULL,
  current_period_end TIMESTAMP NULL,
  grace_ends_at TIMESTAMP NULL,
  gateway VARCHAR(30) NULL,
  gateway_subscription_id VARCHAR(100) NULL,
  cancelled_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY subscriptions_tenant_status_index (tenant_id, status),
  UNIQUE KEY subscriptions_gateway_sub_unique (gateway_subscription_id),
  CONSTRAINT subscriptions_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT subscriptions_plan_fk FOREIGN KEY (plan_id) REFERENCES plans (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscription_invoices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  subscription_id BIGINT UNSIGNED NOT NULL,
  invoice_no VARCHAR(40) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  gst_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL,
  status ENUM('due','paid','failed','void') NOT NULL DEFAULT 'due',
  gateway_payment_id VARCHAR(100) NULL,
  paid_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY subscription_invoices_no_unique (invoice_no),
  KEY subscription_invoices_tenant_index (tenant_id, status),
  CONSTRAINT sub_invoices_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT sub_invoices_subscription_fk FOREIGN KEY (subscription_id) REFERENCES subscriptions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Idempotency store for payment-gateway webhooks (replay protection)
CREATE TABLE payment_webhook_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  gateway VARCHAR(30) NOT NULL,
  event_id VARCHAR(120) NOT NULL,
  type VARCHAR(80) NOT NULL,
  payload JSON NULL,
  processed_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY webhook_events_unique (gateway, event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. Users, sessions, framework tables
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NULL COMMENT 'NULL only for platform super admins',
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(20) NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('super_admin','admin','sales','shareholder','customer') NOT NULL,
  status ENUM('active','invited','disabled') NOT NULL DEFAULT 'active',
  shareholder_id BIGINT UNSIGNED NULL,
  customer_id BIGINT UNSIGNED NULL,
  email_verified_at TIMESTAMP NULL,
  last_login_at TIMESTAMP NULL,
  last_login_ip VARCHAR(45) NULL,
  remember_token VARCHAR(100) NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY users_email_unique (email),
  KEY users_tenant_role_index (tenant_id, role),
  CONSTRAINT users_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_reset_tokens (
  email VARCHAR(190) NOT NULL,
  token VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NULL,
  PRIMARY KEY (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sessions (
  id VARCHAR(255) NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  user_agent TEXT NULL,
  payload LONGTEXT NOT NULL,
  last_activity INT NOT NULL,
  PRIMARY KEY (id),
  KEY sessions_user_id_index (user_id),
  KEY sessions_last_activity_index (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cache (
  `key` VARCHAR(255) NOT NULL,
  value MEDIUMTEXT NOT NULL,
  expiration INT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cache_locks (
  `key` VARCHAR(255) NOT NULL,
  owner VARCHAR(255) NOT NULL,
  expiration INT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  queue VARCHAR(255) NOT NULL,
  payload LONGTEXT NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL,
  reserved_at INT UNSIGNED NULL,
  available_at INT UNSIGNED NOT NULL,
  created_at INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY jobs_queue_index (queue)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_batches (
  id VARCHAR(255) NOT NULL,
  name VARCHAR(255) NOT NULL,
  total_jobs INT NOT NULL,
  pending_jobs INT NOT NULL,
  failed_jobs INT NOT NULL,
  failed_job_ids LONGTEXT NOT NULL,
  options MEDIUMTEXT NULL,
  cancelled_at INT NULL,
  created_at INT NOT NULL,
  finished_at INT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE failed_jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid VARCHAR(255) NOT NULL,
  connection TEXT NOT NULL,
  queue TEXT NOT NULL,
  payload LONGTEXT NOT NULL,
  exception LONGTEXT NOT NULL,
  failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY failed_jobs_uuid_unique (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL,
  auditable_type VARCHAR(120) NULL,
  auditable_id BIGINT UNSIGNED NULL,
  old_values JSON NULL,
  new_values JSON NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY audit_logs_tenant_index (tenant_id, created_at),
  KEY audit_logs_auditable_index (auditable_type, auditable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE impersonation_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  super_admin_id BIGINT UNSIGNED NOT NULL,
  tenant_id BIGINT UNSIGNED NOT NULL,
  target_user_id BIGINT UNSIGNED NOT NULL,
  reason VARCHAR(255) NOT NULL,
  started_at TIMESTAMP NULL,
  expires_at TIMESTAMP NULL,
  ended_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY impersonation_tenant_index (tenant_id),
  CONSTRAINT impersonation_admin_fk FOREIGN KEY (super_admin_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT impersonation_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tenant_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  `key` VARCHAR(80) NOT NULL,
  value JSON NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY tenant_settings_unique (tenant_id, `key`),
  CONSTRAINT tenant_settings_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. Settings / master data (Admin pre-configuration)
-- ---------------------------------------------------------------------
CREATE TABLE stage_groups (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY stage_groups_tenant_index (tenant_id),
  CONSTRAINT stage_groups_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stage_templates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  stage_group_id BIGINT UNSIGNED NOT NULL,
  stage_no VARCHAR(20) NOT NULL,
  seq_no INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(500) NULL,
  cost DECIMAL(15,2) NOT NULL DEFAULT 0,
  duration_days INT UNSIGNED NOT NULL DEFAULT 1,
  stage_type ENUM('independent','dependent') NOT NULL DEFAULT 'independent',
  is_mandatory TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY stage_templates_no_unique (tenant_id, stage_no),
  KEY stage_templates_group_index (stage_group_id, seq_no),
  CONSTRAINT stage_templates_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT stage_templates_group_fk FOREIGN KEY (stage_group_id) REFERENCES stage_groups (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stage_template_links (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  stage_template_id BIGINT UNSIGNED NOT NULL,
  depends_on_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY stage_template_links_unique (stage_template_id, depends_on_id),
  CONSTRAINT stl_stage_fk FOREIGN KEY (stage_template_id) REFERENCES stage_templates (id) ON DELETE CASCADE,
  CONSTRAINT stl_depends_fk FOREIGN KEY (depends_on_id) REFERENCES stage_templates (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE task_templates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  stage_template_id BIGINT UNSIGNED NOT NULL,
  task_no VARCHAR(20) NOT NULL,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(500) NULL,
  effort_days DECIMAL(6,2) NOT NULL DEFAULT 1,
  weight_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY task_templates_stage_index (stage_template_id),
  CONSTRAINT task_templates_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT task_templates_stage_fk FOREIGN KEY (stage_template_id) REFERENCES stage_templates (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE facilities (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  unit ENUM('sqft','rft','nos','lumpsum') NOT NULL DEFAULT 'lumpsum',
  unit_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
  deduct_from_sellable TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY facilities_tenant_index (tenant_id),
  CONSTRAINT facilities_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE brokers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(20) NULL,
  email VARCHAR(190) NULL,
  pan TEXT NULL COMMENT 'encrypted',
  commission_pct DECIMAL(5,2) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY brokers_tenant_index (tenant_id),
  CONSTRAINT brokers_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_writers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  licence_no VARCHAR(60) NULL,
  office VARCHAR(190) NULL,
  phone VARCHAR(20) NULL,
  email VARCHAR(190) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY document_writers_tenant_index (tenant_id),
  CONSTRAINT document_writers_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sub_registrar_offices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  district VARCHAR(80) NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY sro_tenant_index (tenant_id),
  CONSTRAINT sro_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE holidays (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  holiday_date DATE NOT NULL,
  name VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY holidays_unique (tenant_id, holiday_date),
  CONSTRAINT holidays_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE checklist_templates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  purpose ENUM('registration','general') NOT NULL DEFAULT 'general',
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY checklist_templates_tenant_index (tenant_id, purpose),
  CONSTRAINT checklist_templates_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE checklist_template_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  checklist_template_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(190) NOT NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 1,
  needs_upload TINYINT(1) NOT NULL DEFAULT 0,
  needs_date TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY cti_template_index (checklist_template_id, sort_order),
  CONSTRAINT cti_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT cti_template_fk FOREIGN KEY (checklist_template_id) REFERENCES checklist_templates (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notification_groups (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  channel_email TINYINT(1) NOT NULL DEFAULT 1,
  channel_whatsapp TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY notification_groups_tenant_index (tenant_id),
  CONSTRAINT notification_groups_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notification_group_members (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  notification_group_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  name VARCHAR(120) NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(20) NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY ngm_group_index (notification_group_id),
  CONSTRAINT ngm_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT ngm_group_fk FOREIGN KEY (notification_group_id) REFERENCES notification_groups (id) ON DELETE CASCADE,
  CONSTRAINT ngm_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notification_group_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  notification_group_id BIGINT UNSIGNED NOT NULL,
  event_code VARCHAR(60) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY nge_unique (notification_group_id, event_code),
  CONSTRAINT nge_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT nge_group_fk FOREIGN KEY (notification_group_id) REFERENCES notification_groups (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. Layout projects
-- ---------------------------------------------------------------------
CREATE TABLE layouts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(30) NOT NULL,
  name VARCHAR(150) NOT NULL,
  location VARCHAR(190) NULL,
  village VARCHAR(80) NULL,
  taluk VARCHAR(80) NULL,
  district VARCHAR(80) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  total_sqft DECIMAL(14,2) NOT NULL DEFAULT 0,
  sellable_pct DECIMAL(5,2) NOT NULL DEFAULT 55.00,
  std_plot_sqft DECIMAL(10,2) NOT NULL DEFAULT 1200.00,
  default_rate_sqft DECIMAL(12,2) NULL,
  land_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
  contingency_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
  stage_group_id BIGINT UNSIGNED NULL,
  status ENUM('draft','submitted','in_progress','ready_to_launch','launched','closed') NOT NULL DEFAULT 'draft',
  is_public TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'shown on the public website when launched',
  public_summary VARCHAR(500) NULL,
  planned_launch_date DATE NULL,
  submitted_at TIMESTAMP NULL,
  launched_at TIMESTAMP NULL,
  closed_at TIMESTAMP NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY layouts_code_unique (tenant_id, code),
  KEY layouts_tenant_status_index (tenant_id, status),
  CONSTRAINT layouts_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT layouts_group_fk FOREIGN KEY (stage_group_id) REFERENCES stage_groups (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE layout_owners (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  layout_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  father_name VARCHAR(120) NULL,
  address VARCHAR(255) NULL,
  phone VARCHAR(20) NULL,
  aadhaar TEXT NULL COMMENT 'encrypted',
  pan TEXT NULL COMMENT 'encrypted',
  share_pct DECIMAL(5,2) NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY layout_owners_layout_index (layout_id),
  CONSTRAINT layout_owners_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT layout_owners_layout_fk FOREIGN KEY (layout_id) REFERENCES layouts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE layout_survey_numbers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  layout_id BIGINT UNSIGNED NOT NULL,
  survey_no VARCHAR(40) NOT NULL,
  sub_division VARCHAR(40) NULL,
  extent_sqft DECIMAL(14,2) NOT NULL DEFAULT 0,
  guideline_value_sqft DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY lsn_layout_index (layout_id),
  CONSTRAINT lsn_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT lsn_layout_fk FOREIGN KEY (layout_id) REFERENCES layouts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE layout_documents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  layout_id BIGINT UNSIGNED NOT NULL,
  doc_type VARCHAR(60) NOT NULL,
  doc_no VARCHAR(80) NULL,
  doc_date DATE NULL,
  status ENUM('obtained','pending') NOT NULL DEFAULT 'pending',
  file_path VARCHAR(255) NULL,
  original_name VARCHAR(190) NULL,
  mime VARCHAR(80) NULL,
  size_bytes INT UNSIGNED NULL,
  uploaded_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY layout_documents_layout_index (layout_id),
  CONSTRAINT layout_documents_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT layout_documents_layout_fk FOREIGN KEY (layout_id) REFERENCES layouts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_stages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  layout_id BIGINT UNSIGNED NOT NULL,
  stage_template_id BIGINT UNSIGNED NULL,
  stage_no VARCHAR(20) NOT NULL,
  seq_no INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(500) NULL,
  stage_type ENUM('independent','dependent') NOT NULL DEFAULT 'independent',
  is_mandatory TINYINT(1) NOT NULL DEFAULT 1,
  budget_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
  duration_days INT UNSIGNED NOT NULL DEFAULT 1,
  owner_id BIGINT UNSIGNED NULL,
  planned_start DATE NULL,
  planned_end DATE NULL,
  actual_start DATE NULL,
  actual_end DATE NULL,
  status ENUM('pending','in_progress','completed','skipped') NOT NULL DEFAULT 'pending',
  skip_reason VARCHAR(255) NULL,
  progress_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY project_stages_layout_index (layout_id, seq_no),
  CONSTRAINT project_stages_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT project_stages_layout_fk FOREIGN KEY (layout_id) REFERENCES layouts (id) ON DELETE CASCADE,
  CONSTRAINT project_stages_owner_fk FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_stage_links (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  project_stage_id BIGINT UNSIGNED NOT NULL,
  depends_on_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY psl_unique (project_stage_id, depends_on_id),
  CONSTRAINT psl_stage_fk FOREIGN KEY (project_stage_id) REFERENCES project_stages (id) ON DELETE CASCADE,
  CONSTRAINT psl_depends_fk FOREIGN KEY (depends_on_id) REFERENCES project_stages (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_tasks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  project_stage_id BIGINT UNSIGNED NOT NULL,
  task_no VARCHAR(20) NOT NULL,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(500) NULL,
  effort_days DECIMAL(6,2) NOT NULL DEFAULT 1,
  weight_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
  is_done TINYINT(1) NOT NULL DEFAULT 0,
  done_at TIMESTAMP NULL,
  done_by BIGINT UNSIGNED NULL,
  notes VARCHAR(500) NULL,
  evidence_path VARCHAR(255) NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY project_tasks_stage_index (project_stage_id),
  CONSTRAINT project_tasks_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT project_tasks_stage_fk FOREIGN KEY (project_stage_id) REFERENCES project_stages (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE layout_facilities (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  layout_id BIGINT UNSIGNED NOT NULL,
  facility_id BIGINT UNSIGNED NOT NULL,
  qty DECIMAL(12,2) NOT NULL DEFAULT 1,
  unit_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
  total DECIMAL(15,2) NOT NULL DEFAULT 0,
  area_sqft DECIMAL(14,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY layout_facilities_unique (layout_id, facility_id),
  CONSTRAINT layout_facilities_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT layout_facilities_layout_fk FOREIGN KEY (layout_id) REFERENCES layouts (id) ON DELETE CASCADE,
  CONSTRAINT layout_facilities_facility_fk FOREIGN KEY (facility_id) REFERENCES facilities (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE layout_estimates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  layout_id BIGINT UNSIGNED NOT NULL,
  version INT UNSIGNED NOT NULL,
  stage_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
  facility_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
  land_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
  contingency_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
  total_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
  production_value DECIMAL(15,2) NOT NULL DEFAULT 0,
  sellable_sqft DECIMAL(14,2) NOT NULL DEFAULT 0,
  est_plots INT UNSIGNED NOT NULL DEFAULT 0,
  cost_per_sellable_sqft DECIMAL(15,4) NOT NULL DEFAULT 0,
  total_days INT UNSIGNED NOT NULL DEFAULT 0,
  locked_at TIMESTAMP NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY layout_estimates_version_unique (layout_id, version),
  CONSTRAINT layout_estimates_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT layout_estimates_layout_fk FOREIGN KEY (layout_id) REFERENCES layouts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE layout_notification_groups (
  tenant_id BIGINT UNSIGNED NOT NULL,
  layout_id BIGINT UNSIGNED NOT NULL,
  notification_group_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (layout_id, notification_group_id),
  CONSTRAINT lng_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT lng_layout_fk FOREIGN KEY (layout_id) REFERENCES layouts (id) ON DELETE CASCADE,
  CONSTRAINT lng_group_fk FOREIGN KEY (notification_group_id) REFERENCES notification_groups (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. Launch & sales
-- ---------------------------------------------------------------------
CREATE TABLE customers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  email VARCHAR(190) NULL,
  address VARCHAR(255) NULL,
  aadhaar TEXT NULL COMMENT 'encrypted',
  aadhaar_last4 CHAR(4) NULL,
  pan TEXT NULL COMMENT 'encrypted',
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY customers_tenant_phone_index (tenant_id, phone),
  CONSTRAINT customers_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE plots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  layout_id BIGINT UNSIGNED NOT NULL,
  plot_no VARCHAR(20) NOT NULL,
  survey_no VARCHAR(40) NULL,
  size_sqft DECIMAL(10,2) NOT NULL,
  rate_sqft DECIMAL(12,2) NOT NULL,
  cost DECIMAL(15,2) NOT NULL,
  facing ENUM('north','south','east','west','north_east','north_west','south_east','south_west') NULL,
  dimensions VARCHAR(40) NULL,
  boundary_north VARCHAR(120) NULL,
  boundary_south VARCHAR(120) NULL,
  boundary_east VARCHAR(120) NULL,
  boundary_west VARCHAR(120) NULL,
  status ENUM('available','reserved','booked','ongoing_sale','ror','ongoing_reg','sold') NOT NULL DEFAULT 'available',
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY plots_no_unique (tenant_id, layout_id, plot_no),
  KEY plots_tenant_status_index (tenant_id, status),
  CONSTRAINT plots_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT plots_layout_fk FOREIGN KEY (layout_id) REFERENCES layouts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bookings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  plot_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(15,2) NOT NULL,
  booked_at TIMESTAMP NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  status ENUM('pending','active','converted','expired','cancelled') NOT NULL DEFAULT 'active' COMMENT 'pending = online hold awaiting advance',
  source ENUM('office','online') NOT NULL DEFAULT 'office',
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY bookings_tenant_status_index (tenant_id, status, expires_at),
  KEY bookings_plot_index (plot_id),
  CONSTRAINT bookings_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT bookings_plot_fk FOREIGN KEY (plot_id) REFERENCES plots (id),
  CONSTRAINT bookings_customer_fk FOREIGN KEY (customer_id) REFERENCES customers (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sales (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  plot_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED NOT NULL,
  booking_id BIGINT UNSIGNED NULL,
  sale_value DECIMAL(15,2) NOT NULL,
  broker_id BIGINT UNSIGNED NULL,
  commission_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
  commission_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  sale_date DATE NOT NULL,
  due_by DATE NOT NULL,
  paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  status ENUM('ongoing','paid','registered','cancelled') NOT NULL DEFAULT 'ongoing',
  cancel_reason VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY sales_tenant_status_index (tenant_id, status),
  KEY sales_plot_index (plot_id),
  CONSTRAINT sales_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT sales_plot_fk FOREIGN KEY (plot_id) REFERENCES plots (id),
  CONSTRAINT sales_customer_fk FOREIGN KEY (customer_id) REFERENCES customers (id),
  CONSTRAINT sales_booking_fk FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE SET NULL,
  CONSTRAINT sales_broker_fk FOREIGN KEY (broker_id) REFERENCES brokers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sale_instalments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  sale_id BIGINT UNSIGNED NOT NULL,
  seq TINYINT UNSIGNED NOT NULL,
  pct DECIMAL(5,2) NOT NULL,
  amount DECIMAL(15,2) NOT NULL,
  due_date DATE NOT NULL,
  paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  status ENUM('due','partial','paid') NOT NULL DEFAULT 'due',
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY sale_instalments_unique (sale_id, seq),
  KEY sale_instalments_due_index (tenant_id, status, due_date),
  CONSTRAINT sale_instalments_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT sale_instalments_sale_fk FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED NOT NULL,
  booking_id BIGINT UNSIGNED NULL,
  sale_id BIGINT UNSIGNED NULL,
  amount DECIMAL(15,2) NOT NULL,
  mode ENUM('cash','upi','neft','cheque','card') NOT NULL,
  reference_no VARCHAR(80) NULL,
  paid_at DATE NOT NULL,
  receipt_no VARCHAR(40) NOT NULL,
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY payments_receipt_unique (tenant_id, receipt_no),
  KEY payments_sale_index (sale_id),
  KEY payments_booking_index (booking_id),
  CONSTRAINT payments_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT payments_customer_fk FOREIGN KEY (customer_id) REFERENCES customers (id),
  CONSTRAINT payments_booking_fk FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE SET NULL,
  CONSTRAINT payments_sale_fk FOREIGN KEY (sale_id) REFERENCES sales (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE registrations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  plot_id BIGINT UNSIGNED NOT NULL,
  sale_id BIGINT UNSIGNED NOT NULL,
  document_writer_id BIGINT UNSIGNED NULL,
  document_writer_name VARCHAR(120) NULL,
  sub_registrar_office_id BIGINT UNSIGNED NULL,
  planned_date DATE NULL,
  registration_date DATE NULL,
  document_no VARCHAR(60) NULL,
  status ENUM('ongoing','completed') NOT NULL DEFAULT 'ongoing',
  deed_path VARCHAR(255) NULL,
  ack_path VARCHAR(255) NULL,
  completed_at TIMESTAMP NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY registrations_sale_unique (sale_id),
  CONSTRAINT registrations_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT registrations_plot_fk FOREIGN KEY (plot_id) REFERENCES plots (id),
  CONSTRAINT registrations_sale_fk FOREIGN KEY (sale_id) REFERENCES sales (id),
  CONSTRAINT registrations_writer_fk FOREIGN KEY (document_writer_id) REFERENCES document_writers (id) ON DELETE SET NULL,
  CONSTRAINT registrations_sro_fk FOREIGN KEY (sub_registrar_office_id) REFERENCES sub_registrar_offices (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE registration_checklist_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  registration_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(190) NOT NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 1,
  needs_upload TINYINT(1) NOT NULL DEFAULT 0,
  needs_date TINYINT(1) NOT NULL DEFAULT 0,
  value VARCHAR(255) NULL,
  date_value DATE NULL,
  file_path VARCHAR(255) NULL,
  is_done TINYINT(1) NOT NULL DEFAULT 0,
  verified_by BIGINT UNSIGNED NULL,
  verified_at TIMESTAMP NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY rci_registration_index (registration_id, sort_order),
  CONSTRAINT rci_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT rci_registration_fk FOREIGN KEY (registration_id) REFERENCES registrations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE plot_status_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  plot_id BIGINT UNSIGNED NOT NULL,
  from_status VARCHAR(20) NULL,
  to_status VARCHAR(20) NOT NULL,
  reason VARCHAR(255) NULL,
  user_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY psh_plot_index (plot_id, created_at),
  CONSTRAINT psh_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT psh_plot_fk FOREIGN KEY (plot_id) REFERENCES plots (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. Shares (allocated by Admin only; value moves only on plot sales)
-- ---------------------------------------------------------------------
CREATE TABLE shareholders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(20) NULL,
  email VARCHAR(190) NULL,
  pan TEXT NULL COMMENT 'encrypted',
  bank_details TEXT NULL COMMENT 'encrypted',
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY shareholders_tenant_index (tenant_id),
  CONSTRAINT shareholders_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE share_pools (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  layout_id BIGINT UNSIGNED NOT NULL,
  face_value DECIMAL(12,2) NOT NULL DEFAULT 1000.00,
  total_shares DECIMAL(18,4) NOT NULL DEFAULT 0,
  total_capital DECIMAL(15,2) NOT NULL DEFAULT 0,
  cumulative_profit DECIMAL(15,2) NOT NULL DEFAULT 0,
  current_share_value DECIMAL(15,4) NOT NULL DEFAULT 1000.0000,
  baseline_cost_per_sqft DECIMAL(15,4) NULL,
  status ENUM('open','locked','closed') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY share_pools_layout_unique (layout_id),
  CONSTRAINT share_pools_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT share_pools_layout_fk FOREIGN KEY (layout_id) REFERENCES layouts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only ledger of share allocations (no UPDATE/DELETE from the app)
CREATE TABLE share_issuances (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  share_pool_id BIGINT UNSIGNED NOT NULL,
  shareholder_id BIGINT UNSIGNED NOT NULL,
  contribution_type ENUM('cash','land','reinvest') NOT NULL DEFAULT 'cash',
  contribution_amount DECIMAL(15,2) NOT NULL,
  issue_price DECIMAL(15,4) NOT NULL,
  shares DECIMAL(18,4) NOT NULL,
  issued_on DATE NOT NULL,
  reason VARCHAR(255) NULL,
  reversal_of BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY share_issuances_pool_index (share_pool_id, shareholder_id),
  CONSTRAINT share_issuances_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT share_issuances_pool_fk FOREIGN KEY (share_pool_id) REFERENCES share_pools (id) ON DELETE CASCADE,
  CONSTRAINT share_issuances_holder_fk FOREIGN KEY (shareholder_id) REFERENCES shareholders (id),
  CONSTRAINT share_issuances_reversal_fk FOREIGN KEY (reversal_of) REFERENCES share_issuances (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE share_holdings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  share_pool_id BIGINT UNSIGNED NOT NULL,
  shareholder_id BIGINT UNSIGNED NOT NULL,
  shares DECIMAL(18,4) NOT NULL,
  holding_pct DECIMAL(9,6) NOT NULL,
  as_of TIMESTAMP NOT NULL,
  PRIMARY KEY (id),
  KEY share_holdings_pool_index (share_pool_id, as_of),
  CONSTRAINT share_holdings_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT share_holdings_pool_fk FOREIGN KEY (share_pool_id) REFERENCES share_pools (id) ON DELETE CASCADE,
  CONSTRAINT share_holdings_holder_fk FOREIGN KEY (shareholder_id) REFERENCES shareholders (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE share_value_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  share_pool_id BIGINT UNSIGNED NOT NULL,
  plot_id BIGINT UNSIGNED NULL,
  sale_id BIGINT UNSIGNED NULL,
  event_type ENUM('sale','true_up') NOT NULL,
  sale_value DECIMAL(15,2) NOT NULL DEFAULT 0,
  realised_profit DECIMAL(15,2) NOT NULL,
  profit_per_share DECIMAL(15,6) NOT NULL,
  share_value_after DECIMAL(15,4) NOT NULL,
  occurred_at TIMESTAMP NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY share_value_events_sale_unique (sale_id),
  KEY share_value_events_pool_index (share_pool_id, occurred_at),
  CONSTRAINT sve_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT sve_pool_fk FOREIGN KEY (share_pool_id) REFERENCES share_pools (id) ON DELETE CASCADE,
  CONSTRAINT sve_sale_fk FOREIGN KEY (sale_id) REFERENCES sales (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE share_allocations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  share_value_event_id BIGINT UNSIGNED NOT NULL,
  shareholder_id BIGINT UNSIGNED NOT NULL,
  shares_held DECIMAL(18,4) NOT NULL,
  holding_pct DECIMAL(9,6) NOT NULL,
  allocated_profit DECIMAL(15,2) NOT NULL,
  allocated_proceeds DECIMAL(15,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY share_allocations_unique (share_value_event_id, shareholder_id),
  KEY share_allocations_holder_index (shareholder_id),
  CONSTRAINT sa_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT sa_event_fk FOREIGN KEY (share_value_event_id) REFERENCES share_value_events (id) ON DELETE CASCADE,
  CONSTRAINT sa_holder_fk FOREIGN KEY (shareholder_id) REFERENCES shareholders (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE share_payouts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  share_pool_id BIGINT UNSIGNED NOT NULL,
  shareholder_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(15,2) NOT NULL,
  paid_on DATE NOT NULL,
  mode ENUM('cash','upi','neft','cheque') NOT NULL,
  reference_no VARCHAR(80) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY share_payouts_pool_index (share_pool_id, shareholder_id),
  CONSTRAINT sp_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT sp_pool_fk FOREIGN KEY (share_pool_id) REFERENCES share_pools (id) ON DELETE CASCADE,
  CONSTRAINT sp_holder_fk FOREIGN KEY (shareholder_id) REFERENCES shareholders (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. Accounting (append-only ledger; corrections are reversal entries)
-- ---------------------------------------------------------------------
CREATE TABLE ledger_categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  direction ENUM('in','out') NOT NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY ledger_categories_unique (tenant_id, name),
  CONSTRAINT ledger_categories_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ledger_entries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  layout_id BIGINT UNSIGNED NULL,
  project_stage_id BIGINT UNSIGNED NULL,
  ledger_category_id BIGINT UNSIGNED NULL,
  direction ENUM('in','out') NOT NULL,
  type ENUM('investment','expense','sales_income','commission','refund','distribution','other') NOT NULL,
  amount DECIMAL(15,2) NOT NULL,
  entry_date DATE NOT NULL,
  party VARCHAR(150) NULL,
  mode VARCHAR(20) NULL,
  reference_no VARCHAR(80) NULL,
  description VARCHAR(255) NULL,
  attachment_path VARCHAR(255) NULL,
  source_type VARCHAR(60) NULL,
  source_id BIGINT UNSIGNED NULL,
  reversal_of BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY ledger_entries_layout_date_index (tenant_id, layout_id, entry_date),
  KEY ledger_entries_source_index (source_type, source_id),
  KEY ledger_entries_stage_index (project_stage_id),
  CONSTRAINT ledger_entries_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT ledger_entries_layout_fk FOREIGN KEY (layout_id) REFERENCES layouts (id) ON DELETE SET NULL,
  CONSTRAINT ledger_entries_stage_fk FOREIGN KEY (project_stage_id) REFERENCES project_stages (id) ON DELETE SET NULL,
  CONSTRAINT ledger_entries_category_fk FOREIGN KEY (ledger_category_id) REFERENCES ledger_categories (id) ON DELETE SET NULL,
  CONSTRAINT ledger_entries_reversal_fk FOREIGN KEY (reversal_of) REFERENCES ledger_entries (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notification_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NULL,
  channel ENUM('email','whatsapp') NOT NULL,
  recipient VARCHAR(190) NOT NULL,
  event_code VARCHAR(60) NOT NULL,
  subject VARCHAR(190) NULL,
  status ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  error VARCHAR(500) NULL,
  sent_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY notification_logs_tenant_index (tenant_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Purchase / call-back requests: buying is always handled by the sales team
CREATE TABLE purchase_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  plot_id BIGINT UNSIGNED NULL,
  layout_id BIGINT UNSIGNED NULL,
  customer_id BIGINT UNSIGNED NULL,
  booking_id BIGINT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  email VARCHAR(190) NULL,
  message VARCHAR(500) NULL,
  type ENUM('purchase','callback') NOT NULL DEFAULT 'purchase',
  source ENUM('website','portal') NOT NULL DEFAULT 'website',
  status ENUM('new','contacted','closed') NOT NULL DEFAULT 'new',
  handled_by BIGINT UNSIGNED NULL,
  handled_at TIMESTAMP NULL,
  notes VARCHAR(500) NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY purchase_requests_tenant_status_index (tenant_id, status, created_at),
  CONSTRAINT pr_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
  CONSTRAINT pr_plot_fk FOREIGN KEY (plot_id) REFERENCES plots (id) ON DELETE SET NULL,
  CONSTRAINT pr_layout_fk FOREIGN KEY (layout_id) REFERENCES layouts (id) ON DELETE SET NULL,
  CONSTRAINT pr_customer_fk FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
  CONSTRAINT pr_booking_fk FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One-time email codes for online booking and booking tracking (hashed, 10-minute, single use)
CREATE TABLE email_otps (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  purpose VARCHAR(30) NOT NULL,
  code_hash CHAR(64) NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at TIMESTAMP NOT NULL,
  consumed_at TIMESTAMP NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY email_otps_lookup_index (email, purpose, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Deferred foreign keys (users ↔ shareholders / customers)
ALTER TABLE users
  ADD CONSTRAINT users_shareholder_fk FOREIGN KEY (shareholder_id) REFERENCES shareholders (id) ON DELETE SET NULL,
  ADD CONSTRAINT users_customer_fk FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 8. Seed: subscription plans (EDIT prices before go-live)
-- The Super Admin account is NOT seeded here (no default passwords).
-- Create it with:  php artisan vector7:create-super-admin
-- ---------------------------------------------------------------------
INSERT INTO plans (code, name, description, badge, price_monthly, price_yearly, trial_days, max_users, max_layouts, max_plots, max_storage_mb, features, status, sort_order, created_at, updated_at) VALUES
('starter',    'Starter',    'For a single layout project',            NULL,           0.00, 0.00, 14, 3,  1,  150,   2048,  '{"whatsapp":false,"dss":false,"public_listings":false,"google_drive":false,"shareholder_portal":true}',  'active', 1, NOW(), NOW()),
('growth',     'Growth',     'For growing developers with a sales team','Most popular', 0.00, 0.00, 14, 5,  5,  1000,  5120,  '{"whatsapp":true,"dss":true,"public_listings":true,"google_drive":true,"shareholder_portal":true}',     'active', 2, NOW(), NOW()),
('enterprise', 'Enterprise', 'For multi-project promoters',            NULL,           0.00, 0.00, 14, 50, 25, 100000, 51200, '{"whatsapp":true,"dss":true,"public_listings":true,"google_drive":true,"shareholder_portal":true}',     'active', 3, NOW(), NOW());
