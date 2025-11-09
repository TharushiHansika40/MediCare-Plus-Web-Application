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
    
    if (!$input) {
        throw new Exception('Invalid request data');
    }

    // Validate required fields
    $requiredFields = ['name', 'description', 'price', 'duration', 'status'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            throw new Exception("Missing required field: $field");
        }
    }

    // Validate numeric fields
    if (!is_numeric($input['price']) || $input['price'] < 0) {
        throw new Exception('Invalid price value');
    }

    if (!is_numeric($input['duration']) || $input['duration'] < 15) {
        throw new Exception('Duration must be at least 15 minutes');
    }

    if (!in_array($input['status'], ['active', 'inactive'])) {
        throw new Exception('Invalid status');
    }

    $serviceModel = new Service();

    if (empty($input['id'])) {
        // Create new service
        $serviceId = $serviceModel->create([
            'name' => $input['name'],
            'description' => $input['description'],
            'price' => $input['price'],
            'duration' => $input['duration'],
            'specialties' => $input['specialties'],
            'status' => $input['status']
        ]);

    } else {
        // Update existing service
        $serviceId = $input['id'];
        $updateData = [
            'name' => $input['name'],
            'description' => $input['description'],
            'price' => $input['price'],
            'duration' => $input['duration'],
            'specialties' => $input['specialties'],
            'status' => $input['status']
        ];

        $serviceModel->update($serviceId, $updateData);
    }

    echo json_encode([
        'success' => true,
        'message' => empty($input['id']) ? 'Service created successfully' : 'Service updated successfully',
        'service_id' => $serviceId
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}