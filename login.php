<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/controllers/AuthController.php';

$auth = new AuthController();

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    $role = $_SESSION['user_role'];
    header("Location: /dashboard/$role");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $auth->handleLogin($_POST);
    if ($result['success']) {
        header("Location: /dashboard/" . $result['role']);
        exit();
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Medicare</title>
    <link rel="stylesheet" href="/public/css/styles.css">
</head>
<body>
    <div class="container">
        <div class="auth-form">
            <h2>Login to Medicare</h2>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="/login.php" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="btn btn-primary">Login</button>
            </form>

            <p class="auth-links">
                Don't have an account? <a href="/register.php">Register here</a>
                <br>
                <a href="/forgot-password.php">Forgot Password?</a>
            </p>
        </div>
    </div>

    <script src="/public/js/validation.js"></script>
    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;

            if (!isValidEmail(email)) {
                e.preventDefault();
                showError('email', 'Please enter a valid email address');
            }

            if (password.length < 8) {
                e.preventDefault();
                showError('password', 'Password must be at least 8 characters');
            }
        });
    </script>
</body>
</html>