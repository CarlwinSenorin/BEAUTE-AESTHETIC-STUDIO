<?php
require_once '../config/functions.php';
requireStaffOrAdmin();

$conn = getDBConnection();
$today = date('Y-m-d');

// Determine the staff_id for queries
// If admin is viewing the staff panel, show all; if staff, show their own
$isAdmin = isAdmin();
$staffUserId = $_SESSION['staff_user_id'] ?? $_SESSION['admin_id'] ?? null;
$staffRecordId = $_SESSION['staff_id'] ?? null;

// Get staff record if not already in session
if (!$staffRecordId && $staffUserId) {
    $stmt = $conn->prepare("SELECT id FROM staff WHERE user_id = ?");
    $stmt->execute([$staffUserId]);
    $staffRecordId = $stmt->fetchColumn() ?: null;
}

// Stats for this staff member
$stats = [
    'today'     => 0,
    'pending'   => 0,
    'confirmed' => 0,
    'completed' => 0,
];

if ($staffRecordId) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM appointments WHERE staff_id = ? AND appointment_date = ? AND status != 'cancelled'");
    $stmt->execute([$staffRecordId, $today]);
    $stats['today'] = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM appointments WHERE staff_id = ? AND status = 'pending'");
    $stmt->execute([$staffRecordId]);
    $stats['pending'] = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM appointments WHERE staff_id = ? AND status = 'confirmed'");
    $stmt->execute([$staffRecordId]);
    $stats['confirmed'] = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM appointments WHERE staff_id = ? AND status = 'completed'");
    $stmt->execute([$staffRecordId]);
    $stats['completed'] = $stmt->fetchColumn();
}

// Today's appointments for this staff
$todayApts = [];
if ($staffRecordId) {
    $stmt = $conn->prepare("
        SELECT a.*, u.first_name, u.last_name, u.phone
        FROM appointments a
        JOIN users u ON a.user_id = u.id
        WHERE a.staff_id = ? AND a.appointment_date = ?
        ORDER BY a.appointment_time ASC
    ");
    $stmt->execute([$staffRecordId, $today]);
    $todayApts = $stmt->fetchAll();
}
?>
<?php include '../includes/staff-header.php'; ?>

<div class="staff-page-header">
    <h1><i class="fas fa-tachometer-alt"></i> My Dashboard</h1>
    <span style="color:#888; font-size:0.9rem;"><?php echo date('l, F j, Y'); ?></span>
</div>

<!-- Stats -->
<div class="staff-stats-grid">
    <div class="staff-stat-card">
        <div class="staff-stat-icon" style="background:#2a9d8f;">
            <i class="fas fa-calendar-day"></i>
        </div>
        <div class="staff-stat-content">
            <h3><?php echo $stats['today']; ?></h3>
            <p>Today's Appointments</p>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-icon" style="background:#e9c46a;">
            <i class="fas fa-clock"></i>
        </div>
        <div class="staff-stat-content">
            <h3><?php echo $stats['pending']; ?></h3>
            <p>Pending</p>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-icon" style="background:#264653;">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="staff-stat-content">
            <h3><?php echo $stats['confirmed']; ?></h3>
            <p>Confirmed</p>
        </div>
    </div>
    <div class="staff-stat-card">
        <div class="staff-stat-icon" style="background:#28a745;">
            <i class="fas fa-star"></i>
        </div>
        <div class="staff-stat-content">
            <h3><?php echo $stats['completed']; ?></h3>
            <p>Completed</p>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="staff-card">
    <h2><i class="fas fa-bolt"></i> Quick Access</h2>
    <div class="staff-quick-actions">
        <a href="live-monitor.php" class="staff-action-btn">
            <i class="fas fa-heartbeat"></i>
            <span>Live Monitor</span>
        </a>
        <a href="appointments.php" class="staff-action-btn">
            <i class="fas fa-calendar-check"></i>
            <span>Manage Appts</span>
        </a>
        <a href="inventory.php" class="staff-action-btn">
            <i class="fas fa-boxes"></i>
            <span>Inventory</span>
        </a>
        <a href="profile.php" class="staff-action-btn">
            <i class="fas fa-user-edit"></i>
            <span>My Profile</span>
        </a>
    </div>
</div>

<!-- Today's Schedule -->
<div class="staff-card">
    <h2><i class="fas fa-calendar-alt"></i> Today's Schedule</h2>
    <?php if (empty($todayApts)): ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            No appointments scheduled for you today.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="staff-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Client</th>
                        <th>Services</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($todayApts as $apt): ?>
                        <?php
                        $serviceIds = json_decode($apt['services'], true);
                        $serviceNames = 'N/A';
                        if (!empty($serviceIds)) {
                            $ph = implode(',', array_fill(0, count($serviceIds), '?'));
                            $st = $conn->prepare("SELECT name FROM services WHERE id IN ($ph)");
                            $st->execute($serviceIds);
                            $serviceNames = implode(', ', $st->fetchAll(PDO::FETCH_COLUMN));
                        }
                        ?>
                        <tr>
                            <td><strong><?php echo formatTime($apt['appointment_time']); ?></strong></td>
                            <td>
                                <?php echo htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']); ?><br>
                                <small style="color:#999;"><?php echo htmlspecialchars($apt['phone']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($serviceNames); ?></td>
                            <td><span class="status-badge status-<?php echo $apt['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $apt['status'])); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        startAutoRefresh(30);
    });
</script>
<?php include '../includes/staff-footer.php'; ?>
