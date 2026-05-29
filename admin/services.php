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
        $category = $_POST['category'] ?? '';
        $description = sanitize($_POST['description'] ?? '');
        $duration = (int)($_POST['duration'] ?? 60);
        $base_price = (float)($_POST['base_price'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        
        if ($name && $category && $duration && $base_price) {
            $image_url = '';
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
            
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = secureFileUpload(
                    $_FILES['image'], 
                    '../assets/images/services/', 
                    'service_'
                );
                if ($uploadResult['success']) {
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

// Category filter
$categoryFilter = $_GET['category'] ?? 'all';
$validCategories = ['all', 'nails', 'eyebrows', 'lashes', 'wax', 'massages', 'facial', 'skin_slimming'];
if (!in_array($categoryFilter, $validCategories)) $categoryFilter = 'all';

// Get services (filtered)
if ($categoryFilter !== 'all') {
    $stmt = $conn->prepare("SELECT * FROM services WHERE category = ? ORDER BY name");
    $stmt->execute([$categoryFilter]);
} else {
    $stmt = $conn->query("SELECT * FROM services ORDER BY category, name");
}
$services = $stmt->fetchAll();

// Get counts per category
$countStmt = $conn->query("SELECT category, COUNT(*) as cnt FROM services GROUP BY category");
$categoryCounts = ['all' => 0];
while ($row = $countStmt->fetch()) {
    $categoryCounts[$row['category']] = (int)$row['cnt'];
    $categoryCounts['all'] += (int)$row['cnt'];
}

// Get service for editing
$edit_service = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_service = $stmt->fetch();
}

// Category display names
$categoryLabels = [
    'all' => 'All',
    'nails' => 'Nails',
    'eyebrows' => 'Eyebrows',
    'lashes' => 'Lashes',
    'wax' => 'Wax',
    'massages' => 'Massages',
    'facial' => 'Facial',
    'skin_slimming' => 'Skin & Slimming'
];

$categoryIcons = [
    'all' => 'fas fa-th-large',
    'nails' => 'fas fa-hand-sparkles',
    'eyebrows' => 'fas fa-eye',
    'lashes' => 'fas fa-eye',
    'wax' => 'fas fa-fire',
    'massages' => 'fas fa-hands',
    'facial' => 'fas fa-smile',
    'skin_slimming' => 'fas fa-magic'
];
?>
<?php include '../includes/admin-header.php'; ?>

            <div class="admin-content">
            <div class="admin-header">
                <h1><i class="fas fa-spa"></i> Manage Services</h1>
                <a href="index.php" class="btn btn-outline">Back to Dashboard</a>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Add/Edit Form -->
            <div class="admin-card">
                <h2><?php echo $edit_service ? 'Edit Service' : 'Add New Service'; ?></h2>
                <form method="POST" class="admin-form" enctype="multipart/form-data">
                    <?php csrfTokenField(); ?>
                    <input type="hidden" name="action" value="<?php echo $edit_service ? 'edit' : 'add'; ?>">
                    <?php if ($edit_service): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_service['id']; ?>">
                        <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($edit_service['image_url'] ?? ''); ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Service Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($edit_service['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category" required>
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
                            <input type="number" name="duration" value="<?php echo $edit_service['duration'] ?? 60; ?>" min="15" step="15" required>
                        </div>
                        <div class="form-group">
                            <label>Base Price (₱)</label>
                            <input type="number" name="base_price" value="<?php echo $edit_service['base_price'] ?? 0; ?>" min="0" step="0.01" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Service Image</label>
                        <div class="image-upload-container">
                            <?php if (!empty($edit_service['image_url'])): ?>
                                <div class="current-image">
                                    <img src="../<?php echo htmlspecialchars($edit_service['image_url']); ?>" alt="Service Image" style="max-width: 150px; border-radius: 8px; margin-bottom: 10px;">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="image" accept="image/*" class="form-control-file">
                            <small>Recommended size: 500x500px or square aspect ratio</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="4"><?php echo htmlspecialchars($edit_service['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" required>
                            <option value="active" <?php echo ($edit_service['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($edit_service['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?php echo $edit_service ? 'Update' : 'Add'; ?> Service</button>
                        <?php if ($edit_service): ?>
                            <a href="services.php<?php echo $categoryFilter !== 'all' ? '?category=' . $categoryFilter : ''; ?>" class="btn btn-outline">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Category Filter Tabs -->
            <div class="admin-card">
                <div class="category-filter-tabs">
                    <?php foreach ($categoryLabels as $catKey => $catLabel): 
                        $count = $categoryCounts[$catKey] ?? 0;
                        $icon = $categoryIcons[$catKey] ?? 'fas fa-circle';
                    ?>
                        <a href="services.php?category=<?php echo $catKey; ?>" 
                           class="category-tab <?php echo $categoryFilter === $catKey ? 'active' : ''; ?>">
                            <i class="<?php echo $icon; ?>"></i>
                            <span><?php echo $catLabel; ?></span>
                            <span class="category-count"><?php echo $count; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Services List -->
            <div class="admin-card">
                <h2>
                    <?php echo $categoryFilter !== 'all' ? htmlspecialchars($categoryLabels[$categoryFilter] ?? ucfirst($categoryFilter)) . ' Services' : 'All Services'; ?>
                    <span style="font-size:0.8rem; color:#999; font-weight:400; margin-left:8px;">(<?php echo count($services); ?>)</span>
                </h2>
                <?php if (empty($services)): ?>
                    <p class="empty-state"><i class="fas fa-spa" style="font-size:2rem; display:block; margin-bottom:10px; color:#ddd;"></i>No services found in this category.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="admin-table">
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
                                            <img src="../<?php echo htmlspecialchars($service['image_url']); ?>" alt="Service" class="service-thumbnail">
                                        <?php else: ?>
                                            <div class="no-image"><i class="fas fa-image"></i></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($service['name']); ?></strong></td>
                                    <td>
                                        <span class="category-badge category-<?php echo $service['category']; ?>">
                                            <?php echo htmlspecialchars($categoryLabels[$service['category']] ?? ucfirst($service['category'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $service['duration']; ?> min</td>
                                    <td><?php echo formatPrice($service['base_price']); ?></td>
                                    <td><span class="status-badge status-<?php echo $service['status']; ?>"><?php echo ucfirst($service['status']); ?></span></td>
                                    <td style="white-space:nowrap;">
                                        <a href="services.php?edit=<?php echo $service['id']; ?><?php echo $categoryFilter !== 'all' ? '&category=' . $categoryFilter : ''; ?>" class="btn btn-sm btn-primary">Edit</a>
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
                <?php endif; ?>
            </div>
        </div>

    </div>

    <style>
    /* Category Filter Tabs */
    .category-filter-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .category-tab {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 8px 16px;
        border: 2px solid #e2e8f0;
        border-radius: 25px;
        text-decoration: none;
        color: #4a5568;
        font-size: 0.88rem;
        font-weight: 500;
        transition: all 0.3s;
        background: #fff;
    }
    .category-tab:hover {
        border-color: var(--primary-color, #d4a574);
        color: var(--primary-color, #d4a574);
        background: rgba(212, 165, 116, 0.05);
    }
    .category-tab.active {
        background: var(--primary-color, #d4a574);
        color: #fff;
        border-color: var(--primary-color, #d4a574);
    }
    .category-tab.active .category-count {
        background: rgba(255,255,255,0.3);
        color: #fff;
    }
    .category-tab i {
        font-size: 0.9rem;
    }
    .category-count {
        background: #edf2f7;
        color: #718096;
        padding: 1px 8px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 700;
    }

    /* Category Badges */
    .category-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.78rem;
        font-weight: 600;
    }
    .category-nails { background: #fed7e2; color: #97266d; }
    .category-eyebrows { background: #fefcbf; color: #975a16; }
    .category-lashes { background: #c6f6d5; color: #276749; }
    .category-wax { background: #feebc8; color: #c05621; }
    .category-massages { background: #bee3f8; color: #2b6cb0; }
    .category-facial { background: #e9d8fd; color: #6b46c1; }
    .category-skin_slimming { background: #fed7d7; color: #c53030; }
    </style>

<?php include '../includes/admin-footer.php'; ?>
