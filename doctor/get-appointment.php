<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/Appointment.php';
require_once __DIR__ . '/../models/Doctor.php';

$auth = new AuthController();
$auth->requireRole('doctor');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

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
    $appointmentId = filter_input(INPUT_GET, 'appointment_id', FILTER_VALIDATE_INT);

    if (!$appointmentId) {
        throw new Exception('Invalid appointment ID');
    }

    // Get appointment details with patient information
    $query = $pdo->prepare("
        SELECT 
            a.*,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            CONCAT(u.first_name, ' ', u.last_name) as patient_name
        FROM appointments a
        JOIN users u ON u.id = a.patient_id
        WHERE a.id = ?
        AND a.doctor_id = ?
        AND a.status = 'confirmed'
    ");

    $query->execute([$appointmentId, $doctorProfile['id']]);
    $appointment = $query->fetch();

    if (!$appointment) {
        throw new Exception('Appointment not found or unauthorized');
    }

    // Get patient medical history if available
    $historyQuery = $pdo->prepare("
        SELECT 
            mr.*,
            d.display_name as doctor_name
        FROM medical_records mr
        JOIN doctors d ON d.id = mr.doctor_id
        WHERE mr.patient_id = ?
        ORDER BY mr.consultation_date DESC
        LIMIT 5
    ");

    $historyQuery->execute([$appointment['patient_id']]);
    $medicalHistory = $historyQuery->fetchAll();

    // Format response
    $response = [
        'success' => true,
        'appointment' => [
            'id' => $appointment['id'],
            'patient_name' => $appointment['patient_name'],
            'date' => $appointment['date'],
            'start_time' => $appointment['start_time'],
            'end_time' => $appointment['end_time'],
            'reason' => $appointment['reason'],
            'patient_info' => [
                'email' => $appointment['email'],
                'phone' => $appointment['phone']
            ],
            'medical_history' => array_map(function($record) {
                return [
                    'consultation_date' => $record['consultation_date'],
                    'doctor_name' => $record['doctor_name'],
                    'diagnosis' => $record['diagnosis'],
                    'prescriptions' => $record['prescriptions']
                ];
            }, $medicalHistory)
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}