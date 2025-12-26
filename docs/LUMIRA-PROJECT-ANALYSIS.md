# 🚀 LUMIRA Infrastructure - Project Analysis

## 📊 Project Overview

**LUMIRA** is a comprehensive **Managed Service Provider (MSP)** platform - a complete IT services business solution combining e-commerce, helpdesk, ticketing, and customer management.

### Original Infrastructure
- **Web Server**: Windows Server with nginx (10.0.1.100)
- **Database Server**: Red Hat Linux with PostgreSQL (10.0.1.200)
- **Email Server**: hMailServer/MailEnable on Windows
- **Chat Integration**: n8n workflow automation
- **Payments**: PayPal API integration

---

## 🏗️ Architecture Components

### **1. Core Application (PHP)**
- **Files**: 82 PHP files
- **Framework**: Vanilla PHP (no framework)
- **Web Server**: nginx 1.28.0
- **Requirements**: PHP with PostgreSQL extension

### **2. Database (PostgreSQL)**
- **Server**: 10.0.1.200:5432
- **Database**: LUMIRA
- **User**: postgres
- **Schema**: Comprehensive MSP schema

**Tables:**
- Users & Authentication (roles, permissions)
- Clients/Companies
- Tickets & Support
- Orders & E-commerce
- Knowledge Base Articles
- Email Integration
- SLA Tracking
- Subscriptions
- Leads & CRM

### **3. Email System**
- **Original**: hMailServer/MailEnable (Windows)
- **SMTP**: Port 25/587
- **Accounts**:
  - noreply@lumira.local
  - support@lumira.local
  - notifications@lumira.local
- **Features**:
  - Email-to-ticket automation
  - Order confirmations
  - Ticket notifications

### **4. Integrations**

**n8n Workflow Automation:**
- AI Chatbot integration
- Chat widget on website
- Workflow file: `n8n-workflow-lumira-chatbot.json`

**PayPal:**
- Sandbox mode configured
- Client ID & Secret in config
- Order processing
- Subscriptions

**osTicket Integration:**
- Service ticket creation
- Ticket management
- API integration

---

## 📁 Project Structure

```
LUMIRA-Infrastructure-main/
├── inc/                      # Core includes
│   ├── config.php           # Database & SMTP config
│   ├── db.php               # Database connection
│   ├── functions.php        # Helper functions
│   ├── nav.php              # Navigation
│   ├── email.php            # Email functions
│   ├── chat-widget-n8n.php  # Chat integration
│   └── osticket.php         # Ticket system
│
├── admin/                    # Admin panel
│   └── kb-article-edit.php  # Knowledge base editor
│
├── api/                      # API endpoints
│   ├── chat-ai.php          # AI chat API
│   ├── chat-n8n.php         # n8n chat API
│   ├── paypal-*.php         # PayPal APIs
│   └── delete-account.php   # Account management
│
├── database/                 # SQL schemas
│   └── helpdesk-schema.sql  # Helpdesk tables
│
├── kb/                       # Knowledge base
│   ├── index.php            # KB homepage
│   ├── category.php         # Category pages
│   ├── article.php          # Article viewer
│   └── search.php           # Search
│
├── cron/                     # Background jobs
│   ├── process-support-emails.php  # Email-to-ticket
│   └── check-sla-compliance.php    # SLA monitoring
│
├── assets/                   # CSS, JS, images
│
├── User Pages:
│   ├── index.php            # Homepage
│   ├── login.php            # Login system
│   ├── products.php         # Product catalog
│   ├── services.php         # Services catalog
│   ├── cart.php             # Shopping cart
│   ├── checkout.php         # Checkout process
│   ├── tickets.php          # My tickets
│   ├── ticket-view.php      # Ticket details
│   ├── create-ticket.php    # New ticket
│   ├── support.php          # Support portal
│   ├── chat.php             # Live chat
│   └── my-messages.php      # Messages
│
├── Admin Pages:
│   ├── admin.php            # Admin dashboard
│   ├── dashboard-admin.php  # Admin analytics
│   ├── admin-users.php      # User management
│   ├── admin-order-view.php # Order details
│   └── admin-ticket-view.php # Ticket management
│
└── SQL Files:
    ├── msp_schema.sql       # Full database schema
    ├── msp_seed_data.sql    # Sample data
    └── schema.sql           # Base schema
```

---

## ✨ Features Implemented

### **Customer Portal** ✅
- User registration & login
- Product catalog & shopping cart
- Service requests
- Ticket management
- Order history
- Live chat support
- Knowledge base access

### **E-Commerce** ✅
- Product listings
- Shopping cart
- Checkout flow
- PayPal integration
- Order tracking
- Email confirmations

### **Helpdesk/Ticketing** ✅ (40% Complete)
- Email-to-ticket automation
- Ticket creation & management
- SLA tracking & escalation
- Ticket threading
- Attachments support
- Canned responses
- Department routing

### **Admin Panel** ✅
- Dashboard with analytics
- User management
- Order management
- Ticket management
- System configuration

### **Knowledge Base** ⏳ (60% To Build)
- Categories ✅
- Articles (partial)
- Search (to build)
- Ratings (to build)

### **Communication** ✅
- SMTP email integration
- Email notifications
- Live chat (n8n)
- AI chatbot

---

## 🔧 Current Configuration

### **Database Connection**
```php
Host: 10.0.1.200
Port: 5432
Database: LUMIRA
User: postgres
Password: StrongPassword123
```

### **Email (SMTP)**
```php
Host: localhost (MailEnable)
Port: 25
From: noreply@lumira.local
Username: noreply
Password: Strongpassword123
```

### **Site URLs**
```
Homepage: http://10.0.1.100/
Admin: http://10.0.1.100/admin.php
Login: admin@lumira.local / Admin@2025!
```

---

## 🎯 Migration Strategy to Docker

### **Option 1: Docker Compose Stack** (Recommended)

**Services Needed:**
1. **nginx + PHP-FPM** - Web application
2. **PostgreSQL 16** - Database (already have!)
3. **Postfix/SMTP Relay** - Email sending
4. **n8n** - Chat workflows (already have!)

**Benefits:**
- Portable and reproducible
- Easy to manage with Portainer
- Can integrate with existing homelab
- Version controlled

### **Option 2: Add to Existing Homelab**

**Integrate with current stack:**
- Use existing PostgreSQL container
- Use existing n8n container
- Add nginx+PHP container for LUMIRA
- Configure email relay through existing services

---

## 📝 Required Changes for Docker Migration

### **1. Configuration Updates**

**inc/config.php:**
```php
// Update database to use container name
define('DB_HOST', 'mcp-postgres');  // or 'lumira-postgres'
define('DB_PORT', '5432');

// Update SMTP to use relay
define('SMTP_HOST', 'mailhog');  // or external SMTP
define('SMTP_PORT', 1025);

// Update site URL
define('SITE_URL', 'http://192.168.40.103:8080');  // or domain
```

### **2. Dependencies to Install**

**PHP Extensions:**
- pdo_pgsql (PostgreSQL)
- mbstring
- curl
- session
- json

### **3. File Permissions**

```bash
# Application files
chown -R www-data:www-data /var/www/html

# Upload directories
chmod 755 /var/www/html/assets/uploads
```

### **4. Cron Jobs (Background Tasks)**

**Email-to-Ticket Processor:**
```bash
*/5 * * * * php /var/www/html/cron/process-support-emails.php
```

**SLA Compliance Checker:**
```bash
*/5 * * * * php /var/www/html/cron/check-sla-compliance.php
```

---

## 🚀 Next Steps

### **Phase 1: Analyze & Prepare** ✅ (You are here)
- [x] Review project structure
- [x] Understand dependencies
- [x] Plan migration strategy

### **Phase 2: Create Docker Environment**
- [ ] Create Dockerfile for PHP/nginx
- [ ] Create docker-compose.yml
- [ ] Configure environment variables
- [ ] Set up volumes for persistent data

### **Phase 3: Database Migration**
- [ ] Create LUMIRA database in existing PostgreSQL
- [ ] Import schema (msp_schema.sql)
- [ ] Import seed data (msp_seed_data.sql)
- [ ] Test database connection

### **Phase 4: Deploy Application**
- [ ] Deploy containers
- [ ] Configure nginx
- [ ] Test application access
- [ ] Configure email relay

### **Phase 5: Integration**
- [ ] Connect to existing n8n
- [ ] Set up cron jobs
- [ ] Configure backups
- [ ] Test all features

---

## 💡 Recommendations

### **For Production Use:**
1. **Use environment variables** for sensitive config
2. **Set up SSL/TLS** with Let's Encrypt
3. **Configure proper domain** (not IP)
4. **Enable database backups**
5. **Set up logging** (syslog, file rotation)
6. **Add monitoring** (Grafana/Prometheus)

### **Security Improvements:**
1. Move passwords to .env file
2. Use stronger password hashing
3. Add CSRF protection
4. Implement rate limiting
5. Add input validation
6. Enable HTTPS only

---

## 📊 Estimated Work

**To Dockerize:**
- Docker setup: 2-3 hours
- Database migration: 1-2 hours
- Testing & debugging: 2-4 hours
- **Total: 5-9 hours**

**To Complete Original Features:**
- Knowledge Base pages: 4-6 hours
- Admin enhancements: 3-4 hours
- Testing: 2-3 hours
- **Total: 9-13 hours**

---

## 🎓 Project Status

**Completion: ~60%**

**Working:**
- ✅ User authentication & sessions
- ✅ E-commerce & checkout
- ✅ Ticket system (basic)
- ✅ Email integration
- ✅ Chat widget
- ✅ Admin panel
- ✅ Database schema

**To Complete:**
- ⏳ Knowledge base UI
- ⏳ Advanced ticket features
- ⏳ Analytics dashboard
- ⏳ AI chatbot training

---

## 🎯 Conclusion

LUMIRA is a well-structured, functional MSP platform that's ~60% complete. The foundation is solid:
- Clean PHP codebase
- Comprehensive database schema
- Working integrations (PayPal, n8n, osTicket)
- Email automation ready

**Perfect candidate for Docker migration!** It will integrate beautifully with your existing homelab setup.

---

**Ready to proceed?** We can:
1. Create Docker configuration
2. Migrate database
3. Deploy on your homelab
4. Continue development

Let me know how you'd like to proceed!
