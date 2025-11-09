<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/Service.php';

$auth = new AuthController();
$auth->requireRole('admin');

// Get all services with their specialties
$query = $pdo->query("
    SELECT 
        s.*,
        COUNT(d.id) as doctor_count
    FROM services s
    LEFT JOIN doctors d ON FIND_IN_SET(s.id, d.services)
    GROUP BY s.id
    ORDER BY s.name ASC
");

$services = $query->fetchAll();

// Get specialties for filtering
$specialtyQuery = $pdo->query("
    SELECT DISTINCT specialty 
    FROM doctors 
    WHERE specialty IS NOT NULL 
    ORDER BY specialty
");
$specialties = $specialtyQuery->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = "Service Management";
include '../views/header.php';
?>

<div class="admin-section">
    <div class="section-header">
        <h1>Service Management</h1>
        <button class="btn btn-primary" onclick="showAddServiceModal()">
            <i class="fas fa-plus"></i> Add New Service
        </button>
    </div>

    <div class="service-grid">
        <?php foreach ($services as $service): ?>
            <div class="service-card" id="service-<?php echo $service['id']; ?>">
                <div class="service-header">
                    <h3><?php echo htmlspecialchars($service['name']); ?></h3>
                    <div class="service-actions">
                        <button class="btn btn-icon" 
                                onclick="editService(<?php echo $service['id']; ?>)"
                                title="Edit Service">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-icon delete" 
                                onclick="deleteService(<?php echo $service['id']; ?>)"
                                title="Delete Service">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>

                <div class="service-content">
                    <p class="service-description">
                        <?php echo nl2br(htmlspecialchars($service['description'])); ?>
                    </p>
                    
                    <div class="service-meta">
                        <div class="meta-item">
                            <i class="fas fa-dollar-sign"></i>
                            <span><?php echo number_format($service['price'], 2); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-clock"></i>
                            <span><?php echo $service['duration']; ?> minutes</span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-user-md"></i>
                            <span><?php echo $service['doctor_count']; ?> doctors</span>
                        </div>
                    </div>

                    <?php if ($service['specialties']): ?>
                        <div class="service-specialties">
                            <?php foreach (explode(',', $service['specialties']) as $specialty): ?>
                                <span class="specialty-tag">
                                    <?php echo htmlspecialchars(trim($specialty)); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="service-footer">
                    <span class="service-status <?php echo $service['status']; ?>">
                        <?php echo ucfirst($service['status']); ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add/Edit Service Modal -->
<div class="modal" id="serviceModal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title">Add New Service</h3>
            <button class="modal-close" onclick="closeModal('serviceModal')">×</button>
        </div>
        <div class="modal-body">
            <form id="serviceForm" onsubmit="saveService(event)">
                <input type="hidden" id="serviceId">
                
                <div class="form-group">
                    <label for="serviceName">Service Name</label>
                    <input type="text" id="serviceName" required>
                </div>

                <div class="form-group">
                    <label for="serviceDescription">Description</label>
                    <textarea id="serviceDescription" rows="4" required></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="servicePrice">Price ($)</label>
                        <input type="number" id="servicePrice" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="serviceDuration">Duration (minutes)</label>
                        <input type="number" id="serviceDuration" min="15" step="15" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="serviceSpecialties">Required Specialties</label>
                    <select id="serviceSpecialties" multiple>
                        <?php foreach ($specialties as $specialty): ?>
                            <option value="<?php echo htmlspecialchars($specialty); ?>">
                                <?php echo htmlspecialchars($specialty); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Hold Ctrl/Cmd to select multiple specialties</small>
                </div>

                <div class="form-group">
                    <label for="serviceStatus">Status</label>
                    <select id="serviceStatus" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('serviceModal')">Cancel</button>
            <button class="btn btn-primary" onclick="document.getElementById('serviceForm').requestSubmit()">Save</button>
        </div>
    </div>
</div>

<style>
.service-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
}

.service-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    flex-direction: column;
}

.service-header {
    padding: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: start;
    border-bottom: 1px solid #eee;
}

.service-header h3 {
    margin: 0;
    color: var(--primary-color);
}

.service-content {
    padding: 1.5rem;
    flex: 1;
}

.service-description {
    margin: 0 0 1rem 0;
    color: #444;
    line-height: 1.5;
}

.service-meta {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #666;
}

.meta-item i {
    color: var(--primary-color);
}

.service-specialties {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 1rem;
}

.specialty-tag {
    background: #f0f0f0;
    color: #444;
    padding: 0.25rem 0.75rem;
    border-radius: 16px;
    font-size: 0.9rem;
}

.service-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid #eee;
}

.service-status {
    padding: 0.25rem 0.75rem;
    border-radius: 16px;
    font-size: 0.85rem;
    font-weight: 500;
}

.service-status.active {
    background: #E8F5E9;
    color: #388E3C;
}

.service-status.inactive {
    background: #FFEBEE;
    color: #D32F2F;
}

@media (max-width: 768px) {
    .service-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function showAddServiceModal() {
    document.getElementById('serviceForm').reset();
    document.getElementById('serviceId').value = '';
    document.querySelector('#serviceModal .modal-title').textContent = 'Add New Service';
    document.getElementById('serviceModal').style.display = 'flex';
}

function editService(serviceId) {
    // Fetch service data
    fetch(`/admin/get-service.php?id=${serviceId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const service = data.service;
                document.getElementById('serviceId').value = service.id;
                document.getElementById('serviceName').value = service.name;
                document.getElementById('serviceDescription').value = service.description;
                document.getElementById('servicePrice').value = service.price;
                document.getElementById('serviceDuration').value = service.duration;
                document.getElementById('serviceStatus').value = service.status;

                // Set specialties
                const specialtiesSelect = document.getElementById('serviceSpecialties');
                const specialties = service.specialties ? service.specialties.split(',') : [];
                Array.from(specialtiesSelect.options).forEach(option => {
                    option.selected = specialties.includes(option.value.trim());
                });

                document.querySelector('#serviceModal .modal-title').textContent = 'Edit Service';
                document.getElementById('serviceModal').style.display = 'flex';
            }
        });
}

function saveService(event) {
    event.preventDefault();

    const serviceId = document.getElementById('serviceId').value;
    const specialtiesSelect = document.getElementById('serviceSpecialties');
    const selectedSpecialties = Array.from(specialtiesSelect.selectedOptions)
        .map(option => option.value);

    const formData = {
        id: serviceId,
        name: document.getElementById('serviceName').value,
        description: document.getElementById('serviceDescription').value,
        price: parseFloat(document.getElementById('servicePrice').value),
        duration: parseInt(document.getElementById('serviceDuration').value),
        specialties: selectedSpecialties.join(','),
        status: document.getElementById('serviceStatus').value,
        csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
    };

    // Show loading state
    const submitBtn = document.querySelector('#serviceModal .btn-primary');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    fetch('/admin/save-service.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(
                serviceId ? 'Service updated successfully' : 'Service created successfully'
            );
            setTimeout(() => window.location.reload(), 2000);
        } else {
            showNotification(data.message, 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    })
    .catch(error => {
        showNotification('Failed to save service', 'error');
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    });
}

function deleteService(serviceId) {
    if (!confirm('Are you sure you want to delete this service? This action cannot be undone.')) {
        return;
    }

    fetch('/admin/delete-service.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            service_id: serviceId,
            csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Service deleted successfully');
            const serviceCard = document.getElementById(`service-${serviceId}`);
            serviceCard.style.opacity = '0';
            setTimeout(() => {
                serviceCard.style.height = '0';
                serviceCard.style.margin = '0';
                serviceCard.style.padding = '0';
                setTimeout(() => serviceCard.remove(), 300);
            }, 300);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('Failed to delete service', 'error');
    });
}
</script>

<?php include '../views/footer.php'; ?>