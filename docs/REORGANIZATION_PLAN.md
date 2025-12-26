# 🗂️ LUMIRA File Organization Plan

## Current Problem
- 82 PHP files with 30+ in root directory
- Hard to find specific files
- No clear separation of concerns
- Difficult to maintain

---

## ✨ Proposed Clean Structure

```
LUMIRA/
├── public/                    # Public web root (ONLY files accessible via web)
│   ├── index.php             # Homepage
│   ├── .htaccess             # Apache rules
│   └── assets/               # Static files
│       ├── css/
│       ├── js/
│       ├── images/
│       └── uploads/
│
├── app/                       # Application code
│   ├── config/               # Configuration files
│   │   ├── config.php        # Main config
│   │   ├── database.php      # DB settings
│   │   └── email.php         # SMTP settings
│   │
│   ├── controllers/          # Page controllers
│   │   ├── AuthController.php
│   │   ├── TicketController.php
│   │   ├── OrderController.php
│   │   ├── ProductController.php
│   │   └── AdminController.php
│   │
│   ├── models/               # Database models
│   │   ├── User.php
│   │   ├── Ticket.php
│   │   ├── Order.php
│   │   ├── Product.php
│   │   └── Client.php
│   │
│   ├── views/                # Page templates
│   │   ├── layouts/
│   │   │   ├── header.php
│   │   │   ├── footer.php
│   │   │   └── nav.php
│   │   ├── auth/
│   │   │   ├── login.php
│   │   │   └── register.php
│   │   ├── tickets/
│   │   │   ├── list.php
│   │   │   ├── view.php
│   │   │   └── create.php
│   │   ├── orders/
│   │   │   ├── cart.php
│   │   │   ├── checkout.php
│   │   │   └── view.php
│   │   ├── admin/
│   │   │   ├── dashboard.php
│   │   │   ├── users.php
│   │   │   └── tickets.php
│   │   └── kb/
│   │       ├── index.php
│   │       ├── article.php
│   │       └── search.php
│   │
│   ├── services/             # Business logic
│   │   ├── EmailService.php
│   │   ├── TicketService.php
│   │   ├── PaymentService.php
│   │   └── NotificationService.php
│   │
│   ├── helpers/              # Helper functions
│   │   ├── functions.php
│   │   ├── validation.php
│   │   └── formatting.php
│   │
│   └── middleware/           # Authentication, etc.
│       ├── AuthMiddleware.php
│       └── AdminMiddleware.php
│
├── api/                       # API endpoints
│   ├── v1/
│   │   ├── chat.php
│   │   ├── tickets.php
│   │   └── orders.php
│   └── webhooks/
│       ├── paypal.php
│       └── n8n.php
│
├── admin/                     # Admin panel
│   ├── index.php
│   ├── users.php
│   ├── tickets.php
│   └── orders.php
│
├── cron/                      # Scheduled jobs
│   ├── email-to-ticket.php
│   └── sla-check.php
│
├── database/                  # SQL files
│   ├── migrations/
│   │   ├── 001_create_users.sql
│   │   ├── 002_create_tickets.sql
│   │   └── 003_create_orders.sql
│   ├── seeds/
│   │   └── sample_data.sql
│   └── schema/
│       └── full_schema.sql
│
├── tests/                     # Test files
│   ├── EmailTest.php
│   ├── TicketTest.php
│   └── OrderTest.php
│
├── storage/                   # Writable storage
│   ├── logs/
│   │   ├── app.log
│   │   ├── error.log
│   │   └── sla.log
│   ├── cache/
│   └── sessions/
│
├── scripts/                   # Utility scripts
│   ├── setup/
│   │   ├── install.sh
│   │   └── configure.sh
│   └── deploy/
│       └── deploy.sh
│
├── docs/                      # Documentation
│   ├── INSTALLATION.md
│   ├── API.md
│   ├── DEPLOYMENT.md
│   └── TESTING.md
│
├── docker/                    # Docker files
│   ├── nginx/
│   │   ├── Dockerfile
│   │   └── nginx.conf
│   ├── php/
│   │   ├── Dockerfile
│   │   └── php.ini
│   └── docker-compose.yml
│
├── .env.example              # Environment template
├── .gitignore
├── composer.json             # PHP dependencies (optional)
├── README.md
└── LICENSE
```

---

## 🎯 Benefits of This Structure

### **1. Clear Separation**
- **public/** = Only web-accessible files
- **app/** = Application code (protected)
- **api/** = API endpoints
- **storage/** = Writable data

### **2. Security**
- Most files NOT accessible via web
- Only public/ exposed to nginx
- Sensitive config outside web root

### **3. Maintainability**
- Easy to find specific files
- Logical grouping
- Follows MVC pattern
- Scalable for growth

### **4. Docker-Friendly**
- Clean volume mounts
- Easy to containerize
- Proper permissions

### **5. Modern Standards**
- Follows PHP best practices
- Similar to Laravel/Symfony
- Easy for other developers

---

## 📊 File Mapping (Current → New)

### **Current Root Files → New Locations**

```
Current                          →  New Location
─────────────────────────────────────────────────────────────
index.php                        →  public/index.php
login.php                        →  app/views/auth/login.php
products.php                     →  app/views/products/list.php
services.php                     →  app/views/services/list.php
cart.php                         →  app/views/orders/cart.php
checkout.php                     →  app/views/orders/checkout.php
tickets.php                      →  app/views/tickets/list.php
ticket-view.php                  →  app/views/tickets/view.php
create-ticket.php                →  app/views/tickets/create.php
support.php                      →  app/views/support/index.php
chat.php                         →  app/views/chat/index.php
my-messages.php                  →  app/views/messages/list.php
order-view.php                   →  app/views/orders/view.php

# Admin Files
admin.php                        →  admin/index.php
dashboard-admin.php              →  admin/dashboard.php
admin-users.php                  →  admin/users.php
admin-order-view.php             →  admin/orders/view.php
admin-ticket-view.php            →  admin/tickets/view.php

# Include Files
inc/config.php                   →  app/config/config.php
inc/db.php                       →  app/config/database.php
inc/functions.php                →  app/helpers/functions.php
inc/email.php                    →  app/services/EmailService.php
inc/nav.php                      →  app/views/layouts/nav.php
inc/osticket.php                 →  app/services/TicketService.php

# API Files
api/chat-ai.php                  →  api/v1/chat.php
api/paypal-*.php                 →  api/webhooks/paypal.php
api/delete-account.php           →  api/v1/account.php

# Database Files
msp_schema.sql                   →  database/schema/full_schema.sql
msp_seed_data.sql               →  database/seeds/sample_data.sql
schema.sql                       →  database/schema/base_schema.sql
database/helpdesk-schema.sql    →  database/schema/helpdesk.sql

# Test Files
test-*.php                       →  tests/
dbtest.php                       →  tests/DatabaseTest.php

# PowerShell Scripts
*.ps1                            →  scripts/windows/ (keep for reference)

# Documentation
*.md                             →  docs/
README-WHATS-BUILT.md           →  docs/FEATURES.md
QUICK_START.md                  →  docs/QUICK_START.md
DEPLOYMENT_CHECKLIST.md         →  docs/DEPLOYMENT.md
```

---

## 🚀 Migration Steps

### **Option 1: Automated Script** (Recommended)

I can create a script that:
1. Creates new directory structure
2. Moves files to correct locations
3. Updates all require/include paths
4. Updates config references
5. Creates .gitignore
6. Preserves original in backup

### **Option 2: Manual Migration**

1. Create new directory structure
2. Move files category by category
3. Update paths manually
4. Test each section
5. Fix broken includes

### **Option 3: Gradual Refactor**

1. Start with new structure
2. Create route files that include old files
3. Gradually refactor one section at a time
4. No immediate breakage

---

## 📝 Additional Improvements

### **1. Add .htaccess** (Security)
```apache
# In root directory
<FilesMatch "\.(env|md|sql|log)$">
    Require all denied
</FilesMatch>
```

### **2. Environment Variables** (.env)
```env
# Database
DB_HOST=mcp-postgres
DB_PORT=5432
DB_NAME=lumira
DB_USER=postgres
DB_PASS=your_password

# Email
SMTP_HOST=localhost
SMTP_PORT=587
SMTP_USER=noreply@lumira.local
SMTP_PASS=your_password

# Site
SITE_URL=http://lumira.local
APP_ENV=production
```

### **3. Autoloading** (Optional)
```php
// composer.json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        }
    }
}
```

### **4. Router** (Optional)
```php
// public/index.php
switch ($_SERVER['REQUEST_URI']) {
    case '/':
        require '../app/views/home.php';
        break;
    case '/tickets':
        require '../app/views/tickets/list.php';
        break;
    // etc...
}
```

---

## ⚙️ nginx Configuration for New Structure

```nginx
server {
    listen 80;
    server_name lumira.local;
    root /var/www/lumira/public;
    index index.php;

    # Only public/ accessible
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP processing
    location ~ \.php$ {
        fastcgi_pass php:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }

    # Assets caching
    location ~* \.(jpg|jpeg|png|gif|css|js|ico)$ {
        expires 7d;
        add_header Cache-Control "public, immutable";
    }
}
```

---

## 🎯 Recommendation

**For Your Situation:**

Since you're migrating to Docker anyway, I recommend:

**Phase 1: Organize Files** ✅
- Use automated script to reorganize
- Fix all paths
- Test functionality

**Phase 2: Dockerize** ✅
- Create Dockerfile with new structure
- nginx points to public/
- Cleaner, more secure

**Phase 3: Deploy** ✅
- Deploy to your homelab
- Integrate with existing services

This gives you a **clean, modern, maintainable** codebase that's ready for production!

---

## 📋 Next Steps

Want me to:

1. **Create reorganization script?** (Automates the file moves)
2. **Show example refactored files?** (How code changes look)
3. **Create Docker setup with new structure?** (Full containerization)
4. **Do it gradually?** (Refactor section by section)

Let me know your preference! 🚀
