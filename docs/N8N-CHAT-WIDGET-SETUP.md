# 🎨 n8n Native Chat Widget Integration

## Overview

The **n8n native chat widget** is an official, pre-built chat interface that connects directly to your n8n workflow. It's professionally designed and easier to integrate than building a custom widget.

---

## 🆚 Comparison: Custom vs n8n Native Widget

| Feature | Custom Widget (Current) | n8n Native Widget |
|---------|------------------------|-------------------|
| **Setup Time** | Already done | 5 minutes |
| **Styling** | Fully custom | Configurable theme |
| **Maintenance** | You maintain | n8n maintains |
| **Updates** | Manual | Auto via CDN |
| **Features** | Basic | Rich (typing indicators, etc.) |
| **Integration** | Custom PHP API | Direct to n8n |
| **UI Polish** | Good | Professional |

---

## 🚀 Quick Setup (2 Steps)

### **Step 1: Update Your n8n Workflow**

The n8n native widget expects a simpler response format. Update your workflow:

1. Open your workflow in n8n
2. Find the **"Respond to Webhook"** node
3. Change the response body to:

```json
{
  "output": "text",
  "text": "{{ $json.message }}"
}
```

Or simply return just the message string.

### **Step 2: Replace Chat Widget**

**Option A: Replace Existing Widget**

Edit any page that includes the chat widget (check these files):
- `index.php`
- `products.php`
- `services.php`
- Layout/template files

Find:
```php
<?php require_once 'inc/chat-widget.php'; ?>
```

Replace with:
```php
<?php require_once 'inc/chat-widget-n8n.php'; ?>
```

**Option B: Add to Specific Pages**

Add this to any page where you want the chat:
```php
<?php require_once 'inc/chat-widget-n8n.php'; ?>
```

---

## ⚙️ Configuration

Edit `inc/chat-widget-n8n.php` to customize:

### **1. Update Webhook URL** (REQUIRED)

Find this line:
```javascript
webhookUrl: 'http://localhost:5678/webhook/lumira-chat',
```

Change to your production n8n URL:
```javascript
webhookUrl: 'YOUR_N8N_URL/webhook/lumira-chat',
```

### **2. Customize Appearance**

```javascript
chatWindowOptions: {
    title: 'Your Company Name',
    subtitle: 'Your tagline here',
    welcomeMessage: 'Your welcome message',

    // Size
    width: 400,
    height: 600,

    // Colors
    primaryColor: '#your-color',

    // Position: 'bottom-right', 'bottom-left', 'top-right', 'top-left'
    position: 'bottom-right',

    // Auto-open on page load
    defaultOpen: false,
}
```

### **3. Customize Messages**

```javascript
i18n: {
    en: {
        title: 'Support',
        subtitle: 'We are here to help',
        inputPlaceholder: 'Type here...',
        getStarted: 'Start Chat',
        // And more...
    }
}
```

### **4. Theme Customization**

```javascript
theme: {
    chat: {
        backgroundColor: '#1a1a1a',
        textColor: '#ffffff',
    },
    header: {
        backgroundColor: '#dc143c',
        textColor: '#ffffff',
    },
    message: {
        bot: {
            backgroundColor: '#2a2a2a',
            textColor: '#ffffff',
        },
        user: {
            backgroundColor: '#dc143c',
            textColor: '#ffffff',
        }
    }
}
```

---

## 🎯 n8n Workflow Response Format

The n8n chat widget expects specific response formats:

### **Simple Text Response:**
```json
{
  "output": "text",
  "text": "Hello! How can I help you?"
}
```

### **With Typing Indicator:**
```json
{
  "output": "text",
  "text": "Processing your request...",
  "options": {
    "showTyping": true
  }
}
```

### **With Quick Replies:**
```json
{
  "output": "text",
  "text": "How can I help you?",
  "quickReplies": [
    {
      "text": "View Products",
      "value": "show_products"
    },
    {
      "text": "Get Support",
      "value": "need_support"
    }
  ]
}
```

### **Update Your n8n Workflow:**

In the **"Respond to Webhook"** node, use:

```javascript
{
  output: "text",
  text: $json.message,
  metadata: {
    action: $json.action,
    confidence: $json.confidence,
    ticketCreated: $json.ticket_created || false,
    ticketNumber: $json.ticket_number || null
  }
}
```

---

## 🔧 Advanced Features

### **1. Send User Context to n8n**

The widget already passes logged-in user info. In your n8n workflow, access it:

```javascript
// In Function node
const userId = $input.item.json.metadata?.userId;
const userName = $input.item.json.metadata?.userName;
const userEmail = $input.item.json.metadata?.userEmail;

// Use this to personalize responses or auto-fill ticket info
```

### **2. Pre-fill Messages**

For specific pages, you can pre-fill the chat:

```php
<?php
$initialMessage = null;
if (isset($_GET['support'])) {
    $initialMessage = "I need help with my order";
}
?>

<script type="module">
    import { createChat } from 'https://cdn.jsdelivr.net/npm/@n8n/chat/dist/chat.bundle.es.js';

    createChat({
        webhookUrl: 'YOUR_URL',
        <?php if ($initialMessage): ?>
        initialMessages: ['<?= addslashes($initialMessage) ?>'],
        <?php endif; ?>
    });
</script>
```

### **3. Open Chat Programmatically**

Add a button that opens the chat:

```html
<button onclick="window.n8nChat.open()">Need Help?</button>
```

Or auto-open on certain pages:

```javascript
createChat({
    // ... config
    chatWindowOptions: {
        defaultOpen: true  // Auto-open
    }
});
```

### **4. Track Chat Events**

```javascript
createChat({
    webhookUrl: 'YOUR_URL',

    // Event handlers
    onChatOpen: () => {
        console.log('Chat opened');
        // Track analytics
    },

    onChatClose: () => {
        console.log('Chat closed');
    },

    onMessageSent: (message) => {
        console.log('User sent:', message);
    },

    onMessageReceived: (message) => {
        console.log('Bot replied:', message);
    }
});
```

---

## 🎨 Styling Tips

### **Match Your Site Theme**

The widget is already styled to match LUMIRA's red/black theme. To adjust:

```javascript
theme: {
    chat: {
        backgroundColor: '#your-bg-color',
        textColor: '#your-text-color',
    },
    header: {
        backgroundColor: '#your-brand-color',
        textColor: '#ffffff',
    }
}
```

### **Custom CSS**

Add to `chat-widget-n8n.php`:

```css
/* Make chat button pulse */
#n8n-chat button[class*="launcher"] {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

/* Custom shadows */
#n8n-chat [class*="window"] {
    box-shadow: 0 10px 40px rgba(220, 20, 60, 0.3) !important;
}
```

---

## 🧪 Testing

### **1. Test Basic Connection**

1. Add widget to a page
2. Open page in browser
3. Click chat button
4. Type "test"
5. Should get AI response

### **2. Test with n8n Test URL**

For testing, use test webhook:
```javascript
webhookUrl: 'http://localhost:5678/webhook-test/lumira-chat',
```

### **3. Monitor in n8n**

- Open n8n → Executions
- Every chat message creates an execution
- Click to see data flow

---

## 🔍 Troubleshooting

### **Issue: Chat button not appearing**

**Solution:**
- Check browser console for errors (F12)
- Verify CDN URLs are accessible
- Check if widget file is included: `View Source` → search for "n8n-chat"

### **Issue: Messages not sending**

**Solution:**
- Verify webhook URL is correct
- Check n8n workflow is Active
- Test webhook directly with curl
- Check browser console for CORS errors

### **Issue: Styling looks wrong**

**Solution:**
- Clear browser cache (Ctrl+F5)
- Check if custom CSS conflicts with n8n styles
- Try incognito mode to test

### **Issue: No response from AI**

**Solution:**
- Check n8n Executions for errors
- Verify AI service is responding
- Test individual nodes in workflow
- Check response format matches expected structure

---

## 📊 Comparison with Your Current Setup

### **Keep Custom Widget If:**
- ✅ You need very specific UI requirements
- ✅ You want to store conversations in your database directly
- ✅ You have custom business logic in PHP

### **Use n8n Native Widget If:**
- ✅ You want faster setup
- ✅ You prefer official maintained solution
- ✅ You want professional UI out of the box
- ✅ You want automatic updates
- ✅ You want typing indicators, file uploads, etc.

---

## 🚀 Migration Path

Want to try it without breaking existing setup?

### **Step 1: Test on Single Page**

Create `test-n8n-widget.php`:
```php
<?php
require_once 'inc/config.php';
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>n8n Widget Test</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <h1>Testing n8n Native Widget</h1>
    <?php require_once 'inc/chat-widget-n8n.php'; ?>
</body>
</html>
```

Access: `http://10.0.1.100/test-n8n-widget.php`

### **Step 2: Compare**

Test both versions:
- Current: `http://10.0.1.100/index.php` (custom widget)
- New: `http://10.0.1.100/test-n8n-widget.php` (n8n widget)

### **Step 3: Choose**

Pick the one you prefer and deploy!

---

## 🎉 Ready to Use!

The n8n native widget is:
- ✅ Professionally designed
- ✅ Fully responsive
- ✅ Easy to customize
- ✅ Maintained by n8n team
- ✅ Free and open source

Just update the webhook URL and you're good to go!

---

## 📚 Additional Resources

- **n8n Chat Documentation**: https://docs.n8n.io/integrations/builtin/app-nodes/n8n-nodes-langchain.chatui/
- **Configuration Options**: https://www.npmjs.com/package/@n8n/chat
- **Examples**: https://github.com/n8n-io/chat-examples

---

## 💡 Pro Tips

1. **Start with test webhook URL** for debugging
2. **Monitor n8n executions** to see what data is being sent
3. **Customize colors** to match your brand
4. **Add welcome message** to guide users
5. **Use metadata** to pass user info to workflow
6. **Test on mobile** - widget is responsive

Need help? Check the n8n community forum or the documentation!
