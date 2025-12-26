<?php
/**
 * LUMIRA - AI Chat Interface
 */

require_once __DIR__ . '/../../../app/config/config.php';
require_once __DIR__ . '/../../../app/helpers/functions.php';

session_start();

// Handle logout
if (isset($_GET['logout'])) {
    user_logout();
    redirect('/index.php');
}

// Configuration
$OPENWEBUI_BASE_URL = "https://139.182.185.119.nip.io";
$OLLAMA_BASE_URL = "https://ollama.139.182.185.119.nip.io";
$DEFAULT_API_KEY = "sk-efd8502cffc74ba0bca909283a0c2e71";

$AVAILABLE_MODELS = [
    "huihui_ai/deepseek-r1-abliterated:8b" => "DeepSeek R1 (8B)",
    "huihui_ai/qwen2.5-abliterated:7b" => "Qwen 2.5 (7B)",
    "huihui_ai/qwen3-abliterated:8b" => "Qwen 3 (8B)",
    "huihui_ai/gemma3-abliterated:4b" => "Gemma 3 (4B)",
    "huihui_ai/llama3.2-abliterated:3b" => "Llama 3.2 (3B)"
];

// Handle AJAX chat requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'send_message') {
        $message = $_POST['message'] ?? '';
        $model = $_POST['model'] ?? array_key_first($AVAILABLE_MODELS);
        $api_key = $_POST['api_key'] ?? $DEFAULT_API_KEY;
        $history = json_decode($_POST['history'] ?? '[]', true);

        // Build messages array
        $messages = [];
        foreach ($history as $msg) {
            $messages[] = ['role' => 'user', 'content' => $msg['user']];
            if (!empty($msg['assistant'])) {
                $messages[] = ['role' => 'assistant', 'content' => $msg['assistant']];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        // Call API
        $url = $OPENWEBUI_BASE_URL . '/api/chat/completions';
        $data = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => floatval($_POST['temperature'] ?? 0.7),
            'max_tokens' => intval($_POST['max_tokens'] ?? 2048),
            'stream' => false
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            $result = json_decode($response, true);
            $content = $result['choices'][0]['message']['content'] ?? 'No response';
            echo json_encode(['success' => true, 'message' => $content]);
        } else {
            echo json_encode(['success' => false, 'error' => 'API Error: ' . $http_code]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Chat - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="/assets/style.css?v=<?= time() ?>">
    <style>
        .chat-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .chat-settings {
            background: rgba(36, 36, 36, 0.8);
            border: 1px solid rgba(220, 20, 60, 0.4);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .chat-box {
            background: rgba(36, 36, 36, 0.8);
            border: 1px solid rgba(220, 20, 60, 0.4);
            border-radius: 15px;
            padding: 20px;
            height: 500px;
            overflow-y: auto;
            margin-bottom: 20px;
        }

        .chat-message {
            margin-bottom: 15px;
            padding: 12px;
            border-radius: 10px;
        }

        .user-message {
            background: rgba(220, 20, 60, 0.2);
            text-align: right;
            margin-left: 20%;
        }

        .assistant-message {
            background: rgba(36, 36, 36, 0.9);
            border: 1px solid rgba(220, 20, 60, 0.3);
            margin-right: 20%;
        }

        .chat-input-area {
            display: flex;
            gap: 10px;
        }

        .chat-input {
            flex: 1;
        }

        .loading {
            text-align: center;
            color: var(--primary);
            margin: 10px 0;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div>
                <h1><?= SITE_NAME ?></h1>
                <div class="tagline"><?= SITE_TAGLINE ?></div>
            </div>
        </div>
    </header>

    <?php require_once __DIR__ . '/../layouts/nav.php'; ?>

    <main>
        <div class="container chat-container">
            <h2>🤖 AI Chat Assistant</h2>

            <div class="chat-settings">
                <h3>⚙️ Chat Settings</h3>
                <div class="settings-grid">
                    <div class="form-group">
                        <label>AI Model</label>
                        <select id="model" class="form-control">
                            <?php foreach ($AVAILABLE_MODELS as $key => $name): ?>
                                <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Temperature: <span id="temp-value">0.7</span></label>
                        <input type="range" id="temperature" min="0" max="2" step="0.1" value="0.7" style="width: 100%;">
                    </div>

                    <div class="form-group">
                        <label>Max Tokens: <span id="tokens-value">2048</span></label>
                        <input type="range" id="max_tokens" min="256" max="4096" step="256" value="2048" style="width: 100%;">
                    </div>
                </div>

                <div style="margin-top: 15px; display: flex; gap: 10px;">
                    <button onclick="clearChat()" class="btn btn-danger">🗑️ Clear Chat</button>
                    <button onclick="testConnection()" class="btn btn-secondary">🔍 Test Connection</button>
                </div>
            </div>

            <div class="chat-box" id="chatBox">
                <div class="assistant-message">
                    <strong>AI Assistant:</strong><br>
                    Hello! I'm your AI assistant. How can I help you today?
                </div>
            </div>

            <div id="loading" class="loading" style="display: none;">
                ⏳ AI is thinking...
            </div>

            <div class="chat-input-area">
                <textarea
                    id="messageInput"
                    class="chat-input"
                    placeholder="Type your message here..."
                    rows="3"
                    onkeypress="if(event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); sendMessage(); }"
                ></textarea>
                <button onclick="sendMessage()" class="btn" style="height: fit-content;">📤 Send</button>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?> - <?= SITE_TAGLINE ?></p>
        </div>
    </footer>

    <script>
        let chatHistory = [];
        const apiKey = '<?= $DEFAULT_API_KEY ?>';

        // Update slider displays
        document.getElementById('temperature').addEventListener('input', function(e) {
            document.getElementById('temp-value').textContent = e.target.value;
        });

        document.getElementById('max_tokens').addEventListener('input', function(e) {
            document.getElementById('tokens-value').textContent = e.target.value;
        });

        function clearChat() {
            chatHistory = [];
            document.getElementById('chatBox').innerHTML = `
                <div class="assistant-message">
                    <strong>AI Assistant:</strong><br>
                    Hello! I'm your AI assistant. How can I help you today?
                </div>
            `;
        }

        function testConnection() {
            alert('Connection test feature coming soon!');
        }

        function addMessage(role, content) {
            const chatBox = document.getElementById('chatBox');
            const messageDiv = document.createElement('div');
            messageDiv.className = role === 'user' ? 'user-message' : 'assistant-message';
            messageDiv.innerHTML = `<strong>${role === 'user' ? 'You' : 'AI Assistant'}:</strong><br>${escapeHtml(content)}`;
            chatBox.appendChild(messageDiv);
            chatBox.scrollTop = chatBox.scrollHeight;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML.replace(/\n/g, '<br>');
        }

        async function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();

            if (!message) return;

            // Add user message
            addMessage('user', message);
            input.value = '';

            // Show loading
            document.getElementById('loading').style.display = 'block';

            // Get settings
            const model = document.getElementById('model').value;
            const temperature = document.getElementById('temperature').value;
            const max_tokens = document.getElementById('max_tokens').value;

            // Send to server
            try {
                const formData = new FormData();
                formData.append('action', 'send_message');
                formData.append('message', message);
                formData.append('model', model);
                formData.append('api_key', apiKey);
                formData.append('temperature', temperature);
                formData.append('max_tokens', max_tokens);
                formData.append('history', JSON.stringify(chatHistory));

                const response = await fetch('/chat.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                document.getElementById('loading').style.display = 'none';

                if (data.success) {
                    addMessage('assistant', data.message);
                    chatHistory.push({user: message, assistant: data.message});
                } else {
                    addMessage('assistant', '❌ Error: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                document.getElementById('loading').style.display = 'none';
                addMessage('assistant', '❌ Connection error: ' + error.message);
            }
        }
    </script>
</body>
</html>
