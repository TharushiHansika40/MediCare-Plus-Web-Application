<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/Appointment.php';

$auth = new AuthController();
$auth->requireRole('patient');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Verify CSRF token
if (!Security::validateCSRFToken($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

$appointmentId = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
$reason = Security::sanitizeInput($_POST['reason'] ?? '');

if (!$appointmentId) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID']);
    exit;
}

// Verify that the appointment belongs to the current user
$appointment = new Appointment();
$appointmentDetails = $appointment->getById($appointmentId);

if (!$appointmentDetails || $appointmentDetails['patient_id'] !== $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($appointmentDetails['status'] === 'completed') {
    echo json_encode(['success' => false, 'message' => 'Cannot cancel a completed appointment']);
    exit;
}

// Update appointment status
if ($appointment->updateStatus($appointmentId, 'cancelled', $_SESSION['user_id'])) {
    // Send notification emails
    $doctorQuery = $pdo->prepare(
        "SELECT u.email, u.first_name, u.last_name
         FROM users u
         JOIN doctors d ON u.id = d.user_id
         WHERE d.id = ?"
    );
    $doctorQuery->execute([$appointmentDetails['doctor_id']]);
    $doctorInfo = $doctorQuery->fetch();

    // Send email to doctor
    $doctorEmail = $doctorInfo['email'];
    $doctorSubject = 'Appointment Cancelled';
    $doctorMessage = "An appointment has been cancelled by the patient.\n\n";
    $doctorMessage .= "Patient: {$_SESSION['first_name']} {$_SESSION['last_name']}\n";
    $doctorMessage .= "Date: " . date('F j, Y', strtotime($appointmentDetails['date'])) . "\n";
    $doctorMessage .= "Time: " . date('g:i A', strtotime($appointmentDetails['start_time'])) . "\n";
    if ($reason) {
        $doctorMessage .= "Reason for cancellation: $reason\n";
    }

    // Send email to patient
    $patientEmail = $_SESSION['user_email'];
    $patientSubject = 'Appointment Cancellation Confirmation';
    $patientMessage = "Your appointment has been cancelled successfully.\n\n";
    $patientMessage .= "Doctor: Dr. {$doctorInfo['first_name']} {$doctorInfo['last_name']}\n";
    $patientMessage .= "Date: " . date('F j, Y', strtotime($appointmentDetails['date'])) . "\n";
    $patientMessage .= "Time: " . date('g:i A', strtotime($appointmentDetails['start_time'])) . "\n";

    // Send emails
    Utilities::sendEmail($doctorEmail, $doctorSubject, $doctorMessage);
    Utilities::sendEmail($patientEmail, $patientSubject, $patientMessage);

    echo json_encode(['success' => true, 'message' => 'Appointment cancelled successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to cancel appointment']);
}
?>