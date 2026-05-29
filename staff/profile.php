<?php
require_once '../config/functions.php';
requireStaffOrAdmin();

$conn = getDBConnection();

$staffUserId = $_SESSION['staff_user_id'] ?? $_SESSION['admin_id'] ?? null;
$staffRecordId = $_SESSION['staff_id'] ?? null;

if (!$staffRecordId && $staffUserId) {
    $stmt = $conn->prepare("SELECT id FROM staff WHERE user_id = ?");
    $stmt->execute([$staffUserId]);
    $staffRecordId = $stmt->fetchColumn() ?: null;
}

$success = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $firstName   = trim($_POST['first_name'] ?? '');
        $lastName    = trim($_POST['last_name'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');
        $bio         = trim($_POST['bio'] ?? '');
        $specialization = trim($_POST['specialization'] ?? '');

        if ($firstName && $lastName && $phone) {
            // Update users table
            $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$firstName, $lastName, $phone, $staffUserId]);

            // Update or insert staff record
            if ($staffRecordId) {
                $stmt = $conn->prepare("UPDATE staff SET specialization = ?, bio = ? WHERE id = ?");
                $stmt->execute([$specialization, $bio, $staffRecordId]);
            } else {
                $stmt = $conn->prepare("INSERT INTO staff (user_id, specialization, bio) VALUES (?, ?, ?)");
                $stmt->execute([$staffUserId, $specialization, $bio]);
                $staffRecordId = $conn->lastInsertId();
                $_SESSION['staff_id'] = $staffRecordId;
            }

            // Update session name
            $_SESSION['staff_name'] = $firstName . ' ' . $lastName;
            $success = 'Profile updated successfully!';
        } else {
            $error = 'Please fill in all required fields.';
        }
    } elseif ($action === 'change_password') {
        $currentPw  = $_POST['current_password'] ?? '';
        $newPw      = $_POST['new_password'] ?? '';
        $confirmPw  = $_POST['confirm_password'] ?? '';

        if ($currentPw && $newPw && $confirmPw) {
            if ($newPw !== $confirmPw) {
                $error = 'New passwords do not match.';
            } elseif (strlen($newPw) < 6) {
                $error = 'New password must be at least 6 characters.';
            } else {
                $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$staffUserId]);
                $hash = $stmt->fetchColumn();

                if (password_verify($currentPw, $hash)) {
                    $newHash = password_hash($newPw, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$newHash, $staffUserId]);
                    $success = 'Password changed successfully!';
                } else {
                    $error = 'Current password is incorrect.';
                }
            }
        } else {
            $error = 'Please fill in all password fields.';
        }
    } elseif ($action === 'update_availability') {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $avail = [];
        foreach ($days as $day) {
            $avail[$day] = [
                'active' => isset($_POST["avail_{$day}_active"]),
                'start' => $_POST["avail_{$day}_start"] ?? '09:00',
                'end' => $_POST["avail_{$day}_end"] ?? '18:00'
            ];
        }
        $avail_json = json_encode($avail);
        $stmt = $conn->prepare("UPDATE staff SET availability = ? WHERE id = ?");
        $stmt->execute([$avail_json, $staffRecordId]);
        $success = 'Availability updated successfully!';
    }
}

// Fetch current profile data
$user = null;
$staffRecord = null;
if ($staffUserId) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$staffUserId]);
    $user = $stmt->fetch();
}
if ($staffRecordId) {
    $stmt = $conn->prepare("SELECT * FROM staff WHERE id = ?");
    $stmt->execute([$staffRecordId]);
    $staffRecord = $stmt->fetch();
}

$initials = strtoupper(substr($user['first_name'] ?? 'S', 0, 1) . substr($user['last_name'] ?? '', 0, 1));
?>
<style>
    .staff-avatar-wrapper {
        position: relative;
        width: 120px;
        height: 120px;
        margin: 0 auto 1.5rem;
        cursor: pointer;
    }
    .staff-avatar-img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid var(--primary-color);
    }
    .avatar-edit-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        opacity: 0;
        transition: opacity 0.2s;
    }
    .staff-avatar-wrapper:hover .avatar-edit-overlay {
        opacity: 1;
    }
    .text-danger {
        color: #dc3545;
    }
    .btn-link {
        background: none;
        border: none;
        padding: 0;
        text-decoration: underline;
        cursor: pointer;
    }
    .availability-settings {
        margin-top: 1rem;
    }
    .avail-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.8rem;
        border-bottom: 1px solid #eee;
    }
    .avail-row:last-child {
        border-bottom: none;
    }
    .avail-day {
        flex: 0 0 120px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
    }
    .avail-times {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .avail-times input[type="time"] {
        padding: 0.3rem 0.5rem;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 0.9rem;
    }
    .avail-row.day-off {
        opacity: 0.45;
    }
    .avail-row.day-off .avail-times input {
        background: #f0f0f0;
        cursor: not-allowed;
    }
    .avail-info-banner {
        background: linear-gradient(135deg, #e8f8f5, #d5f0eb);
        border-left: 4px solid #2a9d8f;
        border-radius: 6px;
        padding: 0.75rem 1rem;
        margin-bottom: 1rem;
        font-size: 0.88rem;
        color: #1a6b5e;
        display: flex;
        align-items: flex-start;
        gap: 0.6rem;
    }
    .avail-info-banner i { margin-top: 2px; flex-shrink: 0; }
    @media (max-width: 600px) {
        .avail-row {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
    }
</style>
<script>
    function uploadStaffPic(input) {
        if (input.files && input.files[0]) {
            const formData = new FormData();
            formData.append('action', 'upload');
            formData.append('context', 'staff');
            formData.append('profile_pic', input.files[0]);

            fetch('../api/upload-profile-pic.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred.');
            });
        }
    }

    function removeStaffPic() {
        if (confirm('Remove profile picture?')) {
            const formData = new FormData();
            formData.append('action', 'remove');
            formData.append('context', 'staff');

            fetch('../api/upload-profile-pic.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
    }
</script>
<?php include '../includes/staff-header.php'; ?>

<div class="staff-page-header">
    <h1><i class="fas fa-user-edit"></i> Manage Profile</h1>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="staff-profile-grid">

    <!-- Avatar / Info Summary -->
    <div>
        <div class="staff-avatar-section">
            <div class="staff-avatar-wrapper">
                <?php if ($user['profile_picture']): ?>
                    <img src="../<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture" class="staff-avatar-img">
                <?php else: ?>
                    <div class="staff-avatar"><?php echo $initials; ?></div>
                <?php endif; ?>
                <div class="avatar-edit-overlay" onclick="document.getElementById('staff_pic_input').click()">
                    <i class="fas fa-camera"></i>
                </div>
            </div>
            <input type="file" id="staff_pic_input" style="display: none;" accept="image/*" onchange="uploadStaffPic(this)">
            
            <h3><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></h3>
            <p><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
            
            <?php if ($user['profile_picture']): ?>
                <button type="button" class="btn btn-sm btn-link text-danger" style="margin-top: 0.5rem;" onclick="removeStaffPic()">
                    <i class="fas fa-trash"></i> Remove Photo
                </button>
            <?php endif; ?>
            <br>
            <span class="status-badge status-confirmed" style="font-size:0.8rem;">
                <i class="fas fa-user-tie"></i> <?php echo ucfirst($user['role'] ?? 'staff'); ?>
            </span>
        </div>

        <?php if ($staffRecord && $staffRecord['rating'] > 0): ?>
        <div class="staff-card" style="margin-top:1rem; text-align:center;">
            <h2><i class="fas fa-star"></i> My Rating</h2>
            <div style="font-size:2rem; font-weight:700; color:#2a9d8f;">
                <?php echo number_format($staffRecord['rating'], 1); ?>
            </div>
            <div class="star-rating" style="font-size:1.2rem; margin:0.5rem 0;">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="fas fa-star <?php echo $i <= round($staffRecord['rating']) ? 'active' : ''; ?>"></i>
                <?php endfor; ?>
            </div>
            <small style="color:#888;"><?php echo $staffRecord['total_reviews']; ?> review(s)</small>
        </div>
        <?php endif; ?>
    </div>

    <!-- Edit Forms -->
    <div>
        <!-- Profile Info Form -->
        <div class="staff-card">
            <h2><i class="fas fa-id-card"></i> Personal Information</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name <span style="color:red">*</span></label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name <span style="color:red">*</span></label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled style="background:#f5f5f5; cursor:not-allowed;">
                    <small style="color:#999;">Email cannot be changed here. Contact admin.</small>
                </div>
                <div class="form-group">
                    <label>Phone Number <span style="color:red">*</span></label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Specialization</label>
                    <input type="text" name="specialization" placeholder="e.g. Nail Art, Lash Extensions..." value="<?php echo htmlspecialchars($staffRecord['specialization'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Bio / About Me</label>
                    <textarea name="bio" placeholder="Write a short bio about yourself..."><?php echo htmlspecialchars($staffRecord['bio'] ?? ''); ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>
        </div>

        <!-- Change Password Form -->
        <div class="staff-card">
            <h2><i class="fas fa-lock"></i> Change Password</h2>
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required placeholder="Enter current password">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required placeholder="Min. 6 characters">
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required placeholder="Repeat new password">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-key"></i> Change Password
                </button>
            </form>
        </div>

        <!-- Availability Form -->
        <div class="staff-card">
            <h2><i class="fas fa-clock"></i> My Weekly Availability</h2>
            <div class="avail-info-banner">
                <i class="fas fa-info-circle"></i>
                <span>Your availability controls which time slots clients can book you for. Only hours within your set working time will appear as bookable. Uncheck days you are not working.</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_availability">
                <div class="availability-settings">
                    <?php 
                    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                    $current_avail = json_decode($staffRecord['availability'] ?? '{}', true);
                    foreach ($days as $day): 
                        $day_data = $current_avail[$day] ?? ['active' => true, 'start' => '09:00', 'end' => '18:00'];
                        $is_active = !empty($day_data['active']);
                    ?>
                    <div class="avail-row <?php echo $is_active ? '' : 'day-off'; ?>" id="avail_row_<?php echo $day; ?>">
                        <label class="avail-day">
                            <input type="checkbox"
                                   name="avail_<?php echo $day; ?>_active"
                                   id="avail_cb_<?php echo $day; ?>"
                                   <?php echo $is_active ? 'checked' : ''; ?>
                                   onchange="toggleDayAvailability('<?php echo $day; ?>', this.checked)">
                            <?php echo ucfirst($day); ?>
                        </label>
                        <div class="avail-times">
                            <input type="time"
                                   id="avail_start_<?php echo $day; ?>"
                                   name="avail_<?php echo $day; ?>_start"
                                   value="<?php echo htmlspecialchars($day_data['start']); ?>"
                                   <?php echo $is_active ? '' : 'disabled'; ?>>
                            <span>to</span>
                            <input type="time"
                                   id="avail_end_<?php echo $day; ?>"
                                   name="avail_<?php echo $day; ?>_end"
                                   value="<?php echo htmlspecialchars($day_data['end']); ?>"
                                   <?php echo $is_active ? '' : 'disabled'; ?>>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top:1.5rem;">
                    <i class="fas fa-calendar-check"></i> Save Availability
                </button>
            </form>
        </div>
        <script>
        function toggleDayAvailability(day, isActive) {
            const row = document.getElementById('avail_row_' + day);
            const startInput = document.getElementById('avail_start_' + day);
            const endInput = document.getElementById('avail_end_' + day);
            if (isActive) {
                row.classList.remove('day-off');
                startInput.disabled = false;
                endInput.disabled = false;
            } else {
                row.classList.add('day-off');
                startInput.disabled = true;
                endInput.disabled = true;
            }
        }
        </script>
    </div>

</div>

<?php include '../includes/staff-footer.php'; ?>
