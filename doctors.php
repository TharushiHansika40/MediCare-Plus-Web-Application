<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/Doctor.php';
require_once __DIR__ . '/models/Service.php';

$doctorModel = new Doctor();
$serviceModel = new Service();

// Get search parameters
$search = [
    'specialization' => $_GET['specialization'] ?? '',
    'location' => $_GET['location'] ?? '',
    'name' => $_GET['name'] ?? '',
    'service' => $_GET['service'] ?? '',
    'page' => max(1, intval($_GET['page'] ?? 1)),
    'limit' => 10
];

// Get all services for filter
$services = $serviceModel->getAll();

// Get doctors based on search criteria
$doctors = $doctorModel->search($search);

$pageTitle = "Find Doctors";
include 'views/header.php';
?>

<div class="doctors-page">
    <div class="search-section">
        <h1>Find Your Doctor</h1>
        
        <div class="search-filters">
            <form action="/doctors.php" method="GET" class="filter-form">
                <div class="form-group">
                    <input type="text" name="name" placeholder="Search by name"
                           value="<?php echo htmlspecialchars($search['name']); ?>"
                           class="search-input">
                </div>

                <div class="form-group">
                    <select name="specialization" class="form-control">
                        <option value="">All Specializations</option>
                        <?php
                        $specializations = $pdo->query("SELECT DISTINCT specialization FROM doctors WHERE specialization IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($specializations as $spec):
                        ?>
                            <option value="<?php echo htmlspecialchars($spec); ?>"
                                    <?php echo $search['specialization'] === $spec ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($spec); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <select name="service" class="form-control">
                        <option value="">All Services</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?php echo $service['id']; ?>"
                                    <?php echo $search['service'] == $service['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($service['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <input type="text" name="location" placeholder="Location"
                           value="<?php echo htmlspecialchars($search['location']); ?>"
                           class="form-control">
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
            </form>
        </div>
    </div>

    <div class="doctors-grid">
        <?php if ($doctors): ?>
            <?php foreach ($doctors as $doctor): ?>
                <div class="doctor-card">
                    <div class="doctor-avatar">
                        <?php if ($doctor['avatar']): ?>
                            <img src="/public/uploads/avatars/<?php echo htmlspecialchars($doctor['avatar']); ?>"
                                 alt="Dr. <?php echo htmlspecialchars($doctor['display_name']); ?>">
                        <?php else: ?>
                            <div class="avatar-placeholder">
                                <i class="fas fa-user-md"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="doctor-info">
                        <h3 class="doctor-name">
                            Dr. <?php echo htmlspecialchars($doctor['display_name']); ?>
                        </h3>
                        
                        <p class="doctor-specialization">
                            <?php echo htmlspecialchars($doctor['specialization']); ?>
                        </p>

                        <div class="doctor-rating">
                            <?php
                            $rating = round($doctor['rating_avg']);
                            for ($i = 1; $i <= 5; $i++):
                            ?>
                                <i class="fas fa-star <?php echo $i <= $rating ? 'active' : ''; ?>"></i>
                            <?php endfor; ?>
                            <span>(<?php echo $doctor['rating_count']; ?> reviews)</span>
                        </div>

                        <div class="doctor-details">
                            <p>
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($doctor['location']); ?>
                            </p>
                            <p>
                                <i class="fas fa-graduation-cap"></i>
                                <?php echo $doctor['experience_years']; ?> years experience
                            </p>
                            <p>
                                <i class="fas fa-dollar-sign"></i>
                                Consultation fee: $<?php echo number_format($doctor['consultation_fee'], 2); ?>
                            </p>
                        </div>

                        <div class="doctor-actions">
                            <a href="/doctor.php?id=<?php echo $doctor['id']; ?>" 
                               class="btn btn-secondary">View Profile</a>
                            <button class="btn btn-primary"
                                    onclick="bookAppointment(<?php echo $doctor['id']; ?>)">
                                Book Appointment
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php
            // Add pagination
            $totalDoctors = $pdo->query("SELECT COUNT(*) FROM doctors")->fetchColumn();
            $totalPages = ceil($totalDoctors / $search['limit']);
            if ($totalPages > 1):
            ?>
                <div class="pagination">
                    <?php if ($search['page'] > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($search, ['page' => $search['page'] - 1])); ?>"
                           class="pagination-btn">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?<?php echo http_build_query(array_merge($search, ['page' => $i])); ?>"
                           class="page-number <?php echo $i === $search['page'] ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($search['page'] < $totalPages): ?>
                        <a href="?<?php echo http_build_query(array_merge($search, ['page' => $search['page'] + 1])); ?>"
                           class="pagination-btn">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="no-results">
                <i class="fas fa-search"></i>
                <h3>No doctors found</h3>
                <p>Try adjusting your search criteria</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Appointment Booking Modal -->
<div class="modal" id="bookingModal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title">Book Appointment</h3>
            <button class="modal-close" onclick="closeBookingModal()">×</button>
        </div>
        <div class="modal-body">
            <div id="bookingStep1" class="booking-step">
                <div class="calendar" id="appointmentCalendar"></div>
            </div>
            
            <div id="bookingStep2" class="booking-step" style="display: none;">
                <h4>Available Time Slots</h4>
                <div class="time-slots" id="timeSlots"></div>
            </div>
            
            <div id="bookingStep3" class="booking-step" style="display: none;">
                <h4>Appointment Details</h4>
                <form id="appointmentForm">
                    <div class="form-group">
                        <label for="reason">Reason for Visit</label>
                        <textarea id="reason" name="reason" rows="3" required></textarea>
                    </div>
                    
                    <div class="appointment-summary">
                        <h5>Summary</h5>
                        <p>Doctor: <span id="summaryDoctor"></span></p>
                        <p>Date: <span id="summaryDate"></span></p>
                        <p>Time: <span id="summaryTime"></span></p>
                        <p>Fee: <span id="summaryFee"></span></p>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="prevStep()" id="prevButton" style="display: none;">
                Previous
            </button>
            <button class="btn btn-primary" onclick="nextStep()" id="nextButton">
                Next
            </button>
        </div>
    </div>
</div>

<script>
let currentDoctor = null;
let currentStep = 1;
let selectedDate = null;
let selectedSlot = null;

function bookAppointment(doctorId) {
    currentDoctor = doctorId;
    currentStep = 1;
    selectedDate = null;
    selectedSlot = null;
    
    showStep(1);
    document.getElementById('bookingModal').style.display = 'flex';
    initializeCalendar();
}

function closeBookingModal() {
    document.getElementById('bookingModal').style.display = 'none';
    currentDoctor = null;
}

function showStep(step) {
    document.querySelectorAll('.booking-step').forEach(el => el.style.display = 'none');
    document.getElementById(`bookingStep${step}`).style.display = 'block';
    
    document.getElementById('prevButton').style.display = step > 1 ? 'block' : 'none';
    document.getElementById('nextButton').textContent = step === 3 ? 'Confirm Booking' : 'Next';
    
    currentStep = step;
}

function prevStep() {
    if (currentStep > 1) {
        showStep(currentStep - 1);
    }
}

function nextStep() {
    if (currentStep === 3) {
        confirmBooking();
        return;
    }
    
    if (currentStep === 1 && !selectedDate) {
        showNotification('Please select a date', 'error');
        return;
    }
    
    if (currentStep === 2 && !selectedSlot) {
        showNotification('Please select a time slot', 'error');
        return;
    }
    
    showStep(currentStep + 1);
}

function initializeCalendar() {
    // Calendar initialization code will be implemented
    // when we create the appointment booking system
}

function confirmBooking() {
    const reason = document.getElementById('reason').value;
    if (!reason) {
        showNotification('Please provide a reason for the visit', 'error');
        return;
    }
    
    ajaxRequest(
        '/appointments/book.php',
        'POST',
        {
            doctor_id: currentDoctor,
            slot_id: selectedSlot,
            reason: reason,
            csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
        }
    ).then(response => {
        if (response.success) {
            showNotification('Appointment booked successfully');
            setTimeout(() => window.location.href = '/patient/dashboard.php', 2000);
        } else {
            showNotification(response.message, 'error');
        }
        closeBookingModal();
    }).catch(error => {
        showNotification('An error occurred', 'error');
        closeBookingModal();
    });
}

// Handle modal overlay clicks
document.querySelector('.modal-overlay').addEventListener('click', function(e) {
    if (e.target === this) {
        closeBookingModal();
    }
});
</script>

<?php include 'views/footer.php'; ?>