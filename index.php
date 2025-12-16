<?php
$page_title = 'Beranda';
include 'includes/header.php';
require_once __DIR__ . '/config/config.php';
$conn = getDBConnection();

// Get statistics
$total_services = $conn->query("SELECT COUNT(*) as count FROM services WHERE status='active'")->fetch_assoc()['count'];
$total_bookings = $conn->query("SELECT COUNT(*) as count FROM reservations")->fetch_assoc()['count'];
?>

<!-- Hero Section -->
<section class="hero"
    style="background: linear-gradient(135deg, rgba(167, 187, 230, 0.95), rgba(102, 226, 185, 0.95)), 
    url('assets/images/hero-bus.jpg') center/cover; 
    height: 120vh; display: flex; align-items: center; position: relative;">

    <div class="hero-overlay"
        style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.4);">
    </div>

    <div class="hero-content"
        style="position: relative; z-index: 2; width: 100%; text-align: center; padding: 0 2rem;">

        <div style="animation: fadeInDown 1s;">
            <i class="fas fa-bus"
                style="font-size: 4rem; color: white; margin-bottom: 2rem; display: inline-block;">
            </i>
        </div>

        <h1 class="hero-title"
            style="font-size: 4rem; font-weight: 800; color: white; margin-bottom: 1.5rem; 
            text-shadow: 2px 2px 8px rgba(0,0,0,0.3); animation: fadeInUp 1s 0.2s both; line-height: 1.2;">
            Perjalanan Nyaman<br>Dimulai Dari Sini
        </h1>

        <p class="hero-subtitle"
            style="font-size: 1.5rem; color: rgba(255,255,255,0.95); margin-bottom: 3rem; 
           animation: fadeInUp 1s 0.4s both; max-width: 700px; margin-left: auto; margin-right: auto;">
            Booking tiket transportasi dengan mudah, cepat, dan aman
        </p>

        <div class="hero-buttons"
            style="display: flex; gap: 1.5rem; justify-content: center; flex-wrap: wrap; 
             animation: fadeInUp 1s 0.6s both;">

            <a href="<?php echo SITE_URL; ?>/user/reservation.php"
                class="btn btn-primary btn-lg"
                style="padding: 1.25rem 3rem; font-size: 1.2rem; border-radius: 50px;  
                box-shadow: 0 8px 20px rgba(37, 99, 235, 0.4); background: white; color: var(--primary);">
                <i class="fas fa-ticket-alt"></i> Pesan Tiket Sekarang
            </a>

            <a href="<?php echo SITE_URL; ?>/user/check-in.php"
                class="btn btn-outline btn-lg"
                style="padding: 1.25rem 3rem; font-size: 1.2rem; border-radius: 50px; 
               background: transparent; color: white; border: 3px solid white;">
                <i class="fas fa-check-circle"></i> Check-in Online
            </a>
        </div>

        <div style="margin-top: 4rem; animation: fadeInUp 1s 0.8s both;">
            <p style="color: rgba(255,255,255,0.8); margin-bottom: 1rem;">
                Dipercaya oleh ribuan penumpang
            </p>

            <div style="display: flex; gap: 3rem; justify-content: center; align-items: center;">
                <div style="text-align: center;">
                    <h3 style="color: white; font-size: 2.5rem; font-weight: bold; margin-bottom: 0.5rem;">
                        <?php echo $total_services; ?>+
                    </h3>
                    <p style="color: rgba(255,255,255,0.9);">Layanan Aktif</p>
                </div>

                <div style="text-align: center;">
                    <h3 style="color: white; font-size: 2.5rem; font-weight: bold; margin-bottom: 0.5rem;">
                        <?php echo $total_bookings; ?>+
                    </h3>
                    <p style="color: rgba(255,255,255,0.9);">Total Pemesanan</p>
                </div>

                <div style="text-align: center;">
                    <h3 style="color: white; font-size: 2.5rem; font-weight: bold; margin-bottom: 0.5rem;">
                        4.8
                    </h3>
                    <p style="color: rgba(255,255,255,0.9);">Rating Pelanggan</p>
                </div>
            </div>
        </div>

    </div>

    <!-- SCROLL BUTTON (WORKING) -->
    <a href="#features"
        style="position: absolute; bottom: 1rem; left: 50%; transform: translateX(-50%);
              animation: bounce 2s infinite; z-index: 3; cursor: pointer; text-decoration:none;">
        <i class="fas fa-chevron-down" style="font-size: 2rem; color: white;"></i>
    </a>

</section>


<style>
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes fadeInDown {
        from {
            opacity: 0;
            transform: translateY(-30px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes bounce {

        0%,
        20%,
        50%,
        80%,
        100% {
            transform: translateX(-50%) translateY(0);
        }

        40% {
            transform: translateX(-50%) translateY(-10px);
        }

        60% {
            transform: translateX(-50%) translateY(-5px);
        }
    }

    html {
        scroll-behavior: smooth;
    }

    .btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.3) !important;
    }

    .hero-buttons .btn-primary:hover {
        background: var(--primary) !important;
        color: white !important;
    }

    @media (max-width: 768px) {
        .hero-title {
            font-size: 2.5rem !important;
        }

        .hero-subtitle {
            font-size: 1.1rem !important;
        }

        .hero-buttons {
            flex-direction: column;
        }

        .hero-buttons .btn {
            width: 100%;
        }
    }
</style>

<!-- Features Section -->
<section id="features" class="features">
    <div class="container">
        <h2 class="section-title">Mengapa Memilih TransGo?</h2>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <h3>Booking Mudah</h3>
                <p>Pesan tiket kapan saja, di mana saja melalui smartphone atau komputer Anda</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>Aman & Terpercaya</h3>
                <p>Keamanan data dan transaksi Anda adalah prioritas utama kami</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h3>Tepat Waktu</h3>
                <p>Armada dengan jadwal yang teratur dan selalu on-time</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-couch"></i>
                </div>
                <h3>Nyaman</h3>
                <p>Kursi yang ergonomis dan fasilitas entertainment untuk perjalanan menyenangkan</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-headset"></i>
                </div>
                <h3>Customer Support</h3>
                <p>Tim support kami siap membantu 24/7 untuk kebutuhan Anda</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-tags"></i>
                </div>
                <h3>Harga Terjangkau</h3>
                <p>Dapatkan harga terbaik dengan berbagai promo menarik</p>
            </div>
        </div>
    </div>
</section>

<!-- How to Book Section -->
<section class="how-to-book">
    <div class="container">
        <h2 class="section-title">Cara Memesan Tiket</h2>
        <div class="steps-grid">
            <div class="step-card">
                <div class="step-number">1</div>
                <h3>Pilih Layanan</h3>
                <p>Tentukan rute dan jadwal perjalanan yang sesuai dengan kebutuhan Anda</p>
            </div>

            <div class="step-card">
                <div class="step-number">2</div>
                <h3>Isi Data</h3>
                <p>Lengkapi informasi pemesanan dan data penumpang dengan benar</p>
            </div>

            <div class="step-card">
                <div class="step-number">3</div>
                <h3>Bayar</h3>
                <p>Lakukan pembayaran melalui metode yang tersedia dengan aman</p>
            </div>

            <div class="step-card">
                <div class="step-number">4</div>
                <h3>Terima Tiket</h3>
                <p>Dapatkan kode booking dan e-ticket melalui email Anda</p>
            </div>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="stats">
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-bus"></i>
                </div>
                <h3 class="stat-number"><?php echo $total_services; ?>+</h3>
                <p>Layanan Aktif</p>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3 class="stat-number"><?php echo $total_bookings; ?>+</h3>
                <p>Total Pemesanan</p>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <h3 class="stat-number">4.8</h3>
                <p>Rating Pelanggan</p>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-city"></i>
                </div>
                <h3 class="stat-number">25+</h3>
                <p>Kota Tujuan</p>
            </div>
        </div>
    </div>
</section>

<!-- Contact Section -->
<section class="contact-cta">
    <div class="container">
        <h2>Butuh Bantuan?</h2>
        <p>Tim customer support kami siap membantu Anda</p>
        <div class="contact-buttons">
            <a href="https://wa.me/62882006907493" class="btn btn-primary">
                <i class="fas fa-phone"></i> Hubungi Kami
            </a>
            <a href="https://mail.google.com/mail/?view=cm&fs=1&to=hatifsabiri648@gmail.com&su=Hello&body=I%20want%20to%20contact%20you"
                target="_blank"
                class="btn btn-outline">
                <i class="fas fa-envelope"></i> Email Kami
            </a>

            </a>
        </div>
    </div>
</section>

<?php
closeDBConnection($conn);
include 'includes/footer.php';
?>