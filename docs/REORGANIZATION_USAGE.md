# 🚀 LUMIRA Reorganization - Usage Guide

## 📋 What You Have

Two automated scripts that will reorganize your LUMIRA project:

1. **`reorganize-lumira.sh`** - Bash script that moves all files to new structure
2. **`update-paths.php`** - PHP script that updates all require/include paths

---

## ⚡ Quick Start (3 Steps)

### **Step 1: Preview (Dry Run)**

See what will happen without making changes:

```bash
cd /home/proxyserver/Documents/Capstone/LUMIRA-Infrastructure-main
./reorganize-lumira.sh --dry-run
```

**This will:**
- ✅ Show you all file movements
- ✅ Display new directory structure
- ✅ NOT make any changes
- ✅ Let you review before proceeding

---

### **Step 2: Reorganize Files**

Actually reorganize the project:

```bash
./reorganize-lumira.sh
```

**This will:**
- ✅ Create automatic backup
- ✅ Create new directory structure
- ✅ Move all files to correct locations
- ✅ Create .env.example, .gitignore, README.md
- ✅ Save detailed log file

**Time**: ~30 seconds

**Output**: New folder `LUMIRA-Infrastructure-main_reorganized/`

---

### **Step 3: Update Paths**

Fix all require/include statements:

```bash
php update-paths.php
```

**This will:**
- ✅ Find all PHP files
- ✅ Update require/include paths
- ✅ Show summary of changes
- ✅ Make files work with new structure

**Time**: ~1 minute

---

## 🎯 Complete Process

### **Full Workflow:**

```bash
# 1. Navigate to project
cd /home/proxyserver/Documents/Capstone/LUMIRA-Infrastructure-main

# 2. Preview what will happen (RECOMMENDED)
./reorganize-lumira.sh --dry-run

# 3. Review the output, then run for real
./reorganize-lumira.sh

# 4. Update all file paths
php update-paths.php

# 5. Test the reorganized version
cd LUMIRA-Infrastructure-main_reorganized/
# (Set up web server pointing to public/ directory)

# 6. If everything works, replace old with new
cd ..
rm -rf LUMIRA-Infrastructure-main
mv LUMIRA-Infrastructure-main_reorganized LUMIRA-Infrastructure-main

# Done!
```

---

## 📊 What the Scripts Do

### **reorganize-lumira.sh Details**

**Creates This Structure:**
```
LUMIRA-Infrastructure-main_reorganized/
├── public/              # Web root (nginx points here)
│   ├── index.php
│   └── assets/
├── app/                 # Protected application code
│   ├── config/         # Configuration files
│   ├── views/          # Page templates
│   ├── services/       # Business logic
│   └── helpers/        # Utility functions
├── api/                # API endpoints
├── admin/              # Admin panel
├── cron/               # Background jobs
├── database/           # SQL files
├── storage/            # Logs, cache
├── tests/              # Test files
├── scripts/            # PowerShell scripts
├── docs/               # Documentation
└── docker/             # Docker configs
```

**File Movements:**
- `index.php` → `public/index.php`
- `inc/config.php` → `app/config/config.php`
- `tickets.php` → `app/views/tickets/list.php`
- `admin.php` → `admin/index.php`
- And 80+ more files...

---

### **update-paths.php Details**

**Updates These Patterns:**
```php
// OLD:
require 'inc/config.php';
require_once('inc/db.php');
include 'inc/functions.php';

// NEW:
require '../app/config/config.php';
require_once('../app/config/database.php');
include '../app/helpers/functions.php';
```

**Handles:**
- ✅ require
- ✅ require_once
- ✅ include
- ✅ include_once
- ✅ Single and double quotes
- ✅ With and without parentheses

---

## 🛡️ Safety Features

### **Automatic Backup**

Every run creates timestamped backup:
```
LUMIRA-Infrastructure-main_backup_20231225_143022/
```

**To restore from backup:**
```bash
rm -rf LUMIRA-Infrastructure-main_reorganized
cp -r LUMIRA-Infrastructure-main_backup_TIMESTAMP LUMIRA-Infrastructure-main
```

### **Dry Run Mode**

Test before applying:
```bash
./reorganize-lumira.sh --dry-run
php update-paths.php --dry-run
```

### **Detailed Logging**

Log file saved: `reorganization.log`
```bash
# View the log
cat reorganization.log

# Check for errors
grep ERROR reorganization.log
```

---

## ✅ Verification Checklist

After reorganization, verify:

- [ ] New directory structure created
- [ ] All PHP files moved correctly
- [ ] Paths updated in all files
- [ ] No require/include errors
- [ ] Assets (CSS, JS, images) accessible
- [ ] Database connections work
- [ ] Admin panel loads
- [ ] Login system works
- [ ] Tickets system works
- [ ] API endpoints respond

---

## 🐛 Troubleshooting

### **"Permission denied" error**

```bash
chmod +x reorganize-lumira.sh
```

### **PHP not found**

```bash
# Check PHP is installed
php --version

# If not installed
sudo apt install php-cli php-pgsql
```

### **Script doesn't find files**

Make sure you're in the correct directory:
```bash
cd /home/proxyserver/Documents/Capstone/LUMIRA-Infrastructure-main
pwd  # Should show the LUMIRA directory
```

### **Paths still broken after update**

Some paths may need manual adjustment. Check:
```bash
# Find remaining old paths
grep -r "inc/config.php" LUMIRA-Infrastructure-main_reorganized/
grep -r "inc/db.php" LUMIRA-Infrastructure-main_reorganized/
```

### **Want to start over?**

```bash
# Delete reorganized version
rm -rf LUMIRA-Infrastructure-main_reorganized

# Restore from backup
cp -r LUMIRA-Infrastructure-main_backup_TIMESTAMP/* LUMIRA-Infrastructure-main/

# Run scripts again
./reorganize-lumira.sh
```

---

## 📖 What Happens Next

### **After Reorganization:**

1. **Set up nginx** to point to `public/` directory
2. **Configure .env** file with your settings
3. **Test all functionality**
4. **Create Docker container** (optional)
5. **Deploy to your homelab**

### **nginx Configuration:**

```nginx
server {
    listen 80;
    server_name lumira.local;
    root /path/to/LUMIRA-Infrastructure-main/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass php:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

---

## 💡 Tips & Best Practices

### **Before Running:**
- ✅ Commit current state to git (if using version control)
- ✅ Make sure you have disk space (backup needs ~same size as project)
- ✅ Close any files open in editors
- ✅ Stop web server if running

### **After Running:**
- ✅ Review the log file
- ✅ Test all major features
- ✅ Check error logs
- ✅ Verify file permissions
- ✅ Update documentation

### **For Production:**
- ✅ Test on development/staging first
- ✅ Keep backup until fully verified
- ✅ Update deployment scripts
- ✅ Notify team of new structure

---

## 🎓 Understanding the New Structure

### **Web Root (public/)**

Only this directory is accessible via web browser:
```
http://lumira.local/ → public/index.php
http://lumira.local/assets/css/style.css → public/assets/css/style.css
```

Everything else is PROTECTED (more secure!).

### **Application Code (app/)**

All your PHP application logic:
- **config/** = Settings (database, email, etc.)
- **views/** = HTML templates
- **services/** = Business logic
- **helpers/** = Utility functions

### **API (api/)**

RESTful API endpoints:
- `api/v1/chat.php` → Chat API
- `api/webhooks/paypal.php` → PayPal webhooks

### **Storage (storage/)**

Writable directories for:
- **logs/** = Application logs
- **cache/** = Temporary files
- **sessions/** = PHP sessions

---

## 🚀 Next Steps

Once reorganization is complete:

1. **Read**: [LUMIRA-PROJECT-ANALYSIS.md](LUMIRA-PROJECT-ANALYSIS.md)
2. **Follow**: [REORGANIZATION_PLAN.md](REORGANIZATION_PLAN.md)
3. **Deploy**: Create Docker container (we can do this next!)

---

## ❓ Questions?

- Script errors? Check `reorganization.log`
- Path issues? Run `php update-paths.php` again
- Need help? I'm here to assist!

---

**Ready to proceed?** Run the scripts and let me know how it goes! 🎉
