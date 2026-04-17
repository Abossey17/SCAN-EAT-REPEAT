<?php
// logout.php (can be used in both admin and restaurant folders)
session_start();
session_destroy();

// Redirect based on where logout was called from
$redirect = (strpos($_SERVER['REQUEST_URI'], 'admin') !== false) ? 'admin/login.php' : 'restaurant/login.php';
header('Location: ../' . $redirect);
exit();