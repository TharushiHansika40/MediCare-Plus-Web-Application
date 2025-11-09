<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/models/Appointment.php';
require_once __DIR__ . '/models/Doctor.php';

$auth = new AuthController();
$auth->requireRole('patient');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!Security::validateCSRFToken($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    }

    // Validate input
    $doctorId = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
    $slotId = filter_input(INPUT_POST, 'slot_id', FILTER_VALIDATE_INT);
    $reason = Security::sanitizeInput($_POST['reason']);

    if (!$doctorId || !$slotId || !$reason) {
        echo json_encode(['success' => false, 'message' => 'Invalid input parameters']);
        exit;
    }

    // Create appointment
    $appointment = new Appointment();
    $result = $appointment->create([
        'patient_id' => $_SESSION['user_id'],
        'doctor_id' => $doctorId,
        'slot_id' => $slotId,
        'reason' => $reason
    ]);

    if ($result['success']) {
        // Send email notifications
        $doctor = new Doctor();
        $doctorInfo = $doctor->getById($doctorId);
        
        // Get patient info
        $patientInfo = $auth->getCurrentUser();

        // Send email to doctor
        $doctorEmail = $doctorInfo['email'];
        $doctorSubject = 'New Appointment Request';
        $doctorMessage = "You have a new appointment request from {$patientInfo['first_name']} {$patientInfo['last_name']}.\n\n";
        $doctorMessage .= "Please log in to your dashboard to confirm or decline the appointment.";
        
        // Send email to patient
        $patientEmail = $patientInfo['email'];
        $patientSubject = 'Appointment Booking Confirmation';
        $patientMessage = "Your appointment request has been received and is pending confirmation.\n\n";
        $patientMessage .= "You will receive another email once the doctor confirms your appointment.";

        // Send emails (assuming we have a working email system)
        Utilities::sendEmail($doctorEmail, $doctorSubject, $doctorMessage);
        Utilities::sendEmail($patientEmail, $patientSubject, $patientMessage);

        echo json_encode([
            'success' => true,
            'message' => 'Appointment booked successfully',
            'appointment_id' => $result['appointment_id']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['message']
        ]);
    }
    exit;
}

// Handle GET request to get available slots
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $doctorId = filter_input(INPUT_GET, 'doctor_id', FILTER_VALIDATE_INT);
    $date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_STRING);

    if (!$doctorId || !$date || !Utilities::validateDateTime($date)) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }

    // Get available slots
    $query = $pdo->prepare(
        "SELECT id, start_time, end_time 
         FROM availability_slots 
         WHERE doctor_id = ? 
         AND date = ? 
         AND slot_status = 'available'
         ORDER BY start_time"
    );
    $query->execute([$doctorId, $date]);
    $slots = $query->fetchAll();

    echo json_encode([
        'success' => true,
        'slots' => $slots
    ]);
    exit;
}
?>