-- GeekHost checkout + client panel schema (SQLite-compatible)

CREATE TABLE IF NOT EXISTS schema_migrations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    version VARCHAR(64) NOT NULL UNIQUE,
    applied_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email VARCHAR(190) NOT NULL,
    name VARCHAR(190) NOT NULL,
    cpf_cnpj VARCHAR(20) NOT NULL,
    phone VARCHAR(30) NOT NULL DEFAULT '',
    mobile_phone VARCHAR(30) NOT NULL DEFAULT '',
    postal_code VARCHAR(12) NOT NULL DEFAULT '',
    address VARCHAR(190) NOT NULL DEFAULT '',
    address_number VARCHAR(30) NOT NULL DEFAULT '',
    complement VARCHAR(120) NOT NULL DEFAULT '',
    province VARCHAR(2) NOT NULL DEFAULT '',
    city VARCHAR(120) NOT NULL DEFAULT '',
    asaas_customer_id VARCHAR(64) DEFAULT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_customers_cpf_cnpj ON customers (cpf_cnpj);
CREATE INDEX IF NOT EXISTS idx_customers_email ON customers (email);
CREATE UNIQUE INDEX IF NOT EXISTS idx_customers_asaas ON customers (asaas_customer_id);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id VARCHAR(36) NOT NULL,
    customer_id INTEGER DEFAULT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name_enc TEXT NOT NULL DEFAULT '',
    phone_enc TEXT NOT NULL DEFAULT '',
    mobile_enc TEXT NOT NULL DEFAULT '',
    cpf_cnpj_enc TEXT NOT NULL DEFAULT '',
    postal_code_enc TEXT NOT NULL DEFAULT '',
    address_enc TEXT NOT NULL DEFAULT '',
    address_number_enc TEXT NOT NULL DEFAULT '',
    complement_enc TEXT NOT NULL DEFAULT '',
    province_enc TEXT NOT NULL DEFAULT '',
    city_enc TEXT NOT NULL DEFAULT '',
    photo_path VARCHAR(255) DEFAULT NULL,
    asaas_credit_card_token VARCHAR(120) DEFAULT NULL,
    card_brand VARCHAR(40) DEFAULT NULL,
    card_last4 VARCHAR(4) DEFAULT NULL,
    hosting_status VARCHAR(40) NOT NULL DEFAULT 'pending',
    suspended_at TEXT DEFAULT NULL,
    suspend_reason VARCHAR(190) DEFAULT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_users_public_id ON users (public_id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email ON users (email);

CREATE TABLE IF NOT EXISTS user_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    expires_at TEXT NOT NULL,
    created_at TEXT NOT NULL,
    revoked_at TEXT DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_user_sessions_token ON user_sessions (token_hash);
CREATE INDEX IF NOT EXISTS idx_user_sessions_user ON user_sessions (user_id);

CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id VARCHAR(36) NOT NULL,
    invoice_number INTEGER DEFAULT NULL,
    customer_id INTEGER NOT NULL,
    user_id INTEGER DEFAULT NULL,
    plan_code VARCHAR(40) NOT NULL,
    plan_name VARCHAR(120) NOT NULL,
    billing_period VARCHAR(40) NOT NULL,
    domain VARCHAR(190) DEFAULT NULL,
    amount_cents INTEGER NOT NULL,
    renew_amount_cents INTEGER DEFAULT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'BRL',
    billing_type VARCHAR(20) NOT NULL,
    status VARCHAR(40) NOT NULL,
    due_date TEXT DEFAULT NULL,
    asaas_payment_id VARCHAR(64) DEFAULT NULL,
    asaas_subscription_id VARCHAR(64) DEFAULT NULL,
    asaas_invoice_url TEXT DEFAULT NULL,
    pix_qr_code TEXT DEFAULT NULL,
    pix_copy_paste TEXT DEFAULT NULL,
    boleto_url TEXT DEFAULT NULL,
    boleto_identification_field TEXT DEFAULT NULL,
    card_brand VARCHAR(40) DEFAULT NULL,
    card_last4 VARCHAR(4) DEFAULT NULL,
    idempotency_key VARCHAR(64) NOT NULL,
    provisioning_status VARCHAR(40) NOT NULL DEFAULT 'none',
    metadata_json TEXT DEFAULT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    paid_at TEXT DEFAULT NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_orders_public_id ON orders (public_id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_orders_idempotency ON orders (idempotency_key);
CREATE UNIQUE INDEX IF NOT EXISTS idx_orders_asaas_payment ON orders (asaas_payment_id);
CREATE INDEX IF NOT EXISTS idx_orders_status ON orders (status);
CREATE INDEX IF NOT EXISTS idx_orders_user ON orders (user_id);

CREATE TABLE IF NOT EXISTS subscriptions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id VARCHAR(36) NOT NULL,
    customer_id INTEGER NOT NULL,
    user_id INTEGER DEFAULT NULL,
    plan_code VARCHAR(40) NOT NULL,
    billing_period VARCHAR(40) NOT NULL,
    domain VARCHAR(190) DEFAULT NULL,
    amount_cents INTEGER NOT NULL,
    renew_amount_cents INTEGER NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'pending',
    asaas_subscription_id VARCHAR(64) DEFAULT NULL,
    asaas_credit_card_token VARCHAR(120) DEFAULT NULL,
    next_due_date TEXT DEFAULT NULL,
    overdue_since TEXT DEFAULT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_subscriptions_public_id ON subscriptions (public_id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_subscriptions_asaas ON subscriptions (asaas_subscription_id);

CREATE TABLE IF NOT EXISTS services (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id VARCHAR(36) NOT NULL,
    user_id INTEGER NOT NULL,
    order_id INTEGER DEFAULT NULL,
    subscription_id INTEGER DEFAULT NULL,
    plan_code VARCHAR(40) NOT NULL,
    domain VARCHAR(190) NOT NULL DEFAULT '',
    status VARCHAR(40) NOT NULL DEFAULT 'paid_pending_provisioning',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_services_public_id ON services (public_id);
CREATE INDEX IF NOT EXISTS idx_services_user ON services (user_id);

CREATE TABLE IF NOT EXISTS webhook_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_key VARCHAR(190) NOT NULL,
    event_type VARCHAR(80) NOT NULL,
    asaas_payment_id VARCHAR(64) DEFAULT NULL,
    payload_json TEXT NOT NULL,
    processed_at TEXT DEFAULT NULL,
    created_at TEXT NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_webhook_events_key ON webhook_events (event_key);
