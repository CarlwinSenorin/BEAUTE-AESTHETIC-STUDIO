<?php
require_once '../config/functions.php';
requireStaffOrAdmin();

// Clear staff session
session_unset();
session_destroy();

header('Location: ../admin/login.php');
exit;
