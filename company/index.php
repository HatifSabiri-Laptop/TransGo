<?php
// company/index.php
require_once __DIR__ . '/../config/config.php';
include __DIR__ . '/../includes/header.php';
$conn = getDBConnection();
?>
<section class="company-hero" style="background: linear-gradient(135deg, rgba(16,185,129,0.85), rgba(98, 140, 231, 0.75)), url('<?php echo SITE_URL; ?>/assets/images/hero-company.jpg') center/cover; min-height:60vh; display:flex; align-items:center; justify-content:center; color:white; position:relative;">
    <div data-aos="fade-up" style="max-width:980px; text-align:center; padding:3rem;">
        <h1 style="font-size:2.6rem; font-weight:800; margin-bottom:0.5rem;">TransGo — Company Info</h1>
        <p style="font-size:1.1rem; opacity:0.95; margin-bottom:1.5rem;">
            Dibangun dengan semangat menghadirkan perjalanan nyaman. Berdiri sejak <strong>12 March 2018</strong>.
        </p>
        <a href="<?php echo SITE_URL; ?>/user/reservation.php" class="btn btn-primary" style="padding:0.9rem 1.8rem; border-radius:50px; text-decoration:none; background:white; color:var(--primary);">Pesan Sekarang</a>
    </div>
</section>

 <div class="container" style="padding: 0 3rem;">
        <div class="row" style="display:flex; gap:2rem; flex-wrap:wrap;">
            <div style="flex:1 1 560px;" data-aos="fade-right">
            <h2 style="margin-top:1.5rem; background:rgba(116, 185, 255,1.0); padding:0.5rem 1rem; border-radius:10px;"> Overview</h2>
            <p>TransGo didirikan pada <strong>12 March 2018</strong>. Sejak awal tujuan kami adalah membuat layanan transportasi yang mudah, aman, dan dapat diandalkan untuk penumpang di berbagai rute. Kami fokus pada pengalaman pengguna, kecepatan layanan, dan keselamatan perjalanan.</p>

            <h3 style="margin-top:1.5rem; background:rgba(116, 185, 255,1.0); padding:0.5rem 1rem; border-radius:10px;">Mission & Vision</h3>
            <ul>
                <li>Memberi pengalaman perjalanan yang nyaman.</li>
                <li>Meningkatkan akses transportasi antar kota.</li>
                <li>Menjadi penyedia layanan transportasi tepercaya.</li>
                <li>Memerangi kemacetan dan mengurangi polusi udara.</li>
            </ul>
        </div>

        <div style="flex:1 1 360px; margin-top:1.5rem; background:rgba(147, 235, 166, 1); padding:1.5rem; border-radius:20px;" data-aos="fade-left">
            <h2>Quick Facts</h2>
            <ul style="list-style:none; padding:0;">
                <li><strong>Founded:</strong> 12 March 2018</li>
                <li><strong>Headquarters:</strong> Surakarta, Indonesia</li>
                <li><strong>Active Routes:</strong> > 15</li>
                <li><strong>Trusted By:</strong> > 50.000 passengers</li>
            </ul>

            <div style="margin-top:1.2rem;">
                <h3>Contact</h3>
                <p>Email: <?php echo ADMIN_EMAIL; ?><br>Phone: +62 882006907493</p>
            </div>
        </div>
    </div>

    <!-- Timeline -->
    <section style="margin-top:3rem; margin-top:1.5rem; background:rgba(116, 185, 255,1.0); padding:0.5rem 1rem; border-radius:10px;" data-aos="fade-up">
        <h2>Timeline & Achievements</h2>
        <div style="margin-top:1rem;">
            <div style="border-left:3px solid rgba(0,0,0,0.08); padding-left:1rem;">
                <div style="margin-bottom:1.25rem;">
                    <h4>2018 — Company Founded</h4>
                    <p>TransGo resmi berdiri dan memulai rute pertama antar kota.</p>
                </div>

                <div style="margin-bottom:1.25rem;">
                    <h4>2019 — Best Startup Award</h4>
                    <p>Menerima penghargaan Best Startup untuk inovasi layanan transportasi.</p>
                </div>

                <div style="margin-bottom:1.25rem;">
                    <h4>2021 — Expansion</h4>
                    <p>Perluasan rute dan layanan, peningkatan armada, dan integrasi booking online.</p>
                </div>

                <div style="margin-bottom:1.25rem;">
                    <h4>2023 — Golden Tech Excellence Award</h4>
                    <p>Penghargaan atas pencapaian kualitas layanan dan pengalaman pengguna.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Achievements cards -->
    <section style="margin-top:3rem; margin-bottom:3rem; background:rgba(116, 185, 255,1.0); padding:0.5rem 1rem; border-radius:10px;" data-aos="fade-up">
        <h2 data-aos="fade-right">Achievements</h2>
        <div style="display:flex; gap:1rem; flex-wrap:wrap; margin-top:1rem;">
            <div class="card" data-aos="zoom-in" style="flex:1 1 280px; padding:1rem;">
                <h4>🏆 Best Startup Award 2019</h4>
                <p>Pengakuan atas ide dan implementasi produk awal.</p>
            </div>
            <div class="card" data-aos="zoom-in" data-aos-delay="150" style="flex:1 1 280px; padding:1rem;">
                <h4>🏅 Innovation Trophy 2021</h4>
                <p>Penghargaan atas inovasi layanan digital dalam pemesanan tiket.</p>
            </div>
            <div class="card" data-aos="zoom-in" data-aos-delay="300" style="flex:1 1 280px; padding:1rem;">
                <h4>🥇 Tech Excellence 2023</h4>
                <p>Penghargaan global untuk kualitas produk dan tim engineering.</p>
            </div>
        </div>
    </section>
</div>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
AOS.init({ duration: 900, once: true });
</script>

<?php
closeDBConnection($conn);
include __DIR__ . '/../includes/footer.php';
