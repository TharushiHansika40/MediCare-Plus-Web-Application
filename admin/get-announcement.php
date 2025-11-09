<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';

$auth = new AuthController();
$auth->requireRole('admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

try {
    $announcementId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    
    if (!$announcementId) {
        throw new Exception('Invalid announcement ID');
    }

    $query = $pdo->prepare("
        SELECT * FROM announcements WHERE id = ?
    ");

    $query->execute([$announcementId]);
    $announcement = $query->fetch();

    if (!$announcement) {
        throw new Exception('Announcement not found');
    }

    echo json_encode([
        'success' => true,
        'announcement' => $announcement
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}