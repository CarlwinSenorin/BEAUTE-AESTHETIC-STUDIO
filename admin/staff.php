<?php
require_once '../config/functions.php';
requireAdmin();

$conn = getDBConnection();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_complete') {
        // Add complete staff member (user account + staff profile in one step)
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $spec_array = $_POST['specialization'] ?? [];
        $specialization = implode(', ', array_map('sanitize', $spec_array));
        $bio = sanitize($_POST['bio'] ?? '');
        
        if ($first_name && $last_name && $email && $phone && $password) {
            // Validation: No numbers in names
            if (preg_match('/[0-9]/', $first_name) || preg_match('/[0-9]/', $last_name)) {
                $error = 'First Name and Last Name should not contain numbers.';
            } else {
                $first_name = sanitize($first_name);
                $last_name = sanitize($last_name);
                // Check if email exists
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Email already exists. Please use a different email address.';
            } else {
                try {
                    // Start transaction
                    $conn->beginTransaction();
                    
                    // Create user account with staff role
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?, 'staff')");
                    $stmt->execute([$first_name, $last_name, $email, $phone, $hashed_password]);
                    $new_user_id = $conn->lastInsertId();
                    
                    // Create staff profile
                    $stmt = $conn->prepare("INSERT INTO staff (user_id, specialization, bio) VALUES (?, ?, ?)");
                    $stmt->execute([$new_user_id, $specialization, $bio]);
                    $new_staff_id = $conn->lastInsertId();
                    
                    // Commit transaction
                    $conn->commit();
                    
                    $success = 'Staff member added successfully! User account and staff profile created.';
                    $success_staff_id = $new_staff_id;
                } catch (PDOException $e) {
                    // Rollback on error
                    $conn->rollBack();
                    $error = 'Error adding staff member: ' . $e->getMessage();
                }
            }
            } // End name validation else
        } else {
            $error = 'All fields are required (First Name, Last Name, Email, Phone, Password)';
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $spec_array = $_POST['specialization'] ?? [];
        $specialization = implode(', ', array_map('sanitize', $spec_array));
        $bio = sanitize($_POST['bio'] ?? '');
        
        if ($id) {
            // Validation: No numbers in names
            if (preg_match('/[0-9]/', $first_name) || preg_match('/[0-9]/', $last_name)) {
                $error = 'First Name and Last Name should not contain numbers.';
            } else {
                try {
                $conn->beginTransaction();
                
                // Get user_id for this staff record
                $stmt = $conn->prepare("SELECT user_id FROM staff WHERE id = ?");
                $stmt->execute([$id]);
                $user_id = $stmt->fetchColumn();
                
                if ($user_id) {
                    // Update user info
                    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE id = ?");
                    $stmt->execute([sanitize($first_name), sanitize($last_name), $phone, $user_id]);
                }
                
                // Update staff info
                $stmt = $conn->prepare("UPDATE staff SET specialization = ?, bio = ? WHERE id = ?");
                $stmt->execute([$specialization, $bio, $id]);
                
                $conn->commit();
                $success = 'Staff member updated successfully';
            } catch (PDOException $e) {
                $conn->rollBack();
                $error = 'Error updating staff: ' . $e->getMessage();
            }
            } // End name validation else
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM staff WHERE id = ?");
            $stmt->execute([$id]);
            $success = 'Staff profile deleted successfully';
        }
    }
}

// Get all staff with user info
$stmt = $conn->query("SELECT s.*, u.first_name, u.last_name, u.email, u.phone, u.status as user_status 
                      FROM staff s 
                      JOIN users u ON s.user_id = u.id 
                      ORDER BY u.first_name, u.last_name");
$staff_list = $stmt->fetchAll();

// Get the specific specialization categories requested by the user
$all_categories = [
    'Nails',
    'Eyebrows',
    'Lashes',
    'Wax',
    'Massages',
    'Facial',
    'Skin & Slimming'
];


// Get staff for editing or viewing
$edit_staff = null;
$view_staff = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT s.*, u.first_name, u.last_name, u.email, u.phone 
                            FROM staff s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_staff = $stmt->fetch();
} elseif (isset($_GET['view'])) {
    $stmt = $conn->prepare("SELECT s.*, u.first_name, u.last_name, u.email, u.phone 
                            FROM staff s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
    $stmt->execute([$_GET['view']]);
    $view_staff = $stmt->fetch();
}
?>
<?php include '../includes/admin-header.php'; ?>

<style>
.checkbox-group-wrapper {
    background: #fdfdfd;
    border: 1px solid #eee;
    padding: 1rem;
    border-radius: 8px;
}
.select-all-label {
    display: block;
    border-bottom: 1px solid #eee;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
}
.specialization-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 0.8rem;
}
.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    font-size: 0.95rem;
}
.checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}
</style>

            <div class="admin-content">
            <div class="admin-header">
                <h1><i class="fas fa-user-tie"></i> Staff Profiles</h1>
                <a href="index.php" class="btn btn-outline">Back to Dashboard</a>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    <?php if (isset($success_staff_id)): ?>
                        <a href="staff.php?view=<?php echo $success_staff_id; ?>" class="btn btn-sm btn-outline" style="margin-left: 10px;">View Staff Profile</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($view_staff): ?>
            <!-- View Staff Card -->
            <div class="admin-card staff-view-card">
                <h2><i class="fas fa-user"></i> View Staff Profile</h2>
                <div class="staff-view-grid">
                    <div class="staff-view-section">
                        <h4>Personal Info</h4>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($view_staff['first_name'] . ' ' . $view_staff['last_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($view_staff['email']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($view_staff['phone']); ?></p>
                    </div>
                    <div class="staff-view-section">
                        <h4>Professional Info</h4>
                        <p><strong>Specialization:</strong> <?php echo htmlspecialchars($view_staff['specialization'] ?: 'Not set'); ?></p>
                        <p><strong>Rating:</strong> <?php for ($i = 1; $i <= 5; $i++): ?><i class="fas fa-star" style="color: <?php echo $i <= round($view_staff['rating']) ? '#ffc107' : '#ddd'; ?>"></i><?php endfor; ?> (<?php echo $view_staff['total_reviews']; ?> reviews)</p>
                    </div>
                    <?php if ($view_staff['bio']): ?>
                    <div class="staff-view-section full-width">
                        <h4>Bio</h4>
                        <p><?php echo nl2br(htmlspecialchars($view_staff['bio'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="staff-view-actions">
                    <a href="staff.php?edit=<?php echo $view_staff['id']; ?>" class="btn btn-primary">Edit</a>
                    <a href="staff.php" class="btn btn-outline">Back to List</a>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($edit_staff): ?>
            <!-- Edit Staff Card -->
            <div class="admin-card">
                <h2><i class="fas fa-user-edit"></i> Edit Staff Profile</h2>
                <form method="POST" class="admin-form">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?php echo $edit_staff['id']; ?>">
                    
                    <h3 style="margin-top: 0; color: var(--primary-color); border-bottom: 2px solid var(--primary-color); padding-bottom: 0.5rem;">Account Information</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name *</label>
                            <input type="text" name="first_name" value="<?php echo htmlspecialchars($edit_staff['first_name']); ?>" required pattern="[^0-9]*" title="First Name should not contain numbers.">
                        </div>
                        <div class="form-group">
                            <label>Last Name *</label>
                            <input type="text" name="last_name" value="<?php echo htmlspecialchars($edit_staff['last_name']); ?>" required pattern="[^0-9]*" title="Last Name should not contain numbers.">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email (Login)</label>
                            <input type="email" value="<?php echo htmlspecialchars($edit_staff['email']); ?>" disabled style="background:#f5f5f5; cursor:not-allowed;">
                            <small>Email cannot be changed.</small>
                        </div>
                        <div class="form-group">
                            <label>Phone *</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($edit_staff['phone']); ?>" required>
                        </div>
                    </div>
                    
                    <h3 style="color: var(--primary-color); border-bottom: 2px solid var(--primary-color); padding-bottom: 0.5rem; margin-top: 1.5rem;">Professional Information</h3>
                    
                    <div class="form-group">
                        <label>Specialization *</label>
                        <div class="checkbox-group-wrapper">
                            <label class="checkbox-label select-all-label">
                                <input type="checkbox" class="select-all-checkbox" data-target="edit-spec"> <strong>Select All</strong>
                            </label>
                            <div class="specialization-grid">
                                <?php 
                                $current_specs = array_map('trim', explode(',', $edit_staff['specialization'] ?? ''));
                                foreach ($all_categories as $cat): 
                                ?>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="specialization[]" value="<?php echo htmlspecialchars($cat); ?>" 
                                               class="edit-spec-checkbox" <?php echo in_array($cat, $current_specs) ? 'checked' : ''; ?>>
                                        <?php echo str_replace('_', ' & ', ucfirst($cat)); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Bio</label>
                        <textarea name="bio" rows="4" placeholder="Short bio about the staff member..."><?php echo htmlspecialchars($edit_staff['bio'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                        <a href="staff.php" class="btn btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- Add Complete Staff Member -->
            <div class="admin-card" <?php echo ($view_staff || $edit_staff) ? 'style="display:none"' : ''; ?>>
                <h2><i class="fas fa-user-plus"></i> Add New Staff Member</h2>
                <p class="form-description">Create a complete staff member with user account and profile in one step. This will create a new user with staff role and their professional profile.</p>
                
                <form method="POST" class="admin-form">
                    <input type="hidden" name="action" value="add_complete">
                    
                    <h3 style="margin-top: 0; color: var(--primary-color); border-bottom: 2px solid var(--primary-color); padding-bottom: 0.5rem;">Account Information</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name *</label>
                            <input type="text" name="first_name" placeholder="John" required pattern="[^0-9]*" title="First Name should not contain numbers.">
                        </div>
                        <div class="form-group">
                            <label>Last Name *</label>
                            <input type="text" name="last_name" placeholder="Doe" required pattern="[^0-9]*" title="Last Name should not contain numbers.">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" placeholder="john.doe@example.com" required>
                        </div>
                        <div class="form-group">
                            <label>Phone *</label>
                            <input type="tel" name="phone" placeholder="+1234567890" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Password *</label>
                        <input type="password" name="password" placeholder="Minimum 6 characters" required minlength="6">
                        <small>Staff member will use this to login</small>
                    </div>
                    
                    <h3 style="color: var(--primary-color); border-bottom: 2px solid var(--primary-color); padding-bottom: 0.5rem; margin-top: 1.5rem;">Professional Information</h3>
                    
                    <div class="form-group">
                        <label>Specialization *</label>
                        <div class="checkbox-group-wrapper">
                            <label class="checkbox-label select-all-label">
                                <input type="checkbox" class="select-all-checkbox" data-target="add-spec"> <strong>Select All</strong>
                            </label>
                            <div class="specialization-grid">
                                <?php foreach ($all_categories as $cat): ?>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="specialization[]" value="<?php echo htmlspecialchars($cat); ?>" class="add-spec-checkbox">
                                        <?php echo str_replace('_', ' & ', ucfirst($cat)); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <small>What services does this staff member specialize in?</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Bio</label>
                        <textarea name="bio" rows="4" placeholder="Short bio about the staff member's experience and expertise..."></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Staff Member</button>
                    </div>
                </form>
            </div>


            </div>


            <!-- Staff List -->
            <div class="admin-card">
                <h2>All Staff Profiles</h2>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Specialization</th>
                                <th>Status</th>
                                <th>Rating</th>
                                <th>Reviews</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staff_list as $s): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($s['email']); ?></td>
                                    <td><?php echo htmlspecialchars($s['specialization'] ?: '-'); ?></td>
                                    <td><span class="status-badge status-<?php echo $s['user_status']; ?>"><?php echo ucfirst($s['user_status']); ?></span></td>
                                    <td>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star" style="color: <?php echo $i <= round($s['rating']) ? '#ffc107' : '#ddd'; ?>"></i>
                                        <?php endfor; ?>
                                    </td>
                                    <td><?php echo $s['total_reviews']; ?></td>
                                    <td>
                                        <a href="staff.php?edit=<?php echo $s['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                        <a href="staff.php?view=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline">View</a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this staff profile? User account will remain.')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($staff_list)): ?>
                                <tr><td colspan="6">No staff profiles yet. Add one above.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle Select All checkboxes
        const selectAllCheckboxes = document.querySelectorAll('.select-all-checkbox');
        
        selectAllCheckboxes.forEach(selectAll => {
            const targetClass = selectAll.getAttribute('data-target') + '-checkbox';
            const checkboxes = document.querySelectorAll('.' + targetClass);
            
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(cb => {
                    cb.checked = this.checked;
                });
            });
            
            // Update Select All state when individual checkboxes change
            checkboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    const allChecked = Array.from(checkboxes).every(c => c.checked);
                    const noneChecked = Array.from(checkboxes).every(c => !c.checked);
                    
                    selectAll.checked = allChecked;
                    selectAll.indeterminate = !allChecked && !noneChecked;
                });
            });
        });
    });
    </script>
</body>
</html>
<?php include '../includes/admin-footer.php'; ?>
<style>
.staff-view-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin: 1rem 0; }
.staff-view-section.full-width { grid-column: 1 / -1; }
.staff-view-section h4 { margin-bottom: 0.5rem; color: var(--primary-color); }
.staff-view-section pre { background: #f5f5f5; padding: 1rem; border-radius: 6px; overflow-x: auto; }
.staff-view-actions { margin-top: 1.5rem; display: flex; gap: 1rem; }
</style>
