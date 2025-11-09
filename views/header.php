<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';

$auth = new AuthController();
$currentUser = $auth->getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Medicare'; ?></title>
    <link rel="stylesheet" href="/public/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="/public/js/notifications.js"></script>
</head>
<body>
    <header class="main-header">
        <div class="container">
            <nav class="nav-main">
                <div class="logo">
                    <a href="/">Medicare</a>
                </div>
                
                <ul class="nav-links">
                    <li><a href="/services.php">Services</a></li>
                    <li><a href="/doctors.php">Find Doctors</a></li>
                    <?php if ($currentUser): ?>
                        <?php if ($currentUser['role'] === 'admin'): ?>
                            <li><a href="/admin/dashboard.php">Admin Dashboard</a></li>
                        <?php elseif ($currentUser['role'] === 'doctor'): ?>
                            <li><a href="/doctor/dashboard.php">Doctor Dashboard</a></li>
                        <?php else: ?>
                            <li><a href="/patient/dashboard.php">My Dashboard</a></li>
                        <?php endif; ?>
                        <li>
                            <a href="/messages.php" class="relative">
                                Messages
                                <span id="notificationBadge" class="notification-badge hidden">0</span>
                            </a>
                        </li>
                        <li class="dropdown">
                            <a href="#" class="dropdown-toggle">
                                <?php echo htmlspecialchars($currentUser['first_name']); ?> 
                                <i class="fas fa-chevron-down"></i>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a href="/profile.php">Profile</a></li>
                                <li><a href="/appointments/list.php">Appointments</a></li>
                                <li><a href="/logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li><a href="/login.php">Login</a></li>
                        <li><a href="/register.php" class="btn btn-primary">Register</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
    <?php if ($currentUser): ?>
    <script>
        // Initialize notifications for logged-in users
        document.addEventListener('DOMContentLoaded', function() {
            startNotificationPolling();
        });
    </script>
    <?php endif; ?>

    <main class="main-content">
        <div class="container">