<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';

$auth = new AuthController();
$auth->requireRole('patient');

$currentUser = $auth->getCurrentUser();
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total count of reports
$countQuery = $pdo->prepare(
    "SELECT COUNT(*)
     FROM medical_reports mr
     JOIN appointments a ON mr.appointment_id = a.id
     WHERE a.patient_id = ?"
);
$countQuery->execute([$currentUser['id']]);
$totalReports = $countQuery->fetchColumn();
$totalPages = ceil($totalReports / $limit);

// Get reports for current page
$reportsQuery = $pdo->prepare(
    "SELECT mr.*, a.date as appointment_date, 
            d.display_name as doctor_name,
            d.specialization
     FROM medical_reports mr
     JOIN appointments a ON mr.appointment_id = a.id
     JOIN doctors d ON a.doctor_id = d.id
     WHERE a.patient_id = ?
     ORDER BY mr.created_at DESC
     LIMIT ? OFFSET ?"
);
$reportsQuery->execute([$currentUser['id'], $limit, $offset]);
$reports = $reportsQuery->fetchAll();

$pageTitle = "Medical Reports";
include '../views/header.php';
?>

<div class="reports-page">
    <div class="page-header">
        <h1>Medical Reports</h1>
    </div>

    <?php if ($reports): ?>
        <div class="reports-list">
            <?php foreach ($reports as $report): ?>
                <div class="report-card">
                    <div class="report-icon">
                        <i class="fas fa-file-medical"></i>
                    </div>
                    
                    <div class="report-details">
                        <h3 class="report-title">
                            <?php echo htmlspecialchars($report['title']); ?>
                        </h3>
                        
                        <div class="report-meta">
                            <p>
                                <i class="fas fa-user-md"></i>
                                Dr. <?php echo htmlspecialchars($report['doctor_name']); ?>
                                (<?php echo htmlspecialchars($report['specialization']); ?>)
                            </p>
                            <p>
                                <i class="fas fa-calendar"></i>
                                <?php echo date('F j, Y', strtotime($report['appointment_date'])); ?>
                            </p>
                            <p>
                                <i class="fas fa-clock"></i>
                                <?php echo date('g:i A', strtotime($report['created_at'])); ?>
                            </p>
                        </div>

                        <?php if ($report['description']): ?>
                            <div class="report-description">
                                <?php echo nl2br(htmlspecialchars($report['description'])); ?>
                            </div>
                        <?php endif; ?>

                        <div class="report-actions">
                            <a href="/reports/download.php?id=<?php echo $report['id']; ?>"
                               class="btn btn-primary">
                                <i class="fas fa-download"></i> Download Report
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" 
                           class="pagination-btn">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>"
                           class="page-number <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>"
                           class="pagination-btn">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="no-data">
            <i class="fas fa-file-medical"></i>
            <h3>No Medical Reports</h3>
            <p>You don't have any medical reports yet.</p>
        </div>
    <?php endif; ?>
</div>

<style>
/* Additional styles specific to reports page */
.reports-page {
    padding: 2rem 0;
}

.page-header {
    margin-bottom: 2rem;
}

.report-card {
    background: #fff;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    gap: 1.5rem;
}

.report-icon {
    font-size: 2rem;
    color: #3498db;
    background: #e3f2fd;
    width: 64px;
    height: 64px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.report-details {
    flex: 1;
}

.report-title {
    margin: 0 0 1rem;
    color: #2c3e50;
    font-size: 1.25rem;
}

.report-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.report-meta p {
    margin: 0;
    color: #7f8c8d;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.report-description {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 4px;
    margin: 1rem 0;
    color: #2c3e50;
    line-height: 1.6;
}

.report-actions {
    margin-top: 1rem;
}

.no-data {
    text-align: center;
    padding: 3rem;
    color: #7f8c8d;
}

.no-data i {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.no-data h3 {
    margin: 0 0 0.5rem;
    color: #2c3e50;
}

@media (max-width: 768px) {
    .report-card {
        flex-direction: column;
        padding: 1rem;
    }

    .report-meta {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include '../views/footer.php'; ?>