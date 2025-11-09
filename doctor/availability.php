<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/Doctor.php';

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
    $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);
    $startTime = filter_input(INPUT_POST, 'start_time', FILTER_SANITIZE_STRING);
    $endTime = filter_input(INPUT_POST, 'end_time', FILTER_SANITIZE_STRING);
    $repeatWeekly = filter_input(INPUT_POST, 'repeat_weekly', FILTER_VALIDATE_BOOLEAN);
    $repeatUntil = filter_input(INPUT_POST, 'repeat_until', FILTER_SANITIZE_STRING);

    if (!$date || !$startTime || !$endTime) {
        throw new Exception('Missing required fields');
    }

    // Validate time format
    if (!strtotime($startTime) || !strtotime($endTime)) {
        throw new Exception('Invalid time format');
    }

    // Convert to DateTime for comparison
    $startDateTime = new DateTime($date . ' ' . $startTime);
    $endDateTime = new DateTime($date . ' ' . $endTime);

    if ($startDateTime >= $endDateTime) {
        throw new Exception('End time must be after start time');
    }

    // Check if slot overlaps with existing slots
    $overlappingQuery = $pdo->prepare("
        SELECT COUNT(*) FROM doctor_availability 
        WHERE doctor_id = ? 
        AND date = ? 
        AND (
            (start_time < ? AND end_time > ?) OR
            (start_time < ? AND end_time > ?) OR
            (start_time >= ? AND end_time <= ?)
        )
    ");

    // If repeating weekly, calculate all dates
    $dates = [$date];
    if ($repeatWeekly && $repeatUntil) {
        $currentDate = new DateTime($date);
        $endDate = new DateTime($repeatUntil);
        
        while ($currentDate <= $endDate) {
            $currentDate->modify('+1 week');
            if ($currentDate <= $endDate) {
                $dates[] = $currentDate->format('Y-m-d');
            }
        }
    }

    $pdo->beginTransaction();

    foreach ($dates as $slotDate) {
        // Check for overlaps
        $overlappingQuery->execute([
            $doctorProfile['id'],
            $slotDate,
            $endTime,
            $startTime,
            $endTime,
            $startTime,
            $startTime,
            $endTime
        ]);

        if ($overlappingQuery->fetchColumn() > 0) {
            throw new Exception("Time slot overlaps with existing availability on " . $slotDate);
        }

        // Insert availability
        $insertQuery = $pdo->prepare("
            INSERT INTO doctor_availability (
                doctor_id,
                date,
                start_time,
                end_time,
                created_at
            ) VALUES (?, ?, ?, ?, NOW())
        ");

        $insertQuery->execute([
            $doctorProfile['id'],
            $slotDate,
            $startTime,
            $endTime
        ]);
    }

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Availability added successfully'
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