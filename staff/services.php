<?php
require_once '../config/functions.php';
requireStaffOrAdmin();

// Only Admins can manage services
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

$conn = getDBConnection();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    requireCSRFToken();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = sanitize($_POST['name'] ?? '');
        $category = $_POST['category'] ?? '';
        $description = sanitize($_POST['description'] ?? '');
        $duration = (int)($_POST['duration'] ?? 60);
        $base_price = (float)($_POST['base_price'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        
        if ($name && $category && $duration && $base_price) {
            $image_url = '';
            // Handle Image Upload with security validation
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = secureFileUpload(
                    $_FILES['image'], 
                    '../assets/images/services/', 
                    'service_'
                );
                
                if ($uploadResult['success']) {
                    $image_url = 'assets/images/services/' . $uploadResult['path'];
                } else {
                    $error = $uploadResult['error'];
                }
            }

            $stmt = $conn->prepare("INSERT INTO services (name, category, description, duration, base_price, status, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $category, $description, $duration, $base_price, $status, $image_url]);
            $success = 'Service added successfully';
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'] ?? 0;
        $name = sanitize($_POST['name'] ?? '');
        $category = $_POST['category'] ?? '';
        $description = sanitize($_POST['description'] ?? '');
        $duration = (int)($_POST['duration'] ?? 60);
        $base_price = (float)($_POST['base_price'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        
        if ($id) {
            $image_url = $_POST['current_image'] ?? '';
            
            // Handle Image Upload with security validation
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = secureFileUpload(
                    $_FILES['image'], 
                    '../assets/images/services/', 
                    'service_'
                );
                
                if ($uploadResult['success']) {
                    // Delete old image if exists and it's a local file
                    if ($image_url && file_exists('../' . $image_url)) {
                        @unlink('../' . $image_url);
                    }
                    $image_url = 'assets/images/services/' . $uploadResult['path'];
                } else {
                    $error = $uploadResult['error'];
                }
            }

            $stmt = $conn->prepare("UPDATE services SET name = ?, category = ?, description = ?, duration = ?, base_price = ?, status = ?, image_url = ? WHERE id = ?");
            $stmt->execute([$name, $category, $description, $duration, $base_price, $status, $image_url, $id]);
            $success = 'Service updated successfully';
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM services WHERE id = ?");
            $stmt->execute([$id]);
            $success = 'Service deleted successfully';
        }
    }
}

// Get services
$stmt = $conn->query("SELECT * FROM services ORDER BY category, name");
$services = $stmt->fetchAll();

// Get service for editing
$edit_service = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_service = $stmt->fetch();
}
?>
<?php include '../includes/staff-header.php'; ?>

<div class="staff-page-header">
    <h1><i class="fas fa-spa"></i> Manage Services</h1>
    <a href="index.php" class="btn btn-outline btn-sm">Back to Dashboard</a>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Add/Edit Form -->
<div class="staff-card">
    <h2><?php echo $edit_service ? 'Edit Service' : 'Add New Service'; ?></h2>
    <form method="POST" class="staff-form" enctype="multipart/form-data">
        <?php csrfTokenField(); ?>
        <input type="hidden" name="action" value="<?php echo $edit_service ? 'edit' : 'add'; ?>">
        <?php if ($edit_service): ?>
            <input type="hidden" name="id" value="<?php echo $edit_service['id']; ?>">
            <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($edit_service['image_url'] ?? ''); ?>">
        <?php endif; ?>
        
        <div class="form-group" style="margin-bottom: 15px;">
            <label>Service Name</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($edit_service['name'] ?? ''); ?>" required class="form-control">
        </div>
        
        <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
            <div class="form-group">
                <label>Category</label>
                <select name="category" required class="form-control">
                    <option value="nails" <?php echo ($edit_service['category'] ?? '') === 'nails' ? 'selected' : ''; ?>>Nails</option>
                    <option value="eyebrows" <?php echo ($edit_service['category'] ?? '') === 'eyebrows' ? 'selected' : ''; ?>>Eyebrows</option>
                    <option value="lashes" <?php echo ($edit_service['category'] ?? '') === 'lashes' ? 'selected' : ''; ?>>Lashes</option>
                    <option value="wax" <?php echo ($edit_service['category'] ?? '') === 'wax' ? 'selected' : ''; ?>>Wax</option>
                    <option value="massages" <?php echo ($edit_service['category'] ?? '') === 'massages' ? 'selected' : ''; ?>>Massages</option>
                    <option value="facial" <?php echo ($edit_service['category'] ?? '') === 'facial' ? 'selected' : ''; ?>>Facial</option>
                    <option value="skin_slimming" <?php echo ($edit_service['category'] ?? '') === 'skin_slimming' ? 'selected' : ''; ?>>Skin & Slimming</option>
                </select>
            </div>
            <div class="form-group">
                <label>Duration (minutes)</label>
                <input type="number" name="duration" value="<?php echo $edit_service['duration'] ?? 60; ?>" min="15" step="15" required class="form-control">
            </div>
            <div class="form-group">
                <label>Base Price (₱)</label>
                <input type="number" name="base_price" value="<?php echo $edit_service['base_price'] ?? 0; ?>" min="0" step="0.01" required class="form-control">
            </div>
        </div>
        
        <div class="form-group" style="margin-bottom: 15px;">
            <label>Service Image</label>
            <div class="image-upload-container">
                <?php if (!empty($edit_service['image_url'])): ?>
                    <div class="current-image" style="margin-bottom: 10px;">
                        <img src="../<?php echo htmlspecialchars($edit_service['image_url']); ?>" alt="Service Image" style="max-width: 150px; border-radius: 8px;">
                    </div>
                <?php endif; ?>
                <input type="file" name="image" accept="image/*" class="form-control-file">
                <small style="display:block; color:#888;">Recommended size: 500x500px or square aspect ratio</small>
            </div>
        </div>
        
        <div class="form-group" style="margin-bottom: 15px;">
            <label>Description</label>
            <textarea name="description" rows="4" class="form-control"><?php echo htmlspecialchars($edit_service['description'] ?? ''); ?></textarea>
        </div>
        
        <div class="form-group" style="margin-bottom: 15px;">
            <label>Status</label>
            <select name="status" required class="form-control">
                <option value="active" <?php echo ($edit_service['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo ($edit_service['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?php echo $edit_service ? 'Update' : 'Add'; ?> Service</button>
            <?php if ($edit_service): ?>
                <a href="services.php" class="btn btn-outline">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Services List -->
<div class="staff-card">
    <h2>All Services</h2>
    <div class="table-responsive">
        <table class="staff-table">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Duration</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $service): ?>
                    <tr>
                        <td>
                            <?php if (!empty($service['image_url'])): ?>
                                <img src="../<?php echo htmlspecialchars($service['image_url']); ?>" alt="Service" style="width:50px; height:50px; object-fit:cover; border-radius:4px;">
                            <?php else: ?>
                                <div style="width:50px; height:50px; background:#f0f0f0; display:flex; align-items:center; justify-content:center; border-radius:4px; color:#ccc;"><i class="fas fa-image"></i></div>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo htmlspecialchars($service['name']); ?></strong></td>
                        <td><?php echo ucfirst($service['category']); ?></td>
                        <td><?php echo $service['duration']; ?> min</td>
                        <td><?php echo formatPrice($service['base_price']); ?></td>
                        <td><span class="status-badge status-<?php echo $service['status']; ?>"><?php echo ucfirst($service['status']); ?></span></td>
                        <td>
                            <a href="services.php?edit=<?php echo $service['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure?')">
                                <?php csrfTokenField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $service['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/staff-footer.php'; ?>
