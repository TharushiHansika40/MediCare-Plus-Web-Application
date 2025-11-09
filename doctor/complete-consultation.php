<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/Doctor.php';
require_once __DIR__ . '/../config/Utilities.php';

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
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
    $prescriptions = filter_input(INPUT_POST, 'prescriptions', FILTER_SANITIZE_STRING);
    $followUpDate = filter_input(INPUT_POST, 'follow_up_date', FILTER_SANITIZE_STRING);

    if (!$appointmentId || !$notes) {
        throw new Exception('Missing required fields');
    }

    // Get appointment details
    $appointmentQuery = $pdo->prepare("
        SELECT * FROM appointments 
        WHERE id = ? 
        AND doctor_id = ? 
        AND status = 'confirmed'
    ");

    $appointmentQuery->execute([$appointmentId, $doctorProfile['id']]);
    $appointment = $appointmentQuery->fetch();

    if (!$appointment) {
        throw new Exception('Appointment not found or unauthorized');
    }

    // Handle file upload
    $reportPath = null;
    if (isset($_FILES['report']) && $_FILES['report']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['report'];
        
        // Validate file type
        $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Invalid file type. Only PDF and DOC files are allowed.');
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = sprintf(
            'report_%s_%s.%s',
            $appointment['patient_id'],
            date('Ymd_His'),
            $extension
        );

        // Create year/month directories if they don't exist
        $yearMonth = date('Y/m');
        $uploadDir = __DIR__ . '/../public/uploads/medical_reports/' . $yearMonth;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $reportPath = 'uploads/medical_reports/' . $yearMonth . '/' . $filename;
        $fullPath = __DIR__ . '/../public/' . $reportPath;

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            throw new Exception('Failed to upload medical report');
        }
    }

    $pdo->beginTransaction();

    // Create medical record
    $recordQuery = $pdo->prepare("
        INSERT INTO medical_records (
            patient_id,
            doctor_id,
            appointment_id,
            consultation_date,
            diagnosis,
            prescriptions,
            report_file,
            follow_up_date,
            created_at
        ) VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, NOW())
    ");

    $recordQuery->execute([
        $appointment['patient_id'],
        $doctorProfile['id'],
        $appointmentId,
        $notes,
        $prescriptions,
        $reportPath,
        $followUpDate
    ]);

    // Update appointment status
    $updateQuery = $pdo->prepare("
        UPDATE appointments 
        SET status = 'completed',
            completed_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");

    $updateQuery->execute([$appointmentId]);

    // Create notification for patient
    $notificationQuery = $pdo->prepare("
        INSERT INTO notifications (
            user_id,
            title,
            message,
            type,
            created_at
        ) VALUES (?, ?, ?, ?, NOW())
    ");

    $notificationMessage = "Your consultation with Dr. {$doctorProfile['display_name']} is complete. ";
    if ($reportPath) {
        $notificationMessage .= "A medical report has been uploaded. ";
    }
    if ($followUpDate) {
        $notificationMessage .= "A follow-up appointment is recommended on " . date('F j, Y', strtotime($followUpDate));
    }

    $notificationQuery->execute([
        $appointment['patient_id'],
        'Consultation Completed',
        $notificationMessage,
        'consultation_completed'
    ]);

    // If follow-up date is set, create availability automatically
    if ($followUpDate) {
        $availabilityQuery = $pdo->prepare("
            INSERT INTO doctor_availability (
                doctor_id,
                date,
                start_time,
                end_time,
                created_at
            ) VALUES (?, ?, ?, ?, NOW())
        ");

        // Use the same time slot as the current appointment
        $availabilityQuery->execute([
            $doctorProfile['id'],
            $followUpDate,
            $appointment['start_time'],
            $appointment['end_time']
        ]);
    }

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Consultation completed successfully'
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