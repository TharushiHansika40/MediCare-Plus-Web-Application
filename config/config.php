<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'medicare_db');

// Application constants
define('SITE_URL', 'http://localhost/medicare');
define('UPLOAD_PATH', __DIR__ . '/../public/uploads/');

// Session security
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CSRF Token settings
define('CSRF_TOKEN_SECRET', 'your-secret-key');
define('CSRF_TOKEN_LIFETIME', 3600); // 1 hour

// Initialize database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>