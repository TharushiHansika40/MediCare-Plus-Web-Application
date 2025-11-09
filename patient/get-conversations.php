<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/Message.php';

$auth = new AuthController();
$auth->requireRole('patient');

$user = $auth->getCurrentUser();
$messageModel = new Message($pdo);

header('Content-Type: application/json');

try {
    $conversations = $messageModel->getConversations($user['id'], 'patient');
    
    echo json_encode([
        'success' => true,
        'conversations' => $conversations
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}