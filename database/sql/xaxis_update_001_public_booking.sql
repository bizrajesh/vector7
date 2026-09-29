-- =====================================================================
-- Vector7 update 001 — public project pages, online booking holds,
-- purchase/call-back requests and email one-time codes.
-- Run ONLY on databases created before this update (the migration does
-- this automatically). Fresh installs already include these changes.
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE layouts
  ADD COLUMN is_public TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'shown on the public website when launched' AFTER status,
  ADD COLUMN public_summary VARCHAR(500) NULL AFTER is_public;

ALTER TABLE bookings
  MODIFY status ENUM('pending','active','converted','expired','cancelled') NOT NULL DEFAULT 'active' COMMENT 'pending = online hold awaiting advance',
  ADD COLUMN source ENUM('office','online') NOT NULL DEFAULT 'office' AFTER status;

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

UPDATE plans SET features = JSON_SET(features, '$.public_listings', true) WHERE code = 'growth';
