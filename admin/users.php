<?php
require_once '../config/functions.php';
requireAdmin();

$conn = getDBConnection();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'client';
        
        if ($first_name && $last_name && $email && $phone && $password) {
            // Check if email exists
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Email already exists';
            } elseif (preg_match('/[0-9]/', $first_name) || preg_match('/[0-9]/', $last_name)) {
                $error = 'First Name and Last Name should not contain numbers.';
            } else {
                try {
                    $first_name = sanitize($first_name);
                    $last_name = sanitize($last_name);
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$first_name, $last_name, $email, $phone, $hashed_password, $role]);
                    $success = 'User added successfully';
                } catch (PDOException $e) {
                    $error = 'Error adding user: ' . $e->getMessage();
                }
            }
        } else {
            $error = 'All fields are required';
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'] ?? 0;
        $first_name = sanitize($_POST['first_name'] ?? '');
        $last_name = sanitize($_POST['last_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $role = $_POST['role'] ?? 'client';
        $status = $_POST['status'] ?? 'active';
        
        if ($id) {
            if (preg_match('/[0-9]/', $first_name) || preg_match('/[0-9]/', $last_name)) {
                $error = 'First Name and Last Name should not contain numbers.';
            } else {
                $first_name = sanitize($first_name);
                $last_name = sanitize($last_name);
                $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, role = ?, status = ? WHERE id = ?");
                $stmt->execute([$first_name, $last_name, $email, $phone, $role, $status, $id]);
                $success = 'User updated successfully';
            }
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
            $stmt->execute([$id]);
            $success = 'User deleted successfully';
        }
    }
}

// Get users
$query = "SELECT * FROM users ORDER BY created_at DESC";
$stmt = $conn->query($query);
$users = $stmt->fetchAll();


// Get user for editing
$edit_user = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_user = $stmt->fetch();
}

// Initialize categorized arrays
$clients = [];
$staff_users = [];
$admin_users = [];

// Categorize users
if (!empty($users)) {
    foreach ($users as $u) {
        if ($u['role'] === 'client') $clients[] = $u;
        elseif ($u['role'] === 'staff') $staff_users[] = $u;
        elseif ($u['role'] === 'admin') $admin_users[] = $u;
    }
}

// Get active tab from URL or default to clients
$active_tab = $_GET['tab'] ?? 'clients';
?>
<?php include '../includes/admin-header.php'; ?>

<style>
.user-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 2rem;
    border-bottom: 2px solid #eee;
    padding-bottom: 10px;
}

.tab-link {
    padding: 0.8rem 1.5rem;
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 8px 8px 0 0;
    color: var(--dark-color);
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
}

.tab-link.active {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.tab-link:hover:not(.active) {
    background: #e9ecef;
}

.tab-count {
    background: rgba(0,0,0,0.1);
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.8rem;
    margin-left: 5px;
}

.tab-link.active .tab-count {
    background: rgba(255,255,255,0.2);
}
</style>

            <div class="admin-content">
            <div class="admin-header">
                <h1><i class="fas fa-users"></i> Manage Users</h1>
                <a href="index.php" class="btn btn-outline">Back to Dashboard</a>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger" style="color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; padding: 0.75rem 1.25rem; margin-bottom: 1rem; border: 1px solid transparent; border-radius: 0.25rem;"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Add/Edit Form -->
            <div class="admin-card">
                <h2><?php echo $edit_user ? 'Edit User' : 'Add New User'; ?></h2>
                <form method="POST" class="admin-form">
                    <input type="hidden" name="action" value="<?php echo $edit_user ? 'edit' : 'add'; ?>">
                    <?php if ($edit_user): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_user['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" name="first_name" value="<?php echo $edit_user['first_name'] ?? ''; ?>" required pattern="[^0-9]*" title="First Name should not contain numbers.">
                        </div>
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" value="<?php echo $edit_user['last_name'] ?? ''; ?>" required pattern="[^0-9]*" title="Last Name should not contain numbers.">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo $edit_user['email'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" value="<?php echo $edit_user['phone'] ?? ''; ?>" required>
                    </div>
                    
                    <?php if (!$edit_user): ?>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="password" required>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Role</label>
                            <select name="role" required>
                                <option value="client" <?php echo ($edit_user['role'] ?? '') === 'client' ? 'selected' : ''; ?>>Client</option>
                                <option value="staff" <?php echo ($edit_user['role'] ?? '') === 'staff' ? 'selected' : ''; ?>>Staff</option>
                                <option value="admin" <?php echo ($edit_user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        <?php if ($edit_user): ?>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" required>
                                    <option value="active" <?php echo $edit_user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $edit_user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?php echo $edit_user ? 'Update' : 'Add'; ?> User</button>
                        <?php if ($edit_user): ?>
                            <a href="users.php" class="btn btn-outline">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>


            <!-- Tab Navigation -->
            <div class="user-tabs">
                <a href="?tab=clients" 
                   class="tab-link <?php echo $active_tab === 'clients' ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i> Clients 
                    <span class="tab-count"><?php echo count($clients); ?></span>
                </a>
                <a href="?tab=staff" 
                   class="tab-link <?php echo $active_tab === 'staff' ? 'active' : ''; ?>">
                    <i class="fas fa-user-tie"></i> Staff 
                    <span class="tab-count"><?php echo count($staff_users); ?></span>
                </a>
                <a href="?tab=admins" 
                   class="tab-link <?php echo $active_tab === 'admins' ? 'active' : ''; ?>">
                    <i class="fas fa-user-shield"></i> Admins 
                    <span class="tab-count"><?php echo count($admin_users); ?></span>
                </a>
            </div>

            <!-- Users List -->
            <div class="admin-card">
                <?php
                $display_users = [];
                $tab_title = "";
                if ($active_tab === 'clients') {
                    $display_users = $clients;
                    $tab_title = "Clients";
                } elseif ($active_tab === 'staff') {
                    $display_users = $staff_users;
                    $tab_title = "Staff Members";
                } else {
                    $display_users = $admin_users;
                    $tab_title = "Administrators";
                }
                ?>
                <h2><?php echo $tab_title; ?></h2>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($display_users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                    <td><span class="status-badge status-<?php echo $user['status']; ?>"><?php echo ucfirst($user['status']); ?></span></td>
                                    <td><?php echo formatDate($user['created_at']); ?></td>
                                    <td>
                                        <a href="users.php?edit=<?php echo $user['id']; ?>&tab=<?php echo $active_tab; ?>" class="btn btn-sm btn-primary">Edit</a>
                                        <?php if ($user['role'] !== 'admin'): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($display_users)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 2rem;">No users found in this category.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
    </div>
</main>

<?php include '../includes/admin-footer.php'; ?>
