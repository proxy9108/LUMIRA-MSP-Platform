# 🤖 LUMIRA n8n Chatbot Setup Guide

## Overview

This guide will help you integrate n8n as the AI backend for your LUMIRA chatbot, enabling:
- Smart conversation routing
- Automatic ticket creation
- AI-powered intent detection
- Conversation logging
- Integration with Supabase (optional)

---

## 📋 Prerequisites

- [ ] n8n installed and running
- [ ] Your LUMIRA website running
- [ ] AI service (OpenWebUI or OpenAI API) accessible
- [ ] PostgreSQL database access

---

## 🚀 Quick Start (5 Steps)

### Step 1: Install n8n

**Option A: Docker (Recommended)**
```bash
docker run -d --name n8n \
  -p 5678:5678 \
  -v n8n_data:/home/node/.n8n \
  n8nio/n8n
```

**Option B: NPM**
```bash
npm install -g n8n
n8n start
```

Access n8n at: `http://localhost:5678`

### Step 2: Import the Workflow

1. Open n8n at `http://localhost:5678`
2. Click **"Workflows"** → **"Import from File"**
3. Upload: `n8n-workflow-lumira-chatbot.json`
4. Click **"Save"**

### Step 3: Configure the Workflow

**Update the "Call AI Service" node:**
- Change URL to your AI service URL
- Add API credentials if needed
- Test the connection

**Get your Webhook URL:**
1. Click on the **"Webhook - Receive Chat"** node
2. Copy the **"Test URL"** or **"Production URL"**
3. Example: `http://localhost:5678/webhook/lumira-chat`

### Step 4: Update Your Website

**Edit the file: `api/chat-n8n.php`**

Find this line:
```php
$N8N_WEBHOOK_URL = 'http://localhost:5678/webhook/lumira-chat';
```

Replace with your actual n8n webhook URL.

**Update your chat widget to use n8n:**

Edit `inc/chat-widget.php` and change the API endpoint:
```javascript
// OLD
fetch('/api/chat.php', { ... })

// NEW
fetch('/api/chat-n8n.php', { ... })
```

### Step 5: Test the Integration

1. **Activate the workflow in n8n** (toggle the switch to "Active")
2. Open your LUMIRA website
3. Open the chat widget
4. Send a test message: "I need help with my account"
5. Check n8n executions to see if it received the request

---

## 🎯 How It Works

```
User types in chat widget
        ↓
chat-n8n.php receives message
        ↓
Sends to n8n webhook
        ↓
n8n processes with AI
        ↓
Analyzes intent (ticket? question? sales?)
        ↓
Returns response to chat-n8n.php
        ↓
Auto-creates ticket if needed
        ↓
Shows response to user
```

---

## 🔧 Configuration Options

### Enable Auto-Ticket Creation

The system automatically creates tickets when it detects support keywords like:
- "problem"
- "issue"
- "error"
- "broken"
- "not working"
- "urgent"
- "bug"

**To customize keywords**, edit the "Analyze Intent" node in n8n:

```javascript
const supportKeywords = [
  'problem', 'issue', 'error', 'broken',
  'not working', 'help', 'urgent', 'bug', 'support'
  // Add your own keywords here
];
```

### Configure AI Service

**Option 1: Use your existing OpenWebUI**
- Already configured in the workflow
- Uses URL: `https://139.182.185.119.nip.io`

**Option 2: Use OpenAI directly**
1. Get API key from platform.openai.com
2. In n8n workflow, update "Call AI Service" node
3. Change URL to: `https://api.openai.com/v1/chat/completions`
4. Add header: `Authorization: Bearer YOUR_API_KEY`

**Option 3: Use Claude API**
1. Get API key from console.anthropic.com
2. Change URL to: `https://api.anthropic.com/v1/messages`
3. Update request format for Claude

### Add Database Logging

To log all conversations to your database, add a node after "Analyze Intent":

1. Click **"+"** → **"PostgreSQL"**
2. Configure connection to `10.0.1.200`
3. Add INSERT query:

```sql
INSERT INTO chat_logs (session_id, user_message, ai_response, created_at)
VALUES ($session_id, $user_message, $ai_response, NOW())
```

---

## 🎨 Advanced Features

### 1. Add Supabase Integration

**Create Supabase table:**
```sql
CREATE TABLE chat_logs (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  session_id TEXT,
  user_message TEXT,
  ai_response TEXT,
  intent TEXT,
  confidence DECIMAL,
  created_at TIMESTAMP DEFAULT NOW()
);
```

**Add Supabase node to workflow:**
1. After "Analyze Intent"
2. Add "HTTP Request" node
3. Configure:
   - URL: `https://YOUR_PROJECT.supabase.co/rest/v1/chat_logs`
   - Method: POST
   - Headers:
     - `apikey: YOUR_SUPABASE_KEY`
     - `Authorization: Bearer YOUR_SUPABASE_KEY`

### 2. Route to Specific Support Agents

Modify "Analyze Intent" to suggest specific agents:

```javascript
// Determine best agent
let suggestedAgent = null;

if (suggestedCategory === 'Billing') {
  suggestedAgent = 'billing_team@lumira.local';
} else if (suggestedCategory === 'Technical Support') {
  suggestedAgent = 'tech_support@lumira.local';
}

return {
  json: {
    ...existingFields,
    suggested_assignee: suggestedAgent
  }
};
```

Then update `chat-n8n.php` to assign tickets to the suggested agent.

### 3. Add Email Notifications

Add "Send Email" node after ticket creation:
1. Drag "Gmail" or "SMTP" node
2. Connect after ticket creation
3. Configure to notify support team

### 4. Create Dashboard in n8n

View chat analytics:
1. Add "HTTP Request" node to fetch from PostgreSQL
2. Create scheduled workflow (e.g., daily)
3. Generate reports on:
   - Most common issues
   - Ticket creation rate
   - Customer satisfaction

---

## 🧪 Testing Checklist

- [ ] n8n workflow is Active
- [ ] Webhook URL is correct in `chat-n8n.php`
- [ ] Chat widget loads on website
- [ ] Send test message: "Hello"
- [ ] Receive AI response
- [ ] Send test message: "I have a problem with my order"
- [ ] Verify ticket is created in database
- [ ] Check n8n execution history

---

## 🔍 Troubleshooting

### Issue: n8n workflow not triggering

**Solution:**
- Check if workflow is "Active" (green toggle)
- Verify webhook URL matches in `chat-n8n.php`
- Check n8n logs: `docker logs n8n` (if using Docker)

### Issue: AI not responding

**Solution:**
- Test AI service directly in n8n
- Check API credentials
- Verify network connectivity to AI service
- Check timeout settings (increase if needed)

### Issue: Tickets not being created

**Solution:**
- Check if user is logged in (tickets require login)
- Verify database connection in `chat-n8n.php`
- Check PostgreSQL logs
- Add error logging in PHP

### Issue: Chat widget shows error

**Solution:**
- Check browser console for errors
- Verify `/api/chat-n8n.php` is accessible
- Check PHP error logs
- Test endpoint directly with curl:

```bash
curl -X POST http://10.0.1.100/api/chat-n8n.php \
  -H "Content-Type: application/json" \
  -d '{"message":"test"}'
```

---

## 📊 Monitoring & Analytics

### View Chat Analytics in n8n

Create a separate workflow that runs daily:

1. **Trigger:** Schedule (Cron: `0 9 * * *`)
2. **PostgreSQL:** Query chat statistics
3. **Email:** Send report to admin

### Example Analytics Query:
```sql
SELECT
  DATE(created_at) as date,
  COUNT(*) as total_chats,
  SUM(CASE WHEN ticket_created THEN 1 ELSE 0 END) as tickets_created,
  AVG(confidence) as avg_confidence
FROM chat_logs
WHERE created_at >= NOW() - INTERVAL '7 days'
GROUP BY DATE(created_at)
ORDER BY date DESC;
```

---

## 🚀 Next Steps

1. **Test the basic integration**
2. **Monitor chat logs and AI responses**
3. **Adjust intent detection keywords based on real usage**
4. **Add Supabase for advanced analytics** (optional)
5. **Create automated ticket routing rules**
6. **Build customer satisfaction surveys**

---

## 📞 Support

If you encounter issues:
1. Check n8n documentation: https://docs.n8n.io
2. Review n8n execution history for errors
3. Check PHP error logs in LUMIRA
4. Test each component independently

---

## 🎉 Success!

Once everything is working, you'll have:
- ✅ AI-powered chatbot on your website
- ✅ Automatic support ticket creation
- ✅ Smart intent detection
- ✅ Conversation logging
- ✅ Scalable architecture with n8n

Your chatbot is now intelligent enough to:
- Answer product questions
- Detect support issues
- Create tickets automatically
- Route to appropriate teams
- Learn from conversations
