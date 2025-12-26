-- ============================================
-- LUMIRA ENTERPRISE HELPDESK - COMPLETE SCHEMA
-- ============================================
-- This schema adds all osTicket-equivalent features and more

-- ============================================
-- 1. DEPARTMENTS & TEAM STRUCTURE
-- ============================================

CREATE TABLE IF NOT EXISTS departments (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE,
    description TEXT,
    auto_assign_enabled BOOLEAN DEFAULT TRUE,
    round_robin_enabled BOOLEAN DEFAULT TRUE,
    manager_id INTEGER REFERENCES users(id),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS department_members (
    id SERIAL PRIMARY KEY,
    department_id INTEGER REFERENCES departments(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    is_team_lead BOOLEAN DEFAULT FALSE,
    max_concurrent_tickets INTEGER DEFAULT 10,
    current_ticket_count INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(department_id, user_id)
);

-- ============================================
-- 2. KNOWLEDGE BASE SYSTEM
-- ============================================

CREATE TABLE IF NOT EXISTS kb_categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    parent_id INTEGER REFERENCES kb_categories(id) ON DELETE CASCADE,
    display_order INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS kb_articles (
    id SERIAL PRIMARY KEY,
    category_id INTEGER REFERENCES kb_categories(id) ON DELETE SET NULL,
    title VARCHAR(500) NOT NULL,
    slug VARCHAR(500) UNIQUE NOT NULL,
    content TEXT NOT NULL,
    summary TEXT,
    author_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    view_count INTEGER DEFAULT 0,
    helpful_count INTEGER DEFAULT 0,
    not_helpful_count INTEGER DEFAULT 0,
    is_published BOOLEAN DEFAULT FALSE,
    is_featured BOOLEAN DEFAULT FALSE,
    tags TEXT[],
    meta_keywords TEXT,
    meta_description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS kb_article_views (
    id SERIAL PRIMARY KEY,
    article_id INTEGER REFERENCES kb_articles(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    user_email VARCHAR(255),
    ip_address VARCHAR(45),
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS kb_feedback (
    id SERIAL PRIMARY KEY,
    article_id INTEGER REFERENCES kb_articles(id) ON DELETE CASCADE,
    user_email VARCHAR(255),
    was_helpful BOOLEAN NOT NULL,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS kb_search_log (
    id SERIAL PRIMARY KEY,
    search_query VARCHAR(500) NOT NULL,
    results_count INTEGER DEFAULT 0,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- 3. SLA MANAGEMENT
-- ============================================

CREATE TABLE IF NOT EXISTS sla_policies (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    priority_id INTEGER REFERENCES ticket_priorities(id) ON DELETE CASCADE,
    customer_tier VARCHAR(50) DEFAULT 'all', -- 'vip', 'standard', 'all'
    first_response_hours INTEGER NOT NULL,
    resolution_hours INTEGER NOT NULL,
    business_hours_only BOOLEAN DEFAULT TRUE,
    escalation_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sla_breaches (
    id SERIAL PRIMARY KEY,
    ticket_id INTEGER REFERENCES tickets(id) ON DELETE CASCADE,
    sla_policy_id INTEGER REFERENCES sla_policies(id) ON DELETE SET NULL,
    breach_type VARCHAR(50) NOT NULL, -- 'first_response', 'resolution'
    target_time TIMESTAMP NOT NULL,
    actual_time TIMESTAMP,
    hours_overdue NUMERIC(10,2),
    escalated BOOLEAN DEFAULT FALSE,
    escalated_at TIMESTAMP,
    escalated_to_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- 4. CANNED RESPONSES / TEMPLATES
-- ============================================

CREATE TABLE IF NOT EXISTS canned_responses (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    shortcode VARCHAR(50) UNIQUE,
    content TEXT NOT NULL,
    category VARCHAR(100),
    department_id INTEGER REFERENCES departments(id) ON DELETE SET NULL,
    usage_count INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- 5. TICKET ENHANCEMENTS
-- ============================================

-- Add new columns to existing tickets table
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS department_id INTEGER REFERENCES departments(id) ON DELETE SET NULL;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS sla_policy_id INTEGER REFERENCES sla_policies(id) ON DELETE SET NULL;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS first_response_due TIMESTAMP;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS resolution_due TIMESTAMP;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS first_responded_at TIMESTAMP;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS resolved_at TIMESTAMP;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS closed_at TIMESTAMP;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS sla_status VARCHAR(50) DEFAULT 'on_track'; -- 'on_track', 'at_risk', 'breached'
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS source VARCHAR(50) DEFAULT 'web'; -- 'web', 'email', 'phone', 'chat'
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS email_thread_id VARCHAR(255);
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS customer_tier VARCHAR(50) DEFAULT 'standard';
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS satisfaction_rating INTEGER; -- 1-5 stars
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS satisfaction_feedback TEXT;

-- ============================================
-- 6. TICKET ATTACHMENTS
-- ============================================

CREATE TABLE IF NOT EXISTS ticket_attachments (
    id SERIAL PRIMARY KEY,
    ticket_id INTEGER REFERENCES tickets(id) ON DELETE CASCADE,
    comment_id INTEGER REFERENCES ticket_comments(id) ON DELETE CASCADE,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INTEGER NOT NULL,
    mime_type VARCHAR(100),
    uploaded_by_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    uploaded_by_email VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- 7. EMAIL TRACKING
-- ============================================

CREATE TABLE IF NOT EXISTS ticket_email_tracking (
    id SERIAL PRIMARY KEY,
    ticket_id INTEGER REFERENCES tickets(id) ON DELETE CASCADE,
    email_message_id VARCHAR(255) UNIQUE NOT NULL,
    email_subject VARCHAR(500),
    from_address VARCHAR(255) NOT NULL,
    to_address VARCHAR(255),
    email_direction VARCHAR(20) NOT NULL, -- 'inbound', 'outbound'
    raw_headers TEXT,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- 8. INTERNAL NOTES
-- ============================================

ALTER TABLE ticket_comments ADD COLUMN IF NOT EXISTS is_internal BOOLEAN DEFAULT FALSE;
ALTER TABLE ticket_comments ADD COLUMN IF NOT EXISTS mentioned_users INTEGER[];

-- ============================================
-- 9. TICKET WATCHERS
-- ============================================

CREATE TABLE IF NOT EXISTS ticket_watchers (
    id SERIAL PRIMARY KEY,
    ticket_id INTEGER REFERENCES tickets(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(ticket_id, user_id)
);

-- ============================================
-- 10. TICKET MERGE/SPLIT TRACKING
-- ============================================

CREATE TABLE IF NOT EXISTS ticket_relationships (
    id SERIAL PRIMARY KEY,
    parent_ticket_id INTEGER REFERENCES tickets(id) ON DELETE CASCADE,
    child_ticket_id INTEGER REFERENCES tickets(id) ON DELETE CASCADE,
    relationship_type VARCHAR(50) NOT NULL, -- 'merged_into', 'split_from', 'related_to', 'duplicate_of'
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- 11. BUSINESS HOURS CONFIGURATION
-- ============================================

CREATE TABLE IF NOT EXISTS business_hours (
    id SERIAL PRIMARY KEY,
    day_of_week INTEGER NOT NULL, -- 1=Monday, 7=Sunday
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_active BOOLEAN DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS business_holidays (
    id SERIAL PRIMARY KEY,
    holiday_date DATE NOT NULL UNIQUE,
    holiday_name VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE
);

-- ============================================
-- 12. CUSTOMER SATISFACTION SURVEYS
-- ============================================

CREATE TABLE IF NOT EXISTS ticket_surveys (
    id SERIAL PRIMARY KEY,
    ticket_id INTEGER REFERENCES tickets(id) ON DELETE CASCADE,
    survey_token VARCHAR(255) UNIQUE NOT NULL,
    rating INTEGER CHECK (rating >= 1 AND rating <= 5),
    feedback TEXT,
    submitted_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- 13. AUTOMATION RULES
-- ============================================

CREATE TABLE IF NOT EXISTS automation_rules (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    trigger_type VARCHAR(50) NOT NULL, -- 'ticket_created', 'ticket_updated', 'no_response', 'overdue'
    conditions JSONB, -- Flexible conditions storage
    actions JSONB, -- Flexible actions storage
    is_active BOOLEAN DEFAULT TRUE,
    execution_count INTEGER DEFAULT 0,
    last_executed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- INDEXES FOR PERFORMANCE
-- ============================================

-- Tickets
CREATE INDEX IF NOT EXISTS idx_tickets_department ON tickets(department_id);
CREATE INDEX IF NOT EXISTS idx_tickets_sla_status ON tickets(sla_status);
CREATE INDEX IF NOT EXISTS idx_tickets_first_response_due ON tickets(first_response_due);
CREATE INDEX IF NOT EXISTS idx_tickets_resolution_due ON tickets(resolution_due);
CREATE INDEX IF NOT EXISTS idx_tickets_source ON tickets(source);
CREATE INDEX IF NOT EXISTS idx_tickets_email_thread ON tickets(email_thread_id);

-- Knowledge Base
CREATE INDEX IF NOT EXISTS idx_kb_articles_slug ON kb_articles(slug);
CREATE INDEX IF NOT EXISTS idx_kb_articles_category ON kb_articles(category_id);
CREATE INDEX IF NOT EXISTS idx_kb_articles_published ON kb_articles(is_published);
CREATE INDEX IF NOT EXISTS idx_kb_articles_featured ON kb_articles(is_featured);
CREATE INDEX IF NOT EXISTS idx_kb_articles_tags ON kb_articles USING GIN(tags);
CREATE INDEX IF NOT EXISTS idx_kb_categories_slug ON kb_categories(slug);

-- SLA
CREATE INDEX IF NOT EXISTS idx_sla_policies_priority ON sla_policies(priority_id);
CREATE INDEX IF NOT EXISTS idx_sla_breaches_ticket ON sla_breaches(ticket_id);

-- Email Tracking
CREATE INDEX IF NOT EXISTS idx_email_tracking_message_id ON ticket_email_tracking(email_message_id);
CREATE INDEX IF NOT EXISTS idx_email_tracking_ticket ON ticket_email_tracking(ticket_id);

-- Attachments
CREATE INDEX IF NOT EXISTS idx_attachments_ticket ON ticket_attachments(ticket_id);
CREATE INDEX IF NOT EXISTS idx_attachments_comment ON ticket_attachments(comment_id);

-- ============================================
-- DEFAULT DATA
-- ============================================

-- Insert default departments
INSERT INTO departments (name, email, description) VALUES
('Technical Support', 'support@lumira.local', 'Technical issues and troubleshooting'),
('Sales', 'sales@lumira.local', 'Product inquiries and quotes'),
('Billing', 'billing@lumira.local', 'Billing and payment questions')
ON CONFLICT (email) DO NOTHING;

-- Insert default KB categories
INSERT INTO kb_categories (name, slug, description, icon, display_order) VALUES
('Getting Started', 'getting-started', 'New to LUMIRA? Start here!', '🚀', 1),
('Account & Billing', 'account-billing', 'Manage your account and payments', '💳', 2),
('Technical Support', 'technical-support', 'Technical troubleshooting guides', '🔧', 3),
('Orders & Shipping', 'orders-shipping', 'Track orders and shipping info', '📦', 4),
('Products', 'products', 'Product information and guides', '💻', 5)
ON CONFLICT (slug) DO NOTHING;

-- Insert default SLA policies
INSERT INTO sla_policies (name, description, priority_id, customer_tier, first_response_hours, resolution_hours, business_hours_only) VALUES
('Critical - VIP', 'VIP customers with critical issues',
    (SELECT id FROM ticket_priorities WHERE name = 'Critical' LIMIT 1),
    'vip', 1, 4, FALSE),
('High - VIP', 'VIP customers with high priority issues',
    (SELECT id FROM ticket_priorities WHERE name = 'High' LIMIT 1),
    'vip', 2, 8, FALSE),
('Critical - Standard', 'Standard customers with critical issues',
    (SELECT id FROM ticket_priorities WHERE name = 'Critical' LIMIT 1),
    'standard', 2, 8, TRUE),
('High - Standard', 'Standard customers with high priority issues',
    (SELECT id FROM ticket_priorities WHERE name = 'High' LIMIT 1),
    'standard', 4, 24, TRUE),
('Medium - All', 'Medium priority for all customers',
    (SELECT id FROM ticket_priorities WHERE name = 'Medium' LIMIT 1),
    'all', 8, 48, TRUE),
('Low - All', 'Low priority for all customers',
    (SELECT id FROM ticket_priorities WHERE name = 'Low' LIMIT 1),
    'all', 24, 120, TRUE)
ON CONFLICT DO NOTHING;

-- Insert default business hours (Monday-Friday, 9 AM - 5 PM)
INSERT INTO business_hours (day_of_week, start_time, end_time) VALUES
(1, '09:00:00', '17:00:00'), -- Monday
(2, '09:00:00', '17:00:00'), -- Tuesday
(3, '09:00:00', '17:00:00'), -- Wednesday
(4, '09:00:00', '17:00:00'), -- Thursday
(5, '09:00:00', '17:00:00')  -- Friday
ON CONFLICT DO NOTHING;

-- Insert sample canned responses
INSERT INTO canned_responses (title, shortcode, content, category) VALUES
('Welcome Response', '/welcome',
'Hello {{customer_name}},

Thank you for contacting LUMIRA support. I''m {{agent_name}} and I''ll be happy to help you today.

I''ve reviewed your issue and I''m working on a solution. I''ll update you shortly.

Best regards,
{{agent_name}}
LUMIRA Support Team', 'General'),

('Password Reset', '/password-reset',
'Hello {{customer_name}},

To reset your password:

1. Visit: http://10.0.1.100/forgot-password.php
2. Enter your email address: {{customer_email}}
3. Click the reset link sent to your email
4. Create a new password

If you don''t receive the email within 5 minutes, please check your spam folder.

Let me know if you need any assistance!

Best regards,
{{agent_name}}', 'Account'),

('Ticket Resolved', '/resolved',
'Hello {{customer_name}},

I''m pleased to inform you that your issue has been resolved!

Issue: {{ticket_subject}}
Resolution: {{resolution_details}}

If you have any questions or if the issue persists, please reply to this ticket and we''ll be happy to help.

We appreciate your patience!

Best regards,
{{agent_name}}
LUMIRA Support Team', 'General')
ON CONFLICT (shortcode) DO NOTHING;

-- ============================================
-- COMPLETION MESSAGE
-- ============================================

DO $$
BEGIN
    RAISE NOTICE '✅ LUMIRA Enterprise Helpdesk Schema Created Successfully!';
    RAISE NOTICE '📊 Features Added:';
    RAISE NOTICE '   - Departments & Team Routing';
    RAISE NOTICE '   - Knowledge Base System';
    RAISE NOTICE '   - SLA Management & Tracking';
    RAISE NOTICE '   - Canned Responses';
    RAISE NOTICE '   - File Attachments';
    RAISE NOTICE '   - Email Integration';
    RAISE NOTICE '   - Internal Notes & Watchers';
    RAISE NOTICE '   - Customer Surveys';
    RAISE NOTICE '   - Automation Rules';
END $$;
