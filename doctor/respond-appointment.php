<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/Doctor.php';
require_once __DIR__ . '/../models/Appointment.php';

$auth = new AuthController();
$auth->requireRole('doctor');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

Security::validateCSRFToken();

try {
    $currentUser = $auth->getCurrentUser();
    
    // Get doctor profile
    $doctorModel = new Doctor();
    $doctorQuery = $pdo->prepare("SELECT * FROM doctors WHERE user_id = ?");
    $doctorQuery->execute([$currentUser['id']]);
    $doctorProfile = $doctorQuery->fetch();

    if (!$doctorProfile) {
        throw new Exception('Doctor profile not found');
    }

    // Validate input
    $appointmentId = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);

    if (!$appointmentId || !in_array($action, ['confirm', 'decline'])) {
        throw new Exception('Invalid request parameters');
    }

    // Get appointment
    $appointmentModel = new Appointment();
    $appointment = $appointmentModel->getById($appointmentId);

    if (!$appointment) {
        throw new Exception('Appointment not found');
    }

    // Verify doctor owns this appointment
    if ($appointment['doctor_id'] !== $doctorProfile['id']) {
        throw new Exception('Unauthorized access to appointment');
    }

    // Verify appointment is in pending state
    if ($appointment['status'] !== 'pending') {
        throw new Exception('Appointment is not in pending state');
    }

    $pdo->beginTransaction();

    // Update appointment status
    $status = $action === 'confirm' ? 'confirmed' : 'declined';
    $updateQuery = $pdo->prepare("
        UPDATE appointments 
        SET status = ?, 
            updated_at = NOW(),
            responded_at = NOW()
        WHERE id = ?
    ");

    $updateQuery->execute([$status, $appointmentId]);

    // If confirming, check and update availability
    if ($action === 'confirm') {
        // Mark availability as booked
        $availabilityQuery = $pdo->prepare("
            UPDATE doctor_availability
            SET is_booked = TRUE
            WHERE doctor_id = ?
            AND date = ?
            AND start_time = ?
            AND end_time = ?
        ");

        $availabilityQuery->execute([
            $doctorProfile['id'],
            $appointment['date'],
            $appointment['start_time'],
            $appointment['end_time']
        ]);

        if ($availabilityQuery->rowCount() === 0) {
            throw new Exception('Failed to update availability');
        }
    }

    // Send notification to patient
    $notificationTitle = $action === 'confirm' 
        ? 'Appointment Confirmed'
        : 'Appointment Declined';
    
    $notificationMessage = $action === 'confirm'
        ? "Your appointment with Dr. {$doctorProfile['display_name']} on {$appointment['date']} at {$appointment['start_time']} has been confirmed."
        : "Your appointment request with Dr. {$doctorProfile['display_name']} has been declined.";

    $notificationQuery = $pdo->prepare("
        INSERT INTO notifications (
            user_id,
            title,
            message,
            type,
            created_at
        ) VALUES (?, ?, ?, ?, NOW())
    ");

    $notificationQuery->execute([
        $appointment['patient_id'],
        $notificationTitle,
        $notificationMessage,
        'appointment_' . $status
    ]);

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Appointment ' . $action . 'ed successfully'
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