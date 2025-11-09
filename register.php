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
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $auth->handleRegistration($_POST);
    if ($result['success']) {
        $success = $result['message'];
        header("refresh:3;url=/login.php"); // Redirect to login after 3 seconds
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
    <title>Register - Medicare</title>
    <link rel="stylesheet" href="/public/css/styles.css">
</head>
<body>
    <div class="container">
        <div class="auth-form">
            <h2>Register for Medicare</h2>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <br>
                    Redirecting to login page...
                </div>
            <?php endif; ?>
            
            <form method="POST" action="/register.php" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                
                <div class="form-group">
                    <label for="role">Register as</label>
                    <select id="role" name="role" required>
                        <option value="patient">Patient</option>
                        <option value="doctor">Doctor</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" required>
                </div>

                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                    <small>Must be at least 8 characters with uppercase, lowercase, and numbers</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <button type="submit" class="btn btn-primary">Register</button>
            </form>

            <p class="auth-links">
                Already have an account? <a href="/login.php">Login here</a>
            </p>
        </div>
    </div>

    <script src="/public/js/validation.js"></script>
    <script>
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const firstName = document.getElementById('first_name').value;
            const lastName = document.getElementById('last_name').value;
            const phone = document.getElementById('phone').value;

            let hasError = false;

            if (!isValidEmail(email)) {
                showError('email', 'Please enter a valid email address');
                hasError = true;
            }

            if (!isValidPassword(password)) {
                showError('password', 'Password must be at least 8 characters with uppercase, lowercase, and numbers');
                hasError = true;
            }

            if (password !== confirmPassword) {
                showError('confirm_password', 'Passwords do not match');
                hasError = true;
            }

            if (!isValidName(firstName)) {
                showError('first_name', 'Please enter a valid first name');
                hasError = true;
            }

            if (!isValidName(lastName)) {
                showError('last_name', 'Please enter a valid last name');
                hasError = true;
            }

            if (!isValidPhone(phone)) {
                showError('phone', 'Please enter a valid phone number');
                hasError = true;
            }

            if (hasError) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>