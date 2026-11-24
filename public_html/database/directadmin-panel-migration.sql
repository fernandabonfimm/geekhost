-- GeekHost — migration para o MySQL do DirectAdmin.
-- Banco: geek6923_newbanco (ou o nome que aparecer no phpMyAdmin).
-- Pode rodar de novo: coluna ou índice que já existe é ignorado.
-- Não apaga dados e não importa o SQLite local.

SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS gh_add_column;
DELIMITER $$
CREATE PROCEDURE gh_add_column(IN p_table VARCHAR(64), IN p_column VARCHAR(64), IN p_def TEXT)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column
    ) THEN
        SET @gh_sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_def);
        PREPARE gh_stmt FROM @gh_sql;
        EXECUTE gh_stmt;
        DEALLOCATE PREPARE gh_stmt;
    END IF;
END$$
DELIMITER ;

DROP PROCEDURE IF EXISTS gh_add_index;
DELIMITER $$
CREATE PROCEDURE gh_add_index(IN p_name VARCHAR(64), IN p_sql TEXT)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND INDEX_NAME = p_name
    ) THEN
        SET @gh_sql = p_sql;
        PREPARE gh_stmt FROM @gh_sql;
        EXECUTE gh_stmt;
        DEALLOCATE PREPARE gh_stmt;
    END IF;
END$$
DELIMITER ;

CALL gh_add_column('users', 'password_reset_token_hash', 'VARCHAR(64) DEFAULT NULL');
CALL gh_add_column('users', 'password_reset_expires', 'TEXT DEFAULT NULL');
CALL gh_add_column('users', 'email_verified', 'TINYINT NOT NULL DEFAULT 1');
CALL gh_add_column('users', 'billing_day', 'INT DEFAULT NULL');
CALL gh_add_column('users', 'billing_day_source', 'VARCHAR(20) DEFAULT NULL');
CALL gh_add_column('subscriptions', 'billing_day', 'INT DEFAULT NULL');
CALL gh_add_column('customers', 'cpf_hash', 'VARCHAR(64) DEFAULT NULL');
CALL gh_add_column('support_notifications', 'link', 'VARCHAR(255) DEFAULT NULL');

CREATE TABLE IF NOT EXISTS user_preferences (
    user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    prefs_json TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS auth_codes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    purpose VARCHAR(40) NOT NULL,
    target_email VARCHAR(190) NOT NULL,
    code_hash VARCHAR(64) NOT NULL,
    payload TEXT DEFAULT NULL,
    expires_at TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS support_tickets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL,
    number INT NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED DEFAULT NULL,
    subject VARCHAR(190) NOT NULL,
    category VARCHAR(40) NOT NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'normal',
    status VARCHAR(40) NOT NULL DEFAULT 'open',
    assignee_id BIGINT UNSIGNED DEFAULT NULL,
    client_last_read_at TEXT DEFAULT NULL,
    staff_last_read_at TEXT DEFAULT NULL,
    closed_at TEXT DEFAULT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS support_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL,
    ticket_id BIGINT UNSIGNED NOT NULL,
    author_id BIGINT UNSIGNED NOT NULL,
    author_role VARCHAR(20) NOT NULL,
    body TEXT NOT NULL,
    is_internal TINYINT NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS support_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL,
    message_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(190) NOT NULL,
    stored_name VARCHAR(190) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    size_bytes INT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS support_ticket_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED NOT NULL,
    actor_id BIGINT UNSIGNED NOT NULL,
    actor_role VARCHAR(20) NOT NULL,
    event_type VARCHAR(60) NOT NULL,
    meta_json TEXT DEFAULT NULL,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS support_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(60) NOT NULL,
    title VARCHAR(190) NOT NULL,
    body TEXT NOT NULL,
    ticket_public_id VARCHAR(36) DEFAULT NULL,
    link VARCHAR(255) DEFAULT NULL,
    is_read TINYINT NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS kb_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL,
    title VARCHAR(160) NOT NULL,
    summary VARCHAR(255) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_kb_categories_slug (slug)
);

CREATE TABLE IF NOT EXISTS kb_articles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NOT NULL,
    slug VARCHAR(120) NOT NULL,
    title VARCHAR(190) NOT NULL,
    summary VARCHAR(255) NOT NULL DEFAULT '',
    body TEXT NOT NULL,
    published TINYINT NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at VARCHAR(40) NOT NULL,
    updated_at VARCHAR(40) NOT NULL,
    UNIQUE KEY uq_kb_articles_slug (slug)
);

CREATE TABLE IF NOT EXISTS billing_notices (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    stage INT NOT NULL,
    kind VARCHAR(40) NOT NULL,
    sent_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS hosting_cancellations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(190) NOT NULL,
    plan_code VARCHAR(80) DEFAULT NULL,
    domain VARCHAR(190) DEFAULT NULL,
    billing_type VARCHAR(40) DEFAULT NULL,
    billing_period VARCHAR(40) DEFAULT NULL,
    reasons_json TEXT NOT NULL,
    comment TEXT,
    refund_amount_cents INT NOT NULL DEFAULT 0,
    access_until TEXT,
    status VARCHAR(40) NOT NULL DEFAULT 'scheduled',
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS billing_date_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(190) NOT NULL,
    plan_code VARCHAR(80) DEFAULT NULL,
    billing_period VARCHAR(40) DEFAULT NULL,
    previous_day INT DEFAULT NULL,
    requested_day INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    admin_note TEXT,
    created_at TEXT NOT NULL,
    decided_at TEXT DEFAULT NULL
);

CALL gh_add_index('idx_auth_codes_user', 'CREATE INDEX idx_auth_codes_user ON auth_codes (user_id, purpose)');
CALL gh_add_index('idx_support_tickets_public', 'CREATE UNIQUE INDEX idx_support_tickets_public ON support_tickets (public_id)');
CALL gh_add_index('idx_support_tickets_number', 'CREATE UNIQUE INDEX idx_support_tickets_number ON support_tickets (number)');
CALL gh_add_index('idx_support_tickets_user', 'CREATE INDEX idx_support_tickets_user ON support_tickets (user_id)');
CALL gh_add_index('idx_support_tickets_status', 'CREATE INDEX idx_support_tickets_status ON support_tickets (status)');
CALL gh_add_index('idx_support_messages_public', 'CREATE UNIQUE INDEX idx_support_messages_public ON support_messages (public_id)');
CALL gh_add_index('idx_support_messages_ticket', 'CREATE INDEX idx_support_messages_ticket ON support_messages (ticket_id)');
CALL gh_add_index('idx_support_attachments_public', 'CREATE UNIQUE INDEX idx_support_attachments_public ON support_attachments (public_id)');
CALL gh_add_index('idx_support_attachments_message', 'CREATE INDEX idx_support_attachments_message ON support_attachments (message_id)');
CALL gh_add_index('idx_support_notifications_public', 'CREATE UNIQUE INDEX idx_support_notifications_public ON support_notifications (public_id)');
CALL gh_add_index('idx_support_notifications_user', 'CREATE INDEX idx_support_notifications_user ON support_notifications (user_id)');
CALL gh_add_index('idx_billing_notices_order_stage', 'CREATE UNIQUE INDEX idx_billing_notices_order_stage ON billing_notices (order_id, stage)');

DROP PROCEDURE IF EXISTS gh_add_column;
DROP PROCEDURE IF EXISTS gh_add_index;
