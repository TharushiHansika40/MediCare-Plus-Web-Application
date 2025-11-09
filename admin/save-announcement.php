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
    $requiredFields = ['title', 'type', 'content'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Validate type
    if (!in_array($input['type'], ['info', 'alert', 'success', 'warning'])) {
        throw new Exception('Invalid announcement type');
    }

    $currentUser = $auth->getCurrentUser();

    if (empty($input['id'])) {
        // Create new announcement
        $insertQuery = $pdo->prepare("
            INSERT INTO announcements (
                title,
                content,
                type,
                target_patients,
                target_doctors,
                is_pinned,
                created_by,
                updated_by,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $insertQuery->execute([
            $input['title'],
            $input['content'],
            $input['type'],
            $input['target_patients'] ?? true,
            $input['target_doctors'] ?? true,
            $input['is_pinned'] ?? false,
            $currentUser['id'],
            $currentUser['id']
        ]);

        $announcementId = $pdo->lastInsertId();

        // Send notifications to targeted users
        $notifications = [];
        if ($input['target_patients'] ?? true) {
            $notifications[] = [
                'role' => 'patient',
                'title' => 'New Announcement',
                'message' => $input['title']
            ];
        }
        if ($input['target_doctors'] ?? true) {
            $notifications[] = [
                'role' => 'doctor',
                'title' => 'New Announcement',
                'message' => $input['title']
            ];
        }

        foreach ($notifications as $notification) {
            $notifyQuery = $pdo->prepare("
                INSERT INTO notifications (
                    user_id,
                    title,
                    message,
                    type,
                    created_at
                )
                SELECT 
                    id,
                    ?,
                    ?,
                    'announcement',
                    NOW()
                FROM users 
                WHERE role = ?
                AND status = 'active'
            ");

            $notifyQuery->execute([
                $notification['title'],
                $notification['message'],
                $notification['role']
            ]);
        }

    } else {
        // Update existing announcement
        $announcementId = $input['id'];
        
        // Check if announcement exists
        $checkQuery = $pdo->prepare("SELECT id FROM announcements WHERE id = ?");
        $checkQuery->execute([$announcementId]);
        if (!$checkQuery->fetch()) {
            throw new Exception('Announcement not found');
        }

        $updateQuery = $pdo->prepare("
            UPDATE announcements 
            SET title = ?,
                content = ?,
                type = ?,
                target_patients = ?,
                target_doctors = ?,
                is_pinned = ?,
                updated_by = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $updateQuery->execute([
            $input['title'],
            $input['content'],
            $input['type'],
            $input['target_patients'] ?? true,
            $input['target_doctors'] ?? true,
            $input['is_pinned'] ?? false,
            $currentUser['id'],
            $announcementId
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => empty($input['id']) ? 'Announcement created successfully' : 'Announcement updated successfully',
        'announcement_id' => $announcementId
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}