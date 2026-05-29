<?php
require_once '../config/functions.php';
requireStaffOrAdmin();

$conn = getDBConnection();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = sanitize($_POST['name'] ?? '');
        $category = sanitize($_POST['category'] ?? 'General');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $reorder_level = (int)($_POST['reorder_level'] ?? 5);
        $unit = sanitize($_POST['unit'] ?? 'pcs');
        $cost_per_unit = (float)($_POST['cost_per_unit'] ?? 0);
        $linked_service_id = !empty($_POST['linked_service_id']) ? (int)$_POST['linked_service_id'] : null;
        
        if ($name) {
            $status = ($quantity <= 0) ? 'out_of_stock' : (($quantity <= $reorder_level) ? 'low_stock' : 'in_stock');
            $stmt = $conn->prepare("INSERT INTO inventory (name, category, quantity, reorder_level, unit, cost_per_unit, linked_service_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $category, $quantity, $reorder_level, $unit, $cost_per_unit, $linked_service_id, $status]);
            
            // Log initial stock
            $inv_id = $conn->lastInsertId();
            if ($quantity > 0) {
                $stmt = $conn->prepare("INSERT INTO inventory_log (inventory_id, change_type, quantity_change, quantity_after, notes, created_by) VALUES (?, 'restock', ?, ?, 'Initial stock', ?)");
                $stmt->execute([$inv_id, $quantity, $quantity, $_SESSION['user_id'] ?? null]);
            }
            $success = 'Inventory item added successfully';
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $category = sanitize($_POST['category'] ?? 'General');
        $reorder_level = (int)($_POST['reorder_level'] ?? 5);
        $unit = sanitize($_POST['unit'] ?? 'pcs');
        $cost_per_unit = (float)($_POST['cost_per_unit'] ?? 0);
        $linked_service_id = !empty($_POST['linked_service_id']) ? (int)$_POST['linked_service_id'] : null;
        
        if ($id && $name) {
            // Get current quantity to recalculate status
            $stmt = $conn->prepare("SELECT quantity FROM inventory WHERE id = ?");
            $stmt->execute([$id]);
            $current = $stmt->fetch();
            $qty = $current ? $current['quantity'] : 0;
            $status = ($qty <= 0) ? 'out_of_stock' : (($qty <= $reorder_level) ? 'low_stock' : 'in_stock');
            
            $stmt = $conn->prepare("UPDATE inventory SET name = ?, category = ?, reorder_level = ?, unit = ?, cost_per_unit = ?, linked_service_id = ?, status = ? WHERE id = ?");
            $stmt->execute([$name, $category, $reorder_level, $unit, $cost_per_unit, $linked_service_id, $status, $id]);
            $success = 'Inventory item updated';
        }
    } elseif ($action === 'restock') {
        $id = (int)($_POST['id'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 0);
        $notes = sanitize($_POST['notes'] ?? 'Manual restock');
        if ($id && $qty > 0) {
            restockInventory($id, $qty, $notes, $_SESSION['user_id'] ?? null);
            $success = "Restocked $qty units successfully";
        }
    } elseif ($action === 'deduct') {
        $id = (int)($_POST['id'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 0);
        $notes = sanitize($_POST['notes'] ?? 'Manual deduction');
        if ($id && $qty > 0) {
            deductInventory($id, $qty, $notes, $_SESSION['user_id'] ?? null);
            $success = "Deducted $qty units successfully";
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM inventory WHERE id = ?");
            $stmt->execute([$id]);
            $success = 'Inventory item deleted';
        }
    }
}

// Get all inventory items
$stmt = $conn->query("SELECT i.*, s.name as service_name FROM inventory i LEFT JOIN services s ON i.linked_service_id = s.id ORDER BY i.status = 'out_of_stock' DESC, i.status = 'low_stock' DESC, i.name ASC");
$items = $stmt->fetchAll();

// Get services for linking dropdown
$stmt = $conn->query("SELECT id, name FROM services WHERE status = 'active' ORDER BY name");
$services = $stmt->fetchAll();

// Get item for editing
$edit_item = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM inventory WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_item = $stmt->fetch();
}

// Get low stock items
$low_stock = getLowStockItems();

// Get recent activity log
$stmt = $conn->query("SELECT l.*, i.name as item_name, u.first_name as user_name 
                       FROM inventory_log l 
                       JOIN inventory i ON l.inventory_id = i.id 
                       LEFT JOIN users u ON l.created_by = u.id 
                       ORDER BY l.created_at DESC 
                       LIMIT 20");
$recent_logs = $stmt->fetchAll();

// Stats
$total_items = count($items);
$low_count = 0;
$out_count = 0;
$total_value = 0;
foreach ($items as $item) {
    if ($item['status'] === 'low_stock') $low_count++;
    if ($item['status'] === 'out_of_stock') $out_count++;
    $total_value += $item['quantity'] * $item['cost_per_unit'];
}
?>
<?php include '../includes/staff-header.php'; ?>

<div class="staff-page-header">
    <h1><i class="fas fa-boxes"></i> Inventory Management</h1>
    <a href="index.php" class="btn btn-outline btn-sm">Back to Dashboard</a>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<!-- Low Stock Alert Banner -->
<?php if (!empty($low_stock)): ?>
    <div class="alert" style="background: linear-gradient(135deg, #ff9800, #f44336); color: #fff; border: none; padding: 15px 20px; border-radius: 10px; margin-bottom: 20px;">
        <strong><i class="fas fa-exclamation-triangle"></i> Low Stock Alert!</strong>
        <?php echo count($low_stock); ?> item(s) at or below reorder level:
        <strong>
        <?php 
        $alert_names = array_map(function($i) { return $i['name'] . ' (' . $i['quantity'] . ' ' . ($i['unit'] ?? 'pcs') . ')'; }, $low_stock);
        echo implode(', ', $alert_names);
        ?>
        </strong>
    </div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="staff-stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
    <div class="staff-stat-card">
        <div class="staff-stat-content">
            <h3><?php echo $total_items; ?></h3>
            <p>Total Items</p>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-content">
            <h3 style="color: #ff9800;"><?php echo $low_count; ?></h3>
            <p>Low Stock</p>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-content">
            <h3 style="color: #f44336;"><?php echo $out_count; ?></h3>
            <p>Out of Stock</p>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-content">
            <h3 style="color: #4caf50;"><?php echo formatPrice($total_value); ?></h3>
            <p>Total Value</p>
        </div>
    </div>
</div>

<!-- Add/Edit Form -->
<div class="staff-card">
    <h2><?php echo $edit_item ? 'Edit Item' : 'Add Inventory Item'; ?></h2>
    <form method="POST" class="staff-form">
        <?php csrfTokenField(); ?>
        <input type="hidden" name="action" value="<?php echo $edit_item ? 'edit' : 'add'; ?>">
        <?php if ($edit_item): ?>
            <input type="hidden" name="id" value="<?php echo $edit_item['id']; ?>">
        <?php endif; ?>
        
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
            <div class="form-group">
                <label>Item Name</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($edit_item['name'] ?? ''); ?>" required placeholder="e.g. Gel Nail Polish" class="form-control">
            </div>
            <div class="form-group">
                <label>Category</label>
                <input type="text" name="category" value="<?php echo htmlspecialchars($edit_item['category'] ?? 'General'); ?>" required placeholder="e.g. Nail Supplies" class="form-control">
            </div>
        </div>
        
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin-bottom: 15px;">
            <?php if (!$edit_item): ?>
            <div class="form-group">
                <label>Initial Quantity</label>
                <input type="number" name="quantity" value="0" min="0" required class="form-control">
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label>Reorder Level</label>
                <input type="number" name="reorder_level" value="<?php echo $edit_item['reorder_level'] ?? 5; ?>" min="0" required class="form-control">
                <small style="color: #888;">Alert when stock drops to this level</small>
            </div>
            <div class="form-group">
                <label>Unit</label>
                <select name="unit" class="form-control">
                    <?php 
                    $units = ['pcs' => 'Pieces', 'ml' => 'Milliliters', 'g' => 'Grams', 'box' => 'Boxes', 'set' => 'Sets', 'roll' => 'Rolls', 'bottle' => 'Bottles'];
                    foreach ($units as $val => $label): ?>
                        <option value="<?php echo $val; ?>" <?php echo ($edit_item['unit'] ?? 'pcs') === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Cost Per Unit (₱)</label>
                <input type="number" name="cost_per_unit" value="<?php echo $edit_item['cost_per_unit'] ?? 0; ?>" min="0" step="0.01" class="form-control">
            </div>
        </div>
        
        <div class="form-group" style="margin-bottom: 15px;">
            <label>Linked Service (Optional)</label>
            <select name="linked_service_id" class="form-control">
                <option value="">— None —</option>
                <?php foreach ($services as $svc): ?>
                    <option value="<?php echo $svc['id']; ?>" <?php echo ($edit_item['linked_service_id'] ?? '') == $svc['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($svc['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?php echo $edit_item ? 'Update' : 'Add'; ?> Item</button>
            <?php if ($edit_item): ?>
                <a href="inventory.php" class="btn btn-outline">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Inventory Table -->
<div class="staff-card">
    <h2>All Inventory Items</h2>
    <div class="table-responsive">
        <table class="staff-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Qty</th>
                    <th>Reorder Level</th>
                    <th>Unit Cost</th>
                    <th>Linked Service</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="8" style="text-align: center; padding: 30px; color: #888;">No inventory items. Add one above.</td></tr>
                <?php endif; ?>
                <?php foreach ($items as $item): ?>
                    <?php
                    $status_styles = [
                        'in_stock' => 'background: #4caf50; color: #fff;',
                        'low_stock' => 'background: #ff9800; color: #fff;',
                        'out_of_stock' => 'background: #f44336; color: #fff;'
                    ];
                    $status_labels = [
                        'in_stock' => 'In Stock',
                        'low_stock' => 'Low Stock',
                        'out_of_stock' => 'Out of Stock'
                    ];
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($item['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($item['category']); ?></td>
                        <td>
                            <span style="font-weight: 700; font-size: 1.1rem; color: <?php echo $item['status'] === 'out_of_stock' ? '#f44336' : ($item['status'] === 'low_stock' ? '#ff9800' : '#4caf50'); ?>;">
                                <?php echo $item['quantity']; ?>
                            </span>
                            <small><?php echo $item['unit']; ?></small>
                        </td>
                        <td><?php echo $item['reorder_level']; ?></td>
                        <td><?php echo formatPrice($item['cost_per_unit']); ?></td>
                        <td><?php echo $item['service_name'] ? htmlspecialchars($item['service_name']) : '<span style="color:#999;">—</span>'; ?></td>
                        <td>
                            <span class="status-badge" style="<?php echo $status_styles[$item['status']] ?? ''; ?> padding: 4px 10px; border-radius: 12px; font-size: 0.75rem;">
                                <?php echo $status_labels[$item['status']] ?? $item['status']; ?>
                            </span>
                        </td>
                        <td style="white-space: nowrap;">
                            <!-- Restock -->
                            <form method="POST" style="display: inline;" onsubmit="var q=prompt('Restock quantity:'); if(!q||isNaN(q)||q<=0){return false;} this.querySelector('[name=quantity]').value=q; return true;">
                                <?php csrfTokenField(); ?>
                                <input type="hidden" name="action" value="restock">
                                <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                <input type="hidden" name="quantity" value="">
                                <input type="hidden" name="notes" value="Manual restock">
                                <button type="submit" class="btn btn-sm" style="background:#4caf50; color:#fff;" title="Restock">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </form>
                            <!-- Deduct -->
                            <form method="POST" style="display: inline;" onsubmit="var q=prompt('Deduct quantity:'); if(!q||isNaN(q)||q<=0){return false;} this.querySelector('[name=quantity]').value=q; return true;">
                                <?php csrfTokenField(); ?>
                                <input type="hidden" name="action" value="deduct">
                                <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                <input type="hidden" name="quantity" value="">
                                <input type="hidden" name="notes" value="Manual deduction">
                                <button type="submit" class="btn btn-sm" style="background:#ff9800; color:#fff;" title="Deduct">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </form>
                            <a href="inventory.php?edit=<?php echo $item['id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this item?')">
                                <?php csrfTokenField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Activity Log -->
<div class="staff-card">
    <h2><i class="fas fa-history"></i> Recent Activity Log</h2>
    <?php if (empty($recent_logs)): ?>
        <p style="color: #888; text-align: center; padding: 20px;">No activity yet.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="staff-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Item</th>
                        <th>Action</th>
                        <th>Change</th>
                        <th>After</th>
                        <th>Notes</th>
                        <th>By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_logs as $log): ?>
                        <tr>
                            <td><?php echo formatDateTime($log['created_at']); ?></td>
                            <td><?php echo htmlspecialchars($log['item_name']); ?></td>
                            <td>
                                <?php 
                                $type_colors = ['restock' => '#4caf50', 'deduct' => '#f44336', 'adjust' => '#2196f3'];
                                $type_icons = ['restock' => 'fa-plus-circle', 'deduct' => 'fa-minus-circle', 'adjust' => 'fa-sync'];
                                ?>
                                <span style="color: <?php echo $type_colors[$log['change_type']] ?? '#888'; ?>;">
                                    <i class="fas <?php echo $type_icons[$log['change_type']] ?? 'fa-circle'; ?>"></i>
                                    <?php echo ucfirst($log['change_type']); ?>
                                </span>
                            </td>
                            <td style="font-weight: 600; color: <?php echo $log['quantity_change'] >= 0 ? '#4caf50' : '#f44336'; ?>;">
                                <?php echo ($log['quantity_change'] >= 0 ? '+' : '') . $log['quantity_change']; ?>
                            </td>
                            <td><?php echo $log['quantity_after']; ?></td>
                            <td><?php echo htmlspecialchars($log['notes'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/staff-footer.php'; ?>
