<?php
require_once '../config/functions.php';
requireStaffOrAdmin();

$conn = getDBConnection();
$today = date('Y-m-d');

// Helper functions (same as admin live-monitor)
function calculateProgress($startTime, $servicesJson, $conn) {
    if (!$startTime) return 0;
    $serviceIds = json_decode($servicesJson, true);
    if (empty($serviceIds)) return 0;
    $ph = implode(',', array_fill(0, count($serviceIds), '?'));
    $stmt = $conn->prepare("SELECT SUM(duration) FROM services WHERE id IN ($ph)");
    $stmt->execute($serviceIds);
    $totalMin = (int)$stmt->fetchColumn();
    $elapsed = time() - strtotime($startTime);
    $total = $totalMin * 60;
    if ($total <= 0) return 0;
    return min(100, max(0, ($elapsed / $total) * 100));
}

function getDurationText($servicesJson, $conn) {
    $serviceIds = json_decode($servicesJson, true);
    if (empty($serviceIds)) return '0 min';
    $ph = implode(',', array_fill(0, count($serviceIds), '?'));
    $stmt = $conn->prepare("SELECT SUM(duration) FROM services WHERE id IN ($ph)");
    $stmt->execute($serviceIds);
    return (int)$stmt->fetchColumn() . ' min';
}

function getServiceNames($servicesJson, $conn) {
    $serviceIds = json_decode($servicesJson, true);
    if (empty($serviceIds)) return 'No services';
    $ph = implode(',', array_fill(0, count($serviceIds), '?'));
    $stmt = $conn->prepare("SELECT name FROM services WHERE id IN ($ph)");
    $stmt->execute($serviceIds);
    return implode(', ', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

// Determine staff filtering
$isAdmin = isAdmin();
$staffUserId = $_SESSION['staff_user_id'] ?? $_SESSION['admin_id'] ?? null;
$staffRecordId = $_SESSION['staff_id'] ?? null;

if (!$staffRecordId && $staffUserId) {
    $stmt = $conn->prepare("SELECT id FROM staff WHERE user_id = ?");
    $stmt->execute([$staffUserId]);
    $staffRecordId = $stmt->fetchColumn() ?: null;
}

// Active treatments (in_progress)
$sqlActive = "
    SELECT a.*, u.first_name, u.last_name,
           sf.first_name as staff_first, sf.last_name as staff_last
    FROM appointments a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN staff st ON a.staff_id = st.id
    LEFT JOIN users sf ON st.user_id = sf.id
    WHERE a.status = 'in_progress'
";
$paramsActive = [];

if (!$isAdmin) {
    $sqlActive .= " AND a.staff_id = ?";
    $paramsActive[] = $staffRecordId;
}

$sqlActive .= " ORDER BY a.checked_in_at DESC";
$stmt = $conn->prepare($sqlActive);
$stmt->execute($paramsActive);
$active = $stmt->fetchAll();

// Upcoming confirmed for today
$sqlUpcoming = "
    SELECT a.*, u.first_name, u.last_name
    FROM appointments a
    JOIN users u ON a.user_id = u.id
    WHERE a.status = 'confirmed' AND a.appointment_date = ?
";
$paramsUpcoming = [$today];

if (!$isAdmin) {
    $sqlUpcoming .= " AND a.staff_id = ?";
    $paramsUpcoming[] = $staffRecordId;
}

$sqlUpcoming .= " ORDER BY a.appointment_time ASC";
$stmt = $conn->prepare($sqlUpcoming);
$stmt->execute($paramsUpcoming);
$upcoming = $stmt->fetchAll();
?>
<?php include '../includes/staff-header.php'; ?>

<div class="staff-page-header">
    <h1><i class="fas fa-heartbeat"></i> Live Monitor</h1>
    <span id="liveClock" class="live-clock"></span>
</div>

<div style="display:grid; grid-template-columns:2fr 1fr; gap:1.5rem;">

    <!-- In Progress -->
    <div class="staff-card">
        <h2><i class="fas fa-play-circle"></i> In Progress (<?php echo count($active); ?>)</h2>
        <?php if (empty($active)): ?>
            <div class="empty-state">
                <i class="fas fa-spa"></i>
                No treatments currently in progress.
            </div>
        <?php else: ?>
            <div class="treatment-grid">
                <?php foreach ($active as $t): ?>
                    <?php
                    $progress = calculateProgress($t['checked_in_at'], $t['services'], $conn);
                    $duration = getDurationText($t['services'], $conn);
                    $svcNames = getServiceNames($t['services'], $conn);
                    $color = $progress > 90 ? '#dc3545' : ($progress > 75 ? '#ffc107' : '#2a9d8f');
                    ?>
                    <div class="treatment-card">
                        <div class="card-header">
                            <div>
                                <h3><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></h3>
                                <span class="service-name"><?php echo htmlspecialchars($svcNames); ?></span>
                            </div>
                            <div class="status-indicator">
                                <span class="pulsing-dot"></span> Live
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="info-row">
                                <i class="fas fa-user-tie"></i>
                                <span>Staff: <?php echo htmlspecialchars($t['staff_first'] ? $t['staff_first'] . ' ' . $t['staff_last'] : 'Unassigned'); ?></span>
                            </div>
                            <div class="info-row">
                                <i class="fas fa-clock"></i>
                                <span>Started: <?php echo $t['checked_in_at'] ? date('h:i A', strtotime($t['checked_in_at'])) : 'N/A'; ?></span>
                            </div>
                            <div class="info-row">
                                <i class="fas fa-hourglass-half"></i>
                                <span>Duration: <?php echo $duration; ?></span>
                            </div>
                            <div class="progress-container">
                                <div class="progress-bar" style="width:<?php echo $progress; ?>%; background:<?php echo $color; ?>;"></div>
                            </div>
                            <div class="progress-text"><?php echo round($progress); ?>% Complete</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Up Next -->
    <div class="staff-card">
        <h2><i class="fas fa-calendar-check"></i> Up Next</h2>
        <?php if (empty($upcoming)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                No upcoming appointments today.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="staff-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Client</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcoming as $apt): ?>
                            <tr>
                                <td><strong><?php echo formatTime($apt['appointment_time']); ?></strong></td>
                                <td><?php echo htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']); ?></td>
                                <td><span class="status-badge status-confirmed">Confirmed</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
function updateClock() {
    const now = new Date();
    document.getElementById('liveClock').innerText = now.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit', second:'2-digit'});
}
setInterval(updateClock, 1000);
updateClock();

// Auto-refresh every 30 seconds for real-time updates
startAutoRefresh(30);
</script>

<?php include '../includes/staff-footer.php'; ?>
