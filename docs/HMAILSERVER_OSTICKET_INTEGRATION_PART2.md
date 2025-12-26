# 🔧 hMailServer + osTicket Integration - Part 2

## Phase 5: Email Routing Configuration

### Configure osTicket Email Fetching

osTicket can automatically convert emails to tickets by fetching from an email account.

#### 1. Set Up Email Fetching in osTicket

**Login to osTicket Admin:**
- URL: `http://10.0.1.100/osticket/upload/scp/`
- Username: `admin`
- Password: [your admin password]

**Configure Email:**
1. Go to: **Admin Panel → Emails → Emails**
2. Click "Add New Email"
3. Settings:
   ```
   Email Address: support@lumira.local
   Name: LUMIRA Support

   SMTP Settings:
   - SMTP Host: 10.0.1.100 (or localhost)
   - SMTP Port: 587
   - Authentication Required: Yes
   - Username: support@lumira.local
   - Password: Support@2025!

   Mail Fetching (IMAP):
   - Enable: Yes
   - Host: 10.0.1.100
   - Port: 143
   - Protocol: IMAP
   - Username: support@lumira.local
   - Password: Support@2025!
   - Fetch Frequency: Every 5 minutes
   - Mailbox: INBOX
   ```

4. Click "Add Email"

#### 2. Configure Help Topics

Help topics route tickets to the right department:

1. Go to: **Admin Panel → Manage → Help Topics**
2. Default topics exist, but create custom ones:

**Topic 1: Service Request**
```
Topic: Service Request
Status: Active
Department: Support
Auto-response: Yes
Priority: Normal
```

**Topic 2: Order Support**
```
Topic: Order Support
Status: Active
Department: Sales
Auto-response: Yes
Priority: Normal
```

**Topic 3: Technical Support**
```
Topic: Technical Support
Status: Active
Department: Support
Auto-response: Yes
Priority: High
```

#### 3. Configure Email Templates

Customize auto-response emails:

1. Go to: **Admin Panel → Manage → Email Templates**
2. Edit "New Ticket Auto-response":

```
Subject: [LUMIRA Support] Ticket #{ticket.number} - {ticket.subject}

Body:
Dear {ticket.name},

Thank you for contacting LUMIRA Support. Your request has been received and assigned ticket number #{ticket.number}.

Ticket Details:
- Ticket Number: {ticket.number}
- Subject: {ticket.subject}
- Status: {ticket.status}
- Priority: {ticket.priority}

You can check your ticket status at any time:
{ticket.client_link}

Or login to our customer portal:
http://10.0.1.100/tickets.php

Our support team will respond within 24 hours.

Best regards,
LUMIRA Support Team
{site.url}
```

### Configure hMailServer Email Routing

#### Set Up Email Aliases

In hMailServer, create aliases to route emails:

```
hMailServer Administrator → Domains → lumira.local → Aliases → Add

Alias 1:
- Name: sales@lumira.local
- Enabled: Yes
- Forward to: support@lumira.local

Alias 2:
- Name: info@lumira.local
- Enabled: Yes
- Forward to: support@lumira.local
```

All emails will be routed to `support@lumira.local` which osTicket monitors.

#### Configure Email Rules (Optional)

Auto-sort emails by keyword:

```
hMailServer → Settings → Rules → Add

Rule 1: Auto-tag service requests
- Name: Service Request Tagging
- Enabled: Yes
- Criteria: Subject contains "service" OR "request"
- Actions: Set header "X-OSTicket-Topic: Service Request"
```

---

## Phase 6: Update LUMIRA to Use Both Systems

### Update Configuration

Edit `inc/config.php`:

```php
<?php
// ... existing config ...

// osTicket Integration
define('OSTICKET_ENABLED', true);  // Set to false to disable
define('OSTICKET_URL', 'http://10.0.1.100/osticket/upload');
define('OSTICKET_API_KEY', 'YOUR_API_KEY_HERE'); // Get from osTicket admin
define('OSTICKET_API_ENDPOINT', OSTICKET_URL . '/api/tickets.json');

// hMailServer Integration
define('SMTP_HOST', '10.0.1.100');  // hMailServer address
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'noreply@lumira.local');
define('SMTP_PASSWORD', 'NoReply@2025!');
define('SMTP_FROM_EMAIL', 'noreply@lumira.local');
define('SMTP_FROM_NAME', 'LUMIRA');
define('SMTP_ENCRYPTION', 'tls');
define('MAIL_SUPPORT', 'support@lumira.local');
```

### Create Bridge Database Tables

Run this SQL to create the linking tables:

```sql
-- Connect to database
PGPASSWORD='StrongPassword123' "C:\Program Files\PostgreSQL\18\bin\psql.exe" -h 10.0.1.200 -U postgres -d LUMIRA

-- Execute the table creation
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

### Update services.php to Use osTicket

Replace the service request handling in `services.php`:

```php
<?php
// At the top, add:
require_once 'inc/osticket.php';
require_once 'inc/services-osticket.php';

// Replace the service request form submission handler with:
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_service'])) {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        // Use the new osTicket-integrated handler
        $result = handle_service_request_osticket($_POST);

        if ($result['success']) {
            $message = $result['message'];
            $_POST = []; // Clear form
        } else {
            $error = $result['message'];
        }
    }
}
?>
```

---

## Phase 7: Testing

### Testing Checklist

#### 1. Test hMailServer

**Test Internal Email:**
```powershell
# Send test email
$smtp = "10.0.1.100"
$from = "noreply@lumira.local"
$to = "support@lumira.local"
$subject = "Test Email"
$body = "This is a test email."

$credential = New-Object System.Management.Automation.PSCredential("noreply@lumira.local", (ConvertTo-SecureString "NoReply@2025!" -AsPlainText -Force))

Send-MailMessage -SmtpServer $smtp -Port 587 -From $from -To $to -Subject $subject -Body $body -Credential $credential
```

**Expected Result:** ✅ Email appears in support@lumira.local inbox

**Test External Email:**
- Send email from external account (Gmail, etc.) to support@lumira.local
- Check if received

#### 2. Test osTicket

**Test Manual Ticket Creation:**
1. Go to: `http://10.0.1.100/osticket/upload/`
2. Click "Open a New Ticket"
3. Fill out form
4. Submit

**Expected Result:** ✅ Ticket created, auto-response received

**Test Email-to-Ticket:**
1. Send email to support@lumira.local with subject "Test Ticket"
2. Wait 5 minutes (osTicket fetch interval)
3. Check osTicket admin panel

**Expected Result:** ✅ Ticket created from email

#### 3. Test LUMIRA Integration

**Test Service Request:**
1. Go to: `http://10.0.1.100/services.php`
2. Click "Request Service"
3. Fill out form:
   - Service: Any service
   - Name: Test Customer
   - Email: your-test@email.com
   - Phone: 555-1234
   - Subject: Test Integration
   - Details: Testing LUMIRA → osTicket integration
4. Submit

**Expected Results:**
- ✅ LUMIRA ticket created (TKT-YYYYMMDD-XXXXXX)
- ✅ osTicket ticket created (ticket number shown)
- ✅ Email confirmation received with both ticket numbers
- ✅ Ticket visible in LUMIRA "My Tickets"
- ✅ Ticket visible in osTicket portal

**Verify Database:**
```sql
-- Check link was created
SELECT * FROM osticket_ticket_links ORDER BY created_at DESC LIMIT 1;
```

#### 4. Test Order Confirmation

**Test Order Process:**
1. Add product to cart
2. Checkout with email: your-test@email.com
3. Complete order

**Expected Results:**
- ✅ Order confirmation email received via hMailServer
- ✅ Email contains order number and details
- ✅ Professional HTML formatting

---

## Phase 8: Customer Workflow

### Customer Experience

**Service Request Flow:**
1. Customer visits LUMIRA website
2. Goes to Services page
3. Requests a service
4. Receives instant confirmation with:
   - LUMIRA ticket number
   - osTicket ticket number
   - Link to osTicket portal
5. Can track ticket in TWO places:
   - LUMIRA website: `http://10.0.1.100/tickets.php`
   - osTicket portal: Click link in email

**Order Flow:**
1. Customer shops on LUMIRA
2. Adds products to cart
3. Completes checkout
4. Receives order confirmation email
5. Email sent via hMailServer (professional, reliable delivery)

### Support Staff Workflow

**Ticket Management:**
1. Staff receives email notification (hMailServer)
2. Opens osTicket admin panel
3. Sees all tickets in one place
4. Assigns ticket to agent
5. Updates status/priority
6. Adds internal notes
7. Responds to customer
8. Customer receives update via email (hMailServer)
9. Ticket updates sync back to LUMIRA database

---

## Troubleshooting

### hMailServer Issues

**Problem: Cannot send emails**
```
Solution:
1. Check Windows Firewall allows ports 25, 587
2. Verify hMailServer service is running:
   Services → hMailServer → Status: Running
3. Check SMTP logs:
   hMailServer Admin → Utilities → Logging
4. Test with telnet:
   telnet 10.0.1.100 587
```

**Problem: Emails go to spam**
```
Solution:
1. Set up SPF record for your domain
2. Configure DKIM signing in hMailServer
3. Use proper from addresses
4. Don't use words like "free", "urgent" in subject
```

### osTicket Issues

**Problem: osTicket not fetching emails**
```
Solution:
1. Check email fetch settings in osTicket admin
2. Verify IMAP credentials are correct
3. Test IMAP connection:
   telnet 10.0.1.100 143
4. Check osTicket cron job is running
5. Enable debug mode in osTicket
```

**Problem: API not working**
```
Solution:
1. Verify API key is correct in inc/config.php
2. Check IP address is whitelisted in osTicket
3. Test API with curl:
   curl -X POST http://10.0.1.100/osticket/upload/api/tickets.json \
        -H "X-API-Key: YOUR_KEY" \
        -H "Content-Type: application/json" \
        -d '{"name":"Test","email":"test@test.com","subject":"Test","message":"Test"}'
```

### Integration Issues

**Problem: Tickets created in LUMIRA but not osTicket**
```
Solution:
1. Check OSTICKET_ENABLED is true in config
2. Verify API key is configured
3. Check PHP error logs
4. Enable osTicket API debugging
5. Check osticket_ticket_links table for entries
```

**Problem: Emails not being sent**
```
Solution:
1. Check hMailServer is running
2. Verify SMTP credentials in inc/config.php
3. Test with simple PHP mail script
4. Check PHP error logs
5. Verify hMailServer accepts connections on port 587
```

---

## Security Considerations

### Secure hMailServer

1. **Enable SSL/TLS:**
   ```
   - Get SSL certificate (Let's Encrypt or commercial)
   - Configure in hMailServer → Settings → Advanced → SSL Certificates
   - Enable on port 465 (SMTPS) and 993 (IMAPS)
   ```

2. **Anti-Spam Protection:**
   ```
   - Enable SPF checking
   - Enable DKIM verification
   - Configure greylisting
   - Use DNS blacklists
   ```

3. **Authentication:**
   ```
   - Require SMTP authentication
   - Use strong passwords
   - Limit connection attempts
   ```

### Secure osTicket

1. **Change Default Paths:**
   ```
   Rename /scp/ to something less obvious
   Rename /upload/ to something unique
   ```

2. **SSL Certificate:**
   ```
   Configure Nginx with SSL for HTTPS
   Force HTTPS for admin panel
   ```

3. **File Permissions:**
   ```
   Set proper file permissions on osTicket files
   Remove write access after installation
   ```

4. **Database Security:**
   ```
   Use dedicated database user with minimal privileges
   Strong database password
   Limit database connections to localhost
   ```

---

## Monitoring & Maintenance

### Monitor hMailServer

**Check Daily:**
- Email queue length
- Delivery failures
- Spam detection rates
- Disk space usage

**Weekly:**
- Review logs for errors
- Check email account sizes
- Verify backups are working
- Update spam filters

### Monitor osTicket

**Check Daily:**
- Ticket response times
- Unassigned tickets
- Overdue tickets
- Email fetching status

**Weekly:**
- Review ticket metrics
- Check SLA compliance
- Update knowledge base
- Train staff on new features

### Backup Strategy

**hMailServer:**
```powershell
# Backup script
$date = Get-Date -Format "yyyyMMdd"
$backupPath = "C:\Backups\hMailServer\$date"
New-Item -ItemType Directory -Path $backupPath -Force

# Backup data directory
Copy-Item "C:\Program Files\hMailServer\Data\*" -Destination $backupPath -Recurse
```

**osTicket:**
```powershell
# Backup database
PGPASSWORD='OsTicket@2025!' "C:\Program Files\PostgreSQL\18\bin\pg_dump.exe" -h 10.0.1.200 -U osticket_user osticket_db > "C:\Backups\osticket_$(Get-Date -Format 'yyyyMMdd').sql"

# Backup files
Copy-Item "C:\Users\Administrator\Documents\nginx-1.28.0\nginx-1.28.0\html\osticket\*" -Destination "C:\Backups\osticket_files_$(Get-Date -Format 'yyyyMMdd')" -Recurse
```

---

## Performance Optimization

### hMailServer Optimization

```
Settings → Advanced → Auto-ban
- Enable: Yes
- Max failed login attempts: 5
- Ban period: 30 minutes

Settings → Advanced → IP Ranges
- Allow localhost: 127.0.0.1
- Allow local network: 10.0.1.0/24
```

### osTicket Optimization

```
Admin Panel → Settings → System
- Cache enabled: Yes
- Cache backend: Database
- Max file upload: 10 MB
- Cron interval: 5 minutes
```

### Database Optimization

```sql
-- Vacuum and analyze tables weekly
VACUUM ANALYZE tickets;
VACUUM ANALYZE osticket_ticket_links;

-- Add indexes for common queries
CREATE INDEX IF NOT EXISTS idx_tickets_created_at ON tickets(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_tickets_status_id ON tickets(status_id);
```

---

## Summary

You now have a complete integration of:

✅ **hMailServer** - Professional email server
- Sending order confirmations
- Sending ticket notifications
- Receiving support emails
- Full email routing control

✅ **osTicket** - Professional ticket system
- Email-to-ticket conversion
- Advanced ticket management
- Customer portal
- Agent workflows
- SLA tracking

✅ **LUMIRA Integration** - Unified system
- Shared PostgreSQL database
- Single user accounts
- Dual ticket tracking
- Professional email delivery
- Seamless customer experience

**Next Steps:**
1. Install and configure hMailServer
2. Install and configure osTicket
3. Create bridge tables in database
4. Update LUMIRA configuration
5. Test thoroughly
6. Train support staff
7. Go live!

**Documentation:**
- `HMAILSERVER_OSTICKET_INTEGRATION.md` (this file)
- `inc/osticket.php` - osTicket API integration
- `inc/services-osticket.php` - Service request handler
- `inc/email.php` - Email functions (updated for hMailServer)
