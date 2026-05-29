<?php
require_once '../config/functions.php';
requireStaffOrAdmin();

$conn = getDBConnection();

$filter = $_GET['filter'] ?? 'approved';
$validFilters = ['all', 'approved', 'pending'];
if (!in_array($filter, $validFilters)) $filter = 'approved';

// Determine the staff_id for queries
$isAdmin = isAdmin();
$staffUserId = $_SESSION['staff_user_id'] ?? $_SESSION['admin_id'] ?? null;
$staffRecordId = $_SESSION['staff_id'] ?? null;

// Get staff record if not already in session
if (!$staffRecordId && $staffUserId) {
    $stmt = $conn->prepare("SELECT id FROM staff WHERE user_id = ?");
    $stmt->execute([$staffUserId]);
    $staffRecordId = $stmt->fetchColumn() ?: null;
}

// Build query
$sql = "
    SELECT t.*, u.first_name, u.last_name, a.services as appointment_services
    FROM testimonials t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN appointments a ON t.appointment_id = a.id
    WHERE 1=1
";
$params = [];

if (!$isAdmin) {
    $sql .= " AND a.staff_id = ?";
    $params[] = $staffRecordId;
}

if ($filter !== 'all') {
    $sql .= " AND t.status = ?";
    $params[] = $filter;
}

$sql .= " ORDER BY t.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$testimonials = $stmt->fetchAll();

// Attach service names
foreach ($testimonials as &$t) {
    $t['service_names'] = '';
    if (!empty($t['appointment_services'])) {
        $ids = json_decode($t['appointment_services'], true);
        if (!empty($ids)) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $st = $conn->prepare("SELECT name FROM services WHERE id IN ($ph)");
            $st->execute($ids);
            $t['service_names'] = implode(', ', $st->fetchAll(PDO::FETCH_COLUMN));
        }
    }
}
unset($t);

// Summary stats
$statsJoin = "";
$statsWhere = " WHERE status = ?";
$statsParams = ['approved'];

if (!$isAdmin) {
    $statsJoin = " JOIN appointments a ON t.appointment_id = a.id";
    $statsWhere .= " AND a.staff_id = ?";
    $statsParams[] = $staffRecordId;
}

$stmt = $conn->prepare("SELECT COUNT(*) FROM testimonials t $statsJoin $statsWhere");
$stmt->execute($statsParams);
$approvedCount = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT AVG(rating) FROM testimonials t $statsJoin $statsWhere");
$stmt->execute($statsParams);
$avgRating = round($stmt->fetchColumn(), 1);

// Pending stats
$pendingParams = ['pending'];
if (!$isAdmin) $pendingParams[] = $staffRecordId;
$stmt = $conn->prepare("SELECT COUNT(*) FROM testimonials t $statsJoin " . str_replace('status = ?', 'status = ?', $statsWhere));
$stmt->execute($pendingParams);
$pendingCount = $stmt->fetchColumn();
?>
<?php include '../includes/staff-header.php'; ?>

<div class="staff-page-header">
    <h1><i class="fas fa-star"></i> Testimonials &amp; Reviews</h1>
    <span style="color:#888; font-size:0.9rem;"><?php echo count($testimonials); ?> record(s)</span>
</div>

<!-- Summary Stats -->
<div class="staff-stats-grid" style="grid-template-columns:repeat(3,1fr); margin-bottom:1.5rem;">
    <div class="staff-stat-card">
        <div class="staff-stat-icon" style="background:#28a745;"><i class="fas fa-thumbs-up"></i></div>
        <div class="staff-stat-content">
            <h3><?php echo $approvedCount; ?></h3>
            <p>Approved Reviews</p>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-icon" style="background:#e9c46a;"><i class="fas fa-star"></i></div>
        <div class="staff-stat-content">
            <h3><?php echo $avgRating ?: '—'; ?></h3>
            <p>Average Rating</p>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-icon" style="background:#ffc107;"><i class="fas fa-clock"></i></div>
        <div class="staff-stat-content">
            <h3><?php echo $pendingCount; ?></h3>
            <p>Pending Review</p>
        </div>
    </div>
</div>

<!-- Filter Tabs -->
<div class="filter-tabs">
    <a href="testimonials.php?filter=approved" class="filter-tab <?php echo $filter === 'approved' ? 'active' : ''; ?>">Approved</a>
    <a href="testimonials.php?filter=pending"  class="filter-tab <?php echo $filter === 'pending'  ? 'active' : ''; ?>">Pending</a>
    <a href="testimonials.php?filter=all"      class="filter-tab <?php echo $filter === 'all'      ? 'active' : ''; ?>">All</a>
</div>

<div class="staff-card">
    <?php if (empty($testimonials)): ?>
        <div class="empty-state">
            <i class="fas fa-comment-slash"></i>
            No <?php echo $filter !== 'all' ? $filter : ''; ?> reviews found.
        </div>
    <?php else: ?>
        <div class="testimonial-list">
            <?php foreach ($testimonials as $t): ?>
                <div class="testimonial-item">
                    <div class="testimonial-item-header">
                        <div>
                            <strong><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></strong>
                            <?php if ($t['service_names']): ?>
                                <br><small style="color:#888;"><i class="fas fa-spa"></i> <?php echo htmlspecialchars($t['service_names']); ?></small>
                            <?php endif; ?>
                        </div>
                        <div style="text-align:right;">
                            <div class="star-rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= $t['rating'] ? 'active' : ''; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <small style="color:#999;"><?php echo formatDate($t['created_at']); ?></small>
                        </div>
                    </div>
                    <p class="testimonial-text"><?php echo htmlspecialchars($t['review_text']); ?></p>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:0.5rem; padding-top:0.5rem; border-top:1px solid #eee;">
                        <span class="status-badge status-<?php echo $t['status']; ?>"><?php echo ucfirst($t['status']); ?></span>
                        <small style="color:#bbb;">Review #<?php echo $t['id']; ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/staff-footer.php'; ?>
