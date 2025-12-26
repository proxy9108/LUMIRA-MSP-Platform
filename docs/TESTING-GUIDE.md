# LUMIRA Helpdesk - Testing Guide

## 🧪 Test What's Been Built

Follow these steps to test all the features that are complete.

---

## ✅ **Test 1: Database Setup**

### Verify Tables Created

```sql
-- Connect to database
psql -h 10.0.1.200 -U postgres -d LUMIRA

-- Check tables exist
\dt

-- You should see:
-- departments
-- kb_categories
-- kb_articles
-- sla_policies
-- canned_responses
-- ticket_attachments
-- And many more...

-- View default data
SELECT * FROM departments;
SELECT * FROM sla_policies;
SELECT * FROM canned_responses;
SELECT * FROM kb_categories;
```

**Expected Results:**
- 3 departments (Technical Support, Sales, Billing)
- 6 SLA policies
- 3 canned responses
- 5 KB categories

---

## ✅ **Test 2: Email-to-Ticket System**

### Setup

1. **Update Email Password:**
   ```
   Edit file: cron/process-support-emails.php
   Line 15: $IMAP_PASS = 'PUT_YOUR_PASSWORD_HERE';
   ```

2. **Run Setup Script:**
   ```
   Right-click: cron/SETUP-WINDOWS-TASKS.bat
   Choose: Run as Administrator
   ```

3. **Verify Tasks Created:**
   ```
   schtasks /query /tn "LUMIRA-Email-Processor"
   schtasks /query /tn "LUMIRA-SLA-Monitor"
   ```

### Test Email-to-Ticket

**Test 1: Create New Ticket from Email**

1. Send email to `support@lumira.local`:
   ```
   From: yourtest@example.com
   To: support@lumira.local
   Subject: Test ticket - password issue
   Body:

   Hi, I need help resetting my password. The reset email isn't arriving.
   Please help!
   ```

2. Wait 5 minutes (or run manually):
   ```
   php cron/process-support-emails.php
   ```

3. **Check Results:**
   ```sql
   -- Check if ticket created
   SELECT * FROM tickets WHERE source = 'email' ORDER BY created_at DESC LIMIT 1;

   -- Check user created
   SELECT * FROM users WHERE email = 'yourtest@example.com';

   -- Check email tracking
   SELECT * FROM ticket_email_tracking ORDER BY received_at DESC LIMIT 1;
   ```

4. **Check Logs:**
   ```
   Open: logs/email-processor.log
   Look for: "✓ Ticket created successfully"
   ```

**Test 2: Reply to Existing Ticket**

1. Find ticket number from confirmation email (e.g., TKT-20251024-A3F7E2)

2. Send reply:
   ```
   From: yourtest@example.com
   To: support@lumira.local
   Subject: Re: Your support ticket [TKT-20251024-A3F7E2]
   Body:

   Thank you! I was able to reset my password.
   ```

3. Run processor:
   ```
   php cron/process-support-emails.php
   ```

4. **Check Results:**
   ```sql
   -- Check comment added
   SELECT * FROM ticket_comments WHERE ticket_id = (
       SELECT id FROM tickets WHERE ticket_number = 'TKT-20251024-A3F7E2'
   ) ORDER BY created_at DESC;
   ```

**Test 3: Auto-Detection**

Test these keywords in subject/body:

- **High Priority:** "URGENT help needed" → Should auto-set High priority
- **Categories:**
  - "password reset" → Password/Login category
  - "billing question" → Billing category
  - "order #123" → Order Issue category

---

## ✅ **Test 3: SLA Monitoring**

### Setup Test

1. **Create Test Ticket with SLA:**
   ```sql
   -- Create high-priority ticket
   INSERT INTO tickets (
       ticket_number, requester_id, category_id, priority_id, status_id,
       subject, description, source, created_at, updated_at
   ) VALUES (
       'TKT-TEST-SLA001',
       1,  -- Your user ID
       1,  -- Category
       2,  -- High priority
       1,  -- New status
       'Test SLA monitoring',
       'This is a test ticket to verify SLA monitoring',
       'web',
       NOW() - INTERVAL '3 hours',  -- Created 3 hours ago
       NOW()
   );

   -- Apply SLA (High Priority = 4 hour first response)
   UPDATE tickets SET
       sla_policy_id = (SELECT id FROM sla_policies WHERE priority_id = 2 AND customer_tier = 'standard'),
       first_response_due = NOW() + INTERVAL '1 hour',
       resolution_due = NOW() + INTERVAL '20 hours',
       sla_status = 'on_track'
   WHERE ticket_number = 'TKT-TEST-SLA001';
   ```

2. **Run SLA Monitor:**
   ```
   php cron/check-sla-compliance.php
   ```

3. **Check Logs:**
   ```
   Open: logs/sla-monitor.log
   Look for: Status updates for your ticket
   ```

4. **View SLA Status:**
   ```sql
   SELECT ticket_number, subject, sla_status, first_response_due, resolution_due
   FROM tickets
   WHERE ticket_number = 'TKT-TEST-SLA001';
   ```

### Test SLA Breach

1. **Create Overdue Ticket:**
   ```sql
   INSERT INTO tickets (
       ticket_number, requester_id, category_id, priority_id, status_id,
       subject, description, sla_policy_id, first_response_due, resolution_due,
       created_at, updated_at
   ) VALUES (
       'TKT-TEST-BREACH',
       1,
       1,
       2,
       1,
       'Test SLA breach',
       'This ticket is overdue',
       (SELECT id FROM sla_policies WHERE priority_id = 2 LIMIT 1),
       NOW() - INTERVAL '2 hours',  -- Overdue by 2 hours
       NOW() + INTERVAL '20 hours',
       NOW() - INTERVAL '6 hours',
       NOW()
   );
   ```

2. **Run Monitor:**
   ```
   php cron/check-sla-compliance.php
   ```

3. **Check for Breach:**
   ```sql
   SELECT * FROM sla_breaches ORDER BY created_at DESC LIMIT 1;

   SELECT ticket_number, sla_status FROM tickets WHERE ticket_number = 'TKT-TEST-BREACH';
   ```

**Expected:** Status should be 'breached', entry in sla_breaches table

---

## ✅ **Test 4: Knowledge Base**

### View KB Homepage

1. Visit: `http://10.0.1.100/kb/`

2. **You should see:**
   - 📚 Knowledge Base header
   - Search box
   - Stats (Articles, Views)
   - 5 categories (Getting Started, Account & Billing, etc.)
   - Empty for now (no articles created yet)

### Create Test Articles (SQL)

```sql
-- Get admin user ID
SELECT id FROM users WHERE role_id = (SELECT id FROM app_roles WHERE name = 'Admin') LIMIT 1;

-- Create test article
INSERT INTO kb_articles (
    category_id,
    title,
    slug,
    content,
    excerpt,
    author_id,
    published,
    featured,
    created_at,
    updated_at
) VALUES (
    1,  -- Getting Started category
    'How to Create Your First Order',
    'how-to-create-first-order',
    '<h2>Step 1: Browse Products</h2><p>Visit the Products page...</p><h2>Step 2: Add to Cart</h2><p>Click the "Add to Cart" button...</p>',
    'Learn how to place your first order on LUMIRA in just a few simple steps.',
    1,  -- Your admin user ID
    TRUE,  -- Published
    TRUE,  -- Featured
    NOW(),
    NOW()
);

-- Create another article
INSERT INTO kb_articles (
    category_id, title, slug, content, excerpt, author_id, published, featured, created_at, updated_at
) VALUES (
    2,  -- Account & Billing
    'How to Update Your Password',
    'how-to-update-password',
    '<h2>Changing Your Password</h2><p>1. Go to your dashboard<br>2. Click Account Settings<br>3. Enter new password</p>',
    'Keep your account secure by updating your password regularly.',
    1,
    TRUE,
    FALSE,
    NOW(),
    NOW()
);
```

3. **Refresh KB Homepage:**
   - Should see 2 articles now
   - Featured article in featured section
   - Recent articles section

---

## ✅ **Test 5: Canned Responses**

### View Default Templates

```sql
SELECT * FROM canned_responses;
```

**You should see 3 templates:**
- `/welcome` - Welcome response
- `/password-reset` - Password reset instructions
- `/resolved` - Ticket resolved message

### Test in Ticket (Manual for now)

The UI isn't built yet, but the database is ready. When you reply to a ticket, you'll be able to select from these templates.

---

## ✅ **Test 6: File Attachments**

### Test Email Attachments

1. Send email with attachment:
   ```
   To: support@lumira.local
   Subject: Screenshot of error
   Body: See attached screenshot
   Attachment: screenshot.png
   ```

2. Run processor:
   ```
   php cron/process-support-emails.php
   ```

3. **Check Results:**
   ```sql
   SELECT * FROM ticket_attachments ORDER BY created_at DESC LIMIT 1;
   ```

4. **Check File Saved:**
   ```
   Look in: uploads/tickets/[ticket_id]/
   ```

---

## ✅ **Test 7: Windows Task Scheduler**

### Verify Tasks Running

```
# View tasks
schtasks /query /tn "LUMIRA-Email-Processor" /v
schtasks /query /tn "LUMIRA-SLA-Monitor" /v

# Check last run time
schtasks /query /tn "LUMIRA-Email-Processor" /fo LIST

# Run manually
schtasks /run /tn "LUMIRA-Email-Processor"
schtasks /run /tn "LUMIRA-SLA-Monitor"
```

### Monitor Logs

```
# Email processor log
tail -f logs/email-processor.log

# SLA monitor log
tail -f logs/sla-monitor.log

# (On Windows, use: Get-Content -Wait)
Get-Content logs\email-processor.log -Wait
```

---

## 🐛 **Common Issues & Fixes**

### Issue: Email processor fails with "Cannot connect to IMAP"

**Fix:**
1. Check MailEnable is running
2. Verify IMAP enabled on port 143
3. Check support@lumira.local mailbox exists
4. Verify password in script

### Issue: No tickets created from email

**Fix:**
1. Check logs: `logs/email-processor.log`
2. Verify email arrived in mailbox
3. Run manually: `php cron/process-support-emails.php`
4. Check for PHP errors

### Issue: SLA not applied to tickets

**Fix:**
1. Check SLA policies exist: `SELECT * FROM sla_policies;`
2. Verify ticket has priority set
3. Run SLA monitor: `php cron/check-sla-compliance.php`

### Issue: Windows tasks not running

**Fix:**
1. Run as Administrator
2. Check Task Scheduler app
3. Verify PHP path correct in batch file
4. Enable task history in Task Scheduler

---

## 📊 **Success Criteria**

Mark each as ✅ when working:

- [ ] Database schema complete (all tables created)
- [ ] Email-to-Ticket creates new tickets
- [ ] Email-to-Ticket threads replies correctly
- [ ] Email attachments saved
- [ ] SLA policies apply to new tickets
- [ ] SLA monitor detects overdue tickets
- [ ] SLA breach alerts sent
- [ ] Windows tasks run automatically
- [ ] KB homepage displays categories
- [ ] Logs show successful processing

---

## 🚀 **Next: Continue Building**

While you test, I'm building:
- KB article view page
- KB search page
- Admin KB management
- Enhanced ticket pages
- Canned responses UI
- Analytics dashboard

Report any issues you find, and I'll fix them immediately! 🛠️
