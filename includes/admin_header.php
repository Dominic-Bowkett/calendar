<?php
// Expects $page_title to be set before including
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($page_title ?? 'Admin') ?> — <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="<?= APP_URL ?>/admin/dashboard.php">
            <i class="bi bi-calendar3"></i> <?= h(APP_NAME) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= ($active_nav ?? '') === 'dashboard' ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($active_nav ?? '') === 'bookings' ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/bookings.php">
                        <i class="bi bi-calendar-check"></i> Bookings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($active_nav ?? '') === 'services' ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/services.php">
                        <i class="bi bi-briefcase"></i> Services
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($active_nav ?? '') === 'availability' ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/availability.php">
                        <i class="bi bi-clock"></i> Availability
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($active_nav ?? '') === 'google' ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/google.php">
                        <i class="bi bi-google"></i> Google Calendar
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?= h($_SESSION['user_name'] ?? 'Admin') ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/" target="_blank"><i class="bi bi-box-arrow-up-right"></i> View Public Page</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/admin/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
<div class="container-fluid py-4">
