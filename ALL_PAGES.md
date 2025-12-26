# 📋 LUMIRA - All Accessible Pages

## 🏠 Main Application Pages

### Public Pages (No Login Required)
- **/** or **/index.php** - Homepage with features and stats
- **/products.php** - Product catalog (6 products)
- **/services.php** - Services catalog (5 services)
- **/login.php** - Login/Register page
- **/cart.php** - Shopping cart

### Authentication Required Pages
- **/support.php** - Support center (create tickets)
- **/tickets.php** - View all my tickets
- **/ticket-view.php?id=X** - View specific ticket
- **/create-ticket.php** - Create new ticket
- **/chat.php** - Live chat interface
- **/my-messages.php** - User messages inbox
- **/message-view.php?id=X** - View specific message
- **/order-view.php?id=X** - View order details
- **/checkout.php** - Checkout process
- **/subscription-activate.php** - Activate subscription

### Customer Dashboard
- **/dashboard-customer.php** - Customer dashboard
  - Order history
  - Active tickets
  - Account settings

## 🔐 Admin Pages (Admin Role Required)

### Main Admin Area
- **/admin.php** - Admin panel home
- **/dashboard-admin.php** - Admin dashboard with analytics
- **/admin-users.php** - User management
- **/admin-order-view.php** - Order management
- **/admin-ticket-view.php** - Ticket management

### Direct Admin Access
- **/admin-panel/index.php** - Admin panel (via symlink)
- **/admin-panel/users.php** - User management
- **/admin-panel/orders.php** - Orders management
- **/admin-panel/tickets.php** - Tickets management
- **/admin-panel/kb-article-edit.php** - Edit KB articles

## 📚 Knowledge Base Pages

- **/kb.php** - Knowledge base homepage
- **/kb-article.php?id=X** - View KB article
- **/kb-category.php?id=X** - View KB category
- **/kb-search.php?q=term** - Search knowledge base

### Direct KB Access
- **/kb-articles/index.php** - KB home (via symlink)
- **/kb-articles/article.php** - Article view
- **/kb-articles/category.php** - Category view
- **/kb-articles/search.php** - Search page

## 🔌 API Endpoints

### Chat API (v1)
- **/api/v1/chat-chat-ai.php** - AI chat endpoint
- **/api/v1/chat-chat-n8n.php** - N8N chat integration
- **/api/v1/chat-chat.php** - General chat API
- **/api/v1/chat-chat-simple.php** - Simple chat API
- **/api/v1/get-lumira-context.php** - Get chatbot context

### User API
- **/api/v1/delete-account.php** - Delete user account

### PayPal Webhooks
- **/api/webhooks/paypal-capture-order.php** - Capture PayPal order
- **/api/webhooks/paypal-create-order.php** - Create PayPal order
- **/api/webhooks/paypal-redirect-order.php** - Redirect after payment
- **/api/webhooks/paypal-return.php** - Return from PayPal
- **/api/webhooks/paypal-subscribe.php** - PayPal subscription

### Test API Endpoints
- **/api/v1/test-chat-api.php** - Test chat API
- **/api/v1/test-chat.php** - Test chat functionality

## 🧪 Test & Utility Pages

### Database Tests
- **/tests/dbtest.php** - Database connection test

### Email Tests
- **/tests/test-email.php** - Email functionality test
- **/tests/test-email-debug.php** - Email debug test
- **/tests/test-smtp-direct.php** - Direct SMTP test
- **/tests/test-smtp-formats.php** - SMTP format test
- **/tests/test-postmaster.php** - Postmaster test

### Chat Tests
- **/tests/test-chat.php** - Chat functionality test
- **/tests/test-n8n-native-widget.php** - N8N widget test
- **/tests/test-n8n-workflow.php** - N8N workflow test

### Other Tests
- **/tests/test-password.php** - Password hash test
- **/tests/test-paypal-config.php** - PayPal config test
- **/tests/test-php-config.php** - PHP config test
- **/tests/test-updates.php** - Update functionality test

### Admin Utilities
- **/reset-admin-password.php** - Reset admin password

## 🎯 Login Credentials

### Admin Account
- **Email:** admin@lumira.com
- **Password:** Admin@2025!
- **Access:** Full admin privileges

## 📊 Database Status

- ✅ 29 tables created
- ✅ 6 products loaded
- ✅ 5 services loaded
- ✅ 1 admin user
- ✅ Complete MSP schema

## 🚀 Testing Checklist

### Main User Flow
- [ ] Browse products (/products.php)
- [ ] Browse services (/services.php)
- [ ] Add to cart
- [ ] Login/Register
- [ ] View cart
- [ ] Checkout process
- [ ] View orders
- [ ] Create support ticket
- [ ] View tickets
- [ ] Chat with support

### Admin Flow
- [ ] Login as admin
- [ ] View admin dashboard
- [ ] Manage users
- [ ] View/manage orders
- [ ] View/manage tickets
- [ ] Edit KB articles

### API Testing
- [ ] Test chat API
- [ ] Test PayPal integration
- [ ] Test webhooks

## 📁 File Structure

```
public/                    # Web root (nginx points here)
├── *.php                  # 27 router files
├── api/                   # Symlink to ../api
├── admin-panel/           # Symlink to ../admin
├── kb-articles/           # Symlink to ../app/views/kb
├── tests/                 # Symlink to ../tests
└── assets/                # CSS, JS, images
```

All pages are now accessible and working! 🎉
