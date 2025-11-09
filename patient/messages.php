<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/Message.php';

$auth = new AuthController();
$auth->requireRole('patient');

$user = $auth->getCurrentUser();
$messageModel = new Message($pdo);

require_once __DIR__ . '/../views/header.php';
?>

<div class="container-fluid messaging-container py-4">
    <div class="row">
        <!-- Conversation List -->
        <div class="col-md-4 col-lg-3">
            <div class="conversations-list card">
                <div class="card-header">
                    <h5 class="mb-0">Messages</h5>
                </div>
                <div class="list-group list-group-flush" id="conversationsList">
                    <!-- Populated by JavaScript -->
                </div>
            </div>
        </div>

        <!-- Message Thread -->
        <div class="col-md-8 col-lg-9">
            <div class="message-thread card">
                <div class="card-header" id="threadHeader">
                    <h5 class="mb-0">Select a conversation</h5>
                </div>
                <div class="card-body" id="messageThread">
                    <!-- Messages will be loaded here -->
                    <div class="no-messages-selected text-center text-muted">
                        <i class="fas fa-comments fa-3x mb-3"></i>
                        <p>Select a conversation to view messages</p>
                    </div>
                </div>
                <div class="card-footer" id="messageForm" style="display: none;">
                    <form id="sendMessageForm" class="d-flex">
                        <input type="hidden" id="conversationId">
                        <div class="flex-grow-1 me-2">
                            <textarea class="form-control" id="messageContent" rows="1" 
                                    placeholder="Type your message..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.messaging-container {
    height: calc(100vh - 100px);
}

.conversations-list, .message-thread {
    height: 100%;
    display: flex;
    flex-direction: column;
}

.list-group {
    overflow-y: auto;
}

.conversation-item {
    display: flex;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid rgba(0,0,0,.125);
    cursor: pointer;
}

.conversation-item:hover {
    background-color: rgba(0,0,0,.03);
}

.conversation-item.active {
    background-color: var(--primary-color-light);
    color: var(--primary-color);
}

.conversation-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    margin-right: 1rem;
    background-color: var(--primary-color);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.conversation-info {
    flex: 1;
    min-width: 0;
}

.conversation-name {
    font-weight: 500;
    margin-bottom: 0.25rem;
}

.conversation-preview {
    color: #666;
    font-size: 0.875rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.unread-badge {
    background-color: var(--primary-color);
    color: white;
    border-radius: 50%;
    min-width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    margin-left: 0.5rem;
}

.message-thread .card-body {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
}

.message-bubble {
    max-width: 70%;
    margin-bottom: 1rem;
    display: flex;
    flex-direction: column;
}

.message-bubble.sent {
    align-self: flex-end;
}

.message-bubble.received {
    align-self: flex-start;
}

.message-content {
    padding: 0.75rem 1rem;
    border-radius: 1rem;
    position: relative;
    word-wrap: break-word;
}

.message-bubble.sent .message-content {
    background-color: var(--primary-color);
    color: white;
    border-bottom-right-radius: 0.25rem;
}

.message-bubble.received .message-content {
    background-color: #f0f2f5;
    border-bottom-left-radius: 0.25rem;
}

.message-meta {
    font-size: 0.75rem;
    color: #666;
    margin-top: 0.25rem;
}

.message-bubble.sent .message-meta {
    text-align: right;
}

.no-messages-selected {
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #666;
}

#messageForm {
    background-color: #fff;
    border-top: 1px solid rgba(0,0,0,.125);
    padding: 1rem;
}

#messageContent {
    resize: none;
    border-radius: 1.5rem;
    padding: 0.5rem 1rem;
}

#messageForm button {
    border-radius: 50%;
    width: 40px;
    height: 40px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.load-more {
    text-align: center;
    padding: 1rem;
    cursor: pointer;
    color: var(--primary-color);
}

.load-more:hover {
    text-decoration: underline;
}
</style>

<script>
let activeConversation = null;
let lastMessageTime = null;
let isLoadingMore = false;

document.addEventListener('DOMContentLoaded', () => {
    loadConversations();
    setupMessageForm();
    setupPolling();
});

function loadConversations() {
    fetch('/patient/get-conversations.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const list = document.getElementById('conversationsList');
                list.innerHTML = data.conversations.map(conv => createConversationItem(conv)).join('');
                
                // If there's an active conversation, mark it as selected
                if (activeConversation) {
                    const activeItem = list.querySelector(`[data-conversation-id="${activeConversation}"]`);
                    if (activeItem) activeItem.classList.add('active');
                }
            }
        });
}

function createConversationItem(conversation) {
    const initials = conversation.other_user_name
        .split(' ')
        .map(n => n[0])
        .join('')
        .toUpperCase();
        
    return `
        <div class="conversation-item" data-conversation-id="${conversation.id}" 
             onclick="selectConversation(${conversation.id}, '${conversation.other_user_name}')">
            <div class="conversation-avatar">
                ${conversation.other_user_image 
                    ? `<img src="${conversation.other_user_image}" alt="Profile" class="img-fluid">`
                    : initials
                }
            </div>
            <div class="conversation-info">
                <div class="conversation-name">${conversation.other_user_name}</div>
                <div class="conversation-preview">
                    ${conversation.last_message 
                        ? (conversation.last_message_sender == <?php echo $user['id']; ?> 
                            ? 'You: ' : '') + conversation.last_message
                        : 'No messages yet'
                    }
                </div>
            </div>
            ${conversation.unread_count > 0 
                ? `<div class="unread-badge">${conversation.unread_count}</div>`
                : ''
            }
        </div>
    `;
}

function selectConversation(conversationId, doctorName) {
    activeConversation = conversationId;
    lastMessageTime = null;

    // Update UI
    document.querySelectorAll('.conversation-item').forEach(item => {
        item.classList.remove('active');
    });
    document.querySelector(`[data-conversation-id="${conversationId}"]`).classList.add('active');

    // Update thread header
    document.getElementById('threadHeader').innerHTML = `
        <h5 class="mb-0">Dr. ${doctorName}</h5>
    `;

    // Show message form
    document.getElementById('messageForm').style.display = 'block';
    document.getElementById('conversationId').value = conversationId;

    // Clear and load messages
    const thread = document.getElementById('messageThread');
    thread.innerHTML = '';
    loadMessages(conversationId);
}

function loadMessages(conversationId, before = null) {
    if (isLoadingMore) return;
    isLoadingMore = true;

    const url = new URL('/patient/get-messages.php', window.location.origin);
    url.searchParams.append('conversation_id', conversationId);
    if (before) url.searchParams.append('before', before);

    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const thread = document.getElementById('messageThread');
                const atBottom = isAtBottom(thread);
                const oldHeight = thread.scrollHeight;

                // Add load more button if there are more messages
                if (data.has_more && !before) {
                    thread.innerHTML = `
                        <div class="load-more" onclick="loadMessages(${conversationId}, '${data.messages[0].created_at}')">
                            Load more messages
                        </div>
                    `;
                }

                // Add messages
                const messages = before 
                    ? data.messages.concat(getExistingMessages())
                    : data.messages;
                
                thread.innerHTML = messages.map(msg => createMessageBubble(msg)).join('') +
                                 (before ? thread.innerHTML : '');

                // Update last message time
                if (!before && messages.length > 0) {
                    lastMessageTime = messages[messages.length - 1].created_at;
                }

                // Maintain scroll position when loading more
                if (before) {
                    thread.scrollTop = thread.scrollHeight - oldHeight;
                } else if (atBottom) {
                    thread.scrollTop = thread.scrollHeight;
                }
            }
        })
        .finally(() => {
            isLoadingMore = false;
        });
}

function createMessageBubble(message) {
    const isSent = message.sender_id == <?php echo $user['id']; ?>;
    const time = new Date(message.created_at).toLocaleTimeString([], 
        { hour: '2-digit', minute: '2-digit' }
    );
    
    return `
        <div class="message-bubble ${isSent ? 'sent' : 'received'}">
            <div class="message-content">${message.content}</div>
            <div class="message-meta">
                ${time}
                ${isSent && message.is_read ? '<i class="fas fa-check-double"></i>' : ''}
            </div>
        </div>
    `;
}

function getExistingMessages() {
    return Array.from(document.getElementById('messageThread')
        .querySelectorAll('.message-bubble'))
        .map(el => ({
            content: el.querySelector('.message-content').textContent,
            created_at: el.querySelector('.message-meta').textContent,
            sender_id: el.classList.contains('sent') 
                ? <?php echo $user['id']; ?>
                : activeConversation,
            is_read: el.querySelector('.fa-check-double') !== null
        }));
}

function setupMessageForm() {
    document.getElementById('sendMessageForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const content = document.getElementById('messageContent').value.trim();
        if (!content) return;

        const formData = {
            conversation_id: document.getElementById('conversationId').value,
            content: content,
            csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
        };

        fetch('/patient/send-message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('messageContent').value = '';
                loadMessages(activeConversation);
            }
        });
    });

    // Auto-resize textarea
    const textarea = document.getElementById('messageContent');
    textarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
}

function isAtBottom(element) {
    return Math.abs(element.scrollHeight - element.clientHeight - element.scrollTop) < 1;
}

// Poll for new messages
function setupPolling() {
    setInterval(() => {
        if (activeConversation && lastMessageTime) {
            fetch(`/patient/get-messages.php?conversation_id=${activeConversation}&after=${lastMessageTime}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.messages.length > 0) {
                        const thread = document.getElementById('messageThread');
                        const atBottom = isAtBottom(thread);
                        
                        data.messages.forEach(message => {
                            thread.insertAdjacentHTML('beforeend', createMessageBubble(message));
                        });
                        
                        lastMessageTime = data.messages[data.messages.length - 1].created_at;
                        
                        if (atBottom) {
                            thread.scrollTop = thread.scrollHeight;
                        }
                    }
                });
        }
        
        // Update conversations list to show new messages
        loadConversations();
    }, 5000); // Poll every 5 seconds
}
</script>

<?php require_once __DIR__ . '/../views/footer.php'; ?>