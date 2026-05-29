<?php
require_once '../config/functions.php';
requireAdmin();

$conn = getDBConnection();

// Get today's date (use PHP date for timezone consistency)
$today = date('Y-m-d');

// Get statistics
$stats = [
    'total_appointments' => $conn->query("SELECT COUNT(*) FROM appointments WHERE status NOT IN ('cancelled', 'no_show')")->fetchColumn(),
    'pending_appointments' => $conn->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'")->fetchColumn(),
    'reserved_appointments' => $conn->query("SELECT COUNT(*) FROM appointments WHERE status = 'reserved'")->fetchColumn(),
    'confirmed_appointments' => $conn->query("SELECT COUNT(*) FROM appointments WHERE status = 'confirmed'")->fetchColumn(),
    'current_treatments' => $conn->query("SELECT COUNT(*) FROM appointments WHERE status = 'in_progress'")->fetchColumn(),
    'today_appointments' => 0,
    'total_users' => $conn->query("SELECT COUNT(*) FROM users WHERE role = 'client'")->fetchColumn(),
    'total_services' => $conn->query("SELECT COUNT(*) FROM services")->fetchColumn(),
    'total_revenue' => $conn->query("SELECT SUM(final_price) FROM appointments WHERE status = 'completed'")->fetchColumn() ?: 0
];

$stmt = $conn->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date = ?");
$stmt->execute([$today]);
$stats['today_appointments'] = $stmt->fetchColumn();

// Get today's appointments list with staff info
$stmt = $conn->prepare("SELECT a.*, u.first_name, u.last_name, u.email, u.phone,
                               sf.first_name as staff_first, sf.last_name as staff_last
                        FROM appointments a 
                        JOIN users u ON a.user_id = u.id 
                        LEFT JOIN staff st ON a.staff_id = st.id
                        LEFT JOIN users sf ON st.user_id = sf.id
                        WHERE a.appointment_date = ? 
                        ORDER BY a.appointment_time ASC");
$stmt->execute([$today]);
$today_appointments = $stmt->fetchAll();
?>
<?php include '../includes/admin-header.php'; ?>

            <div class="admin-header">
                <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #17a2b8;">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['total_appointments']); ?></h3>
                        <p>Total Appointments</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #ffc107;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['pending_appointments']); ?></h3>
                        <p>Pending</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #fd7e14;">
                        <i class="fas fa-bookmark"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['reserved_appointments']); ?></h3>
                        <p>Reserved</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #28a745;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['confirmed_appointments']); ?></h3>
                        <p>Confirmed</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #e83e8c;">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['today_appointments']); ?></h3>
                        <p>Today's Appointments</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #00bcd4;">
                        <i class="fas fa-heartbeat"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['current_treatments']); ?></h3>
                        <p>Currently Serving</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #007bff;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['total_users']); ?></h3>
                        <p>Total Clients</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #6f42c1;">
                        <i class="fas fa-spa"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['total_services']); ?></h3>
                        <p>Services</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #dc3545;">
                        <i class="fas fa-peso-sign"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo formatPrice($stats['total_revenue']); ?></h3>
                        <p>Total Revenue</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="admin-actions">
                <h2>Quick Actions</h2>
                <div class="action-buttons">
                    <a href="appointments.php" class="action-btn">
                        <i class="fas fa-list"></i>
                        <span>Appointments</span>
                    </a>
                    <a href="calendar.php" class="action-btn">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Calendar</span>
                    </a>
                    <a href="services.php" class="action-btn">
                        <i class="fas fa-spa"></i>
                        <span>Services</span>
                    </a>
                    <a href="staff.php" class="action-btn">
                        <i class="fas fa-user-tie"></i>
                        <span>Staff</span>
                    </a>
                    <a href="users.php" class="action-btn">
                        <i class="fas fa-users"></i>
                        <span>Users</span>
                    </a>
                    <a href="live-monitor.php" class="action-btn">
                        <i class="fas fa-heartbeat"></i>
                        <span>Live Monitor</span>
                    </a>
                    <a href="packages.php" class="action-btn">
                        <i class="fas fa-gift"></i>
                        <span>Packages</span>
                    </a>
                    <a href="reports.php" class="action-btn">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                </div>
            </div>

            <!-- Today's Appointments -->
            <div class="admin-card">
                <h2><i class="fas fa-calendar-day"></i> Today's Appointments (<?php echo date('M j, Y'); ?>)</h2>
                <?php if (empty($today_appointments)): ?>
                    <p class="empty-state" style="text-align:center; padding:2rem; color:#999;">
                        <i class="fas fa-calendar-check" style="font-size:2rem; display:block; margin-bottom:10px; color:#ddd;"></i>
                        No appointments scheduled for today.
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Client</th>
                                    <th>Staff</th>
                                    <th>Services</th>
                                    <th>Pax</th>
                                    <th>Price</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($today_appointments as $apt): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo formatTime($apt['appointment_time']); ?></strong>
                                            <?php if (!empty($apt['end_time'])): ?>
                                                <br><small style="color:#999;">– <?php echo formatTime($apt['end_time']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']); ?></strong><br>
                                            <small style="color:#999;"><?php echo htmlspecialchars($apt['phone']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($apt['staff_first'] ? $apt['staff_first'] . ' ' . $apt['staff_last'] : 'Unassigned'); ?>
                                        </td>
                                        <td style="max-width:160px; white-space:normal; font-size:0.85rem;">
                                            <?php 
                                            $services = json_decode($apt['services'], true);
                                            if (!empty($services)) {
                                                $placeholders = str_repeat('?,', count($services) - 1) . '?';
                                                $stmt = $conn->prepare("SELECT name FROM services WHERE id IN ($placeholders)");
                                                $stmt->execute($services);
                                                echo htmlspecialchars(implode(', ', $stmt->fetchAll(PDO::FETCH_COLUMN)));
                                            } else {
                                                echo 'No services';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php $pax = $apt['pax'] ?? 1; ?>
                                            <span style="background:rgba(212,165,116,0.15); color:#8b6914; padding:2px 8px; border-radius:12px; font-size:0.82rem; font-weight:600;">
                                                <?php echo (int)$pax; ?> <i class="fas fa-user<?php echo $pax > 1 ? 's' : ''; ?>"></i>
                                            </span>
                                        </td>
                                        <td><?php echo formatPrice($apt['final_price']); ?></td>
                                        <td>
                                            <?php
                                            $pm = ucwords(str_replace('_', ' ', $apt['payment_method'] ?? 'N/A'));
                                            $ps = ucfirst($apt['payment_status'] ?? 'N/A');
                                            ?>
                                            <span style="background:#edf2f7; padding:2px 8px; border-radius:4px; font-size:0.78rem; font-weight:600;"><?php echo htmlspecialchars($pm); ?></span><br>
                                            <small style="color:<?php echo strtolower($apt['payment_status'] ?? '') === 'paid' ? '#38a169' : '#d69e2e'; ?>; font-weight:600;"><?php echo htmlspecialchars($ps); ?></small>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $apt['status']; ?>">
                                                <?php echo ucwords(str_replace('_', ' ', $apt['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="appointment-details.php?id=<?php echo $apt['id']; ?>" class="btn btn-sm btn-primary">Manage</a>
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
</main>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        startAutoRefresh(30);
    });
</script>
<?php include '../includes/admin-footer.php'; ?>
