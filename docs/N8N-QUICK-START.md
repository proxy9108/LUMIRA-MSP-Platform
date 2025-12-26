# 🚀 LUMIRA n8n Chatbot - Quick Start

## 📦 What You Got

I've created 3 files for your n8n chatbot integration:

1. **`api/chat-n8n.php`** - New API endpoint that connects to n8n
2. **`n8n-workflow-lumira-chatbot.json`** - Ready-to-import n8n workflow
3. **`N8N-CHATBOT-SETUP-GUIDE.md`** - Complete documentation

---

## ⚡ 5-Minute Setup

### 1. Install n8n (Choose one)

**Docker:**
```bash
docker run -d --name n8n -p 5678:5678 n8nio/n8n
```

**NPM:**
```bash
npm install -g n8n
n8n start
```

### 2. Import Workflow

1. Open: `http://localhost:5678`
2. Click: **Workflows** → **Import from File**
3. Select: `n8n-workflow-lumira-chatbot.json`
4. Click: **Save**

### 3. Get Webhook URL

1. Click the **"Webhook - Receive Chat"** node
2. Copy the **Production URL**
3. Example: `http://localhost:5678/webhook/lumira-chat`

### 4. Configure PHP

Edit `api/chat-n8n.php` line 24:
```php
$N8N_WEBHOOK_URL = 'YOUR_WEBHOOK_URL_HERE';
```

### 5. Update Chat Widget

Edit `inc/chat-widget.php`:

Find:
```javascript
fetch('/api/chat.php', {
```

Change to:
```javascript
fetch('/api/chat-n8n.php', {
```

### 6. Activate & Test

1. In n8n, toggle workflow to **Active** (green)
2. Open your website
3. Click chat widget
4. Send: "I need help with my account"
5. Watch n8n executions tab

---

## 🎯 What It Does

✅ **Auto-creates tickets** when users have problems
✅ **Smart intent detection** (support, sales, questions)
✅ **Uses your product/service data** for accurate answers
✅ **Logs conversations** for analysis
✅ **Assigns tickets** to appropriate categories
✅ **Returns structured data** for better UX

---

## 🔧 AI Service Configuration

The workflow is pre-configured to use your OpenWebUI at:
```
https://139.182.185.119.nip.io
```

**To change AI provider:**

1. Open workflow in n8n
2. Click **"Call AI Service"** node
3. Update URL and credentials

**Popular options:**
- OpenAI: `https://api.openai.com/v1/chat/completions`
- Anthropic Claude: `https://api.anthropic.com/v1/messages`
- Local LLM: `http://localhost:11434` (Ollama)

---

## 📊 How Auto-Ticketing Works

The system creates tickets when it detects these keywords:
- problem
- issue
- error
- broken
- not working
- help
- urgent
- bug
- support

**Example conversations that trigger tickets:**

✅ "My order is not working"
✅ "I have a problem with payment"
✅ "Urgent: Can't access my account"
✅ "Error when trying to login"

❌ "What products do you have?" (just answers)
❌ "Tell me about your services" (just answers)

---

## 🎨 Customization

### Change ticket keywords

Edit "Analyze Intent" node in n8n:
```javascript
const supportKeywords = [
  'problem', 'issue', 'error',
  // Add your keywords here
  'defect', 'malfunction', 'crash'
];
```

### Add category routing

```javascript
if (message.includes('billing')) {
  suggestedCategory = 'Billing';
} else if (message.includes('technical')) {
  suggestedCategory = 'Technical Support';
}
```

### Change AI personality

Edit the system prompt in "Call AI Service" node:
```javascript
{
  role: 'system',
  content: 'You are a friendly and professional assistant...'
}
```

---

## 🧪 Test Commands

**Test in browser console:**
```javascript
fetch('/api/chat-n8n.php', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify({
    message: 'I need help with my order'
  })
}).then(r => r.json()).then(console.log);
```

**Test with curl:**
```bash
curl -X POST http://10.0.1.100/api/chat-n8n.php \
  -H "Content-Type: application/json" \
  -d '{"message":"test"}'
```

**Test n8n directly:**
```bash
curl -X POST http://localhost:5678/webhook/lumira-chat \
  -H "Content-Type: application/json" \
  -d '{"message":"test","session_id":"test123"}'
```

---

## ⚠️ Troubleshooting

### Chat widget shows error
- Check browser console (F12)
- Verify `/api/chat-n8n.php` exists
- Check PHP error log

### n8n not receiving requests
- Is workflow **Active** (green toggle)?
- Correct webhook URL in `chat-n8n.php`?
- Check n8n logs: `docker logs n8n`

### AI not responding
- Test "Call AI Service" node in n8n
- Check API credentials
- Increase timeout if needed

### Tickets not created
- User must be logged in
- Check database connection
- View n8n execution for errors

---

## 📈 Monitor Performance

**View n8n executions:**
1. Click **Executions** in left menu
2. See all chat requests
3. Click any execution to debug

**Check logs:**
```bash
# n8n logs (Docker)
docker logs -f n8n

# PHP logs
tail -f /var/log/php-fpm/error.log

# PostgreSQL logs
tail -f /var/log/postgresql/postgresql-18-main.log
```

---

## 🚀 Next Features to Add

Once basic setup works:

1. **Supabase logging** - Store all conversations
2. **Sentiment analysis** - Detect frustrated customers
3. **Auto-escalation** - Route urgent issues to managers
4. **Email notifications** - Alert staff of new tickets
5. **Chat analytics dashboard** - View conversation insights
6. **Multi-language support** - Detect and respond in user's language

See full documentation in: `N8N-CHATBOT-SETUP-GUIDE.md`

---

## 💡 Pro Tips

1. **Start with Test URL** in n8n (easier to debug)
2. **Check n8n executions** after every test
3. **Use "Execute Node"** in n8n to test individual steps
4. **Keep AI responses short** (500 tokens max for speed)
5. **Log everything** during initial setup
6. **Monitor ticket quality** - adjust keywords as needed

---

## ✅ Success Checklist

- [ ] n8n installed and accessible
- [ ] Workflow imported and Active
- [ ] Webhook URL copied
- [ ] `chat-n8n.php` configured
- [ ] Chat widget updated
- [ ] Test message sent
- [ ] AI response received
- [ ] Ticket auto-created (when using support keywords)
- [ ] Workflow executions visible in n8n

---

## 🎉 You're Done!

Your chatbot now has AI-powered intelligence through n8n!

**What changed:**
- Old: Direct AI → Response
- New: Chat → n8n → AI → Intent Analysis → Auto-ticket → Response

**Benefits:**
- Smarter routing
- Automatic tickets
- Better analytics
- Easier to customize
- Scalable architecture

---

Need help? Check the full guide: **N8N-CHATBOT-SETUP-GUIDE.md**
