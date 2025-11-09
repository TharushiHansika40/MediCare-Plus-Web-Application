<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/Appointment.php';

$auth = new AuthController();
$auth->requireRole('patient');

$currentUser = $auth->getCurrentUser();
$appointments = new Appointment();

// Get upcoming appointments
$upcomingAppointments = $appointments->getByPatient($currentUser['id'], 'confirmed');

// Get past appointments
$pastAppointments = $appointments->getByPatient($currentUser['id'], 'completed');

$pageTitle = "Patient Dashboard";
include '../views/header.php';
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Welcome, <?php echo htmlspecialchars($currentUser['first_name']); ?>!</h1>
    </div>

    <div class="dashboard-stats">
        <div class="dashboard-card">
            <div class="card-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="card-content">
                <h3 class="card-title">Upcoming Appointments</h3>
                <p class="card-value"><?php echo count($upcomingAppointments); ?></p>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-icon">
                <i class="fas fa-history"></i>
            </div>
            <div class="card-content">
                <h3 class="card-title">Past Appointments</h3>
                <p class="card-value"><?php echo count($pastAppointments); ?></p>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-icon">
                <i class="fas fa-file-medical"></i>
            </div>
            <div class="card-content">
                <h3 class="card-title">Medical Reports</h3>
                <p class="card-value">
                    <?php
                    // Count medical reports
                    $reportsQuery = $pdo->prepare(
                        "SELECT COUNT(*) FROM medical_reports mr
                         JOIN appointments a ON mr.appointment_id = a.id
                         WHERE a.patient_id = ?"
                    );
                    $reportsQuery->execute([$currentUser['id']]);
                    echo $reportsQuery->fetchColumn();
                    ?>
                </p>
            </div>
        </div>
    </div>

    <div class="dashboard-section">
        <h2>Upcoming Appointments</h2>
        <?php if ($upcomingAppointments): ?>
            <div class="list-container">
                <?php foreach ($upcomingAppointments as $appointment): ?>
                    <div class="list-item">
                        <div class="list-item-main">
                            <div class="list-item-icon">
                                <i class="fas fa-user-md"></i>
                            </div>
                            <div class="list-item-content">
                                <h4 class="list-item-title">
                                    Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?>
                                </h4>
                                <p class="list-item-description">
                                    <?php 
                                    echo htmlspecialchars($appointment['specialization']); 
                                    echo " • ";
                                    echo date('l, F j, Y', strtotime($appointment['date']));
                                    echo " at ";
                                    echo date('g:i A', strtotime($appointment['start_time']));
                                    ?>
                                </p>
                            </div>
                        </div>
                        <div class="list-item-actions">
                            <button class="btn btn-secondary" 
                                    onclick="rescheduleAppointment(<?php echo $appointment['id']; ?>)">
                                Reschedule
                            </button>
                            <button class="btn btn-danger" 
                                    onclick="cancelAppointment(<?php echo $appointment['id']; ?>)">
                                Cancel
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="no-data">No upcoming appointments</p>
            <a href="/doctors.php" class="btn btn-primary">Book an Appointment</a>
        <?php endif; ?>
    </div>

    <div class="dashboard-section">
        <h2>Recent Medical Reports</h2>
        <?php
        $reportsQuery = $pdo->prepare(
            "SELECT mr.*, a.date as appointment_date, d.display_name as doctor_name
             FROM medical_reports mr
             JOIN appointments a ON mr.appointment_id = a.id
             JOIN doctors d ON a.doctor_id = d.id
             WHERE a.patient_id = ?
             ORDER BY mr.created_at DESC
             LIMIT 5"
        );
        $reportsQuery->execute([$currentUser['id']]);
        $reports = $reportsQuery->fetchAll();
        ?>

        <?php if ($reports): ?>
            <div class="list-container">
                <?php foreach ($reports as $report): ?>
                    <div class="list-item">
                        <div class="list-item-main">
                            <div class="list-item-icon">
                                <i class="fas fa-file-medical"></i>
                            </div>
                            <div class="list-item-content">
                                <h4 class="list-item-title">
                                    <?php echo htmlspecialchars($report['title']); ?>
                                </h4>
                                <p class="list-item-description">
                                    By Dr. <?php echo htmlspecialchars($report['doctor_name']); ?> •
                                    <?php echo date('F j, Y', strtotime($report['appointment_date'])); ?>
                                </p>
                            </div>
                        </div>
                        <div class="list-item-actions">
                            <a href="/reports/download.php?id=<?php echo $report['id']; ?>" 
                               class="btn btn-primary">
                                <i class="fas fa-download"></i> Download
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <a href="/reports/list.php" class="btn btn-link">View All Reports</a>
        <?php else: ?>
            <p class="no-data">No medical reports available</p>
        <?php endif; ?>
    </div>
</div>

<!-- Reschedule Appointment Modal -->
<div class="modal" id="rescheduleModal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title">Reschedule Appointment</h3>
            <button class="modal-close" onclick="closeModal('rescheduleModal')">×</button>
        </div>
        <div class="modal-body">
            <div class="calendar" id="appointmentCalendar"></div>
            <div class="time-slots" id="timeSlots"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('rescheduleModal')">Cancel</button>
            <button class="btn btn-primary" id="confirmReschedule">Confirm</button>
        </div>
    </div>
</div>

<!-- Cancel Appointment Modal -->
<div class="modal" id="cancelModal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title">Cancel Appointment</h3>
            <button class="modal-close" onclick="closeModal('cancelModal')">×</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to cancel this appointment?</p>
            <div class="form-group">
                <label for="cancelReason">Reason for cancellation:</label>
                <textarea id="cancelReason" rows="3" class="form-control"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('cancelModal')">No, Keep It</button>
            <button class="btn btn-danger" id="confirmCancel">Yes, Cancel</button>
        </div>
    </div>
</div>

<script>
let currentAppointmentId = null;

function rescheduleAppointment(appointmentId) {
    currentAppointmentId = appointmentId;
    document.getElementById('rescheduleModal').style.display = 'flex';
    initializeCalendar();
}

function cancelAppointment(appointmentId) {
    currentAppointmentId = appointmentId;
    document.getElementById('cancelModal').style.display = 'flex';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    currentAppointmentId = null;
}

document.getElementById('confirmCancel').addEventListener('click', function() {
    if (!currentAppointmentId) return;

    const reason = document.getElementById('cancelReason').value;
    
    ajaxRequest(
        '/appointments/cancel.php',
        'POST',
        {
            appointment_id: currentAppointmentId,
            reason: reason,
            csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
        }
    ).then(response => {
        if (response.success) {
            showNotification('Appointment cancelled successfully');
            setTimeout(() => window.location.reload(), 2000);
        } else {
            showNotification(response.message, 'error');
        }
        closeModal('cancelModal');
    }).catch(error => {
        showNotification('An error occurred', 'error');
        closeModal('cancelModal');
    });
});

function initializeCalendar() {
    // Calendar initialization code here
    // This will be implemented when we create the appointment booking system
}

// Close modals when clicking outside
window.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.style.display = 'none';
        });
        currentAppointmentId = null;
    }
});
</script>

<?php include '../views/footer.php'; ?>