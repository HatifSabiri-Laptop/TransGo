<?php
// company/article.php
require_once __DIR__ . '/../config/config.php';
include __DIR__ . '/../includes/header.php';
$conn = getDBConnection();

// If you want dynamic articles later you can accept ?id= or ?slug=
// For now we'll show a static achievements overview for modern UI.
?>
<section style="background: linear-gradient(135deg, rgba(16,185,129,0.85), rgba(98, 140, 231, 0.75)); padding:3rem 0;">
    <div class="container" data-aos="fade-up">
        <h1>Achievements & Awards</h1>
        <p style="color:var(--black); font-weight:600;">Pemenuhan kualitas dan dedikasi dari tim TransGo.</p>

        <article style="margin-top:1.5rem;" class="card p-4">
            <h2>🏆 Best Startup Award 2019</h2>
            <p>Deskripsi singkat tentang penghargaan ini, latar belakang acara, dan kenapa TransGo memenangkan penghargaan tersebut.</p>

            <hr>

            <h2>🏅 Innovation Trophy 2021</h2>
            <p>Penjelasan kontribusi teknologi dan fitur layanan yang mendapat pengakuan.</p>

            <hr>

            <h2>🥇 Tech Excellence Award 2023</h2>
            <p>Detail tentang capaian teknis, jumlah pengguna, dan dampak terhadap industri.</p>
        </article>
    </div>
</section>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({
        duration: 800,
        once: true
    });
</script>

<?php
closeDBConnection($conn);
include __DIR__ . '/../includes/footer.php';
