# 🔧 hMailServer + osTicket Integration with LUMIRA

## 📋 Overview

This guide explains how to integrate **hMailServer** (email server) and **osTicket** (ticket system) with your LUMIRA website, sharing the same PostgreSQL database.

### Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    LUMIRA Website (10.0.1.100)              │
│  - Customer orders products/services                        │
│  - Submits support requests                                 │
│  - Views tickets and orders                                 │
└──────────────────┬──────────────────────────────────────────┘
                   │
        ┌──────────┴──────────┬─────────────────┐
        │                     │                 │
        ▼                     ▼                 ▼
┌──────────────┐   ┌────────────────┐   ┌──────────────────┐
│  PostgreSQL  │   │  hMailServer   │   │    osTicket      │
│  Database    │◄──┤  Email Server  │◄──┤  Ticket System   │
│ (10.0.1.200) │   │  - SMTP/IMAP   │   │  - Web UI        │
│              │   │  - Routing     │   │  - Email piping  │
│  Shared Data:│   │  - Delivery    │   │  - Auto-response │
│  - Users     │   └────────────────┘   │  - SLA tracking  │
│  - Tickets   │                        └──────────────────┘
│  - Orders    │
│  - Products  │
└──────────────┘
```

---

## 🎯 Integration Benefits

### Using hMailServer
- ✅ Full control over email server
- ✅ No monthly SMTP costs
- ✅ Unlimited email accounts (support@, sales@, noreply@)
- ✅ Email routing and filtering
- ✅ Anti-spam protection
- ✅ Email backup and archiving
- ✅ Works on Windows Server

### Using osTicket
- ✅ Professional ticket management system
- ✅ Email-to-ticket conversion
- ✅ Customer portal for viewing tickets
- ✅ Agent assignment and workflows
- ✅ SLA management
- ✅ Knowledge base
- ✅ Canned responses
- ✅ Ticket priorities and departments

### Integration with LUMIRA
- ✅ Share same PostgreSQL database
- ✅ Single sign-on for customers
- ✅ Unified user accounts
- ✅ Automatic ticket creation from website
- ✅ Email notifications through hMailServer
- ✅ Order confirmations via email
- ✅ Professional support desk

---

## 📦 Installation Plan

### Phase 1: hMailServer Setup
### Phase 2: osTicket Installation
### Phase 3: Database Integration
### Phase 4: LUMIRA Integration
### Phase 5: Testing & Optimization

---

## Phase 1: hMailServer Installation

### System Requirements
- Windows Server or Windows 10/11
- .NET Framework 4.x
- 500 MB disk space
- Port 25 (SMTP), 110 (POP3), 143 (IMAP), 587 (Submission)

### Installation Steps

#### 1. Download hMailServer
- Download from: https://www.hmailserver.com/download
- Latest version: 5.6.x (free, open source)
- Choose 64-bit installer

#### 2. Install hMailServer

```powershell
# Run installer as Administrator
# Choose components:
# [x] Server
# [x] Administrative tools
# [x] Documentation
# [ ] MySQL (we're using PostgreSQL)

# Installation directory:
C:\Program Files\hMailServer\
```

#### 3. Initial Configuration

**During installation:**
1. Choose database: **Built-in** (hMailServer uses its own DB for mail storage)
2. Set administrator password: `Admin@2025!` (or your choice)
3. Choose default domain: `lumira.local` or your domain

#### 4. Post-Installation Setup

**Open hMailServer Administrator:**

```
Start Menu → hMailServer Administrator
Server: localhost
Username: Administrator
Password: [password you set]
```

### Configure hMailServer

#### 1. Create Domain

```
Settings → Domains → Add
Domain name: lumira.local
```

#### 2. Create Email Accounts

```
Domains → lumira.local → Accounts → Add

Account 1:
- Address: support@lumira.local
- Password: Support@2025!
- Max size: 500 MB
- Enabled: Yes

Account 2:
- Address: noreply@lumira.local
- Password: NoReply@2025!
- Max size: 100 MB
- Enabled: Yes

Account 3:
- Address: sales@lumira.local
- Password: Sales@2025!
- Max size: 500 MB
- Enabled: Yes

Account 4:
- Address: admin@lumira.local
- Password: Admin@2025!
- Max size: 1000 MB
- Enabled: Yes
```

#### 3. Configure SMTP Settings

```
Settings → Protocols → SMTP

General:
- TCP/IP port: 25
- Max number of simultaneous connections: 50
- Max number of connections from single IP: 10

Delivery of e-mail:
- Localhost: 127.0.0.1
- Remote host: [leave default]
- Number of retries: 4
- Minutes between retries: 60
```

#### 4. Enable Submission Port (587)

```
Settings → Advanced → TCP/IP Ports → Add

Port number: 587
Protocol: SMTP
Connection security: None (or STARTTLS if you have certificate)
```

#### 5. Configure Anti-Spam (Optional but recommended)

```
Settings → Anti-spam

Enable:
- SPF verification
- DKIM verification
- Greylisting
- DNS blacklists (Spamhaus, SpamCop)
```

#### 6. Set Up Routing

```
Settings → Protocols → SMTP → Routes → Add

Domain name: *
Target SMTP host: [Your ISP's SMTP server or relay]
TCP/IP port: 25
Server requires authentication: [Yes if relay requires]
```

### Test hMailServer

**Test 1: Send Test Email**

```powershell
# Use telnet or PowerShell
$smtp = "localhost"
$from = "noreply@lumira.local"
$to = "your-test-email@gmail.com"
$subject = "hMailServer Test"
$body = "This is a test email from hMailServer"

Send-MailMessage -SmtpServer $smtp -Port 587 -From $from -To $to -Subject $subject -Body $body
```

**Test 2: Check Logs**

```
hMailServer Administrator → Utilities → Logging → Application
```

---

## Phase 2: osTicket Installation

### System Requirements
- Web server: Nginx (already installed) or Apache
- PHP 7.4+ (check current version)
- PostgreSQL 10+ (already installed at 10.0.1.200)
- 200 MB disk space

### Check PHP Version

```powershell
cd C:\Users\Administrator\Documents\nginx-1.28.0\nginx-1.28.0\php
.\php.exe -v
```

### Install Required PHP Extensions

Check if these are enabled in `php.ini`:

```ini
extension=pgsql
extension=pdo_pgsql
extension=mbstring
extension=intl
extension=imap
extension=fileinfo
extension=gd
extension=curl
extension=zip
```

**Edit PHP config:**
```powershell
notepad "C:\Users\Administrator\Documents\nginx-1.28.0\nginx-1.28.0\php\php.ini"
```

### Download osTicket

1. Go to: https://osticket.com/download/
2. Download latest version (v1.18.x)
3. Extract to: `C:\Users\Administrator\Documents\nginx-1.28.0\nginx-1.28.0\html\osticket`

```powershell
# Download and extract
cd C:\Users\Administrator\Documents\nginx-1.28.0\nginx-1.28.0\html
# Extract osTicket zip here, rename folder to 'osticket'
```

### Create osTicket Database

```powershell
# Connect to PostgreSQL
PGPASSWORD='StrongPassword123' "C:\Program Files\PostgreSQL\18\bin\psql.exe" -h 10.0.1.200 -U postgres -d postgres

# Create database
CREATE DATABASE osticket_db;

# Create dedicated user
CREATE USER osticket_user WITH PASSWORD 'OsTicket@2025!';

# Grant permissions
GRANT ALL PRIVILEGES ON DATABASE osticket_db TO osticket_user;

# Exit
\q
```

### Configure Nginx for osTicket

Add to your Nginx config:

```nginx
# File: C:\Users\Administrator\Documents\nginx-1.28.0\nginx-1.28.0\conf\nginx.conf

# Add this server block
server {
    listen 80;
    server_name support.lumira.local;

    root C:/Users/Administrator/Documents/nginx-1.28.0/nginx-1.28.0/html/osticket/upload;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass   127.0.0.1:9000;
        fastcgi_index  index.php;
        fastcgi_param  SCRIPT_FILENAME  $document_root$fastcgi_script_name;
        include        fastcgi_params;
    }
}
```

**Reload Nginx:**
```powershell
cd C:\Users\Administrator\Documents\nginx-1.28.0\nginx-1.28.0
.\nginx.exe -s reload
```

### Run osTicket Installer

1. Visit: `http://support.lumira.local/setup/` or `http://10.0.1.100/osticket/upload/setup/`
2. Follow installation wizard:

**Database Settings:**
```
Database Type: PostgreSQL
Hostname: 10.0.1.200
Database Name: osticket_db
Username: osticket_user
Password: OsTicket@2025!
Table Prefix: ost_
```

**Admin Account:**
```
Name: LUMIRA Admin
Email: admin@lumira.local
Username: admin
Password: Admin@2025!
```

**Helpdesk Settings:**
```
Helpdesk Name: LUMIRA Support
Default Email: support@lumira.local
```

3. Click "Install Now"
4. Delete setup folder after installation:

```powershell
Remove-Item -Recurse -Force "C:\Users\Administrator\Documents\nginx-1.28.0\nginx-1.28.0\html\osticket\upload\setup"
```

---

## Phase 3: Database Integration

### Option A: Use osTicket's Database (Separate)

**Pros:**
- Easier to manage
- osTicket schema remains unchanged
- Clear separation of concerns

**Cons:**
- Need to sync users between databases
- Duplicate customer data

### Option B: Shared PostgreSQL Database (Recommended)

**Pros:**
- Single source of truth
- No data duplication
- Unified user accounts

**Cons:**
- More complex setup
- Need to map between schemas

### Implementation: Bridge Tables Approach

Create bridge tables to link LUMIRA and osTicket:

```sql
-- Connect to LUMIRA database
PGPASSWORD='StrongPassword123' "C:\Program Files\PostgreSQL\18\bin\psql.exe" -h 10.0.1.200 -U postgres -d LUMIRA

-- Create link to osTicket tickets
CREATE TABLE osticket_ticket_links (
    id SERIAL PRIMARY KEY,
    lumira_ticket_id INTEGER REFERENCES tickets(id) ON DELETE CASCADE,
    osticket_ticket_id INTEGER,
    osticket_ticket_number VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create link to osTicket users
CREATE TABLE osticket_user_links (
    id SERIAL PRIMARY KEY,
    lumira_user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    osticket_user_id INTEGER,
    osticket_email VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes
CREATE INDEX idx_osticket_links_lumira_ticket ON osticket_ticket_links(lumira_ticket_id);
CREATE INDEX idx_osticket_links_lumira_user ON osticket_user_links(lumira_user_id);
```

---

## Phase 4: LUMIRA Integration

### Update LUMIRA Config

Add osTicket API settings to `inc/config.php`:

```php
// osTicket Integration
define('OSTICKET_URL', 'http://10.0.1.100/osticket/upload');
define('OSTICKET_API_KEY', 'YOUR_API_KEY_HERE'); // Get from osTicket admin panel
define('OSTICKET_API_ENDPOINT', OSTICKET_URL . '/api/tickets.json');

// hMailServer Integration
define('MAIL_SERVER', '10.0.1.100'); // or localhost if on same machine
define('MAIL_PORT', 587);
define('MAIL_FROM', 'noreply@lumira.local');
define('MAIL_SUPPORT', 'support@lumira.local');
```

### Get osTicket API Key

1. Login to osTicket admin panel: `http://10.0.1.100/osticket/upload/scp/`
2. Go to: **Admin Panel → Manage → API Keys**
3. Click "Add New API Key"
4. Settings:
   - IP Address: `10.0.1.100` (or `0.0.0.0` for testing)
   - Can create tickets: ✅ Yes
5. Copy the generated API key
6. Paste into `inc/config.php`

### Create osTicket Integration File

I'll create a new file to handle osTicket API integration:

