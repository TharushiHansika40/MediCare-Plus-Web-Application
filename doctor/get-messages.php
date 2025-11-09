<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/Message.php';

$auth = new AuthController();
$auth->requireRole('doctor');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

try {
    $conversationId = filter_input(INPUT_GET, 'conversation_id', FILTER_VALIDATE_INT);
    if (!$conversationId) {
        throw new Exception('Invalid conversation ID');
    }

    $user = $auth->getCurrentUser();
    $messageModel = new Message($pdo);

    // Verify the doctor has access to this conversation
    if (!$messageModel->userHasAccessToConversation($user['id'], $conversationId)) {
        throw new Exception('Access denied');
    }

    // Get the conversation details and messages
    $conversation = $messageModel->getConversationById($conversationId);
    $messages = $messageModel->getMessages($conversationId);

    // Mark messages as read
    $messageModel->markMessagesAsRead($conversationId, $user['id']);

    echo json_encode([
        'success' => true,
        'patient_name' => $conversation['patient_name'],
        'messages' => $messages
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}