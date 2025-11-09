<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Security.php';
require_once __DIR__ . '/../models/User.php';

class AuthController {
    private $user;
    private $security;

    public function __construct() {
        $this->user = new User();
        session_start();
    }

    public function handleRegistration($data) {
        // Validate CSRF token
        if (!Security::validateCSRFToken($data['csrf_token'])) {
            return ['success' => false, 'message' => 'Invalid security token'];
        }

        // Sanitize inputs
        $email = Security::sanitizeInput($data['email']);
        $password = $data['password'];
        $role = Security::sanitizeInput($data['role']);
        $firstName = Security::sanitizeInput($data['first_name']);
        $lastName = Security::sanitizeInput($data['last_name']);
        $phone = Security::sanitizeInput($data['phone']);

        // Validate inputs
        if (!Security::validateEmail($email)) {
            return ['success' => false, 'message' => 'Invalid email address'];
        }

        if (!Security::validatePassword($password)) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters and contain uppercase, lowercase, and numbers'];
        }

        // Register user
        $userId = $this->user->register($email, $password, $role, $firstName, $lastName, $phone);
        
        if ($userId) {
            return ['success' => true, 'message' => 'Registration successful', 'user_id' => $userId];
        } else {
            return ['success' => false, 'message' => 'Registration failed. Email might already be in use.'];
        }
    }

    public function handleLogin($data) {
        // Validate CSRF token
        if (!Security::validateCSRFToken($data['csrf_token'])) {
            return ['success' => false, 'message' => 'Invalid security token'];
        }

        // Sanitize inputs
        $email = Security::sanitizeInput($data['email']);
        $password = $data['password'];

        // Attempt login
        $user = $this->user->login($email, $password);
        
        if ($user) {
            return ['success' => true, 'message' => 'Login successful', 'role' => $user['role']];
        } else {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
    }

    public function handleLogout() {
        $this->user->logout();
        return ['success' => true, 'message' => 'Logout successful'];
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return $this->user->getUserById($_SESSION['user_id']);
        }
        return null;
    }

    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: /login.php');
            exit();
        }
    }

    public function requireRole($roles) {
        $this->requireLogin();
        $roles = (array)$roles;
        if (!in_array($_SESSION['user_role'], $roles)) {
            header('Location: /unauthorized.php');
            exit();
        }
    }
}
?>