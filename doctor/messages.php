<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/Message.php';

$auth = new AuthController();
$auth->requireRole('doctor');

$pageTitle = "Messages";
$currentUser = $auth->getCurrentUser();

include_once __DIR__ . '/../views/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row gap-4">
        <!-- Conversations List -->
        <div class="w-full md:w-1/3 bg-white rounded-lg shadow">
            <div class="p-4 border-b">
                <h2 class="text-xl font-semibold">Patient Conversations</h2>
            </div>
            <div id="conversationsList" class="divide-y max-h-[600px] overflow-y-auto">
                <!-- Conversations will be loaded here -->
            </div>
        </div>

        <!-- Message Thread -->
        <div class="w-full md:w-2/3 bg-white rounded-lg shadow">
            <div id="messageThread" class="h-[600px] flex flex-col">
                <div id="threadHeader" class="p-4 border-b">
                    <h3 class="text-xl font-semibold" id="threadTitle">Select a conversation</h3>
                </div>
                <div id="messagesContainer" class="flex-1 overflow-y-auto p-4">
                    <!-- Messages will be loaded here -->
                </div>
                <div id="messageForm" class="p-4 border-t hidden">
                    <form id="sendMessageForm" class="flex gap-2">
                        <input type="hidden" id="conversationId" name="conversationId">
                        <textarea 
                            id="messageContent" 
                            name="content" 
                            class="flex-1 border rounded-lg p-2 resize-none"
                            rows="2"
                            placeholder="Type your message..."
                            required
                        ></textarea>
                        <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-dark">
                            Send
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentConversationId = null;
const messagesContainer = document.getElementById('messagesContainer');
const threadTitle = document.getElementById('threadTitle');
const messageForm = document.getElementById('messageForm');
const conversationsList = document.getElementById('conversationsList');

// Load conversations
function loadConversations() {
    fetch('get-conversations.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderConversations(data.conversations);
            }
        })
        .catch(error => console.error('Error loading conversations:', error));
}

// Render conversations list
function renderConversations(conversations) {
    conversationsList.innerHTML = conversations.map(conv => `
        <div class="conversation-item p-4 cursor-pointer hover:bg-gray-50 ${conv.id === currentConversationId ? 'bg-gray-100' : ''}"
             onclick="loadConversation(${conv.id})">
            <div class="font-semibold">${escapeHtml(conv.patient_name)}</div>
            <div class="text-sm text-gray-500">
                ${conv.unread_count > 0 ? `<span class="text-primary font-semibold">${conv.unread_count} new</span> • ` : ''}
                Last message: ${formatDate(conv.last_message_date)}
            </div>
        </div>
    `).join('');
}

// Load conversation messages
function loadConversation(conversationId) {
    currentConversationId = conversationId;
    document.getElementById('conversationId').value = conversationId;
    messageForm.classList.remove('hidden');
    
    fetch(`get-messages.php?conversation_id=${conversationId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderMessages(data.messages);
                threadTitle.textContent = data.patient_name;
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        })
        .catch(error => console.error('Error loading messages:', error));
}

// Render messages in the thread
function renderMessages(messages) {
    messagesContainer.innerHTML = messages.map(msg => `
        <div class="message mb-4 ${msg.sender_id === <?php echo $currentUser['id']; ?> ? 'flex justify-end' : ''}">
            <div class="max-w-[75%] ${msg.sender_id === <?php echo $currentUser['id']; ?> ? 'bg-primary text-white' : 'bg-gray-100'} rounded-lg p-3">
                <div class="text-sm ${msg.sender_id === <?php echo $currentUser['id']; ?> ? 'text-white/80' : 'text-gray-500'} mb-1">
                    ${msg.sender_id === <?php echo $currentUser['id']; ?> ? 'You' : escapeHtml(msg.sender_name)}
                </div>
                <div class="break-words">${escapeHtml(msg.content)}</div>
                <div class="text-xs ${msg.sender_id === <?php echo $currentUser['id']; ?> ? 'text-white/80' : 'text-gray-500'} mt-1">
                    ${formatDate(msg.created_at)}
                </div>
            </div>
        </div>
    `).join('');
}

// Send new message
document.getElementById('sendMessageForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = e.target;
    const data = {
        conversation_id: form.conversationId.value,
        content: form.content.value
    };

    fetch('send-message.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?php echo Security::getCSRFToken(); ?>'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            form.content.value = '';
            loadConversation(currentConversationId);
        }
    })
    .catch(error => console.error('Error sending message:', error));
});

// Helper functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString();
}

// Poll for new messages
function pollNewMessages() {
    if (currentConversationId) {
        loadConversation(currentConversationId);
    }
    loadConversations();
}

// Initial load
loadConversations();

// Set up polling
setInterval(pollNewMessages, 10000); // Poll every 10 seconds
</script>

<?php include_once __DIR__ . '/../views/footer.php'; ?>