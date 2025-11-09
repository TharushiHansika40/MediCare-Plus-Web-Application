<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';

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
    
    if (!$input || !isset($input['faq_id'])) {
        throw new Exception('Invalid request data');
    }

    $faqId = filter_var($input['faq_id'], FILTER_VALIDATE_INT);

    if (!$faqId) {
        throw new Exception('Invalid FAQ ID');
    }

    // Check if FAQ exists
    $checkQuery = $pdo->prepare("SELECT id FROM faqs WHERE id = ?");
    $checkQuery->execute([$faqId]);
    if (!$checkQuery->fetch()) {
        throw new Exception('FAQ not found');
    }

    // Delete the FAQ
    $deleteQuery = $pdo->prepare("DELETE FROM faqs WHERE id = ?");
    $deleteQuery->execute([$faqId]);

    echo json_encode([
        'success' => true,
        'message' => 'FAQ deleted successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}