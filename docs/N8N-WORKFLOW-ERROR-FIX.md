# 🔧 Fixing n8n Workflow Error

## ❌ Current Issue

Your n8n workflow is returning:
```
HTTP 500: Internal Server Error
{"message":"Error in workflow"}
```

This means your workflow is receiving requests but **something inside the workflow is failing**.

---

## 🔍 Common Causes & Solutions

### **1. Missing AI/LLM Configuration** ⚠️ (Most Likely)

**Problem:** Your workflow tries to call an AI service (OpenAI, Claude, etc.) but:
- Missing API key
- Wrong model name
- API service is down
- Credentials not configured

**Solution:**
1. Open your workflow in n8n Cloud: https://lumira.app.n8n.cloud
2. Click on any AI node (OpenAI, Chat Model, LLM, etc.)
3. Check if credentials are configured
4. Test the node individually (right-click → "Execute Node")

### **2. Invalid Response Format** ⚠️

**Problem:** Your workflow doesn't return data in the expected format.

**Solution:** The **last node** should return:
```json
{
  "output": "text",
  "text": "Your response message here"
}
```

Or simply:
```json
{
  "message": "Your response message here"
}
```

### **3. Node Configuration Error** ⚠️

**Problem:** A node in your workflow has invalid settings.

**Solution:**
1. Open n8n Cloud
2. Go to **Executions** tab
3. Find the failed execution
4. Click to see which node failed
5. Fix that node's configuration

### **4. Missing Input Data** ⚠️

**Problem:** A node expects data that isn't being passed.

**Solution:** Check that each node receives the data it needs from previous nodes.

---

## 🚀 Quick Fix: Use This Simple Working Workflow

I've created a minimal working workflow you can import:

### **Step 1: Copy This Workflow JSON**

```json
{
  "name": "LUMIRA Chat - Simple Working Version",
  "nodes": [
    {
      "parameters": {
        "httpMethod": "POST",
        "path": "chat",
        "responseMode": "responseNode",
        "options": {}
      },
      "id": "webhook-1",
      "name": "Webhook",
      "type": "n8n-nodes-base.webhook",
      "typeVersion": 1,
      "position": [240, 300],
      "webhookId": "4f9e9fb8-1464-40da-b4f7-abac2c60ac07"
    },
    {
      "parameters": {
        "jsCode": "// Simple echo bot that just returns the user's message\nconst userMessage = $input.item.json.body.message || $input.item.json.message || 'Hello';\n\n// Return in the format expected by n8n chat widget\nreturn {\n  json: {\n    output: 'text',\n    text: `You said: \"${userMessage}\". This is a test response from n8n! Your workflow is working correctly. ✅`\n  }\n};"
      },
      "id": "code-1",
      "name": "Generate Response",
      "type": "n8n-nodes-base.code",
      "typeVersion": 2,
      "position": [460, 300]
    },
    {
      "parameters": {
        "respondWith": "json",
        "responseBody": "={{ $json }}"
      },
      "id": "respond-1",
      "name": "Respond to Webhook",
      "type": "n8n-nodes-base.respondToWebhook",
      "typeVersion": 1,
      "position": [680, 300]
    }
  ],
  "connections": {
    "Webhook": {
      "main": [
        [
          {
            "node": "Generate Response",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Generate Response": {
      "main": [
        [
          {
            "node": "Respond to Webhook",
            "type": "main",
            "index": 0
          }
        ]
      ]
    }
  },
  "settings": {},
  "staticData": null,
  "tags": []
}
```

### **Step 2: Import to n8n**

1. Go to: https://lumira.app.n8n.cloud
2. Click **"Workflows"** in left menu
3. Click **"Import from File"** or **"Import from URL"**
4. Paste the JSON above
5. Click **"Save"**
6. Toggle workflow to **Active** (green)

### **Step 3: Test It**

Open your test page:
```
http://10.0.1.100/test-n8n-native-widget.php
```

Send "Hello" - you should get:
```
You said: "Hello". This is a test response from n8n! Your workflow is working correctly. ✅
```

---

## 🎯 Once Basic Workflow Works, Add AI

After confirming the simple workflow works, add AI step by step:

### **Step 1: Add OpenAI Node** (If using OpenAI)

1. In your workflow, add new node between "Webhook" and "Respond"
2. Search for "OpenAI"
3. Add your OpenAI API key in credentials
4. Configure:
   - Model: `gpt-3.5-turbo`
   - Message: `={{ $json.message }}`

### **Step 2: Or Add HTTP Request** (To use your existing AI)

1. Add "HTTP Request" node
2. Configure:
   - Method: POST
   - URL: `https://139.182.185.119.nip.io` (your AI service)
   - Body:
   ```json
   {
     "model": "your-model",
     "messages": [
       {
         "role": "user",
         "content": "={{ $json.message }}"
       }
     ]
   }
   ```

### **Step 3: Format Response**

Add a "Code" node before "Respond to Webhook":

```javascript
// Extract AI response
const aiResponse = $input.item.json.choices?.[0]?.message?.content
                || $input.item.json.response
                || $input.item.json.text
                || 'Sorry, I could not process your request';

return {
  json: {
    output: 'text',
    text: aiResponse
  }
};
```

---

## 🔍 Debugging Your Current Workflow

### **Check Executions in n8n:**

1. Go to: https://lumira.app.n8n.cloud
2. Click **"Executions"** in left menu
3. Find recent failed executions (red X)
4. Click to see details
5. Look for the node that failed
6. See the error message

### **Common Error Messages:**

| Error | Cause | Fix |
|-------|-------|-----|
| "API key not found" | Missing credentials | Add API key in node settings |
| "Model not found" | Wrong model name | Check model name spelling |
| "Timeout" | AI taking too long | Increase timeout or use faster model |
| "Invalid JSON" | Response not formatted | Add code node to format |
| "Cannot read property" | Missing data | Check data flow between nodes |

---

## 🧪 Test Your Workflow Directly

### **Test from Command Line:**

```bash
# Simple test
curl -X POST https://lumira.app.n8n.cloud/webhook/4f9e9fb8-1464-40da-b4f7-abac2c60ac07/chat \
  -H "Content-Type: application/json" \
  -d '{"message":"test"}'
```

**Expected Response:**
```json
{
  "output": "text",
  "text": "Your AI response here"
}
```

**If you get:**
```json
{"message":"Error in workflow"}
```

Then check n8n executions for the specific error.

### **Test in n8n Interface:**

1. Open your workflow
2. Click the **Webhook** node
3. Click **"Listen for Test Event"**
4. Send a test request using curl above
5. See the data flow through each node
6. Identify which node fails

---

## 📋 Workflow Checklist

Make sure your workflow has:

- [ ] **Webhook node** - Listening for POST requests ✅
- [ ] **Data processing** - Extracts message from request
- [ ] **AI/Response generation** - Creates response
- [ ] **Response formatting** - Returns correct JSON format
- [ ] **Respond to Webhook node** - Sends response back ✅
- [ ] **Workflow is Active** - Green toggle on
- [ ] **All credentials configured** - API keys, etc.
- [ ] **No error nodes** - All nodes execute successfully

---

## 🎯 Recommended Workflow Structure

```
Webhook (Receive)
    ↓
Extract Message (Code/Function)
    ↓
[Optional] Get User Data
    ↓
Call AI Service (OpenAI/HTTP Request)
    ↓
Format Response (Code)
    ↓
[Optional] Log to Database
    ↓
Respond to Webhook (Send back)
```

---

## 💡 Pro Tips

1. **Start Simple** - Use the echo bot workflow first
2. **Test Each Node** - Right-click → "Execute Node"
3. **Check Executions** - Every request creates an execution
4. **Add Error Handling** - Use "On Error" workflow settings
5. **Monitor Response Time** - Should be < 10 seconds
6. **Use Code Nodes** - For custom logic and formatting

---

## 🆘 Still Not Working?

### **Option 1: Use the Simple Echo Workflow**
Just copy/paste the JSON above and import it. It will work immediately.

### **Option 2: Share Your Workflow**
Export your current workflow and I can help debug it.

### **Option 3: Check n8n Logs**
In n8n Cloud executions, the error message will tell you exactly what's wrong.

---

## ✅ Success Criteria

Your workflow is working when:

✅ Chat widget loads on your site
✅ Clicking chat button opens window
✅ Sending message shows "sending..." animation
✅ Response appears within 10 seconds
✅ Response makes sense / is relevant
✅ n8n executions show as successful (green checkmark)
✅ No HTTP 500 errors

---

## 🚀 Next Steps

1. **Import the simple echo workflow** (copy JSON above)
2. **Test it** - Send "Hello" and get response
3. **Confirm it works** - No more 500 errors
4. **Gradually add complexity** - Add AI, database, etc.
5. **Test after each change** - One step at a time

---

## 📞 Quick Help

**See your executions:**
```
https://lumira.app.n8n.cloud → Executions
```

**Test your webhook:**
```
http://10.0.1.100/test-n8n-workflow.php
```

**Test the widget:**
```
http://10.0.1.100/test-n8n-native-widget.php
```

---

## 🎉 You've Got This!

The simple echo workflow will get you working immediately. Once that's confirmed, you can add AI and other features step by step.

Remember: **Test early, test often!** 🧪
