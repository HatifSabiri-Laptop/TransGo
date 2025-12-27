<?php
$page_title = 'Online Check-in';
require_once '../config/config.php';

$conn = getDBConnection();
$error = '';
$success = '';
$booking = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_booking'])) {
        $booking_code = clean_input($_POST['booking_code']);
        $email = clean_input($_POST['email']);

        if (empty($booking_code) || empty($email)) {
            $error = 'Kode booking dan email harus diisi!';
        } else {
            $stmt = $conn->prepare("SELECT r.*, s.service_name, s.route, s.departure_time, s.arrival_time, u.full_name 
                FROM reservations r 
                JOIN services s ON r.service_id = s.id 
                JOIN users u ON r.user_id = u.id 
                WHERE r.booking_code = ? AND r.contact_email = ?");
            $stmt->bind_param("ss", $booking_code, $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $booking = $result->fetch_assoc();

                // Check if already checked in
                if ($booking['checked_in']) {
                    $error = 'Booking ini sudah melakukan check-in!';
                    $booking = null;
                } elseif ($booking['booking_status'] !== 'confirmed') {
                    $error = 'Booking ini tidak aktif atau sudah dibatalkan!';
                    $booking = null;
                } elseif (strtotime($booking['travel_date']) < strtotime(date('Y-m-d'))) {
                    $error = 'Tanggal perjalanan sudah lewat!';
                    $booking = null;
                }
            } else {
                $error = 'Kode booking atau email tidak valid!';
            }
            $stmt->close();
        }
    } elseif (isset($_POST['confirm_checkin'])) {
        $booking_code = clean_input($_POST['booking_code']);

        $stmt = $conn->prepare("UPDATE reservations SET checked_in = 1 WHERE booking_code = ?");
        $stmt->bind_param("s", $booking_code);

        if ($stmt->execute()) {
            if (is_logged_in()) {
                log_activity($conn, $_SESSION['user_id'], 'check_in', "Checked in: $booking_code");
            }
            $success = 'Check-in berhasil! E-ticket Anda telah dikonfirmasi.';
        } else {
            $error = 'Gagal melakukan check-in. Silakan coba lagi.';
        }
        $stmt->close();
    }
}

include '../includes/header.php';
?>

<style>
    .goto-tickets-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        background: var(--secondary);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .goto-tickets-btn:hover {
        background: var(--primary);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        color: white;
    }

    .goto-tickets-btn i {
        font-size: 1.1rem;
    }

    @media (max-width: 768px) {
        .goto-tickets-btn {
            padding: 0.65rem 1.25rem;
            font-size: 0.9rem;
        }
    }
</style>

<section style="padding: 2rem 0; background: var(--light);">
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1><i class="fas fa-plane-departure"></i> Online Check-in</h1>
                <p style="color: var(--gray); margin-top: 0.5rem;">Lakukan check-in untuk perjalanan Anda</p>
            </div>
            <?php if (is_logged_in()): ?>
                <a href="<?php echo SITE_URL; ?>/user/tickets.php" class="goto-tickets-btn">
                    <i class="fas fa-ticket-alt"></i>
                    <span>Lihat Tiket Saya</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<section style="padding: 2rem 0;">
    <div class="container">
        <div style="max-width: 600px; margin: 0 auto;">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <?php if (is_logged_in()): ?>
                        <div style="margin-top: 1rem;">
                            <a href="<?php echo SITE_URL; ?>/user/tickets.php" class="btn btn-secondary">
                                <i class="fas fa-ticket-alt"></i> Lihat Tiket Aktif
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!$booking && !$success): ?>
                <!-- Verification Form -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Verifikasi Booking</h3>
                    </div>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="booking_code">Kode Booking *</label>
                            <input type="text" name="booking_code" id="booking_code" class="form-control"
                                placeholder="Contoh: TRN20241130XXXX" required>
                            <small style="color: var(--gray);">Masukkan kode booking yang Anda terima saat reservasi</small>
                        </div>

                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" name="email" id="email" class="form-control"
                                placeholder="email@example.com" required>
                            <small style="color: var(--gray);">Email yang digunakan saat reservasi</small>
                        </div>

                        <button type="submit" name="verify_booking" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-search"></i> Cari Booking
                        </button>
                    </form>
                </div>

                <div class="card" style="margin-top: 1rem; background: var(--light);">
                    <h4><i class="fas fa-info-circle"></i> Informasi Check-in</h4>
                    <ul style="margin-top: 1rem; padding-left: 1.5rem; color: var(--gray);">
                        <li>Check-in dapat dilakukan maksimal 24 jam sebelum keberangkatan</li>
                        <li>Pastikan kode booking dan email yang Anda masukkan sesuai</li>
                        <li>Setelah check-in, simpan e-ticket untuk ditunjukkan saat keberangkatan</li>
                        <li>Datang minimal 30 menit sebelum waktu keberangkatan</li>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($booking): ?>
                <!-- Booking Details & Check-in Confirmation -->
                <div class="card">
                    <div class="card-header" style="background: var(--secondary); color: white; padding:1.2rem">
                        <h3 class="card-title"><i class="fas fa-ticket-alt"></i> Detail Booking</h3>
                    </div>

                    <div style="padding: 1.5rem; background: var(--light); border-radius: 8px; margin-bottom: 1.5rem;">
                        <div style="text-align: center; margin-bottom: 1rem;">
                            <h2 style="color: var(--primary); margin-bottom: 0.5rem;"><?php echo $booking['booking_code']; ?></h2>
                            <span class="badge badge-success" style="font-size: 1rem;">AKTIF</span>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                        <div>
                            <strong>Layanan:</strong>
                            <p style="color: var(--gray); margin-top: 0.25rem;"><?php echo $booking['service_name']; ?></p>
                        </div>
                        <div>
                            <strong>Rute:</strong>
                            <p style="color: var(--gray); margin-top: 0.25rem;"><?php echo $booking['route']; ?></p>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                        <div>
                            <strong>Keberangkatan:</strong>
                            <p style="color: var(--gray); margin-top: 0.25rem;"><?php echo date('H:i', strtotime($booking['departure_time'])); ?></p>
                        </div>
                        <div>
                            <strong>Tiba:</strong>
                            <p style="color: var(--gray); margin-top: 0.25rem;"><?php echo date('H:i', strtotime($booking['arrival_time'])); ?></p>
                        </div>
                    </div>

                    <div style="margin-bottom: 1.5rem;">
                        <strong>Tanggal Perjalanan:</strong>
                        <p style="color: var(--gray); margin-top: 0.25rem; font-size: 1.1rem;">
                            <?php echo format_date($booking['travel_date']); ?>
                        </p>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                        <div>
                            <strong>Nama Penumpang:</strong>
                            <p style="color: var(--gray); margin-top: 0.25rem;"><?php echo $booking['contact_name']; ?></p>
                        </div>
                        <div>
                            <strong>Jumlah:</strong>
                            <p style="color: var(--gray); margin-top: 0.25rem;"><?php echo $booking['num_passengers']; ?> orang</p>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                        <div>
                            <strong>Telepon:</strong>
                            <p style="color: var(--gray); margin-top: 0.25rem;"><?php echo $booking['contact_phone']; ?></p>
                        </div>
                        <div>
                            <strong>Email:</strong>
                            <p style="color: var(--gray); margin-top: 0.25rem;"><?php echo $booking['contact_email']; ?></p>
                        </div>
                    </div>

                    <div style="background: var(--light); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <strong style="font-size: 1.1rem;">Total Bayar:</strong>
                            <strong style="font-size: 1.5rem; color: var(--primary);">
                                <?php echo format_currency($booking['total_price']); ?>
                            </strong>
                        </div>
                        <span class="badge badge-success" style="margin-top: 0.5rem;">LUNAS</span>
                    </div>

                    <form method="POST" action="">
                        <input type="hidden" name="booking_code" value="<?php echo $booking['booking_code']; ?>">
                        <button type="submit" name="confirm_checkin" class="btn btn-secondary" style="width: 100%;">
                            <i class="fas fa-check-circle"></i> Konfirmasi Check-in
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php
if ($conn instanceof mysqli) {
    $conn->close();
}

include '../includes/footer.php';
?>