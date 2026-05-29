<?php
require_once __DIR__ . '/../config/functions.php';
requireAdmin();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Beaute Aesthetic Studio</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="../assets/js/main.js"></script>
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php include 'admin-sidebar.php'; ?>
        <main class="admin-main-content">
            <div class="admin-topbar">
                <div class="admin-topbar-content">
                    <div class="admin-mobile-toggle" id="adminSidebarToggle">
                        <i class="fas fa-bars"></i>
                    </div>
                    <div class="admin-user-info">
                        <?php 
                        $admin_pic = $_SESSION['admin_profile_pic'] ?? null;
                        if ($admin_pic): ?>
                            <img src="../<?php echo htmlspecialchars($admin_pic); ?>" alt="Admin" class="admin-top-avatar">
                        <?php else: ?>
                            <div class="admin-top-avatar-placeholder"><i class="fas fa-user-shield"></i></div>
                        <?php endif; ?>
                        <h2><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></h2>
                    </div>
                    <a href="../index.php" class="btn btn-sm btn-outline" target="_blank">
                        <i class="fas fa-external-link-alt"></i> View Website
                    </a>
                </div>
            </div>
            <div class="admin-content-wrapper">
