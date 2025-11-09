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
    $userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    
    if (!$userId) {
        throw new Exception('Invalid user ID');
    }

    // Get user with doctor info if applicable
    $query = $pdo->prepare("
        SELECT 
            u.*,
            d.display_name as doctor_display_name,
            d.specialty as doctor_specialty,
            d.qualification as doctor_qualification,
            d.experience as doctor_experience
        FROM users u
        LEFT JOIN doctors d ON d.user_id = u.id
        WHERE u.id = ?
    ");

    $query->execute([$userId]);
    $user = $query->fetch();

    if (!$user) {
        throw new Exception('User not found');
    }

    // Format response
    $response = [
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'status' => $user['status'],
            'phone' => $user['phone']
        ]
    ];

    // Add doctor info if applicable
    if ($user['role'] === 'doctor') {
        $response['user']['doctor_info'] = [
            'display_name' => $user['doctor_display_name'],
            'specialty' => $user['doctor_specialty'],
            'qualification' => $user['doctor_qualification'],
            'experience' => $user['doctor_experience']
        ];
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}