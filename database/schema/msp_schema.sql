-- LUMIRA MSP Database Schema
-- Comprehensive schema for MSP IT Services Company
-- Includes: Users, Clients, Tickets, Leads, KB Articles, Roles & Permissions

-- ============================================
-- MIGRATIONS TRACKER
-- ============================================
CREATE TABLE IF NOT EXISTS schema_migrations (
    id SERIAL PRIMARY KEY,
    version VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- ROLES & PRIVILEGES
-- ============================================

-- App roles table
CREATE TABLE IF NOT EXISTS app_roles (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_system_role BOOLEAN DEFAULT false,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Privileges table
CREATE TABLE IF NOT EXISTS privileges (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    resource VARCHAR(50) NOT NULL, -- e.g., 'tickets', 'users', 'kb_articles'
    action VARCHAR(50) NOT NULL, -- e.g., 'create', 'read', 'update', 'delete', 'manage'
    description TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Role-Privilege mapping
CREATE TABLE IF NOT EXISTS role_privileges (
    id SERIAL PRIMARY KEY,
    role_id INTEGER NOT NULL REFERENCES app_roles(id) ON DELETE CASCADE,
    privilege_id INTEGER NOT NULL REFERENCES privileges(id) ON DELETE CASCADE,
    UNIQUE(role_id, privilege_id)
);

-- ============================================
-- USERS & AUTHENTICATION
-- ============================================

-- Enhanced users table
CREATE TABLE IF NOT EXISTS users_new (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    role_id INTEGER REFERENCES app_roles(id) ON DELETE RESTRICT,
    is_active BOOLEAN DEFAULT true,
    email_verified BOOLEAN DEFAULT false,
    last_login TIMESTAMP,
    failed_login_attempts INTEGER DEFAULT 0,
    locked_until TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Password reset tokens
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users_new(id) ON DELETE CASCADE,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT false,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- User sessions
CREATE TABLE IF NOT EXISTS user_sessions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users_new(id) ON DELETE CASCADE,
    session_token VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- CLIENTS/COMPANIES
-- ============================================

-- Client companies table
CREATE TABLE IF NOT EXISTS clients (
    id SERIAL PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    industry VARCHAR(100),
    website VARCHAR(255),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(50),
    zip_code VARCHAR(20),
    country VARCHAR(100) DEFAULT 'USA',
    phone VARCHAR(50),
    billing_email VARCHAR(255),
    contract_start_date DATE,
    contract_end_date DATE,
    contract_type VARCHAR(50), -- e.g., 'monthly', 'annual', 'project-based'
    billing_cycle VARCHAR(50), -- e.g., 'monthly', 'quarterly', 'annual'
    monthly_recurring_revenue DECIMAL(10, 2),
    status VARCHAR(50) DEFAULT 'active', -- 'active', 'inactive', 'suspended', 'prospect'
    assigned_account_manager_id INTEGER REFERENCES users_new(id) ON DELETE SET NULL,
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Client contacts (users associated with client companies)
CREATE TABLE IF NOT EXISTS client_contacts (
    id SERIAL PRIMARY KEY,
    client_id INTEGER NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users_new(id) ON DELETE CASCADE,
    job_title VARCHAR(100),
    is_primary_contact BOOLEAN DEFAULT false,
    can_create_tickets BOOLEAN DEFAULT true,
    can_approve_invoices BOOLEAN DEFAULT false,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(client_id, user_id)
);

-- ============================================
-- ENHANCED TICKETING SYSTEM
-- ============================================

-- Ticket categories
CREATE TABLE IF NOT EXISTS ticket_categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    parent_category_id INTEGER REFERENCES ticket_categories(id) ON DELETE SET NULL,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Ticket priorities
CREATE TABLE IF NOT EXISTS ticket_priorities (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE, -- 'Low', 'Normal', 'High', 'Critical', 'Emergency'
    level INTEGER NOT NULL UNIQUE,
    sla_response_hours INTEGER, -- Service Level Agreement response time
    sla_resolution_hours INTEGER, -- Service Level Agreement resolution time
    color_code VARCHAR(7) -- Hex color for UI
);

-- Ticket statuses
CREATE TABLE IF NOT EXISTS ticket_statuses (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE, -- 'New', 'Open', 'In Progress', 'Pending', 'Resolved', 'Closed'
    is_open BOOLEAN DEFAULT true,
    is_resolved BOOLEAN DEFAULT false,
    order_position INTEGER NOT NULL,
    color_code VARCHAR(7)
);

-- Enhanced tickets table
CREATE TABLE IF NOT EXISTS tickets_new (
    id SERIAL PRIMARY KEY,
    ticket_number VARCHAR(50) NOT NULL UNIQUE, -- e.g., 'TKT-2025-00001'
    client_id INTEGER REFERENCES clients(id) ON DELETE SET NULL,
    requester_id INTEGER NOT NULL REFERENCES users_new(id) ON DELETE RESTRICT, -- Who created the ticket
    assigned_to_id INTEGER REFERENCES users_new(id) ON DELETE SET NULL, -- Assigned technician
    category_id INTEGER REFERENCES ticket_categories(id) ON DELETE SET NULL,
    priority_id INTEGER NOT NULL REFERENCES ticket_priorities(id),
    status_id INTEGER NOT NULL REFERENCES ticket_statuses(id),
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    resolution_notes TEXT,
    hours_estimated DECIMAL(6, 2),
    hours_actual DECIMAL(6, 2),
    sla_due_date TIMESTAMP,
    first_response_at TIMESTAMP,
    resolved_at TIMESTAMP,
    closed_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Ticket comments/updates
CREATE TABLE IF NOT EXISTS ticket_comments (
    id SERIAL PRIMARY KEY,
    ticket_id INTEGER NOT NULL REFERENCES tickets_new(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users_new(id) ON DELETE RESTRICT,
    comment TEXT NOT NULL,
    is_internal BOOLEAN DEFAULT false, -- Internal note vs client-visible comment
    time_spent_minutes INTEGER DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Ticket attachments
CREATE TABLE IF NOT EXISTS ticket_attachments (
    id SERIAL PRIMARY KEY,
    ticket_id INTEGER NOT NULL REFERENCES tickets_new(id) ON DELETE CASCADE,
    comment_id INTEGER REFERENCES ticket_comments(id) ON DELETE CASCADE,
    uploaded_by_id INTEGER NOT NULL REFERENCES users_new(id) ON DELETE RESTRICT,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size_bytes BIGINT NOT NULL,
    mime_type VARCHAR(100),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Ticket history/audit log
CREATE TABLE IF NOT EXISTS ticket_history (
    id SERIAL PRIMARY KEY,
    ticket_id INTEGER NOT NULL REFERENCES tickets_new(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users_new(id) ON DELETE SET NULL,
    field_changed VARCHAR(100) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- LEADS MANAGEMENT
-- ============================================

-- Lead sources
CREATE TABLE IF NOT EXISTS lead_sources (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE, -- 'Website', 'Referral', 'Cold Call', 'Trade Show', etc.
    description TEXT,
    is_active BOOLEAN DEFAULT true
);

-- Lead statuses
CREATE TABLE IF NOT EXISTS lead_statuses (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE, -- 'New', 'Contacted', 'Qualified', 'Proposal', 'Won', 'Lost'
    is_active BOOLEAN DEFAULT true,
    order_position INTEGER NOT NULL
);

-- Leads table
CREATE TABLE IF NOT EXISTS leads (
    id SERIAL PRIMARY KEY,
    company_name VARCHAR(255),
    contact_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    job_title VARCHAR(100),
    industry VARCHAR(100),
    website VARCHAR(255),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(50),
    zip_code VARCHAR(20),
    country VARCHAR(100) DEFAULT 'USA',
    source_id INTEGER REFERENCES lead_sources(id) ON DELETE SET NULL,
    status_id INTEGER NOT NULL REFERENCES lead_statuses(id),
    assigned_to_id INTEGER REFERENCES users_new(id) ON DELETE SET NULL,
    estimated_value DECIMAL(10, 2),
    estimated_close_date DATE,
    probability INTEGER CHECK (probability >= 0 AND probability <= 100), -- Percentage
    description TEXT,
    notes TEXT,
    converted_to_client_id INTEGER REFERENCES clients(id) ON DELETE SET NULL,
    converted_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Lead activities/interactions
CREATE TABLE IF NOT EXISTS lead_activities (
    id SERIAL PRIMARY KEY,
    lead_id INTEGER NOT NULL REFERENCES leads(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users_new(id) ON DELETE RESTRICT,
    activity_type VARCHAR(50) NOT NULL, -- 'call', 'email', 'meeting', 'note'
    subject VARCHAR(255),
    description TEXT,
    scheduled_at TIMESTAMP,
    completed_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- KNOWLEDGE BASE ARTICLES (Optional)
-- ============================================

-- KB categories
CREATE TABLE IF NOT EXISTS kb_categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    parent_category_id INTEGER REFERENCES kb_categories(id) ON DELETE SET NULL,
    icon VARCHAR(50),
    order_position INTEGER,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- KB articles
CREATE TABLE IF NOT EXISTS kb_articles (
    id SERIAL PRIMARY KEY,
    category_id INTEGER REFERENCES kb_categories(id) ON DELETE SET NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    content TEXT NOT NULL,
    excerpt TEXT,
    author_id INTEGER NOT NULL REFERENCES users_new(id) ON DELETE RESTRICT,
    published BOOLEAN DEFAULT false,
    featured BOOLEAN DEFAULT false,
    view_count INTEGER DEFAULT 0,
    helpful_count INTEGER DEFAULT 0,
    not_helpful_count INTEGER DEFAULT 0,
    published_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- KB article tags
CREATE TABLE IF NOT EXISTS kb_tags (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    slug VARCHAR(50) NOT NULL UNIQUE
);

-- KB article-tag mapping
CREATE TABLE IF NOT EXISTS kb_article_tags (
    id SERIAL PRIMARY KEY,
    article_id INTEGER NOT NULL REFERENCES kb_articles(id) ON DELETE CASCADE,
    tag_id INTEGER NOT NULL REFERENCES kb_tags(id) ON DELETE CASCADE,
    UNIQUE(article_id, tag_id)
);

-- KB article attachments
CREATE TABLE IF NOT EXISTS kb_article_attachments (
    id SERIAL PRIMARY KEY,
    article_id INTEGER NOT NULL REFERENCES kb_articles(id) ON DELETE CASCADE,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size_bytes BIGINT NOT NULL,
    mime_type VARCHAR(100),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- PRODUCTS & SERVICES (Keep existing)
-- ============================================

-- Products table
CREATE TABLE IF NOT EXISTS products (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    sku VARCHAR(100) UNIQUE,
    price_cents INTEGER NOT NULL CHECK (price_cents >= 0),
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Services table
CREATE TABLE IF NOT EXISTS services (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    hourly_rate_cents INTEGER,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- ORDERS & INVOICING
-- ============================================

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
    id SERIAL PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    client_id INTEGER REFERENCES clients(id) ON DELETE SET NULL,
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    customer_address TEXT NOT NULL,
    subtotal_cents INTEGER NOT NULL DEFAULT 0,
    tax_cents INTEGER NOT NULL DEFAULT 0,
    total_cents INTEGER NOT NULL DEFAULT 0,
    status VARCHAR(50) DEFAULT 'pending', -- 'pending', 'paid', 'cancelled'
    paid_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Order items table
CREATE TABLE IF NOT EXISTS order_items (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    product_id INTEGER REFERENCES products(id) ON DELETE RESTRICT,
    service_id INTEGER REFERENCES services(id) ON DELETE RESTRICT,
    description VARCHAR(255) NOT NULL,
    qty DECIMAL(10, 2) NOT NULL CHECK (qty > 0),
    unit_price_cents INTEGER NOT NULL CHECK (unit_price_cents >= 0),
    total_cents INTEGER NOT NULL CHECK (total_cents >= 0),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- INDEXES FOR PERFORMANCE
-- ============================================

-- Users indexes
CREATE INDEX IF NOT EXISTS idx_users_email ON users_new(email);
CREATE INDEX IF NOT EXISTS idx_users_role_id ON users_new(role_id);
CREATE INDEX IF NOT EXISTS idx_users_is_active ON users_new(is_active);

-- Clients indexes
CREATE INDEX IF NOT EXISTS idx_clients_company_name ON clients(company_name);
CREATE INDEX IF NOT EXISTS idx_clients_status ON clients(status);
CREATE INDEX IF NOT EXISTS idx_clients_account_manager ON clients(assigned_account_manager_id);

-- Tickets indexes
CREATE INDEX IF NOT EXISTS idx_tickets_ticket_number ON tickets_new(ticket_number);
CREATE INDEX IF NOT EXISTS idx_tickets_client_id ON tickets_new(client_id);
CREATE INDEX IF NOT EXISTS idx_tickets_requester_id ON tickets_new(requester_id);
CREATE INDEX IF NOT EXISTS idx_tickets_assigned_to_id ON tickets_new(assigned_to_id);
CREATE INDEX IF NOT EXISTS idx_tickets_status_id ON tickets_new(status_id);
CREATE INDEX IF NOT EXISTS idx_tickets_priority_id ON tickets_new(priority_id);
CREATE INDEX IF NOT EXISTS idx_tickets_created_at ON tickets_new(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_tickets_sla_due_date ON tickets_new(sla_due_date);

-- Ticket comments indexes
CREATE INDEX IF NOT EXISTS idx_ticket_comments_ticket_id ON ticket_comments(ticket_id);
CREATE INDEX IF NOT EXISTS idx_ticket_comments_user_id ON ticket_comments(user_id);
CREATE INDEX IF NOT EXISTS idx_ticket_comments_created_at ON ticket_comments(created_at DESC);

-- Leads indexes
CREATE INDEX IF NOT EXISTS idx_leads_email ON leads(email);
CREATE INDEX IF NOT EXISTS idx_leads_company_name ON leads(company_name);
CREATE INDEX IF NOT EXISTS idx_leads_status_id ON leads(status_id);
CREATE INDEX IF NOT EXISTS idx_leads_assigned_to_id ON leads(assigned_to_id);
CREATE INDEX IF NOT EXISTS idx_leads_created_at ON leads(created_at DESC);

-- KB articles indexes
CREATE INDEX IF NOT EXISTS idx_kb_articles_category_id ON kb_articles(category_id);
CREATE INDEX IF NOT EXISTS idx_kb_articles_slug ON kb_articles(slug);
CREATE INDEX IF NOT EXISTS idx_kb_articles_published ON kb_articles(published);
CREATE INDEX IF NOT EXISTS idx_kb_articles_featured ON kb_articles(featured);

-- Products/Services indexes
CREATE INDEX IF NOT EXISTS idx_products_name ON products(name);
CREATE INDEX IF NOT EXISTS idx_products_sku ON products(sku);
CREATE INDEX IF NOT EXISTS idx_products_is_active ON products(is_active);
CREATE INDEX IF NOT EXISTS idx_services_name ON services(name);
CREATE INDEX IF NOT EXISTS idx_services_is_active ON services(is_active);

-- Orders indexes
CREATE INDEX IF NOT EXISTS idx_orders_order_number ON orders(order_number);
CREATE INDEX IF NOT EXISTS idx_orders_client_id ON orders(client_id);
CREATE INDEX IF NOT EXISTS idx_orders_customer_email ON orders(customer_email);
CREATE INDEX IF NOT EXISTS idx_orders_created_at ON orders(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_order_items_order_id ON order_items(order_id);

-- ============================================
-- RECORD INITIAL MIGRATION
-- ============================================

INSERT INTO schema_migrations (version, description)
VALUES ('1.0.0', 'Initial MSP schema with users, clients, tickets, leads, KB articles, and RBAC')
ON CONFLICT (version) DO NOTHING;

-- Success message
DO $$
BEGIN
    RAISE NOTICE '========================================';
    RAISE NOTICE 'LUMIRA MSP Schema Created Successfully!';
    RAISE NOTICE '========================================';
END $$;
