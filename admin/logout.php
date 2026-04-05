<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
session_destroy();
header('Location: ' . APP_URL . '/admin/login.php');
exit;
