<?php
require_once __DIR__ . '/../config/functions.php';
requireStaffOrAdmin();
$current_page = basename($_SERVER['PHP_SELF']);
$staff_name = $_SESSION['staff_name'] ?? ($_SESSION['admin_name'] ?? 'Staff');
$staff_role = $_SESSION['staff_role'] ?? ($_SESSION['admin_role'] ?? 'staff');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Beaute Aesthetic Studio</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/staff.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="../assets/js/main.js"></script>
</head>
<body class="staff-body">
    <div class="staff-wrapper">
        <?php include 'staff-sidebar.php'; ?>
        <main class="staff-main-content">
            <div class="staff-topbar">
                <div class="staff-topbar-content">
                    <div class="staff-mobile-toggle" id="staffSidebarToggle">
                        <i class="fas fa-bars"></i>
                    </div>
                    <div class="staff-user-info">
                        <?php 
                        $staff_pic = $_SESSION['staff_profile_pic'] ?? null;
                        if ($staff_pic): ?>
                            <img src="../<?php echo htmlspecialchars($staff_pic); ?>" alt="Staff" class="staff-top-avatar">
                        <?php else: ?>
                            <div class="staff-top-avatar-placeholder"><i class="fas fa-user-tie"></i></div>
                        <?php endif; ?>
                        <h2>Welcome, <strong><?php echo htmlspecialchars($staff_name); ?></strong></h2>
                    </div>
                    <div class="staff-topbar-right">
                        <span class="staff-role-badge"><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars(ucfirst($staff_role)); ?></span>
                        <a href="../index.php" class="btn btn-outline btn-sm" target="_blank">
                            <i class="fas fa-external-link-alt"></i> View Website
                        </a>
                    </div>
                </div>
            </div>
            <div class="staff-content-wrapper">
