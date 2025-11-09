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
    
    if (!$input || !isset($input['user_id'])) {
        throw new Exception('Invalid request data');
    }

    $userId = filter_var($input['user_id'], FILTER_VALIDATE_INT);

    if (!$userId) {
        throw new Exception('Invalid user ID');
    }

    // Don't allow deleting your own account
    if ($userId === $auth->getCurrentUser()['id']) {
        throw new Exception('Cannot delete your own account');
    }

    $userModel = new User();
    $user = $userModel->getById($userId);

    if (!$user) {
        throw new Exception('User not found');
    }

    // Don't allow deleting other admin accounts
    if ($user['role'] === 'admin' && $userId !== $auth->getCurrentUser()['id']) {
        throw new Exception('Cannot delete other admin accounts');
    }

    $pdo->beginTransaction();

    // Cancel all pending appointments for doctors
    if ($user['role'] === 'doctor') {
        $appointmentQuery = $pdo->prepare("
            UPDATE appointments 
            SET status = 'cancelled',
                updated_at = NOW()
            WHERE doctor_id = (SELECT id FROM doctors WHERE user_id = ?)
            AND status IN ('pending', 'confirmed')
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
                    ' has been cancelled as the doctor is no longer available'),
                'appointment_cancelled',
                NOW()
            FROM appointments 
            WHERE doctor_id = (SELECT id FROM doctors WHERE user_id = ?)
            AND status = 'cancelled'
        ");

        $notificationQuery->execute([$userId, $userId]);

        // Delete doctor record
        $doctorQuery = $pdo->prepare("DELETE FROM doctors WHERE user_id = ?");
        $doctorQuery->execute([$userId]);
    }

    // Cancel all pending appointments for patients
    if ($user['role'] === 'patient') {
        $appointmentQuery = $pdo->prepare("
            UPDATE appointments 
            SET status = 'cancelled',
                updated_at = NOW()
            WHERE patient_id = ?
            AND status IN ('pending', 'confirmed')
        ");

        $appointmentQuery->execute([$userId]);

        // Notify doctors of cancelled appointments
        $notificationQuery = $pdo->prepare("
            INSERT INTO notifications (
                user_id,
                title,
                message,
                type,
                created_at
            )
            SELECT 
                d.user_id,
                'Appointment Cancelled',
                CONCAT('Your appointment with ', 
                    ?,
                    ' has been cancelled as the patient account was removed'),
                'appointment_cancelled',
                NOW()
            FROM appointments a
            JOIN doctors d ON d.id = a.doctor_id
            WHERE a.patient_id = ?
            AND a.status = 'cancelled'
        ");

        $notificationQuery->execute([
            $user['first_name'] . ' ' . $user['last_name'],
            $userId
        ]);
    }

    // Delete user's notifications
    $notifQuery = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
    $notifQuery->execute([$userId]);

    // Delete user's medical records
    if ($user['role'] === 'patient') {
        $recordsQuery = $pdo->prepare("DELETE FROM medical_records WHERE patient_id = ?");
        $recordsQuery->execute([$userId]);
    } elseif ($user['role'] === 'doctor') {
        $recordsQuery = $pdo->prepare("
            DELETE FROM medical_records 
            WHERE doctor_id = (SELECT id FROM doctors WHERE user_id = ?)
        ");
        $recordsQuery->execute([$userId]);
    }

    // Finally delete the user
    $deleteQuery = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $deleteQuery->execute([$userId]);

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'message' => 'User deleted successfully'
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