# 📧 LUMIRA Email Service Setup Guide

## Overview

Your LUMIRA website now has email notifications configured for:
- ✅ Order confirmations
- ✅ Service request/ticket confirmations
- ✅ Ticket updates

## Current Configuration

All email settings are in: `inc/config.php`

```php
// Email configuration (SMTP)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_FROM_EMAIL', 'noreply@lumira.local');
define('SMTP_FROM_NAME', 'LUMIRA Support');
define('SMTP_ENCRYPTION', 'tls');
```

## Setup Options

### Option 1: Gmail SMTP (Recommended for Testing)

#### Step 1: Enable 2-Factor Authentication
1. Go to your Google Account: https://myaccount.google.com/
2. Click "Security" → "2-Step Verification"
3. Enable 2-Factor Authentication

#### Step 2: Generate App Password
1. Go to: https://myaccount.google.com/apppasswords
2. Select "Mail" and your device
3. Click "Generate"
4. Copy the 16-character password

#### Step 3: Update Configuration
Edit `inc/config.php`:

```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'youremail@gmail.com');  // Your Gmail address
define('SMTP_PASSWORD', 'xxxx xxxx xxxx xxxx');  // App password from step 2
define('SMTP_FROM_EMAIL', 'youremail@gmail.com');
define('SMTP_FROM_NAME', 'LUMIRA Support');
define('SMTP_ENCRYPTION', 'tls');
```

### Option 2: Microsoft 365 / Outlook.com

```php
define('SMTP_HOST', 'smtp-mail.outlook.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@outlook.com');
define('SMTP_PASSWORD', 'your-password');
define('SMTP_FROM_EMAIL', 'your-email@outlook.com');
define('SMTP_FROM_NAME', 'LUMIRA Support');
define('SMTP_ENCRYPTION', 'tls');
```

### Option 3: Custom SMTP Server

If you have your own mail server:

```php
define('SMTP_HOST', 'mail.yourdomain.com');
define('SMTP_PORT', 587);  // or 465 for SSL
define('SMTP_USERNAME', 'support@yourdomain.com');
define('SMTP_PASSWORD', 'your-password');
define('SMTP_FROM_EMAIL', 'noreply@yourdomain.com');
define('SMTP_FROM_NAME', 'LUMIRA Support');
define('SMTP_ENCRYPTION', 'tls');  // or 'ssl'
```

### Option 4: Local Development (No SMTP Required)

For testing without SMTP, emails will be logged to PHP error log but not actually sent.
The website will still function normally.

## Upgrading to PHPMailer (Recommended for Production)

The current implementation uses PHP's built-in `mail()` function. For production, install PHPMailer:

### Installation via Composer

```bash
cd C:\Users\Administrator\Documents\nginx-1.28.0\nginx-1.28.0\html
composer require phpmailer/phpmailer
```

### Update inc/email.php

Replace the `send_email()` function with:

```php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

function send_email($to, $subject, $message, $from_email = null, $from_name = null) {
    $from_email = $from_email ?? SMTP_FROM_EMAIL;
    $from_name = $from_name ?? SMTP_FROM_NAME;

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($to);
        $mail->addReplyTo(SITE_EMAIL, SITE_NAME);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = strip_tags($message);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email failed: {$mail->ErrorInfo}");
        return false;
    }
}
```

## Email Templates

All emails use responsive HTML templates with LUMIRA branding:
- Red & black color scheme
- Professional layout
- Mobile responsive
- Automatic footer with site links

### Customizing Email Templates

Edit `inc/email.php` to customize:
- `get_email_template()` - Overall email design
- `send_order_confirmation()` - Order email content
- `send_ticket_confirmation()` - Ticket confirmation content
- `send_ticket_update()` - Ticket update content

## Testing Email Configuration

### Create Test Script

Create `test-email.php` in your html folder:

```php
<?php
require_once 'inc/config.php';
require_once 'inc/email.php';

// Test basic email
$test_email = 'your-test-email@example.com';  // Change this!
$result = send_email(
    $test_email,
    'LUMIRA Email Test',
    get_email_template([
        'title' => 'Email Test',
        'body' => '<p>This is a test email from LUMIRA.</p><p>If you received this, your email configuration is working!</p>'
    ])
);

if ($result) {
    echo "✅ Email sent successfully! Check $test_email";
} else {
    echo "❌ Email failed to send. Check error logs.";
}
```

Visit: `http://10.0.1.100/test-email.php`

### Check PHP Error Logs

Windows PHP error log location (typically):
- `C:\php\logs\php_error.log`
- Check `php.ini` for `error_log` directive

## Troubleshooting

### Emails Not Sending

1. **Check SMTP credentials** - Verify username/password in config
2. **Check firewall** - Ensure port 587 (or 465) is not blocked
3. **Check error logs** - Look for error messages
4. **Test SMTP connection**:

```bash
telnet smtp.gmail.com 587
```

### Gmail "Less Secure Apps" Error

- Use App Passwords (see Option 1 above)
- Don't use "Allow less secure apps" - it's deprecated

### Emails Going to Spam

1. **Set up SPF record** for your domain
2. **Set up DKIM** for your domain
3. **Use a verified sender address**
4. **Test spam score**: https://www.mail-tester.com/

## Email Notifications Reference

### When Emails Are Sent

| Event | Email Sent To | Template |
|-------|--------------|----------|
| Order placed | Customer | Order confirmation with items & total |
| Service request submitted | Customer | Ticket confirmation with ticket # |
| Ticket updated by admin | Customer | Ticket update notification |

### Email Variables

**Order Confirmation:**
- Order number
- Customer name & address
- Itemized products with prices
- Total amount

**Ticket Confirmation:**
- Ticket number
- Service requested
- Status & priority
- Customer's request details

## Security Notes

⚠️ **IMPORTANT:**
- Never commit `inc/config.php` with real passwords to version control
- Use environment variables for production
- Keep SMTP credentials secure
- Consider using app-specific passwords

## Production Recommendations

1. ✅ Use PHPMailer instead of mail()
2. ✅ Use dedicated SMTP service (SendGrid, Mailgun, AWS SES)
3. ✅ Set up proper DNS records (SPF, DKIM, DMARC)
4. ✅ Monitor email delivery rates
5. ✅ Implement email queue for high volume
6. ✅ Add unsubscribe functionality for marketing emails

## Support

If you need help with email configuration:
- Check PHP error logs
- Verify SMTP credentials
- Test with simple SMTP test script
- Contact your hosting provider for SMTP details
