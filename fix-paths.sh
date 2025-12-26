#!/bin/bash
# Fix all require/include paths to use proper relative paths

echo "Fixing paths in all PHP files..."

# Fix public/index.php - already done manually, but keeping for reference

# Fix all files in app/views/* to use correct relative paths
# From app/views/xyz/*.php, we need ../../../app/config/config.php

# app/views/auth/*.php (2 levels deep)
find app/views/auth -name "*.php" -exec sed -i "s|require_once 'app/config/|require_once __DIR__ . '/../../../app/config/|g" {} \;
find app/views/auth -name "*.php" -exec sed -i "s|require_once 'app/helpers/|require_once __DIR__ . '/../../../app/helpers/|g" {} \;
find app/views/auth -name "*.php" -exec sed -i "s|require_once 'app/services/|require_once __DIR__ . '/../../../app/services/|g" {} \;

# app/views/tickets/*.php (2 levels deep)
find app/views/tickets -name "*.php" -exec sed -i "s|require_once 'app/config/|require_once __DIR__ . '/../../../app/config/|g" {} \;
find app/views/tickets -name "*.php" -exec sed -i "s|require_once 'app/helpers/|require_once __DIR__ . '/../../../app/helpers/|g" {} \;
find app/views/tickets -name "*.php" -exec sed -i "s|require_once 'app/services/|require_once __DIR__ . '/../../../app/services/|g" {} \;

# app/views/orders/*.php (2 levels deep)
find app/views/orders -name "*.php" -exec sed -i "s|require_once 'app/config/|require_once __DIR__ . '/../../../app/config/|g" {} \;
find app/views/orders -name "*.php" -exec sed -i "s|require_once 'app/helpers/|require_once __DIR__ . '/../../../app/helpers/|g" {} \;
find app/views/orders -name "*.php" -exec sed -i "s|require_once 'app/services/|require_once __DIR__ . '/../../../app/services/|g" {} \;

# app/views/products/*.php (2 levels deep)
find app/views/products -name "*.php" -exec sed -i "s|require_once 'app/config/|require_once __DIR__ . '/../../../app/config/|g" {} \;
find app/views/products -name "*.php" -exec sed -i "s|require_once 'app/helpers/|require_once __DIR__ . '/../../../app/helpers/|g" {} \;
find app/views/products -name "*.php" -exec sed -i "s|require_once 'app/services/|require_once __DIR__ . '/../../../app/services/|g" {} \;

# app/views/admin/*.php (2 levels deep)
find app/views/admin -name "*.php" -exec sed -i "s|require_once 'app/config/|require_once __DIR__ . '/../../../app/config/|g" {} \;
find app/views/admin -name "*.php" -exec sed -i "s|require_once 'app/helpers/|require_once __DIR__ . '/../../../app/helpers/|g" {} \;
find app/views/admin -name "*.php" -exec sed -i "s|require_once 'app/services/|require_once __DIR__ . '/../../../app/services/|g" {} \;

# app/views/kb/*.php (2 levels deep)
find app/views/kb -name "*.php" -exec sed -i "s|require_once 'app/config/|require_once __DIR__ . '/../../../app/config/|g" {} \;
find app/views/kb -name "*.php" -exec sed -i "s|require_once 'app/helpers/|require_once __DIR__ . '/../../../app/helpers/|g" {} \;
find app/views/kb -name "*.php" -exec sed -i "s|require_once dirname(__DIR__) . '/inc/config.php'|require_once __DIR__ . '/../../../app/config/config.php'|g" {} \;

# app/views/support/*.php (2 levels deep)
find app/views/support -name "*.php" -exec sed -i "s|require_once 'app/config/|require_once __DIR__ . '/../../../app/config/|g" {} \;
find app/views/support -name "*.php" -exec sed -i "s|require_once 'app/helpers/|require_once __DIR__ . '/../../../app/helpers/|g" {} \;

# app/views/chat/*.php (2 levels deep)
find app/views/chat -name "*.php" -exec sed -i "s|require_once 'app/config/|require_once __DIR__ . '/../../../app/config/|g" {} \;
find app/views/chat -name "*.php" -exec sed -i "s|require_once 'app/helpers/|require_once __DIR__ . '/../../../app/helpers/|g" {} \;

# app/views/messages/*.php (2 levels deep)
find app/views/messages -name "*.php" -exec sed -i "s|require_once 'app/config/|require_once __DIR__ . '/../../../app/config/|g" {} \;
find app/views/messages -name "*.php" -exec sed -i "s|require_once 'app/helpers/|require_once __DIR__ . '/../../../app/helpers/|g" {} \;

# app/views/subscription/*.php (2 levels deep)
find app/views/subscription -name "*.php" -exec sed -i "s|require_once 'app/config/|require_once __DIR__ . '/../../../app/config/|g" {} \;
find app/views/subscription -name "*.php" -exec sed -i "s|require_once 'app/helpers/|require_once __DIR__ . '/../../../app/helpers/|g" {} \;

# app/views/layouts/*.php (2 levels deep)
find app/views/layouts -name "*.php" -exec sed -i "s|require_once 'app/config/|require_once __DIR__ . '/../../../app/config/|g" {} \;
find app/views/layouts -name "*.php" -exec sed -i "s|require_once 'app/helpers/|require_once __DIR__ . '/../../../app/helpers/|g" {} \;

# admin/*.php (1 level deep)
find admin -name "*.php" -exec sed -i "s|require_once 'app/config/|require_once __DIR__ . '/../app/config/|g" {} \;
find admin -name "*.php" -exec sed -i "s|require_once 'app/helpers/|require_once __DIR__ . '/../app/helpers/|g" {} \;
find admin -name "*.php" -exec sed -i "s|require_once 'app/services/|require_once __DIR__ . '/../app/services/|g" {} \;

# api/v1/*.php (2 levels deep)
find api/v1 -name "*.php" -exec sed -i "s|require_once 'app/config/|require_once __DIR__ . '/../../app/config/|g" {} \;
find api/v1 -name "*.php" -exec sed -i "s|require_once 'app/helpers/|require_once __DIR__ . '/../../app/helpers/|g" {} \;

# api/webhooks/*.php (2 levels deep)
find api/webhooks -name "*.php" -exec sed -i "s|require_once 'app/config/|require_once __DIR__ . '/../../app/config/|g" {} \;
find api/webhooks -name "*.php" -exec sed -i "s|require_once 'app/helpers/|require_once __DIR__ . '/../../app/helpers/|g" {} \;

echo "✓ All paths fixed!"
echo "You can now test with: php -S 192.168.40.103:8080"
