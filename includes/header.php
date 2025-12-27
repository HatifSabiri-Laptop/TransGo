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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=2.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <style>
        /* Mobile Navigation */
        @media (max-width: 768px) {
            body {
                padding-top: 66px;
            }

            body.menu-open {
                overflow: hidden;
            }

            .navbar {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                z-index: 10000;
                background: white;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }

            .navbar .container {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.75rem 1rem;
            }

            .nav-toggle {
                display: flex !important;
                align-items: center;
                justify-content: center;
                background: none;
                border: none;
                font-size: 1.5rem;
                cursor: pointer;
                padding: 0.5rem;
                width: 44px;
                height: 44px;
                z-index: 10003;
            }

            .nav-menu {
                position: fixed;
                top: 66px;
                left: -100%;
                width: 100%;
                height: calc(100vh - 66px);
                background: white;
                flex-direction: column;
                gap: 0;
                padding: 1rem 0;
                box-shadow: 0 5px 25px rgba(0, 0, 0, 0.1);
                transition: left 0.3s ease;
                overflow-y: auto;
                z-index: 9999;
                list-style: none;
                margin: 0;
            }

            .nav-menu.active {
                left: 0 !important;
            }

            .nav-menu>li {
                width: 100%;
                border-bottom: 1px solid #f0f0f0;
                margin: 0;
            }

            .nav-menu>li>a {
                width: 100%;
                padding: 1rem 1.5rem;
                display: flex;
                align-items: center;
                justify-content: space-between;
                text-decoration: none;
                color: #1e293b;
            }

            .dropdown-menu {
                display: none;
                background: #f8f9fa;
                padding: 0;
                list-style: none;
                margin: 0;
            }

            .dropdown.active .dropdown-menu {
                display: block !important;
            }

            .dropdown-menu li {
                border-bottom: 1px solid #e9ecef;
            }

            .dropdown-menu li a {
                padding: 0.85rem 1.5rem 0.85rem 3rem !important;
                display: flex;
                align-items: center;
                gap: 0.75rem;
                text-decoration: none;
                color: #555;
            }

            .btn-nav {
                display: block;
                width: calc(100% - 3rem);
                margin: 0.75rem auto;
                text-align: center;
                padding: 0.85rem;
                border-radius: 8px;
                text-decoration: none;
                font-weight: 600;
            }

            .btn-nav.btn-login {
                background: rgba(81, 134, 226, 1);
                border: 2px solid #84f368ff;
                color: rgba(255, 243, 243, 1);
            }

            .btn-nav.btn-primary {
                background: #4d75ccff;
                color: white;
                border: 2px solid #5cb44aff;
            }
        }
    </style>
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

            <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation">
                <i class="fas fa-bars"></i>
            </button>

            <ul class="nav-menu" id="navMenu">
                <li><a href="<?php echo SITE_URL; ?>/index.php">Beranda</a></li>
                <?php if (!is_admin()): ?>
                    <li><a href="<?php echo SITE_URL; ?>/user/reservation.php">Reservasi</a></li>
                <?php endif; ?>

                <?php if (is_admin()): ?>
                    <li><a href="<?php echo SITE_URL; ?>/admin/services.php">Kelola Layanan</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/admin/blog-management.php">Kelola Blog</a></li>
                <?php else: ?>
                    <li><a href="<?php echo SITE_URL; ?>/user/check-in.php">Check-in</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/blog/index.php">Blog</a></li>
                <?php endif; ?>
                <li><a href="<?php echo SITE_URL; ?>/user/infographics.php">Statistik</a></li>

                <!-- Company Dropdown -->
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle">Kompani <i class="fas fa-caret-down"></i></a>
                    <ul class="dropdown-menu">
                        <li><a href="<?php echo SITE_URL; ?>/company/index.php"><i class="fas fa-building"></i> Info Kompani</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/company/article.php"><i class="fas fa-trophy"></i> Prestasi</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/company/terms.php"><i class="fas fa-file-contract"></i> Syarat & Ketentuan</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/company/privacy.php"><i class="fas fa-shield-alt"></i> Kebijakan Privasi</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/company/about.php"><i class="fas fa-users"></i> Tentang Kami</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/company/contact.php"><i class="fas fa-envelope"></i> Hubungi Kami</a></li>
                    </ul>
                </li>

                <?php if (is_logged_in()): ?>
                    <li class="dropdown">
                        <a href="#" class="dropdown-toggle">
                            <i class="fas fa-user"></i> <?php echo $_SESSION['full_name']; ?> <i class="fas fa-caret-down"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a href="<?php echo SITE_URL; ?>/user/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/user/profile.php"><i class="fas fa-user-circle"></i> Profil</a></li>

                            <?php if (is_admin()): ?>
                                <!-- Admin Menu Items -->
                                <li><a href="<?php echo SITE_URL; ?>/admin/dashboard.php"><i class="fas fa-cog"></i> Admin Panel</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/admin/activity_logs.php"><i class="fas fa-history"></i> Activity Logs</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/admin/ticket-check.php"><i class="fas fa-ticket-alt"></i> Semua Tiket</a></li>
                            <?php else: ?>
                                <!-- User Menu Items -->
                                <li><a href="<?php echo SITE_URL; ?>/user/tickets.php"><i class="fas fa-ticket-alt"></i> Tiket Saya</a></li>
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

    <script>

    </script>

    <main class="main-content">