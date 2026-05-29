<?php
require_once 'config/functions.php';

// Unset User session variables only
unset($_SESSION['user_id']);
unset($_SESSION['user_name']);
unset($_SESSION['role']);
unset($_SESSION['email']);

// Redirect to user login
header('Location: login.php');
exit;
