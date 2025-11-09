<?php
require_once 'config.php';
require_once __DIR__ . '/../controllers/AuthController.php';

$auth = new AuthController();
$user = $auth->getCurrentUser();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

try {
    // Get unread notifications for the current user
    $query = $pdo->prepare("
        SELECT 
            id,
            title,
            message,
            type,
            reference_id,
            created_at
        FROM notifications 
        WHERE user_id = ? 
        AND is_read = 0
        ORDER BY created_at DESC
    ");

    $query->execute([$user['id']]);
    $notifications = $query->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'notifications' => $notifications
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}