<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<script>
    const BASE_URL = '<?php echo BASE_URL; ?>';
</script>
<header class="main-header">
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <a href="index.php">
                    <i class="fas fa-spa"></i>
                    <span>Beaute Aesthetic Studio</span>
                </a>
            </div>
            <ul class="nav-menu" id="navMenu">
                <li><a href="<?php echo $current_page === 'index.php' ? '#home' : 'index.php'; ?>" class="<?php echo $current_page === 'index.php' ? 'active' : ''; ?>">Home</a></li>
                <li><a href="services.php" class="<?php echo $current_page === 'services.php' ? 'active' : ''; ?>">Services</a></li>
                <li><a href="<?php echo $current_page === 'index.php' ? '#packages' : 'index.php#packages'; ?>">Packages</a></li>
                <li><a href="<?php echo $current_page === 'index.php' ? '#about' : 'index.php#about'; ?>">About Us</a></li>
                <li><a href="<?php echo $current_page === 'index.php' ? '#testimonials' : 'index.php#testimonials'; ?>">Testimonials</a></li>
                <li><a href="<?php echo $current_page === 'index.php' ? '#contact' : 'index.php#contact'; ?>">Contact</a></li>
                <?php if (!isLoggedIn()): ?>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="register.php" class="btn btn-primary btn-sm">Sign Up</a></li>
                <?php endif; ?>
            </ul>
            <div class="nav-toggle" id="navToggle">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </nav>
</header>

<!-- User Sidebar -->
<div class="user-sidebar" id="userSidebar">
    <div class="sidebar-header">
        <h3><i class="fas fa-user"></i> Account</h3>
        <button class="sidebar-close" id="sidebarClose">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="sidebar-menu">
        <ul>
            <?php if (isLoggedIn()): ?>
                <li><a href="dashboard.php" class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>

                <li><a href="recovery-bin.php" class="<?php echo $current_page === 'recovery-bin.php' ? 'active' : ''; ?>"><i class="fas fa-trash-restore"></i> Recovery Bin</a></li>
                <li><a href="calendar.php" class="<?php echo $current_page === 'calendar.php' ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> Calendar</a></li>
                <li><a href="profile.php" class="<?php echo $current_page === 'profile.php' ? 'active' : ''; ?>"><i class="fas fa-user-edit"></i> Profile</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            <?php else: ?>
                <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                <li><a href="register.php"><i class="fas fa-user-plus"></i> Register</a></li>
            <?php endif; ?>
        </ul>
    </div>
</div>

<!-- Sidebar Toggle Button -->
<button class="sidebar-toggle" id="sidebarToggle">
    <?php if (isLoggedIn()): ?>
        <div class="user-toggle-avatar">
            <?php if (isset($_SESSION['user_profile_pic']) && $_SESSION['user_profile_pic']): ?>
                <img src="<?php echo htmlspecialchars($_SESSION['user_profile_pic']); ?>" alt="Profile">
            <?php else: ?>
                <?php 
                $initials = '';
                if (isset($_SESSION['user_name'])) {
                    $names = explode(' ', $_SESSION['user_name']);
                    foreach ($names as $n) $initials .= strtoupper(substr($n, 0, 1));
                    $initials = substr($initials, 0, 2);
                }
                echo $initials ?: '<i class="fas fa-user"></i>';
                ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <i class="fas fa-bars"></i>
    <?php endif; ?>
</button>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
