# LUMIRA MSP - Managed Service Provider Platform

A comprehensive IT service management platform for managed service providers (MSPs) built with PHP and PostgreSQL.

## Features
### 🎫 Support Ticket System
  - Multi-level ticketing with 9 categories (Hardware, Software, Network, Email, Security, etc.)
  - 5 priority levels (Low, Normal, High, Critical, Emergency)
  - Automatic ticket routing and assignment
  - Email notifications for ticket updates
  - Ticket lifecycle management (New → Open → In Progress → Resolved → Closed)
  - Customer and admin ticket views
  - Full audit trail and ticket history
### 📚 Knowledge Base
  - Searchable documentation library
  - Category-based article organization
  - Article view tracking and analytics
  - Customer self-service support
  - Featured articles section
  - Rich text article content
  - Article versioning support

### 💼 Service Catalog & E-Commerce
  - Dual service types: One-time services and recurring subscriptions
  - Product catalog with detailed descriptions
  - Shopping cart functionality
  - Secure checkout process
  - PayPal integration (sandbox and live modes)
  - Order history and tracking
  - Automatic invoice generation

### 💳 Subscription Management
  - Recurring billing for managed services
  - Multiple subscription tiers (Basic, Standard, Premium, Enterprise)
  - Automatic renewal processing
  - Subscription activation/deactivation
  - Customer subscription portal
  - Billing history and statements

### 👥 User Management & RBAC
  - Role-based access control with 6 user roles:
    - Super Admin (full system access)
    - Admin (administrative functions)
    - Manager (team and project management)
    - Technician (support agent, ticket handling)
    - Client Admin (customer account admin)
    - Client User (end customer)
  - User authentication with secure password hashing
  - Session management
  - Permission-based feature access
  - User activity logging

### 📊 Administrative Dashboard
  - Real-time business metrics and KPIs
  - Order management and processing
  - Ticket queue overview
  - Customer management
  - Revenue tracking
  - Service performance analytics
  - User activity monitoring
  - System health indicators
### 💬 Internal Messaging System
  - User-to-user messaging
  - Message inbox with read/unread status
  - Email-style message threading
  - Message archiving
  - Notification system

### 🔐 Security Features
  - Password hashing with bcrypt
  - CSRF protection on all forms
  - SQL injection prevention (PDO prepared statements)
  - XSS attack prevention (input sanitization)
  - Session hijacking protection
  - Role-based authorization
  - Secure payment processing


## Tech Stack

- **Backend:** PHP 8.3+
- **Database:** PostgreSQL 15+
- **Architecture:** MVC Pattern
- **Payment:** PayPal SDK (Sandbox & Live)
- **Server:** Apache/Nginx + PHP-FPM
## Use Cases

  This platform is perfect for:

  1. **Small to Medium MSPs** - Complete business management system
  2. **IT Consulting Firms** - Client management and service delivery
  3. **Help Desk Operations** - Ticket tracking and customer support
  4. **IT Service Businesses** - Service catalog and billing
  5. **Educational Projects** - Learning enterprise application development
  6. **Portfolio Projects** - Demonstrating full-stack development skills

## Project Structure

```
LUMIRA-Infrastructure-main_reorganized/
├── app/
│   ├── config/          # Configuration files
│   │   ├── config.php   # Main configuration
│   │   ├── database.php # Database connection
│   │   └── email.php    # Email configuration
│   ├── helpers/         # Helper functions
│   │   └── functions.php
│   └── views/           # View templates (MVC)
│       ├── admin/       # Admin dashboard
│       ├── auth/        # Login/Register
│       ├── chat/        # Chat widget
│       ├── kb/          # Knowledge base
│       ├── layouts/     # Shared layouts
│       ├── messages/    # User messages
│       ├── orders/      # Orders & checkout
│       ├── products/    # Product catalog
│       ├── subscription/# Subscriptions
│       ├── support/     # Support center
│       └── tickets/     # Ticket system
├── admin/              # Admin-specific pages
├── api/
│   ├── v1/            # API version 1
│   └── webhooks/      # PayPal webhooks
├── public/            # Web root (point server here)
│   ├── assets/        # CSS, JS, images
│   └── *.php          # Page routers
└── Documentation/     # Project documentation
```

## Installation

### Prerequisites

- PHP 8.3 or higher
- PostgreSQL 15 or higher
- Composer (for dependencies)
- Apache or Nginx web server

### Step 1: Clone Repository

```bash
git clone https://github.com/YOUR_USERNAME/LUMIRA-Infrastructure.git
cd LUMIRA-Infrastructure
```

### Step 2: Database Setup

1. Create PostgreSQL database:
```bash
createdb lumira
```

2. Import schema:
```bash
psql -U postgres -d lumira -f database/schema.sql
psql -U postgres -d lumira -f database/seed.sql
```

### Step 3: Configure Application

1. Copy configuration template:
```bash
cp app/config/config.php.example app/config/config.php
```

2. Edit `app/config/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'lumira');
define('DB_USER', 'postgres');
define('DB_PASS', 'your_password');

define('PAYPAL_CLIENT_ID', 'your_paypal_client_id');
define('PAYPAL_CLIENT_SECRET', 'your_paypal_secret');
define('PAYPAL_MODE', 'sandbox'); // or 'live'
```

### Step 4: Web Server Configuration

#### Apache (.htaccess already included)

Point document root to `public/` directory:
```apache
DocumentRoot /path/to/LUMIRA-Infrastructure/public
```

#### Nginx

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/LUMIRA-Infrastructure/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

### Step 5: Set Permissions

```bash
chmod -R 755 public/
chown -R www-data:www-data public/
```

## Default Credentials

**Admin Account:**
- Email: `admin@lumira.com`
- Password: `Admin@2025!`

**Important:** Change these credentials immediately after first login!

## Usage

### Customer Features

1. **Browse Services**: http://your-domain.com/services.php
2. **Submit Tickets**: http://your-domain.com/support.php
3. **Knowledge Base**: http://your-domain.com/kb.php
4. **View Orders**: http://your-domain.com/order-view.php

### Admin Features

1. **Dashboard**: http://your-domain.com/dashboard-admin.php
2. **User Management**: http://your-domain.com/admin-users.php
3. **Ticket Management**: http://your-domain.com/tickets.php
4. **Order Management**: Access via dashboard

## Database Schema

The application uses 29+ tables including:

- `users` - User accounts with RBAC
- `orders` - Order management
- `services` - Service catalog
- `products` - Product inventory
- `tickets` - Support tickets
- `subscriptions` - Recurring subscriptions
- `kb_articles` - Knowledge base articles
- And more...

## API Endpoints

### PayPal Integration

- **Create Order**: `/api/paypal-create-order.php`
- **Capture Payment**: `/api/paypal-capture-order.php`

## Security Features

- Password hashing (bcrypt)
- CSRF protection
- Session management
- SQL injection prevention (PDO prepared statements)
- XSS protection (input sanitization)
- Role-based access control

## Development

### Running Locally

```bash
# Using PHP built-in server (development only)
cd public
php -S localhost:8080
```

### Database Migrations

Run migrations in order:
```bash
psql -U postgres -d lumira -f database/migrations/001_add_payment_method.sql
```

## Production Deployment

1. **Environment Variables**: Move sensitive data to `.env` file
2. **HTTPS**: Enable SSL/TLS
3. **PayPal Live Mode**: Update PayPal credentials
4. **Database Backups**: Set up automated backups
5. **Error Logging**: Configure proper error handling
6. **Performance**: Enable PHP OPcache

## Troubleshooting

### Common Issues

**Database Connection Failed**
- Check PostgreSQL is running: `systemctl status postgresql`
- Verify credentials in `app/config/config.php`

**White Screen/No Errors**
- Enable error display: `ini_set('display_errors', 1);`
- Check PHP error log

**PayPal Integration Issues**
- Verify API credentials
- Check PayPal mode (sandbox vs live)
- Ensure webhook URLs are accessible

## Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit changes (`git commit -m 'Add AmazingFeature'`)
4. Push to branch (`git push origin feature/AmazingFeature`)
5. Open Pull Request

## License

This project is licensed under the MIT License - see LICENSE file for details.

## Support

For issues and questions:
- Open an issue on GitHub
- Check documentation in `/Documentation` folder

## Acknowledgments

- Built as a capstone project
- PayPal SDK for payment integration
- PostgreSQL for robust data management

---

**Version:** 1.0.0  
**Last Updated:** 2025-12-25
