<?php
// company/index.php

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

$conn = getDBConnection();
$cssPath = SITE_URL . '/assets/css/company.css';

// Set page title for header
$page_title = 'Company Information';
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TransGo - Company Information</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $cssPath; ?>">
    <style>
        /* Base Styles */
        :root {
            --primary: #10b981;
            --secondary: #628ce7;
            --accent: #ff6b35;
            --dark: #1a202c;
            --light: #f7fafc;
            --success: #74b9ff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        html,
        body {
            max-width: 100%;
            overflow-x: hidden;
            position: relative;
        }

        body {
            background-color: #f8fafc;
            color: #333;
            line-height: 1.6;
            width: 100%;
            overflow-x: hidden;
        }
        

        /* Main Container */
        .company-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            width: 100%;
            overflow-x: hidden;
        }

        /* Hero Section */
        .company-hero {
            position: relative;
            min-height: 50vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
            padding: 4rem 2rem;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.9), rgba(98, 140, 231, 0.85)),
                url('<?php echo SITE_URL; ?>/assets/images/hero-company.jpg') center/cover no-repeat;
            border-radius: 0 0 40px 40px;
            overflow: hidden;
            width: 100%;
        }

        .hero-content {
            max-width: 900px;
            z-index: 2;
            position: relative;
            width: 100%;
            padding: 0 10px;
        }

        .company-hero h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .company-hero p {
            font-size: 1.3rem;
            opacity: 0.95;
            margin-bottom: 2rem;
            max-width: 100%;
            margin-left: auto;
            margin-right: auto;
            padding: 0 10px;
            word-wrap: break-word;
        }

        .btn-primary {
            display: inline-block;
            padding: 1rem 2.5rem;
            background: white;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            white-space: nowrap;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
            background: var(--light);
        }

        /* Section Title */
        .section-title {
            position: relative;
            font-size: 2.2rem;
            color: var(--dark);
            margin-bottom: 2rem;
            padding-bottom: 0.5rem;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 4px;
            background: var(--primary);
            border-radius: 2px;
        }

        /* Main Content Grid - LAPTOP VIEW */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin: 2.5rem 0;
            width: 100%;
        }

        /* Cards */
        .overview-card,
        .quick-facts-card {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
            width: 100%;
            overflow: hidden;
        }

        .overview-card:hover,
        .quick-facts-card:hover {
            transform: translateY(-5px);
        }

        .quick-facts-card {
            background: linear-gradient(135deg, #93eba6, #74b9ff);
            color: white;
        }

        .quick-facts-card ul {
            list-style: none;
            padding: 0;
        }

        .quick-facts-card li {
            padding: 0.8rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            word-wrap: break-word;
        }

        /* Mission List */
        .mission-list {
            list-style: none;
            padding: 0;
        }

        .mission-list li {
            padding: 1rem 0 1rem 3rem;
            position: relative;
            margin-bottom: 0.8rem;
            word-wrap: break-word;
        }

        .mission-list li:before {
            content: '✓';
            position: absolute;
            left: 0;
            top: 1rem;
            background: var(--primary);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        /* Timeline & Achievements Sections */
        .timeline-section,
        .achievements-section {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin: 1.5rem 0;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            width: 100%;
            overflow: hidden;
        }

        .timeline {
            position: relative;
            padding-left: 2rem;
            margin-top: 2rem;
        }

        .timeline:before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: linear-gradient(to bottom, var(--primary), var(--secondary));
            border-radius: 3px;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 1.5rem;
            padding-left: 2rem;
            word-wrap: break-word;
        }

        .timeline-item:before {
            content: '';
            position: absolute;
            left: -2.5rem;
            top: 0.3rem;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--primary);
            border: 3px solid white;
            box-shadow: 0 0 0 3px var(--primary);
        }

        /* Achievements Grid */
        .achievements-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
            width: 100%;
        }

        .achievement-card {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border-radius: 15px;
            padding: 2rem;
            border-left: 5px solid var(--accent);
            transition: all 0.3s ease;
            width: 100%;
            overflow: hidden;
        }

        .achievement-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }

        .icon-container {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--accent);
        }

        /* Stats Container */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin: 1.5rem 0;
            width: 100%;
        }

        .stat-item {
            text-align: center;
            padding: 1.5rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            width: 100%;
            overflow: hidden;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0.5rem;
            word-wrap: break-word;
        }

        /* Future Vision Grid */
        .future-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            margin-top: 1rem;
            width: 100%;
        }

        .future-grid>div {
            width: 100%;
            overflow: hidden;
        }

        /* TABLET STYLES (768px to 1024px) */
        @media (max-width: 1024px) {
            .company-wrapper {
                padding: 0 15px;
            }

            .company-hero {
                min-height: 60vh;
                padding: 3rem 1.5rem;
                border-radius: 0 0 30px 30px;
            }

            .company-hero h1 {
                font-size: 2.8rem;
                padding: 0 10px;
            }

            .company-hero p {
                font-size: 1.2rem;
                padding: 0 10px;
            }

            .content-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }

            .achievements-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .future-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .section-title {
                font-size: 2rem;
                padding-right: 10px;
            }

            .overview-card,
            .quick-facts-card,
            .timeline-section,
            .achievements-section,
            .achievement-card,
            .stat-item {
                padding: 1.5rem;
            }
        }

        /* MOBILE STYLES (up to 767px) */
        @media (max-width: 767px) {

            html,
            body {
                overflow-x: hidden;
            }

            .company-hero {
                min-height: 45vh;
                padding: 2.5rem 1rem;
                border-radius: 0 0 25px 25px;
                margin: 0;
            }

            .company-hero h1 {
                font-size: 2.2rem;
                line-height: 1.2;
                padding: 0 5px;
                word-break: break-word;
            }

            .company-hero p {
                font-size: 1.1rem;
                margin-bottom: 1.5rem;
                padding: 0 5px;
            }

            .btn-primary {
                padding: 0.9rem 2rem;
                font-size: 1rem;
                max-width: 100%;
                white-space: normal;
            }

            .company-wrapper {
                padding: 0 12px;
                width: 100%;
            }

            /* Force single column layout for mobile */
            .content-grid {
                display: flex;
                flex-direction: column;
                gap: 2rem;
                margin: 3rem 0;
                width: 100%;
            }

            /* Ensure both cards take full width */
            .overview-card,
            .quick-facts-card {
                width: 100%;
                padding: 1.5rem;
                margin: 0;
            }

            /* Stats - 2 columns on mobile */
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
                margin: 2rem 0;
                padding: 0 5px;
            }

            .stat-item {
                padding: 1.2rem 0.8rem;
            }

            .stat-number {
                font-size: 2rem;
            }

            /* Timeline adjustments */
            .timeline-section,
            .achievements-section {
                padding: 1.5rem;
                margin: 2rem 0;
                width: 100%;
            }

            .section-title {
                font-size: 1.8rem;
                padding-right: 0;
                margin-right: 0;
            }

            .timeline {
                padding-left: 1.2rem;
            }

            .timeline-item {
                padding-left: 1.2rem;
                padding-bottom: 1.5rem;
                padding-right: 0;
            }

            .timeline-item:before {
                left: -1.5rem;
            }

            /* Achievements - single column on mobile */
            .achievements-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
                padding: 0;
            }

            .achievement-card {
                padding: 1.5rem;
            }

            /* Mission list adjustments */
            .mission-list li {
                padding: 0.8rem 0 0.8rem 2.2rem;
                margin-bottom: 0.6rem;
                padding-right: 5px;
            }

            .mission-list li:before {
                top: 0.8rem;
                width: 20px;
                height: 20px;
                font-size: 0.8rem;
            }

            /* Future grid single column */
            .future-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
                padding: 0;
            }

            .future-grid>div {
                padding: 1.5rem;
            }
        }

        /* Small Mobile (up to 480px) */
        @media (max-width: 480px) {
            .company-hero h1 {
                font-size: 1.9rem;
                line-height: 1.3;
            }

            .company-hero p {
                font-size: 1rem;
                line-height: 1.5;
            }

            .section-title {
                font-size: 1.6rem;
            }

            .stats-container {
                grid-template-columns: 1fr;
                gap: 1rem;
                padding: 0;
            }

            .overview-card,
            .quick-facts-card,
            .timeline-section,
            .achievements-section {
                padding: 1.2rem;
                border-radius: 15px;
            }

            .icon-container {
                font-size: 2rem;
            }

            .stat-number {
                font-size: 1.8rem;
            }

            .timeline {
                padding-left: 1rem;
            }

            .timeline-item {
                padding-left: 1rem;
            }

            .timeline-item:before {
                left: -1.2rem;
            }
        }

        /* LAPTOP ONLY - Keep original layout */
        @media (min-width: 1025px) {
            .content-grid {
                display: grid;
                grid-template-columns: 2fr 1fr;
                gap: 3rem;
            }

            .stats-container {
                grid-template-columns: repeat(4, 1fr);
            }

            .achievements-grid {
                grid-template-columns: repeat(3, 1fr);
            }

            .future-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <section class="company-hero">
        <div class="hero-content" data-aos="fade-up">
            <h1>TransGo — Beyond Transportation</h1>
            <p>
                Dibangun dengan semangat menghadirkan perjalanan nyaman dan berkesan.
                Berdiri sejak <strong>12 Maret 2018</strong>, kami telah menjadi bagian dari perjalanan puluhan ribu penumpang.
            </p>

            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <!-- Admin Button -->
                <a href="<?php echo SITE_URL; ?>/admin/ticket-check.php" class="btn-primary">
                    <i class="fas fa-ticket-alt"></i> Semua Tiket
                </a>
            <?php else: ?>
                <!-- User Button -->
                <a href="<?php echo SITE_URL; ?>/user/reservation.php" class="btn-primary">
                    <i class="fas fa-bus"></i> Pesan Sekarang
                </a>
            <?php endif; ?>
        </div>

        <div style="position:absolute; bottom:0; left:0; right:0; height:100px; background:linear-gradient(to top, #f8fafc, transparent);"></div>
    </section>


    <div class="company-wrapper">
        <!-- Stats Section -->
        <div class="stats-container" data-aos="fade-up">
            <div class="stat-item">
                <div class="stat-number">50K+</div>
                <div class="stat-label">Penumpang Bahagia</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">15+</div>
                <div class="stat-label">Rute Aktif</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">100+</div>
                <div class="stat-label">Armada Terawat</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">5 Tahun</div>
                <div class="stat-label">Pengalaman</div>
            </div>
        </div>

        <!-- MAIN CONTENT AREA - Responsive grid -->
        <div class="content-grid">
            <!-- Overview Card - Always comes first -->
            <div class="overview-card" data-aos="fade-right">
                <h2 class="section-title">Overview Perusahaan</h2>
                <p>TransGo didirikan pada <strong>12 Maret 2018</strong> dengan visi merevolusi industri transportasi darat di Indonesia. Sejak awal, misi kami adalah menciptakan ekosistem transportasi yang aman, nyaman, dan terintegrasi secara digital.</p>

                <h3 style="margin-top: 2rem; color: var(--primary);">Misi Perusahaan</h3>
                <ul class="mission-list">
                    <li><strong>Inovasi Layanan:</strong> Terus mengembangkan platform digital untuk kemudahan akses dan pemesanan</li>
                    <li><strong>Keamanan Utama:</strong> Menjaga standar keselamatan tertinggi dengan armada terawat dan sopir bersertifikasi</li>
                    <li><strong>Kenyamanan Penumpang:</strong> Menyediakan fasilitas premium dan pengalaman perjalanan yang tak terlupakan</li>
                    <li><strong>Sustainable Mobility:</strong> Berkontribusi pada transportasi berkelanjutan dengan optimasi rute dan emisi rendah</li>
                    <li><strong>Komunitas Terhubung:</strong> Menjembatani jarak antar kota dan memperkuat hubungan masyarakat</li>
                </ul>

                <h3 style="margin-top: 2rem; color: var(--primary);">Visi 2025</h3>
                <p>Menjadi platform transportasi darat terdepan di Asia Tenggara dengan integrasi teknologi AI untuk prediksi permintaan, optimasi rute real-time, dan pengalaman penumpang yang dipersonalisasi.</p>
            </div>

            <!-- Quick Facts Card - Comes after overview on mobile/tablet, stays on side on laptop -->
            <div class="quick-facts-card" data-aos="fade-left">
                <h2 style="color: white; margin-bottom: 1.5rem;">Quick Facts</h2>
                <ul>
                    <li><strong><i class="fas fa-calendar-alt"></i> Didirikan:</strong> 12 Maret 2018</li>
                    <li><strong><i class="fas fa-map-marker-alt"></i> Kantor Pusat:</strong> Surakarta, Jawa Tengah</li>
                    <li><strong><i class="fas fa-route"></i> Rute Aktif:</strong> 15+ lintas kota</li>
                    <li><strong><i class="fas fa-users"></i> Penumpang:</strong> 50.000+ terlayani</li>
                    <li><strong><i class="fas fa-bus"></i> Armada:</strong> 100+ kendaraan</li>
                    <li><strong><i class="fas fa-award"></i> Penghargaan:</strong> 5+ penghargaan nasional</li>
                </ul>

                <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.2);">
                    <h3 style="color: white; margin-bottom: 1rem;">Kontak Kami</h3>
                    <p style="margin-bottom: 0.5rem;">
                        <i class="fas fa-envelope"></i> Email: <?php echo ADMIN_EMAIL; ?>
                    </p>
                    <p style="margin-bottom: 0.5rem;">
                        <i class="fas fa-phone"></i> Telepon: +62 882-0069-07493
                    </p>
                    <p>
                        <i class="fas fa-clock"></i> Operasional: 24/7 Customer Service
                    </p>
                </div>
            </div>
        </div>

        <!-- Timeline Section -->
        <section class="timeline-section" data-aos="fade-up">
            <h2 class="section-title">Journey & Milestones</h2>
            <div class="timeline">
                <div class="timeline-item">
                    <h3 style="color: var(--primary);">2018 — Peluncuran Perdana</h3>
                    <p>TransGo resmi berdiri dengan rute pertama Surakarta-Yogyakarta. Dalam 3 bulan pertama, kami melayani 1,000+ penumpang dengan rating kepuasan 4.8/5.</p>
                </div>

                <div class="timeline-item">
                    <h3 style="color: var(--primary);">2019 — Inovasi Digital & Penghargaan</h3>
                    <p>Meluncurkan aplikasi mobile dan sistem booking online real-time. Meraih <strong>Best Startup Award</strong> dari Kementerian Pariwisata dan Ekonomi Kreatif.</p>
                </div>

                <div class="timeline-item">
                    <h3 style="color: var(--primary);">2020 — Ketahanan di Masa Pandemi</h3>
                    <p>Menerapkan protokol kesehatan lengkap, menjadi transportasi pertama dengan sertifikasi "Safe Travel". Ekspansi ke 5 rute baru di Jawa Tengah.</p>
                </div>

                <div class="timeline-item">
                    <h3 style="color: var(--primary);">2021 — Transformasi Teknologi</h3>
                    <p>Integrasi sistem AI untuk prediksi permintaan, peluncuran fitur tracking real-time, dan kemitraan dengan 50+ hotel dan tempat wisata.</p>
                </div>

                <div class="timeline-item">
                    <h3 style="color: var(--primary);">2022 — Ekspansi Regional</h3>
                    <p>Perluasan ke Jawa Timur dan Jawa Barat, penambahan 30 armada premium, pencapaian 30,000 penumpang setia.</p>
                </div>

                <div class="timeline-item">
                    <h3 style="color: var(--primary);">2023 — Pengakuan Global</h3>
                    <p>Meraih <strong>Golden Tech Excellence Award</strong> untuk inovasi digital, ISO 9001:2015 untuk manajemen kualitas, dan rating 4.9 di Google Play Store.</p>
                </div>
            </div>
        </section>

        <!-- Achievements Section -->
        <section class="achievements-section" data-aos="fade-up">
            <h2 class="section-title">Prestasi & Pengakuan</h2>
            <div class="achievements-grid">
                <div class="achievement-card">
                    <div class="icon-container">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <h3>Best Startup Award 2019</h3>
                    <p>Penghargaan dari Kemenparekraf untuk inovasi bisnis transportasi digital dan kontribusi terhadap ekonomi kreatif.</p>
                </div>

                <div class="achievement-card">
                    <div class="icon-container">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Safe Travel Certified 2020</h3>
                    <p>Sertifikasi internasional untuk standar protokol kesehatan dan keselamatan penumpang tertinggi di industri.</p>
                </div>

                <div class="achievement-card">
                    <div class="icon-container">
                        <i class="fas fa-medal"></i>
                    </div>
                    <h3>Innovation Trophy 2021</h3>
                    <p>Penghargaan atas inovasi sistem booking real-time dan integrasi pembayaran digital yang seamless.</p>
                </div>

                <div class="achievement-card">
                    <div class="icon-container">
                        <i class="fas fa-star"></i>
                    </div>
                    <h3>Customer Excellence 2022</h3>
                    <p>Rating kepuasan pelanggan 96% berdasarkan survei independen terhadap 10,000 penumpang.</p>
                </div>

                <div class="achievement-card">
                    <div class="icon-container">
                        <i class="fas fa-globe"></i>
                    </div>
                    <h3>Golden Tech Excellence 2023</h3>
                    <p>Pengakuan global untuk implementasi AI dalam optimasi rute dan manajemen armada cerdas.</p>
                </div>

                <div class="achievement-card">
                    <div class="icon-container">
                        <i class="fas fa-leaf"></i>
                    </div>
                    <h3>Green Transportation Award 2023</h3>
                    <p>Penghargaan untuk komitmen keberlanjutan dengan armada ramah lingkungan dan program carbon offset.</p>
                </div>
            </div>
        </section>

        <!-- Future Vision Section -->
        <section class="timeline-section" data-aos="fade-up">
            <h2 class="section-title">Masa Depan TransGo</h2>
            <div class="future-grid">
                <div style="background: linear-gradient(135deg, #74b9ff, #0984e3); color: white; padding: 2rem; border-radius: 15px;">
                    <h3 style="color: white;"><i class="fas fa-bolt"></i> Elektrik & AI</h3>
                    <p>Transisi 50% armada ke kendaraan listrik dan implementasi AI untuk prediksi maintenance dan optimasi energi.</p>
                </div>
                <div style="background: linear-gradient(135deg, #00b894, #00a085); color: white; padding: 2rem; border-radius: 15px;">
                    <h3 style="color: white;"><i class="fas fa-expand-arrows-alt"></i> Ekspansi Nasional</h3>
                    <p>Ekspansi ke 10 provinsi baru dan peluncuran layanan antar-pulau dengan sistem ferry terintegrasi.</p>
                </div>
                <div style="background: linear-gradient(135deg, #fd79a8, #e84393); color: white; padding: 2rem; border-radius: 15px;">
                    <h3 style="color: white;"><i class="fas fa-hands-helping"></i> Komunitas & CSR</h3>
                    <p>Program "TransGo Peduli" untuk akses transportasi gratis bagi pelajar dan masyarakat prasejahtera.</p>
                </div>
            </div>
        </section>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            once: true,
            offset: 100,
            disable: window.innerWidth < 768 ? true : false
        });

        function checkMobile() {
            if (window.innerWidth < 768) {
                document.body.classList.add('mobile-view');
            } else {
                document.body.classList.remove('mobile-view');
            }
        }

        window.addEventListener('resize', checkMobile);
        checkMobile();
    </script>

    <?php
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }

    include __DIR__ . '/../includes/footer.php';
    ?>
</body>

</html>