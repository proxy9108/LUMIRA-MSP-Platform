# ✅ LUMIRA Deployment Checklist - hMailServer + osTicket Integration

## Prerequisites

- [ ] Windows Server or Windows 10/11
- [ ] Nginx + PHP-CGI running (already installed)
- [ ] PostgreSQL 18 running at 10.0.1.200 (already installed)
- [ ] LUMIRA website working at http://10.0.1.100
- [ ] Network access between 10.0.1.100 and 10.0.1.200
- [ ] Ports open: 25, 80, 110, 143, 587

---

## Phase 1: Install hMailServer (30 minutes)

### Download and Install

- [ ] Download hMailServer from https://www.hmailserver.com/download
- [ ] Run installer as Administrator
- [ ] Choose components: Server + Admin Tools
- [ ] Select built-in database
- [ ] Set administrator password: `Admin@2025!`
- [ ] Set default domain: `lumira.local`

### Configure hMailServer

- [ ] Open hMailServer Administrator
- [ ] Login with password set above
- [ ] Create domain: `lumira.local`
- [ ] Create email accounts:
  - [ ] `support@lumira.local` (password: `Support@2025!`)
  - [ ] `noreply@lumira.local` (password: `NoReply@2025!`)
  - [ ] `sales@lumira.local` (password: `Sales@2025!`)
  - [ ] `admin@lumira.local` (password: `Admin@2025!`)

### Configure SMTP

- [ ] Settings → Protocols → SMTP
  - [ ] Port 25: Enabled
  - [ ] Port 587: Add new TCP/IP port
- [ ] Test: Send test email to external address
- [ ] Verify: Receive test email

**Test Command:**
```powershell
$smtp = "10.0.1.100"
$from = "noreply@lumira.local"
$to = "your-external-email@gmail.com"
$cred = New-Object System.Management.Automation.PSCredential("noreply@lumira.local", (ConvertTo-SecureString "NoReply@2025!" -AsPlainText -Force))
Send-MailMessage -SmtpServer $smtp -Port 587 -From $from -To $to -Subject "Test" -Body "Test email" -Credential $cred
```

---

## Phase 2: Install osTicket (45 minutes)

### Download osTicket

- [ ] Download from https://osticket.com/download/
- [ ] Extract to: `C:\Users\Administrator\Documents\nginx-1.28.0\nginx-1.28.0\html\osticket`

### Check PHP Extensions

- [ ] Edit `php.ini` and enable:
  ```ini
  extension=pgsql
  extension=pdo_pgsql
  extension=mbstring
  extension=intl
  extension=imap
  extension=gd
  extension=fileinfo
  ```
- [ ] Restart PHP-CGI

### Create osTicket Database

```powershell
PGPASSWORD='StrongPassword123' "C:\Program Files\PostgreSQL\18\bin\psql.exe" -h 10.0.1.200 -U postgres -d postgres
```

```sql
CREATE DATABASE osticket_db;
CREATE USER osticket_user WITH PASSWORD 'OsTicket@2025!';
GRANT ALL PRIVILEGES ON DATABASE osticket_db TO osticket_user;
\q
```

- [ ] Database created successfully

### Configure Nginx for osTicket

Add to `nginx.conf`:

```nginx
server {
    listen 80;
    server_name support.lumira.local;
    root C:/Users/Administrator/Documents/nginx-1.28.0/nginx-1.28.0/html/osticket/upload;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

- [ ] Nginx config updated
- [ ] Reload Nginx: `.\nginx.exe -s reload`

### Run osTicket Installer

- [ ] Visit: `http://10.0.1.100/osticket/upload/setup/`
- [ ] Fill in database settings:
  - Host: `10.0.1.200`
  - Database: `osticket_db`
  - Username: `osticket_user`
  - Password: `OsTicket@2025!`
- [ ] Fill in admin account:
  - Name: `LUMIRA Admin`
  - Email: `admin@lumira.local`
  - Username: `admin`
  - Password: `Admin@2025!`
- [ ] Helpdesk name: `LUMIRA Support`
- [ ] Default email: `support@lumira.local`
- [ ] Click "Install Now"
- [ ] Delete setup folder:
  ```powershell
  Remove-Item -Recurse -Force "C:\Users\Administrator\Documents\nginx-1.28.0\nginx-1.28.0\html\osticket\upload\setup"
  ```

### Get osTicket API Key

- [ ] Login to osTicket admin: `http://10.0.1.100/osticket/upload/scp/`
- [ ] Go to: Admin Panel → Manage → API Keys
- [ ] Click "Add New API Key"
- [ ] IP Address: `10.0.1.100` (or `0.0.0.0` for testing)
- [ ] Can create tickets: ✅ Yes
- [ ] Copy API key: `_________________________`

---

## Phase 3: Configure Email Integration (20 minutes)

### Configure osTicket Email Fetching

- [ ] osTicket Admin → Emails → Add New Email
- [ ] Email Address: `support@lumira.local`
- [ ] SMTP Host: `10.0.1.100`
- [ ] SMTP Port: `587`
- [ ] SMTP Username: `support@lumira.local`
- [ ] SMTP Password: `Support@2025!`
- [ ] Enable Mail Fetching: ✅ Yes
- [ ] IMAP Host: `10.0.1.100`
- [ ] IMAP Port: `143`
- [ ] IMAP Username: `support@lumira.local`
- [ ] IMAP Password: `Support@2025!`
- [ ] Fetch frequency: Every 5 minutes
- [ ] Save

### Test Email-to-Ticket

- [ ] Send email to `support@lumira.local`
- [ ] Wait 5 minutes
- [ ] Check osTicket admin for new ticket
- [ ] Verify auto-response received

---

## Phase 4: LUMIRA Integration (15 minutes)

### Create Bridge Tables

```powershell
PGPASSWORD='StrongPassword123' "C:\Program Files\PostgreSQL\18\bin\psql.exe" -h 10.0.1.200 -U postgres -d LUMIRA
```

```sql
CREATE TABLE IF NOT EXISTS osticket_ticket_links (
    id SERIAL PRIMARY KEY,
    lumira_ticket_id INTEGER REFERENCES tickets(id) ON DELETE CASCADE,
    osticket_ticket_id INTEGER,
    osticket_ticket_number VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS osticket_user_links (
    id SERIAL PRIMARY KEY,
    lumira_user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    osticket_user_id INTEGER,
    osticket_email VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_osticket_links_lumira_ticket ON osticket_ticket_links(lumira_ticket_id);
CREATE INDEX IF NOT EXISTS idx_osticket_links_lumira_user ON osticket_user_links(lumira_user_id);

\q
```

- [ ] Tables created successfully

### Update LUMIRA Configuration

Edit `C:\Users\Administrator\Documents\nginx-1.28.0\nginx-1.28.0\html\inc\config.php`:

```php
// osTicket Integration
define('OSTICKET_ENABLED', true);
define('OSTICKET_URL', 'http://10.0.1.100/osticket/upload');
define('OSTICKET_API_KEY', 'PASTE_YOUR_API_KEY_HERE');
define('OSTICKET_API_ENDPOINT', OSTICKET_URL . '/api/tickets.json');

// hMailServer Integration
define('SMTP_HOST', '10.0.1.100');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'noreply@lumira.local');
define('SMTP_PASSWORD', 'NoReply@2025!');
define('SMTP_FROM_EMAIL', 'noreply@lumira.local');
define('SMTP_FROM_NAME', 'LUMIRA');
define('SMTP_ENCRYPTION', 'tls');
define('MAIL_SUPPORT', 'support@lumira.local');
```

- [ ] Configuration updated
- [ ] API key pasted
- [ ] Credentials correct

### Update services.php

Edit the beginning of `services.php` to add these requires:

```php
require_once 'inc/osticket.php';
require_once 'inc/services-osticket.php';
```

Replace the service request handler with:

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_service'])) {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $result = handle_service_request_osticket($_POST);
        if ($result['success']) {
            $message = $result['message'];
            $_POST = [];
        } else {
            $error = $result['message'];
        }
    }
}
```

- [ ] services.php updated

---

## Phase 5: Testing (30 minutes)

### Test 1: Service Request

- [ ] Go to: `http://10.0.1.100/services.php`
- [ ] Click "Request Service"
- [ ] Fill out form:
  - Service: Any
  - Name: Test User
  - Email: your-test@email.com
  - Phone: 555-1234
  - Subject: Test Integration
  - Details: Testing the integration
- [ ] Submit

**Expected:**
- [ ] ✅ Success message with LUMIRA ticket number
- [ ] ✅ Success message with osTicket number
- [ ] ✅ Email received with both ticket numbers
- [ ] ✅ Ticket in LUMIRA "My Tickets"
- [ ] ✅ Ticket in osTicket admin panel

### Test 2: Order Confirmation

- [ ] Add product to cart
- [ ] Checkout with email: your-test@email.com
- [ ] Complete order

**Expected:**
- [ ] ✅ Order confirmation page
- [ ] ✅ Email received via hMailServer
- [ ] ✅ Professional HTML email

### Test 3: Email-to-Ticket

- [ ] Send email to `support@lumira.local`
- [ ] Subject: "Test Email Ticket"
- [ ] Body: "Testing email to ticket conversion"
- [ ] Wait 5 minutes

**Expected:**
- [ ] ✅ Ticket created in osTicket
- [ ] ✅ Auto-response email received

### Test 4: Database Links

```sql
-- Check ticket links
SELECT * FROM osticket_ticket_links ORDER BY created_at DESC LIMIT 5;
```

- [ ] ✅ Links exist for created tickets

---

## Phase 6: Go Live

### Pre-Launch Checks

- [ ] All tests passing
- [ ] Email delivery working
- [ ] osTicket accessible
- [ ] hMailServer running
- [ ] Backups configured
- [ ] SSL certificates (optional but recommended)
- [ ] Firewall rules configured
- [ ] Documentation reviewed

### Launch Tasks

- [ ] Notify team of new system
- [ ] Train support staff on osTicket
- [ ] Update customer communications
- [ ] Monitor for 24 hours
- [ ] Address any issues

### Post-Launch

- [ ] Set up monitoring alerts
- [ ] Configure automated backups
- [ ] Review ticket workflow
- [ ] Optimize as needed

---

## Troubleshooting Quick Reference

### hMailServer Not Sending

```powershell
# Check service
Get-Service hMailServer

# Check ports
netstat -an | findstr "587"

# Test connection
Test-NetConnection -ComputerName 10.0.1.100 -Port 587
```

### osTicket API Failing

```powershell
# Test API
curl -X POST http://10.0.1.100/osticket/upload/api/tickets.json `
     -H "X-API-Key: YOUR_KEY" `
     -H "Content-Type: application/json" `
     -d '{"name":"Test","email":"test@test.com","subject":"Test","message":"Test"}'
```

### Database Connection Issues

```powershell
# Test connection
PGPASSWORD='StrongPassword123' "C:\Program Files\PostgreSQL\18\bin\psql.exe" -h 10.0.1.200 -U postgres -d LUMIRA -c "SELECT 1"
```

---

## Support

**Documentation:**
- `HMAILSERVER_OSTICKET_INTEGRATION.md` - Complete guide part 1
- `HMAILSERVER_OSTICKET_INTEGRATION_PART2.md` - Complete guide part 2
- `UPDATES_SUMMARY.md` - Previous updates summary
- `EMAIL_SETUP_GUIDE.md` - Basic email setup

**Files Created:**
- `inc/osticket.php` - osTicket API integration
- `inc/services-osticket.php` - Service request handler
- `inc/email.php` - Email functions (updated)

---

## Completion

When all checkboxes are ✅, you have:

✅ **Professional Email Server** (hMailServer)
✅ **Advanced Ticket System** (osTicket)
✅ **Integrated LUMIRA Website**
✅ **Unified Customer Experience**
✅ **Professional Support Workflow**

**Congratulations! Your LUMIRA system is fully integrated!** 🎉
