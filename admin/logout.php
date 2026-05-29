<?php
require_once '../config/functions.php';

// Destroy admin session variables
if (isset($_SESSION['admin_logged_in'])) {
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_name']);
    unset($_SESSION['admin_role']);
    unset($_SESSION['admin_email']);
    unset($_SESSION['admin_logged_in']);
}

// Redirect to admin login
header('Location: login.php');
exit;
