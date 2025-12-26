# LUMIRA MSP Database Migration Summary

## Migration Completed Successfully ✓

**Date:** October 12, 2025
**Database:** LUMIRA on PostgreSQL 18

---

## What Was Done

### 1. Database Schema Creation
Created comprehensive MSP database schema with **31 tables** including:

#### Core Authentication & Authorization
- `users` - Enhanced user table with role-based access
- `app_roles` - 7 predefined roles (super_admin, admin, technician, manager, client_admin, client_user, sales)
- `privileges` - 37 granular permissions for fine-grained access control
- `role_privileges` - Mapping table for role-permission assignments
- `user_sessions` - Session management
- `password_reset_tokens` - Password recovery

#### Client Management (CRM)
- `clients` - Client company information with contracts and billing details
- `client_contacts` - Links users to client companies
- `leads` - Lead/prospect management
- `lead_sources` - Lead origin tracking (9 sources)
- `lead_statuses` - Lead pipeline stages (8 statuses)
- `lead_activities` - Activity tracking for leads

#### Enhanced Ticketing System
- `tickets` - Full-featured support tickets with SLA tracking
- `ticket_priorities` - 5 priority levels (Low, Normal, High, Critical, Emergency) with SLA times
- `ticket_statuses` - 7 statuses (New, Open, In Progress, Pending Customer, Pending Internal, Resolved, Closed)
- `ticket_categories` - 9 categories (Hardware, Software, Network, Email, Security, etc.)
- `ticket_comments` - Comments and time tracking
- `ticket_attachments` - File attachments for tickets
- `ticket_history` - Complete audit trail

#### Knowledge Base (Optional)
- `kb_articles` - Articles with view/helpfulness tracking
- `kb_categories` - 9 categories with hierarchical support
- `kb_tags` - Tagging system
- `kb_article_tags` - Article-tag mapping
- `kb_article_attachments` - File attachments for KB articles

#### Products, Services & Orders
- `products` - Product catalog with SKUs
- `services` - IT services with hourly rates
- `orders` - Customer orders with order numbers
- `order_items` - Line items supporting both products and services

#### System Tables
- `schema_migrations` - Migration tracking

---

## 2. Data Migration

### Users Migrated
- **3 users** successfully migrated from old schema to new schema:
  - admin@lumira.com (Super Admin)
  - admin@lumira.local (Admin)
  - TestAccount1@test.domain.com (Client User)

### Role Mapping
- Old `admin` role → New `admin` role
- Old `customer` role → New `client_user` role

### Backup Tables Created
- `users_old` - Backup of original users table
- `tickets_old` - Backup of original tickets table

---

## 3. Default Data Loaded

### Roles & Permissions
- **7 app roles** with appropriate privilege assignments
- **37 granular privileges** for access control
- Pre-configured role-privilege mappings

### Reference Data
- **5 ticket priorities** with SLA response/resolution times
- **7 ticket statuses** with color codes
- **9 ticket categories**
- **9 lead sources**
- **8 lead statuses**
- **9 KB categories**

### Sample Data
- **6 sample products** (Managed IT packages, cloud backup, hardware, licenses)
- **8 sample services** (on-site support, remote support, consulting, etc.)

### Default Admin Account
- **Email:** admin@lumira.com
- **Password:** Admin@2025!
- **Role:** Super Administrator
- ⚠️ **IMPORTANT:** Change this password after first login!

---

## 4. PHP Code Updates

### Updated Files
1. **inc/functions.php**
   - Updated `user_login()` to join with app_roles table
   - Updated `user_register()` to use role_id instead of role string
   - Updated `is_user_admin()` and `is_user_customer()` to check role_name
   - Updated `get_user_tickets()` to use new ticket schema with joins

2. **dashboard-customer.php**
   - Updated to use `is_user_admin()` function
   - Changed ticket display to show new fields (ticket_number, subject, priority, status)
   - Updated to pass user_id instead of email to ticket function

3. **dashboard-admin.php**
   - Updated queries to join with new tables (app_roles, ticket_priorities, ticket_statuses, etc.)
   - Enhanced ticket display with priority/status colors
   - Updated user display to show role display names

---

## 5. Database Connection Info

**Connection Details:**
- Host: 10.0.1.200
- Port: 5432
- Database: LUMIRA
- User: postgres
- Password: StrongPassword123

**Config File:** `inc/config.php`

---

## New Features Available

### Role-Based Access Control (RBAC)
- Fine-grained permissions system
- 7 predefined roles with appropriate access levels
- Easy to extend with custom roles and privileges

### Enhanced Ticketing
- Priority levels with color coding
- Status tracking with workflow support
- SLA tracking (response and resolution times)
- Ticket assignment to technicians
- Internal vs external comments
- Time tracking on comments
- File attachments
- Complete audit history

### Lead Management (CRM)
- Lead capture and tracking
- Source attribution
- Pipeline management with statuses
- Probability and value estimation
- Activity tracking
- Lead-to-client conversion tracking

### Client Management
- Company information
- Contract and billing details
- Multiple contacts per client
- Account manager assignment
- Client portal access for users

### Knowledge Base
- Self-service articles
- Category organization
- Tagging system
- View and helpfulness tracking
- File attachments
- Publish/draft workflow

---

## Next Steps

### Immediate Actions
1. ✅ Change admin password: admin@lumira.com / Admin@2025!
2. ✅ Test user login and registration
3. ✅ Verify dashboards display correctly
4. ✅ Test database connectivity from web interface

### Development Tasks
1. Create user registration page (currently only have login)
2. Implement ticket creation form with new fields
3. Build client management interface
4. Create lead management system
5. Develop KB article management
6. Implement privilege checking in all admin functions
7. Add file upload functionality for ticket attachments

### Optional Cleanup
- Remove backup tables `users_old` and `tickets_old` after verification
- Update `inc/config.php` password from StrongPassword123! to StrongPassword123

---

## Database Access Commands

### Connect via psql
```powershell
& "C:\Program Files\PostgreSQL\18\bin\psql.exe" -h 10.0.1.200 -U postgres -d LUMIRA
```

### Run SQL file
```powershell
$env:PGPASSWORD = "StrongPassword123"
& "C:\Program Files\PostgreSQL\18\bin\psql.exe" -h 10.0.1.200 -U postgres -d LUMIRA -f "your-file.sql"
```

### Quick Queries
```sql
-- View all users with roles
SELECT u.email, u.full_name, r.name as role
FROM users u
JOIN app_roles r ON u.role_id = r.id;

-- View ticket counts by status
SELECT ts.name, COUNT(*)
FROM tickets t
JOIN ticket_statuses ts ON t.status_id = ts.id
GROUP BY ts.name;

-- View privileges by role
SELECT r.name as role, p.name as privilege
FROM role_privileges rp
JOIN app_roles r ON rp.role_id = r.id
JOIN privileges p ON rp.privilege_id = p.id
ORDER BY r.name, p.name;
```

---

## Files Created

1. `msp_schema.sql` - Complete database schema
2. `msp_seed_data.sql` - Default data and sample records
3. `apply-msp-schema.ps1` - PowerShell script to apply schema
4. `MIGRATION_SUMMARY.md` - This document

---

## Support & Documentation

For questions about:
- **Database schema:** See `msp_schema.sql`
- **Default data:** See `msp_seed_data.sql`
- **PHP integration:** See updated files in `inc/` directory
- **Configuration:** See `inc/config.php` and `inc/db.php`

---

**Migration Status: ✅ COMPLETE**
