<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/Message.php';

$auth = new AuthController();
$auth->requireRole('doctor');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

Security::validateCSRFToken();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['conversation_id']) || !isset($input['content'])) {
        throw new Exception('Invalid request data');
    }

    $user = $auth->getCurrentUser();
    $messageModel = new Message($pdo);

    // Verify the doctor has access to this conversation
    if (!$messageModel->userHasAccessToConversation($user['id'], $input['conversation_id'])) {
        throw new Exception('Access denied');
    }

    // Validate content
    $content = trim($input['content']);
    if (empty($content)) {
        throw new Exception('Message content cannot be empty');
    }

    if (mb_strlen($content) > 1000) {
        throw new Exception('Message is too long (maximum 1000 characters)');
    }

    // Send the message
    $messageId = $messageModel->sendMessage([
        'conversation_id' => $input['conversation_id'],
        'sender_id' => $user['id'],
        'content' => $content
    ]);

    // Get the patient's ID from the conversation
    $conversation = $messageModel->getConversationById($input['conversation_id']);
    $patientId = $conversation['patient_id'];

    // Create notification for the patient
    $notificationQuery = $pdo->prepare("
        INSERT INTO notifications (
            user_id,
            title,
            message,
            type,
            reference_id,
            created_at
        ) VALUES (?, ?, ?, ?, ?, NOW())
    ");

    $notificationQuery->execute([
        $patientId,
        'New Message from Doctor',
        'Dr. ' . $user['name'] . ' has sent you a message',
        'message',
        $messageId
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully',
        'message_id' => $messageId
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}