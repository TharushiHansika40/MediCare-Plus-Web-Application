<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';

$auth = new AuthController();
$auth->requireRole('admin');

// Get content types
$contentQuery = $pdo->query("
    SELECT 
        c.*,
        u.first_name,
        u.last_name,
        COUNT(ci.id) as items_count
    FROM content_types c
    LEFT JOIN users u ON u.id = c.updated_by
    LEFT JOIN content_items ci ON ci.type_id = c.id
    GROUP BY c.id
    ORDER BY c.name ASC
");
$contentTypes = $contentQuery->fetchAll();

// Get FAQs
$faqQuery = $pdo->query("
    SELECT 
        f.*,
        u.first_name,
        u.last_name
    FROM faqs f
    LEFT JOIN users u ON u.id = f.updated_by
    ORDER BY f.category, f.display_order
");
$faqs = $faqQuery->fetchAll();

// Get announcements
$announcementQuery = $pdo->query("
    SELECT 
        a.*,
        u.first_name,
        u.last_name
    FROM announcements a
    LEFT JOIN users u ON u.id = a.created_by
    ORDER BY a.created_at DESC
    LIMIT 10
");
$announcements = $announcementQuery->fetchAll();

$pageTitle = "Content Management";
include '../views/header.php';
?>

<div class="admin-section content-management">
    <div class="section-header">
        <h1>Content Management</h1>
        <div class="header-actions">
            <button class="btn btn-secondary" onclick="showAddFAQModal()">
                <i class="fas fa-question-circle"></i> Add FAQ
            </button>
            <button class="btn btn-secondary" onclick="showAddAnnouncementModal()">
                <i class="fas fa-bullhorn"></i> New Announcement
            </button>
            <button class="btn btn-primary" onclick="showAddContentModal()">
                <i class="fas fa-plus"></i> Add Content
            </button>
        </div>
    </div>

    <div class="content-grid">
        <!-- Dynamic Content Section -->
        <div class="content-section">
            <h2>Dynamic Content</h2>
            <div class="content-types-grid">
                <?php foreach ($contentTypes as $type): ?>
                    <div class="content-type-card">
                        <div class="card-header">
                            <h3><?php echo htmlspecialchars($type['name']); ?></h3>
                            <div class="card-actions">
                                <button class="btn btn-icon" 
                                        onclick="manageContent(<?php echo $type['id']; ?>)"
                                        title="Manage Content">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <p><?php echo htmlspecialchars($type['description']); ?></p>
                            <div class="meta-info">
                                <span>
                                    <i class="fas fa-file-alt"></i>
                                    <?php echo $type['items_count']; ?> items
                                </span>
                                <?php if ($type['updated_by']): ?>
                                    <span>
                                        <i class="fas fa-user"></i>
                                        Updated by <?php echo htmlspecialchars($type['first_name'] . ' ' . $type['last_name']); ?>
                                    </span>
                                <?php endif; ?>
                                <span>
                                    <i class="fas fa-clock"></i>
                                    <?php echo Utilities::timeAgo($type['updated_at']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- FAQs Section -->
        <div class="content-section">
            <h2>Frequently Asked Questions</h2>
            <div class="faq-list">
                <?php 
                $currentCategory = '';
                foreach ($faqs as $faq): 
                    if ($faq['category'] !== $currentCategory):
                        $currentCategory = $faq['category'];
                ?>
                    <div class="faq-category">
                        <h3><?php echo htmlspecialchars($currentCategory); ?></h3>
                    </div>
                <?php endif; ?>

                <div class="faq-item" id="faq-<?php echo $faq['id']; ?>">
                    <div class="faq-question">
                        <span><?php echo htmlspecialchars($faq['question']); ?></span>
                        <div class="faq-actions">
                            <button class="btn btn-icon" 
                                    onclick="editFAQ(<?php echo $faq['id']; ?>)"
                                    title="Edit FAQ">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-icon delete" 
                                    onclick="deleteFAQ(<?php echo $faq['id']; ?>)"
                                    title="Delete FAQ">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="faq-answer">
                        <?php echo nl2br(htmlspecialchars($faq['answer'])); ?>
                        <div class="meta-info">
                            <small>
                                Last updated by <?php echo htmlspecialchars($faq['first_name'] . ' ' . $faq['last_name']); ?> 
                                <?php echo Utilities::timeAgo($faq['updated_at']); ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Announcements Section -->
        <div class="content-section">
            <h2>Recent Announcements</h2>
            <div class="announcement-list">
                <?php foreach ($announcements as $announcement): ?>
                    <div class="announcement-item" id="announcement-<?php echo $announcement['id']; ?>">
                        <div class="announcement-header">
                            <div class="announcement-meta">
                                <span class="announcement-type <?php echo $announcement['type']; ?>">
                                    <?php echo ucfirst($announcement['type']); ?>
                                </span>
                                <span class="announcement-date">
                                    <?php echo date('F j, Y', strtotime($announcement['created_at'])); ?>
                                </span>
                            </div>
                            <div class="announcement-actions">
                                <button class="btn btn-icon" 
                                        onclick="editAnnouncement(<?php echo $announcement['id']; ?>)"
                                        title="Edit Announcement">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-icon delete" 
                                        onclick="deleteAnnouncement(<?php echo $announcement['id']; ?>)"
                                        title="Delete Announcement">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <h4><?php echo htmlspecialchars($announcement['title']); ?></h4>
                        <div class="announcement-content">
                            <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                        </div>
                        <div class="meta-info">
                            <small>
                                Posted by <?php echo htmlspecialchars($announcement['first_name'] . ' ' . $announcement['last_name']); ?>
                            </small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Content Type Modal -->
<div class="modal" id="contentModal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title">Add Content Type</h3>
            <button class="modal-close" onclick="closeModal('contentModal')">×</button>
        </div>
        <div class="modal-body">
            <form id="contentForm" onsubmit="saveContent(event)">
                <input type="hidden" id="contentId">
                
                <div class="form-group">
                    <label for="contentName">Name</label>
                    <input type="text" id="contentName" required>
                </div>

                <div class="form-group">
                    <label for="contentDescription">Description</label>
                    <textarea id="contentDescription" rows="3" required></textarea>
                </div>

                <div class="form-group">
                    <label for="contentFields">Fields</label>
                    <div id="fieldsList">
                        <!-- Dynamic fields will be added here -->
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="addField()">
                        <i class="fas fa-plus"></i> Add Field
                    </button>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('contentModal')">Cancel</button>
            <button class="btn btn-primary" onclick="document.getElementById('contentForm').requestSubmit()">Save</button>
        </div>
    </div>
</div>

<!-- Add/Edit FAQ Modal -->
<div class="modal" id="faqModal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title">Add FAQ</h3>
            <button class="modal-close" onclick="closeModal('faqModal')">×</button>
        </div>
        <div class="modal-body">
            <form id="faqForm" onsubmit="saveFAQ(event)">
                <input type="hidden" id="faqId">

                <div class="form-group">
                    <label for="faqCategory">Category</label>
                    <input type="text" id="faqCategory" required 
                           list="faqCategories" autocomplete="off">
                    <datalist id="faqCategories">
                        <?php 
                        $categories = array_unique(array_column($faqs, 'category'));
                        foreach ($categories as $category):
                        ?>
                            <option value="<?php echo htmlspecialchars($category); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="form-group">
                    <label for="faqQuestion">Question</label>
                    <input type="text" id="faqQuestion" required>
                </div>

                <div class="form-group">
                    <label for="faqAnswer">Answer</label>
                    <textarea id="faqAnswer" rows="4" required></textarea>
                </div>

                <div class="form-group">
                    <label for="faqOrder">Display Order</label>
                    <input type="number" id="faqOrder" min="1" value="1">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('faqModal')">Cancel</button>
            <button class="btn btn-primary" onclick="document.getElementById('faqForm').requestSubmit()">Save</button>
        </div>
    </div>
</div>

<!-- Add/Edit Announcement Modal -->
<div class="modal" id="announcementModal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title">New Announcement</h3>
            <button class="modal-close" onclick="closeModal('announcementModal')">×</button>
        </div>
        <div class="modal-body">
            <form id="announcementForm" onsubmit="saveAnnouncement(event)">
                <input type="hidden" id="announcementId">

                <div class="form-group">
                    <label for="announcementTitle">Title</label>
                    <input type="text" id="announcementTitle" required>
                </div>

                <div class="form-group">
                    <label for="announcementType">Type</label>
                    <select id="announcementType" required>
                        <option value="info">Information</option>
                        <option value="alert">Alert</option>
                        <option value="success">Success</option>
                        <option value="warning">Warning</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="announcementContent">Content</label>
                    <textarea id="announcementContent" rows="4" required></textarea>
                </div>

                <div class="form-group">
                    <label>Target Audience</label>
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" id="targetPatients" checked> Patients
                        </label>
                        <label>
                            <input type="checkbox" id="targetDoctors" checked> Doctors
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" id="announcementPinned"> Pin to top
                    </label>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('announcementModal')">Cancel</button>
            <button class="btn btn-primary" onclick="document.getElementById('announcementForm').requestSubmit()">Save</button>
        </div>
    </div>
</div>

<style>
.content-management .content-grid {
    display: grid;
    gap: 2rem;
}

.content-types-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}

.content-type-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.content-type-card .card-header {
    padding: 1.5rem;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.content-type-card .card-body {
    padding: 1.5rem;
}

.meta-info {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
    color: #666;
    font-size: 0.9rem;
}

.meta-info i {
    color: var(--primary-color);
    margin-right: 0.25rem;
}

.faq-list {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 1.5rem;
}

.faq-category {
    margin: 2rem 0 1rem;
    color: var(--primary-color);
    border-bottom: 2px solid var(--primary-color-light);
    padding-bottom: 0.5rem;
}

.faq-category:first-child {
    margin-top: 0;
}

.faq-item {
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #eee;
}

.faq-question {
    display: flex;
    justify-content: space-between;
    align-items: start;
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.faq-answer {
    color: #444;
    line-height: 1.5;
    margin-left: 1.5rem;
}

.announcement-list {
    display: grid;
    gap: 1rem;
}

.announcement-item {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 1.5rem;
}

.announcement-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.announcement-meta {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.announcement-type {
    padding: 0.25rem 0.75rem;
    border-radius: 16px;
    font-size: 0.85rem;
    font-weight: 500;
}

.announcement-type.info { background: #E3F2FD; color: #1976D2; }
.announcement-type.alert { background: #FFEBEE; color: #D32F2F; }
.announcement-type.success { background: #E8F5E9; color: #388E3C; }
.announcement-type.warning { background: #FFF3E0; color: #F57C00; }

.announcement-date {
    color: #666;
    font-size: 0.9rem;
}

.announcement-content {
    color: #444;
    line-height: 1.5;
    margin: 1rem 0;
}

#fieldsList {
    display: grid;
    gap: 1rem;
    margin-bottom: 1rem;
}

.field-item {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 1rem;
    align-items: end;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 4px;
}

.checkbox-group {
    display: flex;
    gap: 1.5rem;
}

@media (max-width: 768px) {
    .content-types-grid {
        grid-template-columns: 1fr;
    }

    .field-item {
        grid-template-columns: 1fr;
    }

    .announcement-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
}
</style>

<script>
let contentFields = [];

function showAddContentModal() {
    document.getElementById('contentForm').reset();
    document.getElementById('contentId').value = '';
    contentFields = [];
    renderFields();
    document.querySelector('#contentModal .modal-title').textContent = 'Add Content Type';
    document.getElementById('contentModal').style.display = 'flex';
}

function addField() {
    contentFields.push({
        name: '',
        type: 'text',
        required: false
    });
    renderFields();
}

function removeField(index) {
    contentFields.splice(index, 1);
    renderFields();
}

function updateField(index, key, value) {
    contentFields[index][key] = value;
}

function renderFields() {
    const container = document.getElementById('fieldsList');
    container.innerHTML = contentFields.map((field, index) => `
        <div class="field-item">
            <div class="form-group">
                <label>Field Name</label>
                <input type="text" 
                       value="${field.name}" 
                       onchange="updateField(${index}, 'name', this.value)"
                       required>
            </div>
            <div class="form-group">
                <label>Field Type</label>
                <select onchange="updateField(${index}, 'type', this.value)">
                    <option value="text" ${field.type === 'text' ? 'selected' : ''}>Text</option>
                    <option value="textarea" ${field.type === 'textarea' ? 'selected' : ''}>Text Area</option>
                    <option value="number" ${field.type === 'number' ? 'selected' : ''}>Number</option>
                    <option value="date" ${field.type === 'date' ? 'selected' : ''}>Date</option>
                    <option value="image" ${field.type === 'image' ? 'selected' : ''}>Image</option>
                </select>
            </div>
            <div class="form-group">
                <button type="button" 
                        class="btn btn-icon delete"
                        onclick="removeField(${index})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
}

function saveContent(event) {
    event.preventDefault();

    const formData = {
        id: document.getElementById('contentId').value,
        name: document.getElementById('contentName').value,
        description: document.getElementById('contentDescription').value,
        fields: contentFields,
        csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
    };

    // Show loading state
    const submitBtn = document.querySelector('#contentModal .btn-primary');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    fetch('/admin/save-content.php', {
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
                formData.id ? 'Content type updated successfully' : 'Content type created successfully'
            );
            setTimeout(() => window.location.reload(), 2000);
        } else {
            showNotification(data.message, 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    })
    .catch(error => {
        showNotification('Failed to save content type', 'error');
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    });
}

function showAddFAQModal() {
    document.getElementById('faqForm').reset();
    document.getElementById('faqId').value = '';
    document.querySelector('#faqModal .modal-title').textContent = 'Add FAQ';
    document.getElementById('faqModal').style.display = 'flex';
}

function editFAQ(faqId) {
    fetch(`/admin/get-faq.php?id=${faqId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const faq = data.faq;
                document.getElementById('faqId').value = faq.id;
                document.getElementById('faqCategory').value = faq.category;
                document.getElementById('faqQuestion').value = faq.question;
                document.getElementById('faqAnswer').value = faq.answer;
                document.getElementById('faqOrder').value = faq.display_order;

                document.querySelector('#faqModal .modal-title').textContent = 'Edit FAQ';
                document.getElementById('faqModal').style.display = 'flex';
            }
        });
}

function saveFAQ(event) {
    event.preventDefault();

    const formData = {
        id: document.getElementById('faqId').value,
        category: document.getElementById('faqCategory').value,
        question: document.getElementById('faqQuestion').value,
        answer: document.getElementById('faqAnswer').value,
        display_order: document.getElementById('faqOrder').value,
        csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
    };

    // Show loading state
    const submitBtn = document.querySelector('#faqModal .btn-primary');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    fetch('/admin/save-faq.php', {
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
                formData.id ? 'FAQ updated successfully' : 'FAQ created successfully'
            );
            setTimeout(() => window.location.reload(), 2000);
        } else {
            showNotification(data.message, 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    })
    .catch(error => {
        showNotification('Failed to save FAQ', 'error');
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    });
}

function deleteFAQ(faqId) {
    if (!confirm('Are you sure you want to delete this FAQ?')) {
        return;
    }

    fetch('/admin/delete-faq.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            faq_id: faqId,
            csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('FAQ deleted successfully');
            const faqElement = document.getElementById(`faq-${faqId}`);
            faqElement.style.opacity = '0';
            setTimeout(() => {
                faqElement.style.height = '0';
                faqElement.style.margin = '0';
                faqElement.style.padding = '0';
                setTimeout(() => faqElement.remove(), 300);
            }, 300);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('Failed to delete FAQ', 'error');
    });
}

function showAddAnnouncementModal() {
    document.getElementById('announcementForm').reset();
    document.getElementById('announcementId').value = '';
    document.querySelector('#announcementModal .modal-title').textContent = 'New Announcement';
    document.getElementById('announcementModal').style.display = 'flex';
}

function editAnnouncement(announcementId) {
    fetch(`/admin/get-announcement.php?id=${announcementId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const announcement = data.announcement;
                document.getElementById('announcementId').value = announcement.id;
                document.getElementById('announcementTitle').value = announcement.title;
                document.getElementById('announcementType').value = announcement.type;
                document.getElementById('announcementContent').value = announcement.content;
                document.getElementById('targetPatients').checked = announcement.target_patients;
                document.getElementById('targetDoctors').checked = announcement.target_doctors;
                document.getElementById('announcementPinned').checked = announcement.is_pinned;

                document.querySelector('#announcementModal .modal-title').textContent = 'Edit Announcement';
                document.getElementById('announcementModal').style.display = 'flex';
            }
        });
}

function saveAnnouncement(event) {
    event.preventDefault();

    const formData = {
        id: document.getElementById('announcementId').value,
        title: document.getElementById('announcementTitle').value,
        type: document.getElementById('announcementType').value,
        content: document.getElementById('announcementContent').value,
        target_patients: document.getElementById('targetPatients').checked,
        target_doctors: document.getElementById('targetDoctors').checked,
        is_pinned: document.getElementById('announcementPinned').checked,
        csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
    };

    // Show loading state
    const submitBtn = document.querySelector('#announcementModal .btn-primary');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    fetch('/admin/save-announcement.php', {
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
                formData.id ? 'Announcement updated successfully' : 'Announcement created successfully'
            );
            setTimeout(() => window.location.reload(), 2000);
        } else {
            showNotification(data.message, 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    })
    .catch(error => {
        showNotification('Failed to save announcement', 'error');
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    });
}

function deleteAnnouncement(announcementId) {
    if (!confirm('Are you sure you want to delete this announcement?')) {
        return;
    }

    fetch('/admin/delete-announcement.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            announcement_id: announcementId,
            csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Announcement deleted successfully');
            const announcementElement = document.getElementById(`announcement-${announcementId}`);
            announcementElement.style.opacity = '0';
            setTimeout(() => {
                announcementElement.style.height = '0';
                announcementElement.style.margin = '0';
                announcementElement.style.padding = '0';
                setTimeout(() => announcementElement.remove(), 300);
            }, 300);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('Failed to delete announcement', 'error');
    });
}
</script>

<?php include '../views/footer.php'; ?>