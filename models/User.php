<?php
require_once __DIR__ . '/../config/config.php';

class User {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function register($email, $password, $role, $firstName, $lastName, $phone) {
        try {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            
            $stmt = $this->pdo->prepare(
                "INSERT INTO users (email, password_hash, role, first_name, last_name, phone) 
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            
            $stmt->execute([$email, $password_hash, $role, $firstName, $lastName, $phone]);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            return false;
        }
    }

    public function login($email, $password) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Update last login
                $updateStmt = $this->pdo->prepare(
                    "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?"
                );
                $updateStmt->execute([$user['id']]);

                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_email'] = $user['email'];
                
                // Regenerate session ID for security
                session_regenerate_id(true);
                
                return $user;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }

    public function getUserById($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Get user error: " . $e->getMessage());
            return false;
        }
    }

    public function updateProfile($id, $firstName, $lastName, $phone) {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE users 
                 SET first_name = ?, last_name = ?, phone = ? 
                 WHERE id = ?"
            );
            return $stmt->execute([$firstName, $lastName, $phone, $id]);
        } catch (PDOException $e) {
            error_log("Update profile error: " . $e->getMessage());
            return false;
        }
    }

    public function changePassword($id, $newPassword) {
        try {
            $password_hash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $this->pdo->prepare(
                "UPDATE users SET password_hash = ? WHERE id = ?"
            );
            return $stmt->execute([$password_hash, $id]);
        } catch (PDOException $e) {
            error_log("Change password error: " . $e->getMessage());
            return false;
        }
    }

    public function logout() {
        $_SESSION = array();
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time()-3600, '/');
        }
        session_destroy();
    }
}
?>