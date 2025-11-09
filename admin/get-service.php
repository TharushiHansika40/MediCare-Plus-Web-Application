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
    $serviceId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    
    if (!$serviceId) {
        throw new Exception('Invalid service ID');
    }

    $query = $pdo->prepare("
        SELECT * FROM services WHERE id = ?
    ");

    $query->execute([$serviceId]);
    $service = $query->fetch();

    if (!$service) {
        throw new Exception('Service not found');
    }

    echo json_encode([
        'success' => true,
        'service' => $service
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}