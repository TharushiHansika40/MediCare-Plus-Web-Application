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
    
    if (!$input) {
        throw new Exception('Invalid request data');
    }

    // Validate required fields
    $requiredFields = ['category', 'question', 'answer'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $currentUser = $auth->getCurrentUser();

    if (empty($input['id'])) {
        // Create new FAQ
        $insertQuery = $pdo->prepare("
            INSERT INTO faqs (
                category,
                question,
                answer,
                display_order,
                created_by,
                updated_by,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $insertQuery->execute([
            $input['category'],
            $input['question'],
            $input['answer'],
            $input['display_order'] ?? 999,
            $currentUser['id'],
            $currentUser['id']
        ]);

        $faqId = $pdo->lastInsertId();

    } else {
        // Update existing FAQ
        $faqId = $input['id'];
        
        // Check if FAQ exists
        $checkQuery = $pdo->prepare("SELECT id FROM faqs WHERE id = ?");
        $checkQuery->execute([$faqId]);
        if (!$checkQuery->fetch()) {
            throw new Exception('FAQ not found');
        }

        $updateQuery = $pdo->prepare("
            UPDATE faqs 
            SET category = ?,
                question = ?,
                answer = ?,
                display_order = ?,
                updated_by = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $updateQuery->execute([
            $input['category'],
            $input['question'],
            $input['answer'],
            $input['display_order'] ?? 999,
            $currentUser['id'],
            $faqId
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => empty($input['id']) ? 'FAQ created successfully' : 'FAQ updated successfully',
        'faq_id' => $faqId
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}