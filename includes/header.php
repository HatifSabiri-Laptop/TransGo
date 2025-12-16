<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
</head>
<body>

    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <a href="<?php echo SITE_URL; ?>/index.php">
                    <img src="<?php echo SITE_URL; ?>/assets/images/logo.jpg" alt="TransGo Logo" class="logo-img">
                    <span>TransGo</span>
                </a>
            </div>

            <button class="nav-toggle" id="navToggle">
                <i class="fas fa-bars"></i>
            </button>

            <ul class="nav-menu" id="navMenu">
                <li><a href="<?php echo SITE_URL; ?>/index.php">Beranda</a></li>
                <li><a href="<?php echo SITE_URL; ?>/user/reservation.php">Reservasi</a></li>

                <!-- Company Dropdown -->
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle">Kompani<i class="fas fa-caret-down"></i></a>
                    <ul class="dropdown-menu">
                        <li><a href="<?php echo SITE_URL; ?>/company/index.php"><i class="fas fa-building"></i> Info Kompani</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/company/article.php"><i class="fas fa-trophy"></i> Prestasi</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/company/terms.php"><i class="fas fa-file-contract"></i> Syarat & Ketentuan</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/company/privacy.php"><i class="fas fa-shield-alt"></i> Kebijakan Privasi</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/company/about.php"><i class="fas fa-users"></i> Tentang Kami</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/company/contact.php"><i class="fas fa-envelope"></i> Hubungi Kami</a></li>
                    </ul>
                </li>

                <li><a href="<?php echo SITE_URL; ?>/user/check-in.php">Check-in</a></li>
                <li><a href="<?php echo SITE_URL; ?>/user/infographics.php">Statistik</a></li>

                <?php if (is_logged_in()): ?>
                    <li class="dropdown">
                        <a href="#" class="dropdown-toggle">
                            <i class="fas fa-user"></i> <?php echo $_SESSION['full_name']; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a href="<?php echo SITE_URL; ?>/user/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/user/profile.php"><i class="fas fa-user-circle"></i> Profil</a></li>
                            <?php if (is_admin()): ?>
                                <li><a href="<?php echo SITE_URL; ?>/admin/dashboard.php"><i class="fas fa-cog"></i> Admin Panel</a></li>
                            <?php endif; ?>
                            <li><a href="<?php echo SITE_URL; ?>/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li><a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn-nav btn-login">Login</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/auth/register.php" class="btn-nav btn-primary">Daftar</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <main class="main-content">
