<?php
// company/terms.php
require_once __DIR__ . '/../config/config.php';
include __DIR__ . '/../includes/header.php';
?>
<section class="container" style="padding:6rem 3rem; margin: 2rem auto; background:rgba(141, 224, 224, 1); align-items:center; max-width:1500px;" data-aos="fade-up">
    <h1>Terms & Conditions</h1>
    <p style="color:var(--gray);">Syarat dan ketentuan ini mengatur penggunaan layanan TransGo. Dengan menggunakan layanan kami, Anda menyetujui ketentuan-ketentuan berikut:</p>

    <ol style="margin-top:1rem;">
        <li><strong>Akun:</strong> Pengguna bertanggung jawab menjaga kerahasiaan akun dan password.</li>
        <li><strong>Pemesanan & Pembayaran:</strong> Semua pemesanan dianggap sah setelah pembayaran diverifikasi (sesuai kebijakan kami).</li>
        <li><strong>Kebijakan Pembatalan:</strong> Pembatalan harus diajukan melalui sistem; ada ketentuan pengembalian dana sesuai jenis tiket.</li>
        <li><strong>Perubahan Layanan:</strong> TransGo berhak mengubah rute, harga, atau jadwal sesuai kebutuhan operasional.</li>
        <li><strong>Konten:</strong> Semua konten di website ini milik TransGo dan tidak boleh digunakan tanpa izin.</li>
    </ol>

    <p style="margin-top:1rem;">Jika Anda memiliki pertanyaan seputar syarat ini, silakan hubungi kami melalui halaman Contact.</p>
</section>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
AOS.init({ duration: 700, once: true });
</script>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
include __DIR__ . '/../includes/footer.php';
