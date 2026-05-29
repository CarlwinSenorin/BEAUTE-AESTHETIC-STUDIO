<?php
require_once '../config/functions.php';
requireAdmin();

$conn = getDBConnection();
$today = date('Y-m-d');

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    requireCSRFToken();

    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? 0;

    if ($id) {
        if ($action === 'complete') {
            // Mark as completed
            $stmt = $conn->prepare("UPDATE appointments SET status = 'completed' WHERE id = ?");
            $stmt->execute([$id]);
        }
    }
    
    // Redirect to avoid form resubmission
    header('Location: live-monitor.php');
    exit;
}

// Get active treatments (in_progress)
$stmt = $conn->prepare("
    SELECT a.*, u.first_name, u.last_name, u.email, u.phone, 
           s.first_name as staff_first_name, s.last_name as staff_last_name
    FROM appointments a 
    JOIN users u ON a.user_id = u.id 
    LEFT JOIN staff st ON a.staff_id = st.id
    LEFT JOIN users s ON st.user_id = s.id
    WHERE a.status = 'in_progress'
    ORDER BY a.checked_in_at DESC
");
$stmt->execute();
$active_treatments = $stmt->fetchAll();

// Get upcoming confirmed appointments for today (ready to start)
$stmt = $conn->prepare("
    SELECT a.*, u.first_name, u.last_name, u.email
    FROM appointments a 
    JOIN users u ON a.user_id = u.id 
    WHERE a.status = 'confirmed' AND a.appointment_date = ?
    ORDER BY a.appointment_time ASC
");
$stmt->execute([$today]);
$upcoming_appointments = $stmt->fetchAll();

// Helper to calculate progress
function calculateProgress($startTime, $servicesJson, $conn) {
    if (!$startTime) return 0;
    
    // Get total duration from services
    $serviceIds = json_decode($servicesJson, true);
    if (empty($serviceIds)) return 0;
    
    $placeholders = str_repeat('?,', count($serviceIds) - 1) . '?';
    $stmt = $conn->prepare("SELECT SUM(duration) FROM services WHERE id IN ($placeholders)");
    $stmt->execute($serviceIds);
    $totalDurationMinutes = (int)$stmt->fetchColumn();
    
    $elapsedSeconds = time() - strtotime($startTime);
    $totalSeconds = $totalDurationMinutes * 60;
    
    if ($totalSeconds <= 0) return 0;
    
    $percent = ($elapsedSeconds / $totalSeconds) * 100;
    return min(100, max(0, $percent));
}

// Helper to get duration text
function getDurationText($servicesJson, $conn) {
    $serviceIds = json_decode($servicesJson, true);
    if (empty($serviceIds)) return '0 min';
    
    $placeholders = str_repeat('?,', count($serviceIds) - 1) . '?';
    $stmt = $conn->prepare("SELECT SUM(duration) FROM services WHERE id IN ($placeholders)");
    $stmt->execute($serviceIds);
    $minutes = (int)$stmt->fetchColumn();
    
    return $minutes . ' min';
}

// Helper to get service names
function getServiceNames($servicesJson, $conn) {
    $serviceIds = json_decode($servicesJson, true);
    if (empty($serviceIds)) return 'No services';
    
    $placeholders = str_repeat('?,', count($serviceIds) - 1) . '?';
    $stmt = $conn->prepare("SELECT name FROM services WHERE id IN ($placeholders)");
    $stmt->execute($serviceIds);
    $names = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    return implode(', ', $names);
}
?>
<?php include '../includes/admin-header.php'; ?>

<div class="admin-header">
    <h1><i class="fas fa-heartbeat"></i> Live Monitor</h1>
    <div class="header-actions">
        <span id="currentTime" class="live-clock"></span>
    </div>
</div>

<div class="live-monitor-container">
    
    <!-- Active Treatments Section -->
    <div class="monitor-section">
        <div class="section-header">
            <h2><i class="fas fa-play-circle"></i> In Progress (<?php echo count($active_treatments); ?>)</h2>
        </div>
        
        <?php if (empty($active_treatments)): ?>
            <div class="empty-state-card">
                <i class="fas fa-spa"></i>
                <p>No treatments currently in progress</p>
            </div>
        <?php else: ?>
            <div class="treatment-grid">
                <?php foreach ($active_treatments as $treatment): ?>
                    <?php 
                        $progress = calculateProgress($treatment['checked_in_at'], $treatment['services'], $conn);
                        $duration = getDurationText($treatment['services'], $conn);
                        $serviceNames = getServiceNames($treatment['services'], $conn);
                        
                        // Determine progress color
                        $progressColor = '#28a745'; // Green
                        if ($progress > 90) $progressColor = '#dc3545'; // Red (Overtime/Finishing)
                        else if ($progress > 75) $progressColor = '#ffc107'; // Yellow
                    ?>
                    <div class="treatment-card">
                        <div class="card-header">
                            <div class="client-info">
                                <h3><?php echo htmlspecialchars($treatment['first_name'] . ' ' . $treatment['last_name']); ?></h3>
                                <span class="service-name"><?php echo htmlspecialchars($serviceNames); ?></span>
                            </div>
                            <div class="status-indicator">
                                <span class="pulsing-dot"></span> Live
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <div class="info-row">
                                <i class="fas fa-user-tie"></i>
                                <span>Staff: <?php echo htmlspecialchars($treatment['staff_first_name'] ? $treatment['staff_first_name'] . ' ' . $treatment['staff_last_name'] : 'Unassigned'); ?></span>
                            </div>
                            <div class="info-row">
                                <i class="fas fa-clock"></i>
                                <span>Started: <?php echo date('h:i A', strtotime($treatment['checked_in_at'])); ?></span>
                            </div>
                            <div class="info-row">
                                <i class="fas fa-hourglass-half"></i>
                                <span>Duration: <?php echo $duration; ?></span>
                            </div>
                            
                            <div class="progress-container">
                                <div class="progress-bar" style="width: <?php echo $progress; ?>%; background-color: <?php echo $progressColor; ?>;"></div>
                            </div>
                            <div class="progress-text">
                                <span><?php echo round($progress); ?>% Complete</span>
                            </div>
                        </div>
                        
                        <div class="card-footer">
                            <form method="POST" onsubmit="return confirm('Mark treatment as completed?')">
                                <?php csrfTokenField(); ?>
                                <input type="hidden" name="action" value="complete">
                                <input type="hidden" name="id" value="<?php echo $treatment['id']; ?>">
                                <button type="submit" class="btn btn-success btn-block">
                                    <i class="fas fa-check-circle"></i> Complete Treatment
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Up Next Section -->
    <div class="monitor-section">
        <div class="section-header">
            <h2><i class="fas fa-calendar-check"></i> Up Next</h2>
        </div>
        
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Client</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($upcoming_appointments)): ?>
                        <tr><td colspan="4" class="text-center">No upcoming appointments for today</td></tr>
                    <?php else: ?>
                        <?php foreach ($upcoming_appointments as $apt): ?>
                            <tr>
                                <td><?php echo formatTime($apt['appointment_time']); ?></td>
                                <td><?php echo htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']); ?></td>
                                <td><span class="status-badge status-confirmed">Confirmed</span></td>
                                <td>
                                    <a href="appointments.php" class="btn btn-sm btn-primary">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
</div>

<style>
    .live-clock {
        font-size: 1.2rem;
        font-weight: bold;
        color: #d4a574;
        background: #fdf3e6;
        padding: 0.5rem 1rem;
        border-radius: 8px;
    }
    
    .live-monitor-container {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
    }
    
    @media (max-width: 1024px) {
        .live-monitor-container {
            grid-template-columns: 1fr;
        }
    }
    
    .monitor-section {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    }
    
    .section-header {
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .treatment-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }
    
    .treatment-card {
        border: 1px solid #eee;
        border-radius: 10px;
        overflow: hidden;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .treatment-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    
    .card-header {
        padding: 15px;
        background: #fdf3e6;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }
    
    .client-info h3 {
        margin: 0 0 5px 0;
        font-size: 1.1rem;
        color: #333;
    }
    
    .service-name {
        font-size: 0.85rem;
        color: #666;
        display: block;
    }
    
    .status-indicator {
        display: flex;
        align-items: center;
        font-size: 0.8rem;
        font-weight: bold;
        color: #dc3545;
        background: rgba(220, 53, 69, 0.1);
        padding: 4px 8px;
        border-radius: 20px;
    }
    
    .pulsing-dot {
        width: 8px;
        height: 8px;
        background-color: #dc3545;
        border-radius: 50%;
        margin-right: 6px;
        animation: pulse 1.5s infinite;
    }
    
    @keyframes pulse {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(220, 53, 69, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
    }
    
    .card-body {
        padding: 15px;
    }
    
    .info-row {
        margin-bottom: 8px;
        font-size: 0.9rem;
        color: #555;
        display: flex;
        align-items: center;
    }
    
    .info-row i {
        width: 20px;
        color: #d4a574;
        margin-right: 8px;
    }
    
    .progress-container {
        height: 8px;
        background: #eee;
        border-radius: 4px;
        margin: 15px 0 5px 0;
        overflow: hidden;
    }
    
    .progress-bar {
        height: 100%;
        transition: width 1s linear;
    }
    
    .progress-text {
        text-align: right;
        font-size: 0.8rem;
        color: #666;
    }
    
    .card-footer {
        padding: 15px;
        border-top: 1px solid #eee;
    }
    
    .empty-state-card {
        text-align: center;
        padding: 40px;
        color: #999;
    }
    
    .empty-state-card i {
        font-size: 3rem;
        margin-bottom: 10px;
        color: #ddd;
    }
</style>

    </div>
<?php include '../includes/admin-footer.php'; ?>
<script>
    // Update active clock
    function updateClock() {
        const now = new Date();
        document.getElementById('currentTime').innerText = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', second:'2-digit'});
    }
    setInterval(updateClock, 1000);
    updateClock();
    
    // Auto-refresh page every 30 seconds for real-time updates
    startAutoRefresh(30);
</script>
