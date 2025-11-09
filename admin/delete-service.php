<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/Service.php';

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
    
    if (!$input || !isset($input['service_id'])) {
        throw new Exception('Invalid request data');
    }

    $serviceId = filter_var($input['service_id'], FILTER_VALIDATE_INT);

    if (!$serviceId) {
        throw new Exception('Invalid service ID');
    }

    // Check if service exists
    $serviceQuery = $pdo->prepare("SELECT * FROM services WHERE id = ?");
    $serviceQuery->execute([$serviceId]);
    $service = $serviceQuery->fetch();

    if (!$service) {
        throw new Exception('Service not found');
    }

    $pdo->beginTransaction();

    // Check for active appointments using this service
    $appointmentQuery = $pdo->prepare("
        SELECT COUNT(*) 
        FROM appointments
        WHERE service_id = ?
        AND status IN ('pending', 'confirmed')
    ");

    $appointmentQuery->execute([$serviceId]);
    $activeAppointments = $appointmentQuery->fetchColumn();

    if ($activeAppointments > 0) {
        throw new Exception('Cannot delete service with active appointments');
    }

    // Remove service from doctors' services
    $doctorQuery = $pdo->prepare("
        UPDATE doctors 
        SET services = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', services, ','), ?,',', ','))
        WHERE FIND_IN_SET(?, services)
    ");

    $doctorQuery->execute([$serviceId, $serviceId]);

    // Delete the service
    $deleteQuery = $pdo->prepare("DELETE FROM services WHERE id = ?");
    $deleteQuery->execute([$serviceId]);

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Service deleted successfully'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}