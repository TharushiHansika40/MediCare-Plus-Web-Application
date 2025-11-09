<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Doctor.php';

$auth = new AuthController();
$auth->requireRole('admin');

// Get filters from query string
$role = filter_input(INPUT_GET, 'role', FILTER_SANITIZE_STRING) ?: 'all';
$status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING) ?: 'all';
$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING);
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$perPage = 10;

// Build query conditions
$conditions = [];
$params = [];

if ($role !== 'all') {
    $conditions[] = "u.role = ?";
    $params[] = $role;
}

if ($status !== 'all') {
    $conditions[] = "u.status = ?";
    $params[] = $status;
}

if ($search) {
    $conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

$whereClause = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

// Get total count for pagination
$countQuery = $pdo->prepare("
    SELECT COUNT(*) 
    FROM users u 
    $whereClause
");
$countQuery->execute($params);
$totalUsers = $countQuery->fetchColumn();
$totalPages = ceil($totalUsers / $perPage);

// Get users with pagination
$offset = ($page - 1) * $perPage;
$query = $pdo->prepare("
    SELECT 
        u.*,
        COALESCE(d.display_name, '') as doctor_name,
        COALESCE(d.specialty, '') as specialty,
        (
            SELECT COUNT(*) 
            FROM appointments a 
            WHERE (a.patient_id = u.id OR a.doctor_id = COALESCE(d.id, 0))
        ) as appointment_count
    FROM users u
    LEFT JOIN doctors d ON d.user_id = u.id AND u.role = 'doctor'
    $whereClause
    ORDER BY u.created_at DESC
    LIMIT ? OFFSET ?
");

$params[] = $perPage;
$params[] = $offset;
$query->execute($params);
$users = $query->fetchAll();

$pageTitle = "User Management";
include '../views/header.php';
?>

<div class="admin-section">
    <div class="section-header">
        <h1>User Management</h1>
        <button class="btn btn-primary" onclick="showAddUserModal()">
            <i class="fas fa-user-plus"></i> Add New User
        </button>
    </div>

    <div class="filters-container">
        <form class="filters-form" method="GET">
            <div class="form-group">
                <label for="role">Role</label>
                <select name="role" id="role" onchange="this.form.submit()">
                    <option value="all" <?php echo $role === 'all' ? 'selected' : ''; ?>>All Roles</option>
                    <option value="patient" <?php echo $role === 'patient' ? 'selected' : ''; ?>>Patients</option>
                    <option value="doctor" <?php echo $role === 'doctor' ? 'selected' : ''; ?>>Doctors</option>
                    <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admins</option>
                </select>
            </div>

            <div class="form-group">
                <label for="status">Status</label>
                <select name="status" id="status" onchange="this.form.submit()">
                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                </select>
            </div>

            <div class="form-group search-group">
                <input type="text" name="search" id="search" 
                       value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Search users...">
                <button type="submit" class="btn btn-secondary">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Activity</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <div class="user-info">
                                <div class="user-avatar">
                                    <?php if ($user['avatar']): ?>
                                        <img src="/public/uploads/avatars/<?php echo htmlspecialchars($user['avatar']); ?>"
                                             alt="<?php echo htmlspecialchars($user['first_name']); ?>">
                                    <?php else: ?>
                                        <div class="avatar-placeholder">
                                            <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="user-details">
                                    <span class="user-name">
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </span>
                                    <?php if ($user['role'] === 'doctor' && $user['doctor_name']): ?>
                                        <span class="user-subtitle">
                                            <?php echo htmlspecialchars($user['specialty']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <span class="badge role-<?php echo $user['role']; ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge status-<?php echo $user['status']; ?>">
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="date-info" title="<?php echo $user['created_at']; ?>">
                                <?php echo Utilities::timeAgo($user['created_at']); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo $user['appointment_count']; ?> appointments
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-icon" 
                                        onclick="editUser(<?php echo $user['id']; ?>)"
                                        title="Edit User">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($user['status'] === 'active'): ?>
                                    <button class="btn btn-icon" 
                                            onclick="updateUserStatus(<?php echo $user['id']; ?>, 'inactive')"
                                            title="Deactivate User">
                                        <i class="fas fa-user-slash"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-icon" 
                                            onclick="updateUserStatus(<?php echo $user['id']; ?>, 'active')"
                                            title="Activate User">
                                        <i class="fas fa-user-check"></i>
                                    </button>
                                <?php endif; ?>
                                <button class="btn btn-icon delete" 
                                        onclick="deleteUser(<?php echo $user['id']; ?>)"
                                        title="Delete User">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&role=<?php echo $role; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>" 
                   class="btn btn-secondary">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
            <?php endif; ?>

            <div class="page-numbers">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="current-page"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&role=<?php echo $role; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?>&role=<?php echo $role; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>" 
                   class="btn btn-secondary">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Add/Edit User Modal -->
<div class="modal" id="userModal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title">Add New User</h3>
            <button class="modal-close" onclick="closeModal('userModal')">×</button>
        </div>
        <div class="modal-body">
            <form id="userForm" onsubmit="saveUser(event)">
                <input type="hidden" id="userId">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="firstName">First Name</label>
                        <input type="text" id="firstName" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="lastName">Last Name</label>
                        <input type="text" id="lastName" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="userRole">Role</label>
                        <select id="userRole" required onchange="toggleDoctorFields()">
                            <option value="patient">Patient</option>
                            <option value="doctor">Doctor</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="userStatus">Status</label>
                        <select id="userStatus" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                </div>

                <div id="doctorFields" style="display: none;">
                    <div class="form-group">
                        <label for="displayName">Display Name</label>
                        <input type="text" id="displayName">
                    </div>

                    <div class="form-group">
                        <label for="specialty">Specialty</label>
                        <input type="text" id="specialty">
                    </div>

                    <div class="form-group">
                        <label for="qualification">Qualification</label>
                        <input type="text" id="qualification">
                    </div>

                    <div class="form-group">
                        <label for="experience">Years of Experience</label>
                        <input type="number" id="experience" min="0">
                    </div>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone">
                </div>

                <div class="form-group" id="passwordGroup">
                    <label for="password">Password</label>
                    <input type="password" id="password">
                    <small class="help-text">Leave blank to keep current password when editing</small>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('userModal')">Cancel</button>
            <button class="btn btn-primary" onclick="document.getElementById('userForm').requestSubmit()">Save</button>
        </div>
    </div>
</div>

<style>
.admin-section {
    padding: 2rem;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.filters-container {
    background: #fff;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.filters-form {
    display: flex;
    gap: 1rem;
    align-items: flex-end;
}

.search-group {
    display: flex;
    gap: 0.5rem;
    flex: 1;
}

.search-group input {
    flex: 1;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    overflow: hidden;
}

.avatar-placeholder {
    width: 100%;
    height: 100%;
    background: var(--primary-color);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

.user-details {
    display: flex;
    flex-direction: column;
}

.user-subtitle {
    font-size: 0.85rem;
    color: #666;
}

.badge {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.85rem;
    font-weight: 500;
}

.role-admin { background: #E3F2FD; color: #1976D2; }
.role-doctor { background: #E8F5E9; color: #388E3C; }
.role-patient { background: #FFF3E0; color: #F57C00; }

.status-active { background: #E8F5E9; color: #388E3C; }
.status-inactive { background: #FFEBEE; color: #D32F2F; }
.status-pending { background: #FFF3E0; color: #F57C00; }

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.btn-icon {
    width: 32px;
    height: 32px;
    padding: 0;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    margin-top: 2rem;
}

.page-numbers {
    display: flex;
    gap: 0.5rem;
}

.current-page {
    background: var(--primary-color);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 4px;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

@media (max-width: 768px) {
    .filters-form {
        flex-direction: column;
    }

    .search-group {
        width: 100%;
    }

    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function showAddUserModal() {
    document.getElementById('userForm').reset();
    document.getElementById('userId').value = '';
    document.getElementById('passwordGroup').style.display = 'block';
    document.querySelector('#userModal .modal-title').textContent = 'Add New User';
    document.getElementById('userModal').style.display = 'flex';
    toggleDoctorFields();
}

function editUser(userId) {
    // Fetch user data
    fetch(`/admin/get-user.php?id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const user = data.user;
                document.getElementById('userId').value = user.id;
                document.getElementById('firstName').value = user.first_name;
                document.getElementById('lastName').value = user.last_name;
                document.getElementById('email').value = user.email;
                document.getElementById('userRole').value = user.role;
                document.getElementById('userStatus').value = user.status;
                document.getElementById('phone').value = user.phone || '';
                
                // Doctor specific fields
                if (user.role === 'doctor' && user.doctor_info) {
                    document.getElementById('displayName').value = user.doctor_info.display_name || '';
                    document.getElementById('specialty').value = user.doctor_info.specialty || '';
                    document.getElementById('qualification').value = user.doctor_info.qualification || '';
                    document.getElementById('experience').value = user.doctor_info.experience || '';
                }

                document.getElementById('passwordGroup').style.display = 'none';
                document.querySelector('#userModal .modal-title').textContent = 'Edit User';
                document.getElementById('userModal').style.display = 'flex';
                toggleDoctorFields();
            }
        });
}

function toggleDoctorFields() {
    const role = document.getElementById('userRole').value;
    const doctorFields = document.getElementById('doctorFields');
    doctorFields.style.display = role === 'doctor' ? 'block' : 'none';

    const displayNameInput = document.getElementById('displayName');
    const specialtyInput = document.getElementById('specialty');
    displayNameInput.required = role === 'doctor';
    specialtyInput.required = role === 'doctor';
}

function saveUser(event) {
    event.preventDefault();
    const userId = document.getElementById('userId').value;
    const isNewUser = !userId;

    const formData = {
        id: userId,
        first_name: document.getElementById('firstName').value,
        last_name: document.getElementById('lastName').value,
        email: document.getElementById('email').value,
        role: document.getElementById('userRole').value,
        status: document.getElementById('userStatus').value,
        phone: document.getElementById('phone').value,
        password: document.getElementById('password').value,
        csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
    };

    if (formData.role === 'doctor') {
        formData.doctor_info = {
            display_name: document.getElementById('displayName').value,
            specialty: document.getElementById('specialty').value,
            qualification: document.getElementById('qualification').value,
            experience: document.getElementById('experience').value
        };
    }

    // Show loading state
    const submitBtn = document.querySelector('#userModal .btn-primary');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    fetch('/admin/save-user.php', {
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
                isNewUser ? 'User created successfully' : 'User updated successfully'
            );
            setTimeout(() => window.location.reload(), 2000);
        } else {
            showNotification(data.message, 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    })
    .catch(error => {
        showNotification('Failed to save user', 'error');
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    });
}

function updateUserStatus(userId, status) {
    if (!confirm(`Are you sure you want to ${status === 'active' ? 'activate' : 'deactivate'} this user?`)) {
        return;
    }

    fetch('/admin/update-user-status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            user_id: userId,
            status: status,
            csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('User status updated successfully');
            setTimeout(() => window.location.reload(), 2000);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('Failed to update user status', 'error');
    });
}

function deleteUser(userId) {
    if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        return;
    }

    fetch('/admin/delete-user.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            user_id: userId,
            csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('User deleted successfully');
            setTimeout(() => window.location.reload(), 2000);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('Failed to delete user', 'error');
    });
}

// Initialize components
document.addEventListener('DOMContentLoaded', function() {
    toggleDoctorFields();
});
</script>

<?php include '../views/footer.php'; ?>