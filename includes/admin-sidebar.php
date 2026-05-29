<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Admin Sidebar -->
<div class="admin-sidebar">
    <div class="admin-sidebar-header">
        <h3><i class="fas fa-shield-alt"></i> Admin Panel</h3>
    </div>
    <nav class="admin-sidebar-nav">
        <ul>
            <li>
                <a href="index.php" class="<?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="live-monitor.php" class="<?php echo $current_page === 'live-monitor.php' ? 'active' : ''; ?>">
                    <i class="fas fa-heartbeat"></i>
                    <span>Live Monitor</span>
                </a>
            </li>
            <li>
                <a href="appointments.php" class="<?php echo $current_page === 'appointments.php' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i>
                    <span>Appointments List</span>
                </a>
            </li>
            <li>
                <a href="calendar.php" class="<?php echo $current_page === 'calendar.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Appointment Calendar</span>
                </a>
            </li>

            <li>
                <a href="users.php" class="<?php echo $current_page === 'users.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
            </li>
            <li>
                <a href="staff.php" class="<?php echo $current_page === 'staff.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-tie"></i>
                    <span>Staff Profiles</span>
                </a>
            </li>
            <li>
                <a href="services.php" class="<?php echo $current_page === 'services.php' ? 'active' : ''; ?>">
                    <i class="fas fa-spa"></i>
                    <span>Services</span>
                </a>
            </li>
            <li>
                <a href="packages.php" class="<?php echo $current_page === 'packages.php' ? 'active' : ''; ?>">
                    <i class="fas fa-gift"></i>
                    <span>Packages</span>
                </a>
            </li>
            <li>
                <a href="testimonials.php" class="<?php echo $current_page === 'testimonials.php' ? 'active' : ''; ?>">
                    <i class="fas fa-star"></i>
                    <span>Testimonials</span>
                </a>
            </li>
            <li>
                <a href="messages.php" class="<?php echo $current_page === 'messages.php' ? 'active' : ''; ?>">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
                </a>
            </li>
            <li>
                <a href="notification-logs.php" class="<?php echo $current_page === 'notification-logs.php' ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i>
                    <span>Notification Logs</span>
                </a>
            </li>
            <li>
                <a href="reports.php" class="<?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li>
                <a href="promotions.php" class="<?php echo $current_page === 'promotions.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tags"></i>
                    <span>Promotions</span>
                </a>
            </li>
            <li>
                <a href="segments.php" class="<?php echo $current_page === 'segments.php' ? 'active' : ''; ?>">
                    <i class="fas fa-layer-group"></i>
                    <span>Customer Segments</span>
                </a>
            </li>
            <li>
                <a href="inventory.php" class="<?php echo $current_page === 'inventory.php' ? 'active' : ''; ?>">
                    <i class="fas fa-boxes"></i>
                    <span>Inventory</span>
                </a>
            </li>
            <li>
                <a href="recovery-bin.php" class="<?php echo $current_page === 'recovery-bin.php' ? 'active' : ''; ?>">
                    <i class="fas fa-trash-restore"></i>
                    <span>Recovery Bin</span>
                </a>
            </li>
            <li>
                <a href="settings.php" class="<?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    <span>System Settings</span>
                </a>
            </li>
            <li>
                <hr style="border: 0; border-top: 1px solid rgba(255,255,255,0.1); margin: 10px 0;">
            </li>
            <li>
                <a href="logout.php" class="logout-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>
</div>
