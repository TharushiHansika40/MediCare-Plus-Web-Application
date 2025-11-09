<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';

$auth = new AuthController();
$auth->requireRole('patient');

$reportId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$reportId) {
    die('Invalid report ID');
}

// Verify that the report belongs to the current user's appointment
$query = $pdo->prepare(
    "SELECT mr.*, a.patient_id
     FROM medical_reports mr
     JOIN appointments a ON mr.appointment_id = a.id
     WHERE mr.id = ? AND a.patient_id = ?"
);
$query->execute([$reportId, $_SESSION['user_id']]);
$report = $query->fetch();

if (!$report) {
    die('Unauthorized access');
}

$filePath = __DIR__ . '/../public/uploads/reports/' . $report['file_path'];

if (!file_exists($filePath)) {
    die('File not found');
}

// Get file extension
$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

// Set appropriate Content-Type header
$contentTypes = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];

$contentType = $contentTypes[$extension] ?? 'application/octet-stream';

// Set headers for file download
header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . basename($report['file_path']) . '"');
header('Content-Length: ' . filesize($filePath));

// Disable output buffering
while (ob_get_level()) {
    ob_end_clean();
}

// Output file contents
readfile($filePath);
exit;
?>