# hMailServer Setup Guide for LUMIRA

hMailServer has been installed successfully! Now we need to configure it manually through the Administrator GUI.

## Step 1: Open hMailServer Administrator

1. Click Start Menu
2. Search for "hMailServer Administrator"
3. Click to open

## Step 2: First Login

When the connection dialog appears:

- **Server**: localhost
- **Username**: Administrator
- **Password**: (leave blank on first run)
- Click "Connect"

If it asks you to set a password:
- **New Password**: `Admin@2025!`
- **Confirm Password**: `Admin@2025!`
- Click "OK"

## Step 3: Create Domain

1. In the left panel, expand "Welcome to hMailServer"
2. Right-click on "Domains" → "Add domain..."
3. Enter:
   - **Domain name**: `lumira.local`
4. Click "Save"
5. Select the newly created "lumira.local" domain

## Step 4: Create Email Accounts

For each account below, do the following:

1. Select "lumira.local" domain in left panel
2. Click "Accounts" in the main area
3. Click "Add..." button
4. Enter the details
5. Click "Save"

### Account 1: No Reply (for automated emails)
- **Address**: `noreply`
- **Password**: `NoReply@2025!`
- **Max size (MB)**: 100
- **Enabled**: ✓ Checked

### Account 2: Support (customer support)
- **Address**: `support`
- **Password**: `Support@2025!`
- **Max size (MB)**: 500
- **Enabled**: ✓ Checked

### Account 3: Sales
- **Address**: `sales`
- **Password**: `Sales@2025!`
- **Max size (MB)**: 500
- **Enabled**: ✓ Checked

### Account 4: Admin
- **Address**: `admin`
- **Password**: `Admin@2025!`
- **Max size (MB)**: 1000
- **Enabled**: ✓ Checked

## Step 5: Configure SMTP Settings

1. Click "Settings" in the left panel (under Welcome to hMailServer)
2. Expand "Protocols"
3. Click on "SMTP"

**SMTP Settings:**
- Make sure these are enabled:
  - ✓ Enable SMTP
  - Port 25 (for incoming)
  - Port 587 (for submission)

4. Click "Delivery of e-mail" tab:
   - **Local host name**: `lumira.local`
   - **Send all e-mail to external SMTP server** : Leave unchecked for now

5. Click "Save"

## Step 6: Test Configuration

After saving all accounts, you can test by running:

```powershell
cd C:\Users\Administrator\Documents\nginx-1.28.0\nginx-1.28.0\html
.\test-hmailserver.ps1
```

This will send test emails to verify the configuration.

## Step 7: Configure LUMIRA

Once hMailServer is working, run:

```powershell
.\configure-lumira-email.ps1
```

This will update LUMIRA's PHP configuration to use hMailServer for sending emails.

## Troubleshooting

### Can't connect to Administrator
- Make sure hMailServer service is running:
  ```powershell
  Get-Service hMailServer
  ```
- If stopped, start it:
  ```powershell
  Start-Service hMailServer
  ```

### Emails not sending
1. Check hMailServer Administrator → Utilities → Logging
2. Enable "SMTP" logging with level "Debug"
3. Try sending test email again
4. Check the logs for errors

### Port conflicts
- Make sure no other service is using ports 25, 587, 110, or 143
- Check with: `netstat -an | findstr ":25 :587 :110 :143"`

## Summary

You should now have:
- ✓ hMailServer installed and running
- ✓ Domain `lumira.local` created
- ✓ 4 email accounts created:
  - noreply@lumira.local
  - support@lumira.local
  - sales@lumira.local
  - admin@lumira.local

**Next**: Run `.\configure-lumira-email.ps1` to connect LUMIRA to hMailServer!
