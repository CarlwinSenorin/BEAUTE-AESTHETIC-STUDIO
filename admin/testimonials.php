<?php
require_once '../config/functions.php';
requireAdmin();

$conn = getDBConnection();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? 0;
    $status = $_POST['status'] ?? '';
    
    if ($action === 'update_status' && $id && $status) {
        $stmt = $conn->prepare("UPDATE testimonials SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        $success = 'Testimonial status updated';
    } elseif ($action === 'delete' && $id) {
        $stmt = $conn->prepare("DELETE FROM testimonials WHERE id = ?");
        $stmt->execute([$id]);
        $success = 'Testimonial deleted';
    }
}

// Get testimonials with service info (reviews based on spa services)
$filter = $_GET['filter'] ?? 'all';
$sql = "SELECT t.*, u.first_name, u.last_name, u.email, a.services as appointment_services
        FROM testimonials t 
        JOIN users u ON t.user_id = u.id
        LEFT JOIN appointments a ON t.appointment_id = a.id";
        
if ($filter !== 'all') {
    $sql .= " WHERE t.status = ?";
}

$sql .= " ORDER BY t.created_at DESC";

$stmt = $filter !== 'all' ? $conn->prepare($sql) : $conn->prepare($sql);
if ($filter !== 'all') {
    $stmt->execute([$filter]);
} else {
    $stmt->execute();
}
$testimonials = $stmt->fetchAll();

// Attach service names to each testimonial
foreach ($testimonials as &$t) {
    $t['service_names'] = 'N/A';
    if (!empty($t['appointment_services'])) {
        $svc_ids = json_decode($t['appointment_services'], true);
        if (!empty($svc_ids)) {
            $ph = implode(',', array_fill(0, count($svc_ids), '?'));
            $st = $conn->prepare("SELECT name FROM services WHERE id IN ($ph)");
            $st->execute($svc_ids);
            $t['service_names'] = implode(', ', $st->fetchAll(PDO::FETCH_COLUMN));
        }
    }
}
unset($t);
?>
<?php include '../includes/admin-header.php'; ?>

            <div class="admin-content">
            <div class="admin-header">
                <h1><i class="fas fa-star"></i> Manage Testimonials</h1>
                <a href="index.php" class="btn btn-outline">Back to Dashboard</a>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="admin-card">
                <div class="filter-buttons">
                    <a href="testimonials.php" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
                    <a href="testimonials.php?filter=pending" class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending</a>
                    <a href="testimonials.php?filter=approved" class="filter-btn <?php echo $filter === 'approved' ? 'active' : ''; ?>">Approved</a>
                    <a href="testimonials.php?filter=rejected" class="filter-btn <?php echo $filter === 'rejected' ? 'active' : ''; ?>">Rejected</a>
                </div>
            </div>

            <!-- Testimonials List -->
            <div class="admin-card">
                <h2>All Testimonials</h2>
                <div class="testimonials-admin-list">
                    <?php foreach ($testimonials as $testimonial): ?>
                        <div class="testimonial-admin-item">
                            <div class="testimonial-header">
                                <div>
                                    <strong><?php echo htmlspecialchars($testimonial['first_name'] . ' ' . $testimonial['last_name']); ?></strong>
                                    <small><?php echo htmlspecialchars($testimonial['email']); ?></small>
                                </div>
                                <div class="testimonial-rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= $testimonial['rating'] ? 'active' : ''; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <?php if ($testimonial['service_names'] !== 'N/A'): ?>
                                <p class="testimonial-services"><small><i class="fas fa-spa"></i> Service: <?php echo htmlspecialchars($testimonial['service_names']); ?></small></p>
                            <?php endif; ?>
                            <p class="testimonial-text"><?php echo htmlspecialchars($testimonial['review_text']); ?></p>
                            <div class="testimonial-footer">
                                <span class="status-badge status-<?php echo $testimonial['status']; ?>">
                                    <?php echo ucfirst($testimonial['status']); ?>
                                </span>
                                <span class="testimonial-date"><?php echo formatDate($testimonial['created_at']); ?></span>
                                <div class="testimonial-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="id" value="<?php echo $testimonial['id']; ?>">
                                        <select name="status" onchange="this.form.submit()" class="status-select">
                                            <option value="pending" <?php echo $testimonial['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="approved" <?php echo $testimonial['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                            <option value="rejected" <?php echo $testimonial['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        </select>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $testimonial['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>

    <style>
        .testimonials-admin-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        .testimonial-admin-item {
            padding: 1.5rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: var(--light-color);
        }
        .testimonial-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .testimonial-rating .fa-star {
            color: #ddd;
            font-size: 0.9rem;
        }
        .testimonial-rating .fa-star.active {
            color: var(--warning-color);
        }
        .testimonial-text {
            margin-bottom: 1rem;
            color: #555;
            line-height: 1.6;
        }
        .testimonial-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid #ddd;
        }
        .testimonial-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
    </style>
<?php include '../includes/admin-footer.php'; ?>
