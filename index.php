<style>
    @media (max-width: 768px) {
        .nav-menu {
            left: -100%;
            transition: left 0.3s;
        }

        .nav-menu.active {
            left: 0 !important;
        }

        .nav-toggle {
            display: flex !important;
        }
    }
</style>
<?php

$page_title = 'Beranda';
include 'includes/header.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$conn = getDBConnection();

// Get statistics
$total_services = $conn->query("SELECT COUNT(*) as count FROM services WHERE status='active'")->fetch_assoc()['count'];
$total_bookings = $conn->query("SELECT COUNT(*) as count FROM reservations")->fetch_assoc()['count'];
?>

<!-- Snowfall Canvas -->
<canvas id="snow"></canvas>

<style>
    /* inline styles in index.php */
    .hero {
        background: linear-gradient(135deg, rgba(150, 235, 210, 0.73), rgba(102, 226, 185, 0.64)),
            url('assets/images/hero-bus.jpg') center/cover !important;
        display: flex !important;
        align-items: center !important;
        position: relative !important;
        min-height: 100vh !important;
        height: auto !important;
    }

    .hero-content {
        position: relative !important;
        z-index: 2 !important;
        width: 100% !important;
        text-align: center !important;
        padding: 0 2rem !important;
        margin: 0 auto !important;
    }

    @media (max-width: 768px) {
        .hero-buttons .btn {
            padding: 1rem 1.5rem !important;
            font-size: 1rem !important;
            border-radius: 25px !important;
            width: 100% !important;
            max-width: 280px !important;
            margin: 0 auto;
        }

        .hero-content>div:last-of-type>div h3 {
            font-size: 1.75rem !important;
        }
    }
</style>
<!-- Hero Section -->
<section class="hero">
    <div class="hero-overlay"></div>

    <div class="hero-content">
        <div class="hero-icon-wrapper">
            <i class="fas fa-bus"></i>
        </div>

        <h1 class="hero-title">
            Perjalanan Nyaman<br>Dimulai Dari Sini
        </h1>

        <p class="hero-subtitle">
            Booking tiket transportasi dengan mudah, cepat, dan aman
        </p>

        <div class="hero-buttons">
            <?php if (is_admin()): ?>
                <!-- Admin Buttons -->
                <a href="<?php echo SITE_URL; ?>/admin/dashboard.php"
                    class="btn btn-primary btn-lg">
                    <i class="fas fa-ticket-alt"></i> Admin Dashboard
                </a>
                <a href="<?php echo SITE_URL; ?>/admin/cancellations.php"
                    class="btn btn-primary btn-lg">
                    <i class="fas fa-times-circle"></i> Kelola Pembatalan
                </a>
            <?php else: ?>
                <!-- User Buttons -->
                <a href="<?php echo SITE_URL; ?>/user/reservation.php"
                    class="btn btn-primary btn-lg">
                    <i class="fas fa-ticket-alt"></i> Pesan Sekarang
                </a>

                <a href="<?php echo SITE_URL; ?>/user/check-in.php"
                    class="btn btn-outline btn-lg">
                    <i class="fas fa-sign-in-alt"></i> Check In
                </a>
            <?php endif; ?>
        </div>

        <div class="hero-stats-section">
            <p class="hero-stats-label">Dipercaya oleh ribuan penumpang</p>

            <div class="hero-stats-grid">
                <div class="hero-stat-item">
                    <h3 class="hero-stat-number"><?php echo $total_services; ?>+</h3>
                    <p class="hero-stat-label">Layanan Aktif</p>
                </div>

                <div class="hero-stat-item">
                    <h3 class="hero-stat-number"><?php echo $total_bookings; ?>+</h3>
                    <p class="hero-stat-label">Total Pemesanan</p>
                </div>

                <div class="hero-stat-item">
                    <h3 class="hero-stat-number">4.8</h3>
                    <p class="hero-stat-label">Rating Pelanggan</p>
                </div>
            </div>
        </div>

    </div>

    <a href="#features" class="scroll-button">
        <i class="fas fa-chevron-down"></i>
    </a>

</section>

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

<!-- Luxury Carousel Section -->
<section class="luxury-carousel-section">
    <div class="carousel-container">
        <div class="carousel-slide active">
            <img src="<?php echo SITE_URL; ?>/assets/images/luxury1-new.jpg" alt="Luxury Transport 1">
            <div class="carousel-overlay">
                <h3>Bersihin Kelas Atas</h3>
                <p>Armada yang bersih dan nyaman</p>
            </div>
        </div>
        <div class="carousel-slide">
            <img src="<?php echo SITE_URL; ?>/assets/images/luxury2-new.jpg" alt="Luxury Transport 2">
            <div class="carousel-overlay">
                <h3>Interior Mewah</h4>
                <p>Desain interior yang elegan dan modern</p>
            </div>
        </div>
        <div class="carousel-slide">
            <img src="<?php echo SITE_URL; ?>/assets/images/luxury3-new.jpg" alt="Luxury Transport 3">
            <div class="carousel-overlay">
                <h3>Teknologi Terkini</h5>
                <p>Dilengkapi dengan teknologi dan entertainment terbaru</p>
            </div>
        </div>
        <div class="carousel-slide">
            <img src="<?php echo SITE_URL; ?>/assets/images/luxury4.jpg" alt="Luxury Transport 4">
            <div class="carousel-overlay">
                <h4>Best Experience</h6>
                <p>Dapatkan pengalaman penumpang yang memuaskan</p>
            </div>
        </div>
     <div class="carousel-slide">
            <img src="<?php echo SITE_URL; ?>/assets/images/luxury5.jpg" alt="Luxury Transport 5">
            <div class="carousel-overlay">
                <h5>Luxurious Seats</h7>
                <p>Dapatkan kursi yang nyaman, elegan dan bersih dalam bus AC luxurious</p>
            </div>
        </div>
        <!-- Navigation Dots -->
        <div class="carousel-dots">
            <span class="dot active" data-slide="0"></span>
            <span class="dot" data-slide="1"></span>
            <span class="dot" data-slide="2"></span>
            <span class="dot" data-slide="3"></span>
              <span class="dot" data-slide="4"></span>
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
                <p>Kota Terjangkau</p>
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
                class="btn btn-primary">
                <i class="fas fa-envelope"></i> Email Kami
            </a>
        </div>
    </div>
</section>

<script>
    // Snowfall Effect
    const canvas = document.getElementById('snow');
    const ctx = canvas.getContext('2d');

    function resizeCanvas() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    }

    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    let snowflakes = [];

    // Initialize snowflakes
    for (let i = 0; i < 150; i++) {
        snowflakes.push({
            x: Math.random() * canvas.width,
            y: Math.random() * canvas.height,
            r: Math.random() * 4 + 1,
            d: Math.random() * 1
        });
    }

    function drawSnow() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = "white";
        ctx.beginPath();

        for (let i = 0; i < snowflakes.length; i++) {
            let f = snowflakes[i];
            ctx.moveTo(f.x, f.y);
            ctx.arc(f.x, f.y, f.r, 0, Math.PI * 2, true);
        }
        ctx.fill();
        moveSnow();
    }

    let angle = 0;

    function moveSnow() {
        angle += 0.01;
        for (let i = 0; i < snowflakes.length; i++) {
            let f = snowflakes[i];
            f.y += Math.cos(angle + f.d) + 1 + f.r / 2;
            f.x += Math.sin(angle) * 2;

            if (f.y > canvas.height) {
                snowflakes[i] = {
                    x: Math.random() * canvas.width,
                    y: 0,
                    r: f.r,
                    d: f.d
                };
            }
        }
    }

    setInterval(drawSnow, 33);

    // Carousel functionality
    document.addEventListener('DOMContentLoaded', function() {
        const slides = document.querySelectorAll('.carousel-slide');
        const dots = document.querySelectorAll('.carousel-dots .dot');
        let currentSlide = 0;
        const slideInterval = 3000; // 3 seconds

        function showSlide(index) {
            // Remove active class from all slides and dots
            slides.forEach(slide => slide.classList.remove('active'));
            dots.forEach(dot => dot.classList.remove('active'));

            // Add active class to current slide and dot
            slides[index].classList.add('active');
            dots[index].classList.add('active');
        }

        function nextSlide() {
            currentSlide = (currentSlide + 1) % slides.length;
            showSlide(currentSlide);
        }

        // Auto advance slides
        let autoSlide = setInterval(nextSlide, slideInterval);

        // Manual dot navigation
        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => {
                currentSlide = index;
                showSlide(currentSlide);
                // Reset auto advance
                clearInterval(autoSlide);
                autoSlide = setInterval(nextSlide, slideInterval);
            });
        });

        // Pause on hover
        const carouselContainer = document.querySelector('.carousel-container');
        carouselContainer.addEventListener('mouseenter', () => {
            clearInterval(autoSlide);
        });

        carouselContainer.addEventListener('mouseleave', () => {
            autoSlide = setInterval(nextSlide, slideInterval);
        });
    });
</script>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

include __DIR__ . '/includes/footer.php';
?>