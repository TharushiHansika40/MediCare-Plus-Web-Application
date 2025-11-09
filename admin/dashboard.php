<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Doctor.php';
require_once __DIR__ . '/../models/Appointment.php';

$auth = new AuthController();
$auth->requireRole('admin');

// Get statistics
$userModel = new User();
$doctorModel = new Doctor();
$appointmentModel = new Appointment();

$stats = [
    'total_patients' => $userModel->countByRole('patient'),
    'total_doctors' => $userModel->countByRole('doctor'),
    'total_appointments' => $appointmentModel->getTotalCount(),
    'pending_appointments' => $appointmentModel->getCountByStatus('pending'),
    'completed_appointments' => $appointmentModel->getCountByStatus('completed'),
    'total_revenue' => $appointmentModel->getTotalRevenue()
];

// Get recent activities
$recentActivities = $pdo->query("
    SELECT 
        'appointment' as type,
        a.id,
        CONCAT(p.first_name, ' ', p.last_name) as patient_name,
        CONCAT('Dr. ', d.display_name) as doctor_name,
        a.status,
        a.created_at
    FROM appointments a
    JOIN users p ON p.id = a.patient_id
    JOIN doctors d ON d.id = a.doctor_id
    WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    UNION ALL
    SELECT 
        'user' as type,
        u.id,
        CONCAT(u.first_name, ' ', u.last_name) as name,
        u.role as role,
        'registered' as status,
        u.created_at
    FROM users u
    WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY created_at DESC
    LIMIT 10
")->fetchAll();

// Get top performing doctors
$topDoctors = $pdo->query("
    SELECT 
        d.id,
        d.display_name,
        d.specialty,
        COUNT(a.id) as appointment_count,
        AVG(d.rating_avg) as rating
    FROM doctors d
    LEFT JOIN appointments a ON a.doctor_id = d.id
    WHERE a.status = 'completed'
    AND a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY d.id
    ORDER BY appointment_count DESC
    LIMIT 5
")->fetchAll();

$pageTitle = "Admin Dashboard";
include '../views/header.php';
?>

<div class="dashboard admin-dashboard">
    <div class="dashboard-header">
        <h1>Admin Dashboard</h1>
        <div class="dashboard-actions">
            <a href="/admin/users.php" class="btn btn-secondary">
                <i class="fas fa-users"></i> Manage Users
            </a>
            <a href="/admin/services.php" class="btn btn-secondary">
                <i class="fas fa-stethoscope"></i> Manage Services
            </a>
            <a href="/admin/content.php" class="btn btn-secondary">
                <i class="fas fa-edit"></i> Manage Content
            </a>
        </div>
    </div>

    <div class="dashboard-stats">
        <div class="dashboard-card">
            <div class="card-icon patients">
                <i class="fas fa-users"></i>
            </div>
            <div class="card-content">
                <h3 class="card-title">Total Patients</h3>
                <p class="card-value"><?php echo number_format($stats['total_patients']); ?></p>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-icon doctors">
                <i class="fas fa-user-md"></i>
            </div>
            <div class="card-content">
                <h3 class="card-title">Active Doctors</h3>
                <p class="card-value"><?php echo number_format($stats['total_doctors']); ?></p>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-icon appointments">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="card-content">
                <h3 class="card-title">Total Appointments</h3>
                <p class="card-value"><?php echo number_format($stats['total_appointments']); ?></p>
                <p class="card-subtitle">
                    <?php echo number_format($stats['pending_appointments']); ?> pending
                </p>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-icon revenue">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="card-content">
                <h3 class="card-title">Total Revenue</h3>
                <p class="card-value">$<?php echo number_format($stats['total_revenue'], 2); ?></p>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="dashboard-section">
            <h2>Recent Activity</h2>
            <div class="activity-feed">
                <?php foreach ($recentActivities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon <?php echo $activity['type']; ?>">
                            <i class="fas fa-<?php echo $activity['type'] === 'appointment' ? 'calendar' : 'user'; ?>"></i>
                        </div>
                        <div class="activity-content">
                            <?php if ($activity['type'] === 'appointment'): ?>
                                <p>
                                    New appointment <?php echo htmlspecialchars($activity['status']); ?> 
                                    between <?php echo htmlspecialchars($activity['patient_name']); ?> 
                                    and <?php echo htmlspecialchars($activity['doctor_name']); ?>
                                </p>
                            <?php else: ?>
                                <p>
                                    New <?php echo htmlspecialchars($activity['role']); ?> 
                                    <?php echo htmlspecialchars($activity['name']); ?> registered
                                </p>
                            <?php endif; ?>
                            <span class="activity-time">
                                <?php echo Utilities::timeAgo($activity['created_at']); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="dashboard-section">
            <h2>Top Performing Doctors</h2>
            <div class="doctor-list">
                <?php foreach ($topDoctors as $doctor): ?>
                    <div class="doctor-card">
                        <div class="doctor-info">
                            <h4><?php echo htmlspecialchars($doctor['display_name']); ?></h4>
                            <p class="specialty"><?php echo htmlspecialchars($doctor['specialty']); ?></p>
                            <div class="doctor-stats">
                                <span class="stat">
                                    <i class="fas fa-calendar-check"></i>
                                    <?php echo $doctor['appointment_count']; ?> appointments
                                </span>
                                <span class="stat">
                                    <i class="fas fa-star"></i>
                                    <?php echo number_format($doctor['rating'], 1); ?> rating
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="dashboard-section">
        <h2>Performance Analytics</h2>
        <div class="analytics-grid">
            <div class="chart-container">
                <h3>Appointments Overview</h3>
                <canvas id="appointmentsChart"></canvas>
            </div>
            <div class="chart-container">
                <h3>Revenue Trend</h3>
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
    </div>
</div>

<style>
.admin-dashboard .dashboard-grid {
    display: grid;
    grid-template-columns: 3fr 2fr;
    gap: 2rem;
    margin: 2rem 0;
}

.activity-feed {
    background: #fff;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.activity-item {
    display: flex;
    align-items: start;
    padding: 1rem 0;
    border-bottom: 1px solid #eee;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    flex-shrink: 0;
}

.activity-icon.appointment {
    background: var(--primary-color-light);
    color: var(--primary-color);
}

.activity-icon.user {
    background: var(--secondary-color-light);
    color: var(--secondary-color);
}

.activity-content {
    flex: 1;
}

.activity-time {
    font-size: 0.85rem;
    color: #666;
}

.doctor-list {
    display: grid;
    gap: 1rem;
}

.doctor-card {
    background: #fff;
    border-radius: 8px;
    padding: 1rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.doctor-info h4 {
    margin: 0 0 0.5rem 0;
    color: var(--primary-color);
}

.specialty {
    font-size: 0.9rem;
    color: #666;
    margin: 0 0 0.5rem 0;
}

.doctor-stats {
    display: flex;
    gap: 1rem;
}

.stat {
    font-size: 0.9rem;
    color: #444;
}

.stat i {
    color: var(--primary-color);
    margin-right: 0.25rem;
}

.analytics-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 2rem;
    margin-top: 1rem;
}

.chart-container {
    background: #fff;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.chart-container h3 {
    margin: 0 0 1rem 0;
    font-size: 1.1rem;
}

@media (max-width: 992px) {
    .admin-dashboard .dashboard-grid {
        grid-template-columns: 1fr;
    }

    .analytics-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Initialize charts
document.addEventListener('DOMContentLoaded', function() {
    // Appointments chart
    const appointmentsCtx = document.getElementById('appointmentsChart').getContext('2d');
    new Chart(appointmentsCtx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Completed',
                data: [65, 59, 80, 81, 56, 55],
                borderColor: '#4CAF50',
                tension: 0.1
            }, {
                label: 'Cancelled',
                data: [28, 48, 40, 19, 86, 27],
                borderColor: '#f44336',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        }
    });

    // Revenue chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Revenue',
                data: [4500, 5900, 8000, 8100, 5600, 5500],
                backgroundColor: '#2196F3'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        }
    });
});
</script>

<?php include '../views/footer.php'; ?>