<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="staff-sidebar">
    <div class="staff-sidebar-header">
        <h3><i class="fas fa-spa"></i> Beaute Studio</h3>
        <span class="staff-badge"><i class="fas fa-user-tie"></i> Staff Portal</span>
    </div>
    <nav class="staff-sidebar-nav">
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
                    <i class="fas fa-calendar-check"></i>
                    <span>Manage Appointments</span>
                </a>
            </li>
            <li>
                <a href="inventory.php" class="<?php echo $current_page === 'inventory.php' ? 'active' : ''; ?>">
                    <i class="fas fa-boxes"></i>
                    <span>Inventory</span>
                </a>
            </li>
            <li>
                <a href="profile.php" class="<?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-edit"></i>
                    <span>Manage Profile</span>
                </a>
            </li>
            <li>
                <a href="testimonials.php" class="<?php echo $current_page === 'testimonials.php' ? 'active' : ''; ?>">
                    <i class="fas fa-star"></i>
                    <span>Testimonials / Reviews</span>
                </a>
            </li>
            <li>
                <hr style="border:0; border-top:1px solid rgba(255,255,255,0.1); margin:10px 0;">
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
