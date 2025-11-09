<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';

$auth = new AuthController();
$auth->requireRole('admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

try {
    $faqId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    
    if (!$faqId) {
        throw new Exception('Invalid FAQ ID');
    }

    $query = $pdo->prepare("
        SELECT * FROM faqs WHERE id = ?
    ");

    $query->execute([$faqId]);
    $faq = $query->fetch();

    if (!$faq) {
        throw new Exception('FAQ not found');
    }

    echo json_encode([
        'success' => true,
        'faq' => $faq
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}