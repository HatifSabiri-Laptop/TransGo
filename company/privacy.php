<?php
// company/privacy.php
require_once __DIR__ . '/../config/config.php';
include __DIR__ . '/../includes/header.php';
?>
<section class="container" style="padding:6rem 3rem; margin: 2rem auto; background:rgba(141, 224, 224, 1); align-items:center; max-width:1500px;" data-aos="fade-up">
    <h1>Privacy Policy</h1>
    <p style="color:var(--gray);">Kami menghargai privasi Anda. Kebijakan ini menjelaskan jenis data yang kami kumpulkan dan bagaimana kami menggunakannya.</p>

    <h4 style="margin-top:1rem;">Informasi yang kami kumpulkan</h4>
    <ul>
        <li>Data pendaftaran (nama, email, nomor telepon).</li>
        <li>Riwayat pemesanan dan data perjalanan.</li>
        <li>Log penggunaan dan data analitik anonim.</li>
    </ul>

    <h4 style="margin-top:1rem;">Bagaimana kami menggunakan data</h4>
    <ul>
        <li>Menyediakan dan mengoperasikan layanan pemesanan.</li>
        <li>Mengirim notifikasi terkait pemesanan (email/sms).</li>
        <li>Analitik internal untuk meningkatkan layanan.</li>
    </ul>

    <p style="margin-top:1rem;">Untuk permintaan khusus terkait data pribadi Anda (akses, koreksi, penghapusan), silakan hubungi <?php echo ADMIN_EMAIL; ?>.</p>
</section>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
AOS.init({ duration: 700, once: true });
</script>

<?php
include __DIR__ . '/../includes/footer.php';
