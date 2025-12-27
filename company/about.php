<?php
// company/about.php
require_once __DIR__ . '/../config/config.php';
include __DIR__ . '/../includes/header.php';
?>
<section class="container" style="padding:6rem 1rem; margin: 2rem auto; background:rgba(141, 224, 224, 1); align-items:center; max-width:1500px;" data-aos="fade-up">
    <h1>About Us</h1>
    <p style="color:var(--gray);">TransGo adalah tim yang berdedikasi menghadirkan solusi transportasi modern. Kami percaya pada teknologi yang memudahkan hidup orang.</p>

    <div style="display:flex; gap:2rem; flex-wrap:wrap; margin-top:2rem;">
        <div style="flex:1 1 320px;" data-aos="fade-right">
            <div class="card p-3">
                <h4>Our Mission</h4>
                <p>Menyediakan akses transportasi aman, terjangkau, dan nyaman untuk semua orang.</p>
            </div>
        </div>

        <div style="flex:1 1 320px;" data-aos="fade-up">
            <div class="card p-3">
                <h4>Our Vision</h4>
                <p>Menjadi platform transportasi pilihan dengan pengalaman pengguna terbaik.</p>
            </div>
        </div>

        <div style="flex:1 1 320px;" data-aos="fade-left">
            <div class="card p-3">
                <h4>Our Values</h4>
                <ul>
                    <li>Customer-first</li>
                    <li>Integrity</li>
                    <li>Innovation</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
AOS.init({ duration: 800, once: true });
</script>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
include __DIR__ . '/../includes/footer.php';
