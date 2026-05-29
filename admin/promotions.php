<?php
require_once '../config/functions.php';
requireAdmin();

$conn = getDBConnection();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $discount_type = $_POST['discount_type'] ?? 'percentage';
        $discount_value = (float)($_POST['discount_value'] ?? 0);
        $min_purchase = (float)($_POST['min_purchase'] ?? 0) ?: null;
        $valid_from = $_POST['valid_from'] ?? date('Y-m-d');
        $valid_until = $_POST['valid_until'] ?? date('Y-m-d', strtotime('+30 days'));
        $usage_limit = !empty($_POST['usage_limit']) ? (int)$_POST['usage_limit'] : null;
        $status = $_POST['status'] ?? 'active';
        
        if ($name && $discount_value > 0) {
            $stmt = $conn->prepare("INSERT INTO promotions (name, description, discount_type, discount_value, min_purchase, valid_from, valid_until, usage_limit, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $description, $discount_type, $discount_value, $min_purchase, $valid_from, $valid_until, $usage_limit, $status]);
            $success = 'Promotion created successfully';
        } else {
            $error = 'Please fill in all required fields';
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $discount_type = $_POST['discount_type'] ?? 'percentage';
        $discount_value = (float)($_POST['discount_value'] ?? 0);
        $min_purchase = (float)($_POST['min_purchase'] ?? 0) ?: null;
        $valid_from = $_POST['valid_from'] ?? '';
        $valid_until = $_POST['valid_until'] ?? '';
        $usage_limit = !empty($_POST['usage_limit']) ? (int)$_POST['usage_limit'] : null;
        $status = $_POST['status'] ?? 'active';
        
        if ($id && $name) {
            $stmt = $conn->prepare("UPDATE promotions SET name = ?, description = ?, discount_type = ?, discount_value = ?, min_purchase = ?, valid_from = ?, valid_until = ?, usage_limit = ?, status = ? WHERE id = ?");
            $stmt->execute([$name, $description, $discount_type, $discount_value, $min_purchase, $valid_from, $valid_until, $usage_limit, $status, $id]);
            $success = 'Promotion updated successfully';
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare("UPDATE promotions SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?");
            $stmt->execute([$id]);
            $success = 'Promotion status toggled';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM promotions WHERE id = ?");
            $stmt->execute([$id]);
            $success = 'Promotion deleted';
        }
    }
}

// Get all promotions
$stmt = $conn->query("SELECT * FROM promotions ORDER BY created_at DESC");
$promotions = $stmt->fetchAll();

// Get promotion for editing
$edit_promo = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM promotions WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_promo = $stmt->fetch();
}

// Stats
$active_count = 0;
$total_used = 0;
foreach ($promotions as $p) {
    if ($p['status'] === 'active') $active_count++;
    $total_used += $p['used_count'];
}
?>
<?php include '../includes/admin-header.php'; ?>

            <div class="admin-content">
            <div class="admin-header">
                <h1><i class="fas fa-tags"></i> Promotions Management</h1>
                <a href="index.php" class="btn btn-outline">Back to Dashboard</a>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="stats-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px;">
                <div class="admin-card" style="text-align: center; padding: 20px;">
                    <div style="font-size: 2rem; font-weight: 700; color: var(--primary-color, #e91e63);"><?php echo count($promotions); ?></div>
                    <div style="color: #888; font-size: 0.9rem;">Total Promotions</div>
                </div>
                <div class="admin-card" style="text-align: center; padding: 20px;">
                    <div style="font-size: 2rem; font-weight: 700; color: #4caf50;"><?php echo $active_count; ?></div>
                    <div style="color: #888; font-size: 0.9rem;">Active</div>
                </div>
                <div class="admin-card" style="text-align: center; padding: 20px;">
                    <div style="font-size: 2rem; font-weight: 700; color: #2196f3;"><?php echo $total_used; ?></div>
                    <div style="color: #888; font-size: 0.9rem;">Total Uses</div>
                </div>
            </div>

            <!-- Add/Edit Form -->
            <div class="admin-card">
                <h2><?php echo $edit_promo ? 'Edit Promotion' : 'Add New Promotion'; ?></h2>
                <form method="POST" class="admin-form">
                    <?php csrfTokenField(); ?>
                    <input type="hidden" name="action" value="<?php echo $edit_promo ? 'edit' : 'add'; ?>">
                    <?php if ($edit_promo): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_promo['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Promotion Code / Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($edit_promo['name'] ?? ''); ?>" required placeholder="e.g. SUMMER20">
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="2" placeholder="Brief description of the promotion"><?php echo htmlspecialchars($edit_promo['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Discount Type</label>
                            <select name="discount_type" required>
                                <option value="percentage" <?php echo ($edit_promo['discount_type'] ?? '') === 'percentage' ? 'selected' : ''; ?>>Percentage (%)</option>
                                <option value="fixed" <?php echo ($edit_promo['discount_type'] ?? '') === 'fixed' ? 'selected' : ''; ?>>Fixed Amount (₱)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Discount Value</label>
                            <input type="number" name="discount_value" value="<?php echo $edit_promo['discount_value'] ?? ''; ?>" min="0" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label>Min. Purchase (₱)</label>
                            <input type="number" name="min_purchase" value="<?php echo $edit_promo['min_purchase'] ?? ''; ?>" min="0" step="0.01" placeholder="Optional">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Valid From</label>
                            <input type="date" name="valid_from" value="<?php echo $edit_promo['valid_from'] ?? date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Valid Until</label>
                            <input type="date" name="valid_until" value="<?php echo $edit_promo['valid_until'] ?? date('Y-m-d', strtotime('+30 days')); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Usage Limit</label>
                            <input type="number" name="usage_limit" value="<?php echo $edit_promo['usage_limit'] ?? ''; ?>" min="1" placeholder="Unlimited">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="active" <?php echo ($edit_promo['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($edit_promo['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?php echo $edit_promo ? 'Update' : 'Create'; ?> Promotion</button>
                        <?php if ($edit_promo): ?>
                            <a href="promotions.php" class="btn btn-outline">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Promotions List -->
            <div class="admin-card">
                <h2>All Promotions</h2>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Discount</th>
                                <th>Min. Purchase</th>
                                <th>Valid Period</th>
                                <th>Usage</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($promotions)): ?>
                                <tr><td colspan="7" style="text-align: center; padding: 30px; color: #888;">No promotions yet. Create one above.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($promotions as $promo): ?>
                                <?php 
                                    $is_expired = strtotime($promo['valid_until']) < time();
                                    $discount_display = $promo['discount_type'] === 'percentage' 
                                        ? $promo['discount_value'] . '%' 
                                        : formatPrice($promo['discount_value']);
                                ?>
                                <tr<?php echo $is_expired ? ' style="opacity: 0.6;"' : ''; ?>>
                                    <td>
                                        <strong><?php echo htmlspecialchars($promo['name']); ?></strong>
                                        <?php if ($promo['description']): ?>
                                            <br><small style="color: #999;"><?php echo htmlspecialchars($promo['description']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="font-weight: 600; color: var(--primary-color, #e91e63);"><?php echo $discount_display; ?></span>
                                    </td>
                                    <td><?php echo $promo['min_purchase'] ? formatPrice($promo['min_purchase']) : '—'; ?></td>
                                    <td>
                                        <?php echo formatDate($promo['valid_from']); ?>
                                        <br><small>to <?php echo formatDate($promo['valid_until']); ?></small>
                                        <?php if ($is_expired): ?>
                                            <br><span class="status-badge" style="background: #f44336; color: #fff; font-size: 0.7rem;">Expired</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $promo['used_count']; ?><?php echo $promo['usage_limit'] ? ' / ' . $promo['usage_limit'] : ''; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $promo['status']; ?>">
                                            <?php echo ucfirst($promo['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="promotions.php?edit=<?php echo $promo['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                        <form method="POST" style="display: inline;">
                                            <?php csrfTokenField(); ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?php echo $promo['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline" title="Toggle status">
                                                <i class="fas fa-<?php echo $promo['status'] === 'active' ? 'pause' : 'play'; ?>"></i>
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this promotion?')">
                                            <?php csrfTokenField(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $promo['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>
<?php include '../includes/admin-footer.php'; ?>
