# 🚀 Your n8n Chatbot Setup - LUMIRA

## ✅ Configuration Complete!

All files have been configured with your n8n Cloud webhook URL.

---

## 📍 Your n8n Cloud Details

**Webhook URL:**
```
https://lumira.app.n8n.cloud/webhook/4f9e9fb8-1464-40da-b4f7-abac2c60ac07/chat
```

**n8n Cloud Dashboard:**
```
https://lumira.app.n8n.cloud
```

---

## 🎯 What's Ready to Use

### ✅ **1. n8n Native Chat Widget**
**File:** `inc/chat-widget-n8n.php`
**Status:** ✅ Configured with your webhook URL
**Usage:** Include this file on any page to add the chat widget

**Test it:**
```
http://10.0.1.100/test-n8n-native-widget.php
```

### ✅ **2. Chat API Backend**
**File:** `api/chat-n8n.php`
**Status:** ✅ Configured with your webhook URL
**Usage:** Backend API that connects to n8n (for custom integrations)

### ✅ **3. Workflow Tester**
**File:** `test-n8n-workflow.php`
**Status:** ✅ Pre-filled with your webhook URL
**Usage:** Test your n8n workflow responses

**Test it:**
```
http://10.0.1.100/test-n8n-workflow.php
```

---

## 🧪 Quick Test (3 Steps)

### Step 1: Test the Native Widget
```
1. Open: http://10.0.1.100/test-n8n-native-widget.php
2. Click the chat button (bottom-right corner)
3. Send a test message: "Hello"
4. You should get a response from your n8n workflow
```

### Step 2: Test Your Workflow
```
1. Open: http://10.0.1.100/test-n8n-workflow.php
2. Click "Send Test Request" (webhook URL already filled)
3. See the full response from n8n
```

### Step 3: Deploy Site-Wide
```
If tests work, add to any page:
<?php require_once 'inc/chat-widget-n8n.php'; ?>
```

---

## 📋 Quick Deploy Checklist

- [ ] Open test page: `http://10.0.1.100/test-n8n-native-widget.php`
- [ ] Click chat button and send "test" message
- [ ] Verify you get a response from n8n
- [ ] Check n8n Cloud dashboard for execution logs
- [ ] Try a support message: "I have a problem"
- [ ] Verify ticket creation (if configured in workflow)
- [ ] Deploy to pages: Add `<?php require_once 'inc/chat-widget-n8n.php'; ?>`

---

## 🎨 Customization

All settings are in: `inc/chat-widget-n8n.php`

**Change colors:**
```javascript
primaryColor: '#dc143c',  // Your brand color
```

**Change position:**
```javascript
position: 'bottom-right',  // or bottom-left, top-right, top-left
```

**Change title:**
```javascript
title: 'LUMIRA Support',
subtitle: 'How can we help you today?',
```

**Auto-open chat:**
```javascript
defaultOpen: true,  // Opens automatically on page load
```

---

## 🔧 n8n Workflow Requirements

Your n8n workflow should return responses in this format:

### Simple Response:
```json
{
  "output": "text",
  "text": "Your AI response message here"
}
```

### OR just return:
```json
{
  "message": "Your AI response message here"
}
```

The widget will automatically format it correctly.

---

## 🎯 Workflow Response Examples

### Normal Chat Response:
```json
{
  "output": "text",
  "text": "Hello! How can I help you today?"
}
```

### With Quick Replies (Buttons):
```json
{
  "output": "text",
  "text": "What would you like to know about?",
  "quickReplies": [
    {
      "text": "Products",
      "value": "show_products"
    },
    {
      "text": "Services",
      "value": "show_services"
    },
    {
      "text": "Support",
      "value": "get_support"
    }
  ]
}
```

### Ticket Creation Response:
```json
{
  "output": "text",
  "text": "I've created support ticket #TKT-12345 for you. Our team will respond within 4 hours.",
  "metadata": {
    "action": "create_ticket",
    "ticketNumber": "TKT-12345"
  }
}
```

---

## 📊 Testing Your Workflow

### Test from Command Line:
```bash
curl -X POST https://lumira.app.n8n.cloud/webhook/4f9e9fb8-1464-40da-b4f7-abac2c60ac07/chat \
  -H "Content-Type: application/json" \
  -d '{"message":"test"}'
```

### Test from PowerShell:
```powershell
Invoke-RestMethod -Uri "https://lumira.app.n8n.cloud/webhook/4f9e9fb8-1464-40da-b4f7-abac2c60ac07/chat" -Method POST -Body '{"message":"test"}' -ContentType "application/json"
```

### Test from Browser Console:
```javascript
fetch('https://lumira.app.n8n.cloud/webhook/4f9e9fb8-1464-40da-b4f7-abac2c60ac07/chat', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify({message: 'test'})
})
.then(r => r.json())
.then(console.log);
```

---

## 🔍 Troubleshooting

### Chat widget not appearing?
1. Clear browser cache (Ctrl+F5)
2. Check browser console (F12) for errors
3. Verify file is included: View Source → search for "n8n-chat"

### No response from chatbot?
1. Check n8n Cloud dashboard for workflow executions
2. Verify workflow is **Active** (toggle on)
3. Test webhook directly with curl command above
4. Check if workflow has any error nodes

### CORS errors?
- n8n Cloud should handle CORS automatically
- If issues persist, check n8n workflow settings

### Slow responses?
- Check your AI provider response time
- Consider adding a timeout in workflow
- Test workflow nodes individually in n8n

---

## 📈 Monitor Your Chatbot

### View Executions in n8n Cloud:
1. Go to: https://lumira.app.n8n.cloud
2. Click **Executions** in left menu
3. See all chat requests and responses
4. Click any execution to debug

### Common Metrics to Watch:
- Response time (should be < 5 seconds)
- Success rate (should be > 95%)
- Error types and frequencies
- Most common user questions

---

## 🚀 Next Steps

### Immediate:
1. ✅ Test the widget on demo page
2. ✅ Send various test messages
3. ✅ Check n8n executions
4. ✅ Deploy to your homepage

### Soon:
- Add more sophisticated AI responses
- Implement ticket auto-creation
- Add quick reply buttons
- Connect to Supabase for analytics
- Add sentiment analysis
- Create escalation rules

### Later:
- Multi-language support
- Voice input/output
- File attachment handling
- Integration with CRM
- Advanced routing rules
- Customer satisfaction surveys

---

## 📞 Support Resources

**n8n Documentation:**
- Chat widget: https://docs.n8n.io/integrations/builtin/app-nodes/n8n-nodes-langchain.chatui/
- Webhooks: https://docs.n8n.io/integrations/builtin/core-nodes/n8n-nodes-base.webhook/

**Your Files:**
- Widget: `inc/chat-widget-n8n.php`
- API: `api/chat-n8n.php`
- Test page: `test-n8n-native-widget.php`
- Workflow tester: `test-n8n-workflow.php`
- Setup guide: `N8N-CHAT-WIDGET-SETUP.md`

---

## 🎉 You're All Set!

Your n8n chatbot is configured and ready to use!

**Quick Start:**
```
1. Visit: http://10.0.1.100/test-n8n-native-widget.php
2. Click the chat button (bottom-right)
3. Send: "Hello"
4. See your n8n AI respond!
```

**Deploy:**
```php
// Add to any page:
<?php require_once 'inc/chat-widget-n8n.php'; ?>
```

**That's it!** 🚀

---

## 📝 Your Webhook URL (Save This!)

```
https://lumira.app.n8n.cloud/webhook/4f9e9fb8-1464-40da-b4f7-abac2c60ac07/chat
```

Keep this URL safe - anyone with it can send messages to your workflow!
