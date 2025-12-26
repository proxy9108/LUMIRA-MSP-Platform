-- LUMIRA MSP Default Data
-- Initial roles, privileges, statuses, and reference data

-- ============================================
-- APP ROLES
-- ============================================

INSERT INTO app_roles (name, display_name, description, is_system_role) VALUES
('super_admin', 'Super Administrator', 'Full system access with all privileges', true),
('admin', 'Administrator', 'System administrator with most privileges', true),
('technician', 'Technician', 'Technical staff who handle tickets and support', true),
('manager', 'Manager', 'Team manager with oversight capabilities', true),
('client_admin', 'Client Administrator', 'Client company administrator', true),
('client_user', 'Client User', 'Standard client user', true),
('sales', 'Sales Representative', 'Sales team member', true)
ON CONFLICT (name) DO NOTHING;

-- ============================================
-- PRIVILEGES
-- ============================================

-- User management privileges
INSERT INTO privileges (name, resource, action, description) VALUES
('users.view', 'users', 'read', 'View user profiles'),
('users.create', 'users', 'create', 'Create new users'),
('users.edit', 'users', 'update', 'Edit user profiles'),
('users.delete', 'users', 'delete', 'Delete users'),
('users.manage', 'users', 'manage', 'Full user management'),

-- Client management privileges
('clients.view', 'clients', 'read', 'View client companies'),
('clients.create', 'clients', 'create', 'Create new clients'),
('clients.edit', 'clients', 'update', 'Edit client information'),
('clients.delete', 'clients', 'delete', 'Delete clients'),
('clients.manage', 'clients', 'manage', 'Full client management'),

-- Ticket privileges
('tickets.view_all', 'tickets', 'read', 'View all tickets'),
('tickets.view_own', 'tickets', 'read', 'View own tickets only'),
('tickets.view_assigned', 'tickets', 'read', 'View assigned tickets'),
('tickets.create', 'tickets', 'create', 'Create new tickets'),
('tickets.edit', 'tickets', 'update', 'Edit tickets'),
('tickets.delete', 'tickets', 'delete', 'Delete tickets'),
('tickets.assign', 'tickets', 'update', 'Assign tickets to technicians'),
('tickets.close', 'tickets', 'update', 'Close tickets'),

-- Lead privileges
('leads.view', 'leads', 'read', 'View leads'),
('leads.create', 'leads', 'create', 'Create new leads'),
('leads.edit', 'leads', 'update', 'Edit leads'),
('leads.delete', 'leads', 'delete', 'Delete leads'),
('leads.convert', 'leads', 'update', 'Convert leads to clients'),

-- KB article privileges
('kb.view_published', 'kb_articles', 'read', 'View published KB articles'),
('kb.view_all', 'kb_articles', 'read', 'View all KB articles'),
('kb.create', 'kb_articles', 'create', 'Create KB articles'),
('kb.edit', 'kb_articles', 'update', 'Edit KB articles'),
('kb.delete', 'kb_articles', 'delete', 'Delete KB articles'),
('kb.publish', 'kb_articles', 'update', 'Publish KB articles'),

-- Order/Invoice privileges
('orders.view', 'orders', 'read', 'View orders'),
('orders.create', 'orders', 'create', 'Create orders'),
('orders.edit', 'orders', 'update', 'Edit orders'),
('orders.delete', 'orders', 'delete', 'Delete orders'),

-- Reporting privileges
('reports.view', 'reports', 'read', 'View reports and analytics'),
('reports.export', 'reports', 'read', 'Export report data'),

-- System privileges
('system.settings', 'system', 'manage', 'Manage system settings'),
('system.roles', 'system', 'manage', 'Manage roles and privileges')
ON CONFLICT (name) DO NOTHING;

-- ============================================
-- ROLE-PRIVILEGE MAPPINGS
-- ============================================

-- Super Admin: All privileges
INSERT INTO role_privileges (role_id, privilege_id)
SELECT r.id, p.id
FROM app_roles r
CROSS JOIN privileges p
WHERE r.name = 'super_admin'
ON CONFLICT (role_id, privilege_id) DO NOTHING;

-- Admin: Most privileges
INSERT INTO role_privileges (role_id, privilege_id)
SELECT r.id, p.id
FROM app_roles r, privileges p
WHERE r.name = 'admin'
AND p.name IN (
    'users.view', 'users.create', 'users.edit',
    'clients.view', 'clients.create', 'clients.edit', 'clients.manage',
    'tickets.view_all', 'tickets.create', 'tickets.edit', 'tickets.assign', 'tickets.close',
    'leads.view', 'leads.create', 'leads.edit', 'leads.convert',
    'kb.view_all', 'kb.create', 'kb.edit', 'kb.publish',
    'orders.view', 'orders.create', 'orders.edit',
    'reports.view', 'reports.export'
)
ON CONFLICT (role_id, privilege_id) DO NOTHING;

-- Technician: Ticket-focused privileges
INSERT INTO role_privileges (role_id, privilege_id)
SELECT r.id, p.id
FROM app_roles r, privileges p
WHERE r.name = 'technician'
AND p.name IN (
    'tickets.view_assigned', 'tickets.view_all', 'tickets.create', 'tickets.edit', 'tickets.close',
    'clients.view',
    'kb.view_all', 'kb.create', 'kb.edit',
    'orders.view'
)
ON CONFLICT (role_id, privilege_id) DO NOTHING;

-- Manager: Team oversight privileges
INSERT INTO role_privileges (role_id, privilege_id)
SELECT r.id, p.id
FROM app_roles r, privileges p
WHERE r.name = 'manager'
AND p.name IN (
    'users.view',
    'clients.view', 'clients.edit',
    'tickets.view_all', 'tickets.create', 'tickets.edit', 'tickets.assign', 'tickets.close',
    'leads.view', 'leads.create', 'leads.edit',
    'kb.view_all', 'kb.create', 'kb.edit', 'kb.publish',
    'orders.view', 'orders.create', 'orders.edit',
    'reports.view', 'reports.export'
)
ON CONFLICT (role_id, privilege_id) DO NOTHING;

-- Client Admin: Client-side management
INSERT INTO role_privileges (role_id, privilege_id)
SELECT r.id, p.id
FROM app_roles r, privileges p
WHERE r.name = 'client_admin'
AND p.name IN (
    'tickets.view_own', 'tickets.create', 'tickets.edit',
    'kb.view_published',
    'orders.view'
)
ON CONFLICT (role_id, privilege_id) DO NOTHING;

-- Client User: Basic client access
INSERT INTO role_privileges (role_id, privilege_id)
SELECT r.id, p.id
FROM app_roles r, privileges p
WHERE r.name = 'client_user'
AND p.name IN (
    'tickets.view_own', 'tickets.create',
    'kb.view_published',
    'orders.view'
)
ON CONFLICT (role_id, privilege_id) DO NOTHING;

-- Sales: Lead-focused privileges
INSERT INTO role_privileges (role_id, privilege_id)
SELECT r.id, p.id
FROM app_roles r, privileges p
WHERE r.name = 'sales'
AND p.name IN (
    'clients.view', 'clients.create',
    'leads.view', 'leads.create', 'leads.edit', 'leads.convert',
    'tickets.view_own',
    'kb.view_published',
    'reports.view'
)
ON CONFLICT (role_id, privilege_id) DO NOTHING;

-- ============================================
-- TICKET PRIORITIES
-- ============================================

INSERT INTO ticket_priorities (name, level, sla_response_hours, sla_resolution_hours, color_code) VALUES
('Low', 1, 48, 120, '#28a745'),
('Normal', 2, 24, 72, '#17a2b8'),
('High', 3, 8, 24, '#ffc107'),
('Critical', 4, 4, 12, '#fd7e14'),
('Emergency', 5, 1, 4, '#dc3545')
ON CONFLICT (name) DO NOTHING;

-- ============================================
-- TICKET STATUSES
-- ============================================

INSERT INTO ticket_statuses (name, is_open, is_resolved, order_position, color_code) VALUES
('New', true, false, 1, '#007bff'),
('Open', true, false, 2, '#17a2b8'),
('In Progress', true, false, 3, '#ffc107'),
('Pending Customer', true, false, 4, '#6c757d'),
('Pending Internal', true, false, 5, '#6c757d'),
('Resolved', false, true, 6, '#28a745'),
('Closed', false, true, 7, '#343a40')
ON CONFLICT (name) DO NOTHING;

-- ============================================
-- TICKET CATEGORIES
-- ============================================

INSERT INTO ticket_categories (name, description) VALUES
('Hardware', 'Hardware-related issues and requests'),
('Software', 'Software installation, updates, and issues'),
('Network', 'Network connectivity and infrastructure'),
('Email', 'Email system issues and requests'),
('Security', 'Security-related incidents and requests'),
('Access', 'Account and access management'),
('Backup & Recovery', 'Data backup and recovery requests'),
('Cloud Services', 'Cloud platform and service issues'),
('Other', 'Other requests not categorized')
ON CONFLICT (name) DO NOTHING;

-- ============================================
-- LEAD SOURCES
-- ============================================

INSERT INTO lead_sources (name, description) VALUES
('Website', 'Lead from website contact form'),
('Referral', 'Referred by existing client'),
('Cold Call', 'Outbound cold calling'),
('Email Campaign', 'Marketing email campaign'),
('Social Media', 'Social media channels'),
('Trade Show', 'Trade show or event'),
('Partner', 'Partner referral'),
('Direct', 'Direct contact'),
('Other', 'Other sources')
ON CONFLICT (name) DO NOTHING;

-- ============================================
-- LEAD STATUSES
-- ============================================

INSERT INTO lead_statuses (name, order_position) VALUES
('New', 1),
('Contacted', 2),
('Qualified', 3),
('Proposal Sent', 4),
('Negotiation', 5),
('Won', 6),
('Lost', 7),
('On Hold', 8)
ON CONFLICT (name) DO NOTHING;

-- ============================================
-- KB CATEGORIES
-- ============================================

INSERT INTO kb_categories (name, description, icon, order_position) VALUES
('Getting Started', 'New user guides and setup instructions', 'rocket', 1),
('Account Management', 'User account and profile management', 'user', 2),
('Troubleshooting', 'Common issues and solutions', 'wrench', 3),
('Security', 'Security best practices and guidelines', 'shield', 4),
('Network', 'Network setup and configuration', 'network', 5),
('Email', 'Email setup and troubleshooting', 'envelope', 6),
('Software', 'Software guides and tutorials', 'desktop', 7),
('Hardware', 'Hardware setup and maintenance', 'server', 8),
('FAQs', 'Frequently asked questions', 'question', 9)
ON CONFLICT (name) DO NOTHING;

-- ============================================
-- SAMPLE ADMIN USER
-- ============================================
-- Password: Admin@2025! (hashed with PASSWORD_BCRYPT)
-- You should change this after first login!

INSERT INTO users_new (email, password_hash, full_name, phone, role_id, is_active, email_verified)
SELECT
    'admin@lumira.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'System Administrator',
    '555-0100',
    (SELECT id FROM app_roles WHERE name = 'super_admin'),
    true,
    true
ON CONFLICT (email) DO NOTHING;

-- ============================================
-- SAMPLE PRODUCTS
-- ============================================

INSERT INTO products (name, description, sku, price_cents, is_active) VALUES
('Managed IT Support - Small Business', 'Comprehensive IT support for businesses with 1-10 users', 'MIT-SB-001', 149900, true),
('Managed IT Support - Medium Business', 'Comprehensive IT support for businesses with 11-50 users', 'MIT-MB-001', 299900, true),
('Cloud Backup - 100GB', 'Secure cloud backup storage - 100GB', 'BCK-100GB', 2999, true),
('Cloud Backup - 500GB', 'Secure cloud backup storage - 500GB', 'BCK-500GB', 9999, true),
('Firewall Hardware', 'Enterprise-grade firewall appliance', 'FW-ENT-001', 149900, true),
('Antivirus License', 'Enterprise antivirus license per device', 'AV-LIC-001', 4999, true)
ON CONFLICT (sku) DO NOTHING;

-- ============================================
-- SAMPLE SERVICES
-- ============================================

INSERT INTO services (name, description, hourly_rate_cents, is_active) VALUES
('On-Site Support', 'On-site technical support and troubleshooting', 15000, true),
('Remote Support', 'Remote technical support via phone/chat/remote desktop', 10000, true),
('Network Setup', 'Network design, installation, and configuration', 17500, true),
('Server Installation', 'Server hardware and software installation', 20000, true),
('Security Audit', 'Comprehensive security assessment and recommendations', 25000, true),
('Data Migration', 'Data migration and transfer services', 15000, true),
('Emergency Support', 'After-hours emergency technical support', 30000, true),
('Consulting', 'IT consulting and strategy services', 20000, true)
ON CONFLICT (name) DO NOTHING;

-- ============================================
-- RECORD SEED DATA MIGRATION
-- ============================================

INSERT INTO schema_migrations (version, description)
VALUES ('1.0.1', 'Default MSP seed data with roles, privileges, and reference data')
ON CONFLICT (version) DO NOTHING;

-- Success message
DO $$
BEGIN
    RAISE NOTICE '========================================';
    RAISE NOTICE 'LUMIRA MSP Seed Data Loaded Successfully!';
    RAISE NOTICE '========================================';
    RAISE NOTICE 'Default Admin User: admin@lumira.com';
    RAISE NOTICE 'Default Password: Admin@2025!';
    RAISE NOTICE 'IMPORTANT: Change the admin password after first login!';
    RAISE NOTICE '========================================';
END $$;
