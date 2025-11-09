<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/User.php';

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
    
    if (!$input || !isset($input['user_id'], $input['status'])) {
        throw new Exception('Invalid request data');
    }

    $userId = filter_var($input['user_id'], FILTER_VALIDATE_INT);
    $status = $input['status'];

    if (!$userId) {
        throw new Exception('Invalid user ID');
    }

    if (!in_array($status, ['active', 'inactive'])) {
        throw new Exception('Invalid status');
    }

    // Don't allow deactivating your own account
    if ($userId === $auth->getCurrentUser()['id']) {
        throw new Exception('Cannot modify your own account status');
    }

    $userModel = new User();
    $user = $userModel->getById($userId);

    if (!$user) {
        throw new Exception('User not found');
    }

    // Don't allow modifying other admin accounts
    if ($user['role'] === 'admin' && $userId !== $auth->getCurrentUser()['id']) {
        throw new Exception('Cannot modify other admin accounts');
    }

    $pdo->beginTransaction();

    // Update user status
    $updateQuery = $pdo->prepare("
        UPDATE users 
        SET status = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    $updateQuery->execute([$status, $userId]);

    // If deactivating a doctor, cancel their pending appointments
    if ($status === 'inactive' && $user['role'] === 'doctor') {
        $appointmentQuery = $pdo->prepare("
            UPDATE appointments 
            SET status = 'cancelled',
                updated_at = NOW()
            WHERE doctor_id = (SELECT id FROM doctors WHERE user_id = ?)
            AND status = 'pending'
        ");

        $appointmentQuery->execute([$userId]);

        // Notify patients of cancelled appointments
        $notificationQuery = $pdo->prepare("
            INSERT INTO notifications (
                user_id,
                title,
                message,
                type,
                created_at
            )
            SELECT 
                patient_id,
                'Appointment Cancelled',
                CONCAT('Your appointment with ', 
                    (SELECT display_name FROM doctors WHERE user_id = ?),
                    ' has been cancelled due to doctor unavailability'),
                'appointment_cancelled',
                NOW()
            FROM appointments 
            WHERE doctor_id = (SELECT id FROM doctors WHERE user_id = ?)
            AND status = 'cancelled'
        ");

        $notificationQuery->execute([$userId, $userId]);
    }

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'message' => 'User status updated successfully'
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