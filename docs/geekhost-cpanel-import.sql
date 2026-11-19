SET NAMES utf8mb4;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET FOREIGN_KEY_CHECKS = 0;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `version` VARCHAR(64) NOT NULL,
  `applied_at` VARCHAR(40) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_schema_migrations_version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `customers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(190) NOT NULL,
  `name` VARCHAR(190) NOT NULL,
  `cpf_cnpj` VARCHAR(20) NOT NULL,
  `phone` VARCHAR(30) NOT NULL DEFAULT '',
  `mobile_phone` VARCHAR(30) NOT NULL DEFAULT '',
  `postal_code` VARCHAR(12) NOT NULL DEFAULT '',
  `address` VARCHAR(190) NOT NULL DEFAULT '',
  `address_number` VARCHAR(30) NOT NULL DEFAULT '',
  `complement` VARCHAR(120) NOT NULL DEFAULT '',
  `province` VARCHAR(2) NOT NULL DEFAULT '',
  `city` VARCHAR(120) NOT NULL DEFAULT '',
  `asaas_customer_id` VARCHAR(64) DEFAULT NULL,
  `created_at` VARCHAR(40) NOT NULL,
  `updated_at` VARCHAR(40) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customers_cpf_cnpj` (`cpf_cnpj`),
  UNIQUE KEY `uq_customers_asaas` (`asaas_customer_id`),
  KEY `idx_customers_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` VARCHAR(36) NOT NULL,
  `customer_id` BIGINT UNSIGNED DEFAULT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `password_is_set` TINYINT NOT NULL DEFAULT 1,
  `password_setup_token_hash` VARCHAR(64) DEFAULT NULL,
  `password_setup_expires` VARCHAR(40) DEFAULT NULL,
  `name_enc` TEXT NOT NULL,
  `phone_enc` TEXT NOT NULL,
  `mobile_enc` TEXT NOT NULL,
  `cpf_cnpj_enc` TEXT NOT NULL,
  `postal_code_enc` TEXT NOT NULL,
  `address_enc` TEXT NOT NULL,
  `address_number_enc` TEXT NOT NULL,
  `complement_enc` TEXT NOT NULL,
  `province_enc` TEXT NOT NULL,
  `city_enc` TEXT NOT NULL,
  `photo_path` VARCHAR(255) DEFAULT NULL,
  `asaas_credit_card_token` VARCHAR(120) DEFAULT NULL,
  `card_brand` VARCHAR(40) DEFAULT NULL,
  `card_last4` VARCHAR(4) DEFAULT NULL,
  `hosting_status` VARCHAR(40) NOT NULL DEFAULT 'pending',
  `suspended_at` VARCHAR(40) DEFAULT NULL,
  `suspend_reason` VARCHAR(190) DEFAULT NULL,
  `created_at` VARCHAR(40) NOT NULL,
  `updated_at` VARCHAR(40) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_public_id` (`public_id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `token_hash` VARCHAR(64) NOT NULL,
  `expires_at` VARCHAR(40) NOT NULL,
  `created_at` VARCHAR(40) NOT NULL,
  `revoked_at` VARCHAR(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_sessions_token` (`token_hash`),
  KEY `idx_user_sessions_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `orders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` VARCHAR(36) NOT NULL,
  `invoice_number` INT DEFAULT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED DEFAULT NULL,
  `plan_code` VARCHAR(40) NOT NULL,
  `plan_name` VARCHAR(120) NOT NULL,
  `billing_period` VARCHAR(40) NOT NULL,
  `domain` VARCHAR(190) DEFAULT NULL,
  `amount_cents` INT NOT NULL,
  `renew_amount_cents` INT DEFAULT NULL,
  `currency` VARCHAR(3) NOT NULL DEFAULT 'BRL',
  `billing_type` VARCHAR(20) NOT NULL,
  `status` VARCHAR(40) NOT NULL,
  `due_date` VARCHAR(40) DEFAULT NULL,
  `asaas_payment_id` VARCHAR(64) DEFAULT NULL,
  `asaas_subscription_id` VARCHAR(64) DEFAULT NULL,
  `asaas_invoice_url` TEXT,
  `pix_qr_code` TEXT,
  `pix_copy_paste` TEXT,
  `boleto_url` TEXT,
  `boleto_identification_field` TEXT,
  `card_brand` VARCHAR(40) DEFAULT NULL,
  `card_last4` VARCHAR(4) DEFAULT NULL,
  `idempotency_key` VARCHAR(64) NOT NULL,
  `provisioning_status` VARCHAR(40) NOT NULL DEFAULT 'none',
  `metadata_json` TEXT,
  `created_at` VARCHAR(40) NOT NULL,
  `updated_at` VARCHAR(40) NOT NULL,
  `paid_at` VARCHAR(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_public_id` (`public_id`),
  UNIQUE KEY `uq_orders_idempotency` (`idempotency_key`),
  UNIQUE KEY `uq_orders_asaas_payment` (`asaas_payment_id`),
  KEY `idx_orders_status` (`status`),
  KEY `idx_orders_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` VARCHAR(36) NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED DEFAULT NULL,
  `plan_code` VARCHAR(40) NOT NULL,
  `billing_period` VARCHAR(40) NOT NULL,
  `domain` VARCHAR(190) DEFAULT NULL,
  `amount_cents` INT NOT NULL,
  `renew_amount_cents` INT NOT NULL,
  `status` VARCHAR(40) NOT NULL DEFAULT 'pending',
  `asaas_subscription_id` VARCHAR(64) DEFAULT NULL,
  `asaas_credit_card_token` VARCHAR(120) DEFAULT NULL,
  `next_due_date` VARCHAR(40) DEFAULT NULL,
  `overdue_since` VARCHAR(40) DEFAULT NULL,
  `created_at` VARCHAR(40) NOT NULL,
  `updated_at` VARCHAR(40) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subscriptions_public_id` (`public_id`),
  UNIQUE KEY `uq_subscriptions_asaas` (`asaas_subscription_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `services` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` VARCHAR(36) NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `order_id` BIGINT UNSIGNED DEFAULT NULL,
  `subscription_id` BIGINT UNSIGNED DEFAULT NULL,
  `plan_code` VARCHAR(40) NOT NULL,
  `domain` VARCHAR(190) NOT NULL DEFAULT '',
  `status` VARCHAR(40) NOT NULL DEFAULT 'paid_pending_provisioning',
  `created_at` VARCHAR(40) NOT NULL,
  `updated_at` VARCHAR(40) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_services_public_id` (`public_id`),
  KEY `idx_services_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `webhook_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_key` VARCHAR(190) NOT NULL,
  `event_type` VARCHAR(80) NOT NULL,
  `asaas_payment_id` VARCHAR(64) DEFAULT NULL,
  `payload_json` TEXT NOT NULL,
  `processed_at` VARCHAR(40) DEFAULT NULL,
  `created_at` VARCHAR(40) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_webhook_events_key` (`event_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_domains` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` VARCHAR(36) NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `domain` VARCHAR(190) NOT NULL,
  `plan_code` VARCHAR(40) NOT NULL DEFAULT 'light',
  `ns_ok` TINYINT NOT NULL DEFAULT 0,
  `ns_checked_at` VARCHAR(40) DEFAULT NULL,
  `ns_raw_json` TEXT,
  `status` VARCHAR(40) NOT NULL DEFAULT 'awaiting_dns',
  `ssl_status` VARCHAR(40) NOT NULL DEFAULT 'none',
  `hosting_account_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` VARCHAR(40) NOT NULL,
  `updated_at` VARCHAR(40) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_domains_public` (`public_id`),
  UNIQUE KEY `uq_user_domains_user_domain` (`user_id`, `domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_sites` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` VARCHAR(36) NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `domain_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(190) NOT NULL DEFAULT '',
  `status` VARCHAR(40) NOT NULL DEFAULT 'ready',
  `wordpress_status` VARCHAR(40) NOT NULL DEFAULT 'not_installed',
  `site_type` VARCHAR(40) NOT NULL DEFAULT 'blank',
  `ssl_status` VARCHAR(40) NOT NULL DEFAULT 'none',
  `created_at` VARCHAR(40) NOT NULL,
  `updated_at` VARCHAR(40) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_sites_public` (`public_id`),
  KEY `idx_user_sites_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `hosting_accounts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` VARCHAR(36) NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `order_id` BIGINT UNSIGNED DEFAULT NULL,
  `service_id` BIGINT UNSIGNED DEFAULT NULL,
  `plan_code` VARCHAR(40) NOT NULL,
  `da_username` VARCHAR(64) NOT NULL,
  `da_password_enc` TEXT,
  `da_package` VARCHAR(80) NOT NULL DEFAULT '',
  `primary_domain` VARCHAR(190) DEFAULT NULL,
  `status` VARCHAR(40) NOT NULL DEFAULT 'pending',
  `last_error` TEXT,
  `provisioned_at` VARCHAR(40) DEFAULT NULL,
  `created_at` VARCHAR(40) NOT NULL,
  `updated_at` VARCHAR(40) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hosting_accounts_public` (`public_id`),
  UNIQUE KEY `uq_hosting_accounts_username` (`da_username`),
  KEY `idx_hosting_accounts_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `hosting_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` VARCHAR(36) NOT NULL,
  `hosting_account_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `job_type` VARCHAR(40) NOT NULL,
  `payload_json` TEXT,
  `status` VARCHAR(40) NOT NULL DEFAULT 'pending',
  `attempts` INT NOT NULL DEFAULT 0,
  `last_error` TEXT,
  `available_at` VARCHAR(40) DEFAULT NULL,
  `created_at` VARCHAR(40) NOT NULL,
  `updated_at` VARCHAR(40) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hosting_jobs_public` (`public_id`),
  KEY `idx_hosting_jobs_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `hosting_audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `hosting_account_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `actor` VARCHAR(40) NOT NULL,
  `action` VARCHAR(80) NOT NULL,
  `detail_json` TEXT,
  `created_at` VARCHAR(40) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hosting_audit_account` (`hosting_account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `role` VARCHAR(40) NOT NULL DEFAULT 'suporte',
  `status` VARCHAR(40) NOT NULL DEFAULT 'active',
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` VARCHAR(40) NOT NULL,
  `updated_at` VARCHAR(40) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_users_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `admin_audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_user_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `action` VARCHAR(80) NOT NULL,
  `resource_type` VARCHAR(80) NOT NULL DEFAULT '',
  `resource_id` VARCHAR(80) DEFAULT NULL,
  `result` VARCHAR(40) NOT NULL DEFAULT 'ok',
  `ip` VARCHAR(64) DEFAULT NULL,
  `detail_json` TEXT,
  `created_at` VARCHAR(40) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_admin_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `admin_notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` VARCHAR(80) NOT NULL,
  `title` VARCHAR(190) NOT NULL,
  `body` TEXT NOT NULL,
  `link` VARCHAR(255) DEFAULT NULL,
  `read_at` VARCHAR(40) DEFAULT NULL,
  `created_at` VARCHAR(40) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `app_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(120) NOT NULL,
  `value_json` TEXT NOT NULL,
  `updated_at` VARCHAR(40) NOT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_app_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` VARCHAR(36) NOT NULL,
  `number` INT NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `service_id` BIGINT UNSIGNED DEFAULT NULL,
  `subject` VARCHAR(190) NOT NULL,
  `category` VARCHAR(40) NOT NULL,
  `priority` VARCHAR(20) NOT NULL DEFAULT 'normal',
  `status` VARCHAR(40) NOT NULL DEFAULT 'open',
  `assignee_id` BIGINT UNSIGNED DEFAULT NULL,
  `client_last_read_at` VARCHAR(40) DEFAULT NULL,
  `staff_last_read_at` VARCHAR(40) DEFAULT NULL,
  `closed_at` VARCHAR(40) DEFAULT NULL,
  `created_at` VARCHAR(40) NOT NULL,
  `updated_at` VARCHAR(40) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_support_tickets_public` (`public_id`),
  UNIQUE KEY `uq_support_tickets_number` (`number`),
  KEY `idx_support_tickets_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `support_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` VARCHAR(36) NOT NULL,
  `ticket_id` BIGINT UNSIGNED NOT NULL,
  `author_id` BIGINT UNSIGNED NOT NULL,
  `author_role` VARCHAR(20) NOT NULL,
  `body` TEXT NOT NULL,
  `is_internal` TINYINT NOT NULL DEFAULT 0,
  `created_at` VARCHAR(40) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_support_messages_public` (`public_id`),
  KEY `idx_support_messages_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `support_attachments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` VARCHAR(36) NOT NULL,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `original_name` VARCHAR(190) NOT NULL,
  `stored_name` VARCHAR(190) NOT NULL,
  `mime_type` VARCHAR(120) NOT NULL,
  `size_bytes` INT NOT NULL,
  `created_at` VARCHAR(40) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_support_attachments_public` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `support_ticket_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` BIGINT UNSIGNED NOT NULL,
  `actor_id` BIGINT UNSIGNED NOT NULL,
  `actor_role` VARCHAR(20) NOT NULL,
  `event_type` VARCHAR(60) NOT NULL,
  `meta_json` TEXT,
  `created_at` VARCHAR(40) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_support_ticket_events_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `support_notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` VARCHAR(36) NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `type` VARCHAR(60) NOT NULL,
  `title` VARCHAR(190) NOT NULL,
  `body` TEXT NOT NULL,
  `ticket_public_id` VARCHAR(36) DEFAULT NULL,
  `link` VARCHAR(255) DEFAULT NULL,
  `is_read` TINYINT NOT NULL DEFAULT 0,
  `created_at` VARCHAR(40) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_support_notifications_public` (`public_id`),
  KEY `idx_support_notifications_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO `users` (
  `public_id`, `customer_id`, `email`, `password_hash`, `password_is_set`,
  `name_enc`, `phone_enc`, `mobile_enc`, `cpf_cnpj_enc`, `postal_code_enc`,
  `address_enc`, `address_number_enc`, `complement_enc`, `province_enc`, `city_enc`,
  `hosting_status`, `created_at`, `updated_at`
)
SELECT
  'a1111111-1111-4111-8111-111111111111',
  NULL,
  'admin@geekhost.com.br',
  '$2y$12$cRaQXgSpf.FLKQvQwgNJweXfKXaTFleFGuc4RLJQjofZdj4zRN55e',
  1,
  'Geek Admin',
  '', '', '', '',
  '', '', '', '', '',
  'active',
  '2026-01-01T00:00:00+00:00',
  '2026-01-01T00:00:00+00:00'
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `users` WHERE `email` = 'admin@geekhost.com.br'
);

INSERT INTO `admin_users` (`user_id`, `role`, `status`, `created_at`, `updated_at`)
SELECT u.`id`, 'super_admin', 'active', '2026-01-01T00:00:00+00:00', '2026-01-01T00:00:00+00:00'
FROM `users` u
WHERE u.`email` = 'admin@geekhost.com.br'
  AND NOT EXISTS (
    SELECT 1 FROM `admin_users` a WHERE a.`user_id` = u.`id`
  );

INSERT INTO `app_settings` (`setting_key`, `value_json`, `updated_at`)
SELECT 'directadmin.reseller_username', '"geek6923"', '2026-01-01T00:00:00+00:00'
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `app_settings` WHERE `setting_key` = 'directadmin.reseller_username'
);

INSERT INTO `schema_migrations` (`version`, `applied_at`)
SELECT 'cpanel-import-v3', '2026-01-01T00:00:00+00:00'
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `schema_migrations` WHERE `version` = 'cpanel-import-v3'
);