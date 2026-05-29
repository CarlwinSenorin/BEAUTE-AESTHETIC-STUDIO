<?php
require_once '../config/functions.php';
requireAdmin();

$conn = getDBConnection();

// Get all services for package selection
$stmt = $conn->query("SELECT id, name, base_price as price, category FROM services WHERE status = 'active' ORDER BY category, name");
$all_services = $stmt->fetchAll();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $services = $_POST['services'] ?? [];
        $pax = max(1, (int)($_POST['pax'] ?? 1));
        $original_price = (float)($_POST['original_price'] ?? 0);
        $discounted_price = (float)($_POST['discounted_price'] ?? 0);
        $valid_from = $_POST['valid_from'] ?? date('Y-m-d');
        $valid_until = $_POST['valid_until'] ?? null;
        $status = $_POST['status'] ?? 'active';
        
        if ($name && !empty($services) && $original_price && $discounted_price) {
            $discount_percentage = (($original_price - $discounted_price) / $original_price) * 100;
            $services_json = json_encode($services);
            
            $stmt = $conn->prepare("INSERT INTO packages (name, description, services, pax, original_price, discounted_price, discount_percentage, valid_from, valid_until, status) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $description, $services_json, $pax, $original_price, $discounted_price, $discount_percentage, $valid_from, $valid_until, $status]);
            $success = 'Package added successfully';
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'] ?? 0;
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $services = $_POST['services'] ?? [];
        $pax = max(1, (int)($_POST['pax'] ?? 1));
        $original_price = (float)($_POST['original_price'] ?? 0);
        $discounted_price = (float)($_POST['discounted_price'] ?? 0);
        $valid_from = $_POST['valid_from'] ?? date('Y-m-d');
        $valid_until = $_POST['valid_until'] ?? null;
        $status = $_POST['status'] ?? 'active';
        
        if ($id && $name && !empty($services)) {
            $discount_percentage = (($original_price - $discounted_price) / $original_price) * 100;
            $services_json = json_encode($services);
            
            $stmt = $conn->prepare("UPDATE packages SET name = ?, description = ?, services = ?, pax = ?, original_price = ?, discounted_price = ?, discount_percentage = ?, valid_from = ?, valid_until = ?, status = ? WHERE id = ?");
            $stmt->execute([$name, $description, $services_json, $pax, $original_price, $discounted_price, $discount_percentage, $valid_from, $valid_until, $status, $id]);
            $success = 'Package updated successfully';
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM packages WHERE id = ?");
            $stmt->execute([$id]);
            $success = 'Package deleted successfully';
        }
    }
}

// Get packages
$stmt = $conn->query("SELECT * FROM packages ORDER BY created_at DESC");
$packages = $stmt->fetchAll();

// Get package for editing
$edit_package = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM packages WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_package = $stmt->fetch();
}
?>
<?php include '../includes/admin-header.php'; ?>

            <div class="admin-content">
            <div class="admin-header">
                <h1><i class="fas fa-gift"></i> Manage Packages</h1>
                <a href="index.php" class="btn btn-outline">Back to Dashboard</a>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- Add/Edit Form -->
            <div class="admin-card">
                <h2><?php echo $edit_package ? 'Edit Package' : 'Add New Package'; ?></h2>
                <form method="POST" class="admin-form">
                    <input type="hidden" name="action" value="<?php echo $edit_package ? 'edit' : 'add'; ?>">
                    <?php if ($edit_package): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_package['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Package Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($edit_package['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="3"><?php echo htmlspecialchars($edit_package['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Number of Persons (Pax)</label>
                        <input type="number" name="pax" value="<?php echo $edit_package['pax'] ?? 1; ?>" min="1" max="10" required>
                        <small class="text-muted">How many persons this package is for</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Select Services</label>
                        <div class="services-wrapper" style="border: 1px solid #ddd; padding: 1rem; border-radius: 8px; max-height: 400px; overflow-y: auto;">
                            <?php 
                            $selected_services = $edit_package ? json_decode($edit_package['services'], true) : [];
                            $current_category = null;
                            
                            foreach ($all_services as $service): 
                                if ($service['category'] !== $current_category):
                                    $current_category = $service['category'];
                            ?>
                                <div class="category-group" style="margin-bottom: 1rem;">
                                    <h4 style="margin: 0.5rem 0; color: var(--primary-color); border-bottom: 1px solid #eee; padding-bottom: 0.25rem;">
                                        <?php echo htmlspecialchars(ucfirst($current_category)); ?>
                                    </h4>
                                    <div class="services-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.5rem;">
                                <?php endif; ?>
                                
                                <label class="service-checkbox-label" style="display: flex; align-items: center; gap: 0.5rem; background: #f8f9fa; padding: 0.5rem; border-radius: 4px;">
                                    <input type="checkbox" class="service-checkbox" name="services[]" 
                                           value="<?php echo $service['id']; ?>" 
                                           data-price="<?php echo $service['price']; ?>"
                                           <?php echo in_array($service['id'], $selected_services) ? 'checked' : ''; ?>>
                                    <div>
                                        <?php echo htmlspecialchars($service['name']); ?>
                                        <div style="font-size: 0.8rem; color: #666;">₱<?php echo number_format($service['price'], 2); ?></div>
                                    </div>
                                </label>
                                
                                <?php 
                                // Check if next service has different category or is end of array to close div
                                $next_index = array_search($service, $all_services) + 1;
                                if (!isset($all_services[$next_index]) || $all_services[$next_index]['category'] !== $current_category):
                                ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Original Price (₱)</label>
                            <input type="number" id="original_price" name="original_price" value="<?php echo $edit_package['original_price'] ?? 0; ?>" min="0" step="0.01" readonly style="background-color: #e9ecef;">
                            <small class="text-muted">Auto-calculated based on selected services</small>
                        </div>
                         <div class="form-group">
                            <label>Discount Percentage (%)</label>
                            <input type="number" id="discount_percentage" name="discount_percentage" value="<?php echo $edit_package['discount_percentage'] ?? 0; ?>" min="0" max="100" step="0.1">
                        </div>
                        <div class="form-group">
                            <label>Discounted Price (₱)</label>
                            <input type="number" id="discounted_price" name="discounted_price" value="<?php echo $edit_package['discounted_price'] ?? 0; ?>" min="0" step="0.01" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Valid From</label>
                            <input type="date" name="valid_from" value="<?php echo $edit_package['valid_from'] ?? date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Valid Until (Optional)</label>
                            <input type="date" name="valid_until" value="<?php echo $edit_package['valid_until'] ?? ''; ?>">
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" required>
                                <option value="active" <?php echo ($edit_package['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($edit_package['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?php echo $edit_package ? 'Update' : 'Add'; ?> Package</button>
                        <?php if ($edit_package): ?>
                            <a href="packages.php" class="btn btn-outline">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Packages List -->
            <div class="admin-card">
                <h2>All Packages</h2>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Services</th>
                                <th>Pax</th>
                                <th>Original Price</th>
                                <th>Discounted Price</th>
                                <th>Discount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($packages as $package): 
                                $services = json_decode($package['services'], true);
                                if (!empty($services) && is_array($services)) {
                                    $placeholders = str_repeat('?,', count($services) - 1) . '?';
                                    $stmt = $conn->prepare("SELECT name FROM services WHERE id IN ($placeholders)");
                                    $stmt->execute($services);
                                    $service_names = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                } else {
                                    $service_names = [];
                                }
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($package['name']); ?></td>
                                    <td><small><?php echo htmlspecialchars(implode(', ', $service_names)); ?></small></td>
                                    <td><?php echo $package['pax'] ?? 1; ?> <?php echo ($package['pax'] ?? 1) > 1 ? 'Persons' : 'Person'; ?></td>
                                    <td><?php echo formatPrice($package['original_price']); ?></td>
                                    <td><?php echo formatPrice($package['discounted_price']); ?></td>
                                    <td><?php echo number_format($package['discount_percentage'], 1); ?>%</td>
                                    <td><span class="status-badge status-<?php echo $package['status']; ?>"><?php echo ucfirst($package['status']); ?></span></td>
                                    <td>
                                        <a href="packages.php?edit=<?php echo $package['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $package['id']; ?>">
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.service-checkbox');
            const originalPriceInput = document.getElementById('original_price');
            const discountPercentInput = document.getElementById('discount_percentage');
            const discountedPriceInput = document.getElementById('discounted_price');

            function calculateTotals() {
                let total = 0;
                checkboxes.forEach(cb => {
                    if (cb.checked) {
                        total += parseFloat(cb.getAttribute('data-price') || 0);
                    }
                });

                originalPriceInput.value = total.toFixed(2);
                calculateDiscount();
            }

            function calculateDiscount() {
                const total = parseFloat(originalPriceInput.value) || 0;
                const percent = parseFloat(discountPercentInput.value) || 0;
                
                if (total > 0) {
                    const discounted = total - (total * (percent / 100));
                    discountedPriceInput.value = discounted.toFixed(2);
                } else {
                    discountedPriceInput.value = "0.00";
                }
            }
            
            // Recalculate percentage if discounted price is manually changed
            function calculatePercentage() {
                const total = parseFloat(originalPriceInput.value) || 0;
                const discounted = parseFloat(discountedPriceInput.value) || 0;
                
                if (total > 0 && discounted <= total) {
                    const percent = ((total - discounted) / total) * 100;
                    discountPercentInput.value = percent.toFixed(1);
                }
            }

            checkboxes.forEach(cb => {
                cb.addEventListener('change', calculateTotals);
            });

            discountPercentInput.addEventListener('input', calculateDiscount);
            discountedPriceInput.addEventListener('input', calculatePercentage);
        });
    </script>
    <style>
        .services-checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 0.5rem;
            max-height: 200px;
            overflow-y: auto;
            padding: 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .service-checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
    </style>
<?php include '../includes/admin-footer.php'; ?>
