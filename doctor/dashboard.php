<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/Appointment.php';
require_once __DIR__ . '/../models/Doctor.php';

$auth = new AuthController();
$auth->requireRole('doctor');

$currentUser = $auth->getCurrentUser();

// Get doctor profile
$doctorModel = new Doctor();
$doctorQuery = $pdo->prepare("SELECT * FROM doctors WHERE user_id = ?");
$doctorQuery->execute([$currentUser['id']]);
$doctorProfile = $doctorQuery->fetch();

if (!$doctorProfile) {
    die('Doctor profile not found');
}

// Get today's appointments
$appointmentModel = new Appointment();
$todayAppointments = $appointmentModel->getByDoctor($doctorProfile['id'], 'confirmed');

// Filter appointments for today
$todayAppointments = array_filter($todayAppointments, function($apt) {
    return $apt['date'] === date('Y-m-d');
});

// Get pending appointment requests
$pendingAppointments = $appointmentModel->getByDoctor($doctorProfile['id'], 'pending');

$pageTitle = "Doctor Dashboard";
include '../views/header.php';
?>

<div class="dashboard doctor-dashboard">
    <div class="dashboard-header">
        <h1>Welcome, Dr. <?php echo htmlspecialchars($doctorProfile['display_name']); ?>!</h1>
        <a href="/doctor/profile.php" class="btn btn-secondary">
            <i class="fas fa-user-edit"></i> Edit Profile
        </a>
    </div>

    <div class="dashboard-stats">
        <div class="dashboard-card">
            <div class="card-icon">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="card-content">
                <h3 class="card-title">Today's Appointments</h3>
                <p class="card-value"><?php echo count($todayAppointments); ?></p>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-icon pending">
                <i class="fas fa-clock"></i>
            </div>
            <div class="card-content">
                <h3 class="card-title">Pending Requests</h3>
                <p class="card-value"><?php echo count($pendingAppointments); ?></p>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-icon rating">
                <i class="fas fa-star"></i>
            </div>
            <div class="card-content">
                <h3 class="card-title">Rating</h3>
                <p class="card-value">
                    <?php echo number_format($doctorProfile['rating_avg'], 1); ?>
                    <small>(<?php echo $doctorProfile['rating_count']; ?> reviews)</small>
                </p>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="dashboard-section">
            <h2>Today's Schedule</h2>
            <?php if ($todayAppointments): ?>
                <div class="list-container">
                    <?php foreach ($todayAppointments as $appointment): ?>
                        <div class="list-item">
                            <div class="list-item-main">
                                <div class="list-item-icon">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="list-item-content">
                                    <h4 class="list-item-title">
                                        <?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?>
                                    </h4>
                                    <p class="list-item-description">
                                        <?php echo date('g:i A', strtotime($appointment['start_time'])); ?> - 
                                        <?php echo date('g:i A', strtotime($appointment['end_time'])); ?>
                                    </p>
                                    <?php if ($appointment['reason']): ?>
                                        <p class="appointment-reason">
                                            <?php echo htmlspecialchars($appointment['reason']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="list-item-actions">
                                <button class="btn btn-primary"
                                        onclick="startConsultation(<?php echo $appointment['id']; ?>)">
                                    Start Consultation
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-data">No appointments scheduled for today</p>
            <?php endif; ?>
        </div>

        <div class="dashboard-section">
            <h2>Pending Requests</h2>
            <?php if ($pendingAppointments): ?>
                <div class="list-container">
                    <?php foreach ($pendingAppointments as $appointment): ?>
                        <div class="list-item">
                            <div class="list-item-main">
                                <div class="list-item-icon">
                                    <i class="fas fa-user-clock"></i>
                                </div>
                                <div class="list-item-content">
                                    <h4 class="list-item-title">
                                        <?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?>
                                    </h4>
                                    <p class="list-item-description">
                                        <?php echo date('l, F j, Y', strtotime($appointment['date'])); ?><br>
                                        <?php echo date('g:i A', strtotime($appointment['start_time'])); ?> - 
                                        <?php echo date('g:i A', strtotime($appointment['end_time'])); ?>
                                    </p>
                                    <?php if ($appointment['reason']): ?>
                                        <p class="appointment-reason">
                                            <?php echo htmlspecialchars($appointment['reason']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="list-item-actions">
                                <button class="btn btn-secondary"
                                        onclick="respondToRequest(<?php echo $appointment['id']; ?>, 'decline')">
                                    Decline
                                </button>
                                <button class="btn btn-primary"
                                        onclick="respondToRequest(<?php echo $appointment['id']; ?>, 'confirm')">
                                    Confirm
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-data">No pending appointment requests</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-section">
        <h2>Availability Management</h2>
        <div class="availability-manager">
            <div class="calendar-section">
                <div id="availabilityCalendar" class="calendar"></div>
            </div>
            <div class="time-slots-section">
                <h3>Time Slots</h3>
                <div id="timeSlots" class="time-slots"></div>
                <button class="btn btn-primary" onclick="addTimeSlot()">
                    <i class="fas fa-plus"></i> Add Time Slot
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Time Slot Modal -->
<div class="modal" id="addSlotModal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title">Add Time Slot</h3>
            <button class="modal-close" onclick="closeModal('addSlotModal')">×</button>
        </div>
        <div class="modal-body">
            <form id="addSlotForm">
                <div class="form-group">
                    <label for="slotDate">Date</label>
                    <input type="date" id="slotDate" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="startTime">Start Time</label>
                    <input type="time" id="startTime" required>
                </div>
                
                <div class="form-group">
                    <label for="endTime">End Time</label>
                    <input type="time" id="endTime" required>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="repeatWeekly"> Repeat weekly
                    </label>
                    <div id="repeatOptions" style="display: none;">
                        <label for="repeatUntil">Repeat until</label>
                        <input type="date" id="repeatUntil" 
                               min="<?php echo date('Y-m-d', strtotime('+1 week')); ?>">
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('addSlotModal')">Cancel</button>
            <button class="btn btn-primary" onclick="saveTimeSlot()">Save</button>
        </div>
    </div>
</div>

<!-- Consultation Modal -->
<div class="modal" id="consultationModal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title">Patient Consultation</h3>
            <button class="modal-close" onclick="closeModal('consultationModal')">×</button>
        </div>
        <div class="modal-body">
            <div id="patientInfo"></div>
            <form id="consultationForm">
                <div class="form-group">
                    <label for="consultationNotes">Consultation Notes</label>
                    <textarea id="consultationNotes" rows="4" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="prescriptions">Prescriptions</label>
                    <textarea id="prescriptions" rows="4"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="followUpDate">Follow-up Date (if needed)</label>
                    <input type="date" id="followUpDate" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                </div>
                
                <div class="form-group">
                    <label>Upload Medical Report</label>
                    <div class="file-upload">
                        <div class="file-upload-preview">
                            <i class="fas fa-file-medical"></i>
                            <p>Click to upload medical report</p>
                        </div>
                        <input type="file" id="medicalReport" accept=".pdf,.doc,.docx">
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('consultationModal')">Save Draft</button>
            <button class="btn btn-primary" onclick="completeConsultation()">Complete & Send</button>
        </div>
    </div>
</div>

<style>
/* Additional styles for doctor dashboard */
.doctor-dashboard .dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 2rem;
    margin: 2rem 0;
}

.availability-manager {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 2rem;
    background: #fff;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.time-slots {
    display: grid;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.time-slot {
    background: #f8f9fa;
    padding: 0.75rem;
    border-radius: 4px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.time-slot-info {
    font-size: 0.9rem;
}

.time-slot-actions {
    display: flex;
    gap: 0.5rem;
}

.appointment-reason {
    font-size: 0.9rem;
    color: #666;
    margin-top: 0.5rem;
    font-style: italic;
}

.calendar-section {
    background: #fff;
    border-radius: 8px;
    padding: 1rem;
}

@media (max-width: 992px) {
    .availability-manager {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .doctor-dashboard .dashboard-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Availability calendar initialization
function initializeAvailabilityCalendar() {
    // Calendar initialization code will be implemented
}

function addTimeSlot() {
    document.getElementById('addSlotModal').style.display = 'flex';
}

function saveTimeSlot() {
    const date = document.getElementById('slotDate').value;
    const startTime = document.getElementById('startTime').value;
    const endTime = document.getElementById('endTime').value;
    const repeatWeekly = document.getElementById('repeatWeekly').checked;
    const repeatUntil = repeatWeekly ? document.getElementById('repeatUntil').value : null;

    if (!date || !startTime || !endTime) {
        showNotification('Please fill in all required fields', 'error');
        return;
    }

    if (repeatWeekly && !repeatUntil) {
        showNotification('Please specify repeat end date', 'error');
        return;
    }

    ajaxRequest(
        '/doctor/availability.php',
        'POST',
        {
            date,
            start_time: startTime,
            end_time: endTime,
            repeat_weekly: repeatWeekly,
            repeat_until: repeatUntil,
            csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
        }
    ).then(response => {
        if (response.success) {
            showNotification('Time slot(s) added successfully');
            closeModal('addSlotModal');
            initializeAvailabilityCalendar(); // Refresh calendar
        } else {
            showNotification(response.message, 'error');
        }
    }).catch(error => {
        showNotification('Failed to add time slot', 'error');
    });
}

function respondToRequest(appointmentId, action) {
    ajaxRequest(
        '/doctor/respond-appointment.php',
        'POST',
        {
            appointment_id: appointmentId,
            action,
            csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
        }
    ).then(response => {
        if (response.success) {
            showNotification('Appointment ' + action + 'ed successfully');
            setTimeout(() => window.location.reload(), 2000);
        } else {
            showNotification(response.message, 'error');
        }
    }).catch(error => {
        showNotification('Failed to process request', 'error');
    });
}

function startConsultation(appointmentId) {
    // Fetch appointment details
    ajaxRequest(
        '/doctor/get-appointment.php',
        'GET',
        { appointment_id: appointmentId }
    ).then(response => {
        if (response.success) {
            const appointment = response.appointment;
            document.getElementById('patientInfo').innerHTML = `
                <div class="patient-info">
                    <h4>Patient Information</h4>
                    <p><strong>Name:</strong> ${appointment.patient_name}</p>
                    <p><strong>Reason:</strong> ${appointment.reason || 'Not specified'}</p>
                </div>
            `;
            document.getElementById('consultationModal').style.display = 'flex';
            // Store appointment ID for form submission
            document.getElementById('consultationForm').dataset.appointmentId = appointmentId;
        } else {
            showNotification(response.message, 'error');
        }
    }).catch(error => {
        showNotification('Failed to load appointment details', 'error');
    });
}

function completeConsultation() {
    const form = document.getElementById('consultationForm');
    const appointmentId = form.dataset.appointmentId;
    const notes = document.getElementById('consultationNotes').value;
    const prescriptions = document.getElementById('prescriptions').value;
    const followUpDate = document.getElementById('followUpDate').value;
    const reportFile = document.getElementById('medicalReport').files[0];

    if (!notes) {
        showNotification('Please enter consultation notes', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('appointment_id', appointmentId);
    formData.append('notes', notes);
    formData.append('prescriptions', prescriptions);
    formData.append('follow_up_date', followUpDate);
    formData.append('report', reportFile);
    formData.append('csrf_token', '<?php echo Security::generateCSRFToken(); ?>');

    // Show loading state
    const submitButton = document.querySelector('#consultationModal .btn-primary');
    const originalText = submitButton.textContent;
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    fetch('/doctor/complete-consultation.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Consultation completed successfully');
            setTimeout(() => window.location.reload(), 2000);
        } else {
            showNotification(data.message, 'error');
            submitButton.disabled = false;
            submitButton.textContent = originalText;
        }
    })
    .catch(error => {
        showNotification('Failed to save consultation', 'error');
        submitButton.disabled = false;
        submitButton.textContent = originalText;
    });
}

// Handle repeat weekly checkbox
document.getElementById('repeatWeekly').addEventListener('change', function() {
    document.getElementById('repeatOptions').style.display = 
        this.checked ? 'block' : 'none';
});

// Initialize components
document.addEventListener('DOMContentLoaded', function() {
    initializeAvailabilityCalendar();
});
</script>

<?php include '../views/footer.php'; ?>