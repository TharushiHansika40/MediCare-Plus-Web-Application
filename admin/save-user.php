<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Doctor.php';

$auth = new AuthController();
$auth->requireRole('admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

Security::validateCSRFToken();

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid request data');
    }

    // Validate required fields
    $requiredFields = ['first_name', 'last_name', 'email', 'role', 'status'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Additional validation
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    if (!in_array($input['role'], ['patient', 'doctor', 'admin'])) {
        throw new Exception('Invalid role');
    }

    if (!in_array($input['status'], ['active', 'inactive', 'pending'])) {
        throw new Exception('Invalid status');
    }

    $pdo->beginTransaction();

    // Check if email is already taken by another user
    $emailQuery = $pdo->prepare("
        SELECT id FROM users 
        WHERE email = ? AND id != ?
    ");
    $emailQuery->execute([$input['email'], $input['id'] ?? 0]);
    if ($emailQuery->fetch()) {
        throw new Exception('Email is already taken');
    }

    $userModel = new User();
    
    if (empty($input['id'])) {
        // Creating new user
        if (empty($input['password'])) {
            throw new Exception('Password is required for new users');
        }

        $userId = $userModel->create([
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'role' => $input['role'],
            'status' => $input['status'],
            'phone' => $input['phone'] ?? null
        ]);

    } else {
        // Updating existing user
        $userId = $input['id'];
        $updateData = [
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'email' => $input['email'],
            'role' => $input['role'],
            'status' => $input['status'],
            'phone' => $input['phone'] ?? null
        ];

        // Only update password if provided
        if (!empty($input['password'])) {
            $updateData['password'] = password_hash($input['password'], PASSWORD_DEFAULT);
        }

        $userModel->update($userId, $updateData);
    }

    // Handle doctor-specific information
    if ($input['role'] === 'doctor') {
        $doctorModel = new Doctor();
        $doctorInfo = $input['doctor_info'] ?? [];

        if (empty($doctorInfo['display_name']) || empty($doctorInfo['specialty'])) {
            throw new Exception('Display name and specialty are required for doctors');
        }

        // Check if doctor record exists
        $doctorQuery = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ?");
        $doctorQuery->execute([$userId]);
        $existingDoctor = $doctorQuery->fetch();

        if ($existingDoctor) {
            // Update existing doctor
            $updateQuery = $pdo->prepare("
                UPDATE doctors 
                SET display_name = ?,
                    specialty = ?,
                    qualification = ?,
                    experience = ?,
                    updated_at = NOW()
                WHERE user_id = ?
            ");

            $updateQuery->execute([
                $doctorInfo['display_name'],
                $doctorInfo['specialty'],
                $doctorInfo['qualification'] ?? null,
                $doctorInfo['experience'] ?? null,
                $userId
            ]);

        } else {
            // Create new doctor
            $insertQuery = $pdo->prepare("
                INSERT INTO doctors (
                    user_id,
                    display_name,
                    specialty,
                    qualification,
                    experience,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");

            $insertQuery->execute([
                $userId,
                $doctorInfo['display_name'],
                $doctorInfo['specialty'],
                $doctorInfo['qualification'] ?? null,
                $doctorInfo['experience'] ?? null
            ]);
        }
    }

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'message' => empty($input['id']) ? 'User created successfully' : 'User updated successfully'
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