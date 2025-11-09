<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';

$auth = new AuthController();
$auth->requireRole('admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

Security::validateCSRFToken();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['id'])) {
        throw new Exception('Invalid request: announcement ID is required');
    }

    $id = $input['id'];

    // Check if announcement exists
    $checkQuery = $pdo->prepare("SELECT id FROM announcements WHERE id = ?");
    $checkQuery->execute([$id]);
    if (!$checkQuery->fetch()) {
        throw new Exception('Announcement not found');
    }

    // Delete related notifications
    $deleteNotificationsQuery = $pdo->prepare("
        DELETE FROM notifications 
        WHERE type = 'announcement' 
        AND reference_id = ?
    ");
    $deleteNotificationsQuery->execute([$id]);

    // Delete the announcement
    $deleteQuery = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
    $deleteQuery->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'Announcement deleted successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}