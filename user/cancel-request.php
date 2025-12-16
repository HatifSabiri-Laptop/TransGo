<?php
$page_title = 'Permintaan Pembatalan';
require_once '../config/config.php';
require_login();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get active bookings
$active_bookings = $conn->query("SELECT r.*, s.service_name, s.route 
    FROM reservations r 
    JOIN services s ON r.service_id = s.id 
    WHERE r.user_id = $user_id 
    AND r.booking_status = 'confirmed' 
    AND r.travel_date >= CURDATE()
    AND r.id NOT IN (SELECT reservation_id FROM cancellation_requests WHERE user_id = $user_id AND status = 'pending')
    ORDER BY r.travel_date ASC");

// Get cancellation history
$cancellation_history = $conn->query("
    SELECT cr.*, r.booking_code, s.service_name, s.route, r.total_price
    FROM cancellation_requests cr
    JOIN reservations r ON cr.reservation_id = r.id
    JOIN services s ON r.service_id = s.id
    WHERE cr.user_id = $user_id
    ORDER BY cr.created_at DESC
");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reservation_id = intval($_POST['reservation_id']);
    $reason = clean_input($_POST['reason']);

    if (empty($reservation_id) || empty($reason)) {
        $error = 'Pilih booking dan berikan alasan pembatalan!';
    } elseif (strlen($reason) < 10) {
        $error = 'Alasan pembatalan minimal 10 karakter!';
    } else {

        // Check ownership and status
        $check = $conn->prepare("
            SELECT id 
            FROM reservations 
            WHERE id = ? 
            AND user_id = ? 
            AND booking_status = 'confirmed'
        ");
        $check->bind_param("ii", $reservation_id, $user_id);
        $check->execute();

        if ($check->get_result()->num_rows === 0) {
            $error = 'Booking tidak valid atau sudah dibatalkan!';
        } else {

            // Check if already cancelled
            $check_cancel = $conn->prepare("
                SELECT id 
                FROM cancellation_requests 
                WHERE reservation_id = ? 
                AND status IN ('pending', 'approved')
            ");
            $check_cancel->bind_param("i", $reservation_id);
            $check_cancel->execute();

            if ($check_cancel->get_result()->num_rows > 0) {
                $error = 'Booking ini sudah dibatalkan sebelumnya!';
            } else {

                // Insert instant cancellation
                $stmt = $conn->prepare("
                    INSERT INTO cancellation_requests 
                    (reservation_id, user_id, reason, status, processed_at) 
                    VALUES (?, ?, ?, 'approved', NOW())
                ");
                $stmt->bind_param("iis", $reservation_id, $user_id, $reason);

                if ($stmt->execute()) {

                    // Update booking status
                    $update = $conn->prepare("
                        UPDATE reservations 
                        SET booking_status = 'cancelled' 
                        WHERE id = ?
                    ");
                    $update->bind_param("i", $reservation_id);
                    $update->execute();
                    $update->close();

                    log_activity($conn, $user_id, 'cancel_request', "Cancelled reservation ID instantly: $reservation_id");

                    $success = 'Booking berhasil dibatalkan!';
                } else {
                    $error = 'Gagal membatalkan booking. Silakan coba lagi.';
                }

                $stmt->close();
            }

            $check_cancel->close();
        }

        $check->close();
    }

    // Refresh data
    $active_bookings = $conn->query("
        SELECT r.*, s.service_name, s.route 
        FROM reservations r 
        JOIN services s ON r.service_id = s.id 
        WHERE r.user_id = $user_id 
        AND r.booking_status = 'confirmed' 
        AND r.travel_date >= CURDATE()
        ORDER BY r.travel_date ASC
    ");

    $cancellation_history = $conn->query("
        SELECT cr.*, r.booking_code, s.service_name, s.route, r.total_price
        FROM cancellation_requests cr
        JOIN reservations r ON cr.reservation_id = r.id
        JOIN services s ON r.service_id = s.id
        WHERE cr.user_id = $user_id
        ORDER BY cr.created_at DESC
    ");
}


include '../includes/header.php';
?>

<section style="padding: 2rem 0; background: var(--light);">
    <div class="container">
        <h1>Permintaan Pembatalan</h1>
        <p style="color: var(--gray);">Ajukan pembatalan untuk booking Anda</p>
    </div>
</section>

<section style="padding: 2rem 0;">
    <div class="container">
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
            <!-- Cancellation Form -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Form Pembatalan</h3>
                    </div>

                    <?php if ($active_bookings->num_rows > 0): ?>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="reservation_id">Pilih Booking *</label>
                                <select name="reservation_id" id="reservation_id" class="form-control" required>
                                    <option value="">-- Pilih Booking --</option>
                                    <?php while ($booking = $active_bookings->fetch_assoc()): ?>
                                        <option value="<?php echo $booking['id']; ?>">
                                            <?php echo $booking['booking_code']; ?> -
                                            <?php echo $booking['service_name']; ?>
                                            (<?php echo format_date($booking['travel_date']); ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="reason">Alasan Pembatalan *</label>
                                <textarea name="reason" id="reason" class="form-control" rows="5"
                                    placeholder="Jelaskan alasan Anda membatalkan booking ini..." required></textarea>
                                <small style="color: var(--gray);">Minimal 10 karakter</small>
                            </div>

                            <button type="submit" class="btn btn-danger" style="width: 100%;">
                                <i class="fas fa-ban"></i> Ajukan Pembatalan
                            </button>
                        </form>
                    <?php else: ?>
                        <p style="text-align: center; padding: 2rem; color: var(--gray);">
                            Tidak ada booking aktif yang dapat dibatalkan.
                        </p>
                    <?php endif; ?>
                </div>

                <div class="card" style="margin-top: 1rem; background: #fef3c7; border: 2px solid var(--accent);">
                    <h4 style="color: #92400e;"><i class="fas fa-exclamation-triangle"></i> Kebijakan Pembatalan</h4>
                    <ul style="margin-top: 1rem; padding-left: 1.5rem; color: #92400e;">
                        <li>Pembatalan akan ditinjau oleh admin dalam 1-2 hari kerja</li>
                        <li>Pengembalian dana dilakukan jika pembatalan disetujui</li>
                        <li>Pembatalan kurang dari 24 jam sebelum keberangkatan mungkin tidak disetujui</li>
                        <li>Biaya administrasi mungkin berlaku</li>
                    </ul>
                </div>
            </div>

            <!-- Cancellation History -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Riwayat Pembatalan</h3>
                    </div>

                    <?php if ($cancellation_history->num_rows > 0): ?>
                        <div style="max-height: 600px; overflow-y: auto;">
                            <?php while ($cancel = $cancellation_history->fetch_assoc()): ?>
                                <div style="border-bottom: 1px solid var(--light); padding: 1.5rem 0;">
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                                        <strong><?php echo $cancel['booking_code']; ?></strong>
                                        <?php
                                        $badge_class = $cancel['status'] === 'pending' ? 'warning' : ($cancel['status'] === 'approved' ? 'success' : 'danger');
                                        ?>
                                        <span class="badge badge-<?php echo $badge_class; ?>">
                                            <?php echo ucfirst($cancel['status']); ?>
                                        </span>
                                    </div>

                                    <p style="color: var(--gray); font-size: 0.9rem; margin-bottom: 0.5rem;">
                                        <?php echo $cancel['service_name']; ?> - <?php echo $cancel['route']; ?>
                                    </p>

                                    <p style="color: var(--gray); font-size: 0.9rem; margin-bottom: 0.5rem;">
                                        <strong>Alasan:</strong> <?php echo htmlspecialchars($cancel['reason']); ?>
                                    </p>

                                    <?php if ($cancel['admin_notes']): ?>
                                        <p style="color: var(--gray); font-size: 0.9rem; margin-bottom: 0.5rem;">
                                            <strong>Catatan Admin:</strong> <?php echo htmlspecialchars($cancel['admin_notes']); ?>
                                        </p>
                                    <?php endif; ?>

                                    <div style="display: flex; justify-content: space-between; margin-top: 0.5rem;">
                                        <small style="color: var(--gray);">
                                            Diajukan: <?php echo format_datetime($cancel['created_at']); ?>
                                        </small>
                                        <?php if ($cancel['processed_at']): ?>
                                            <small style="color: var(--gray);">
                                                Diproses: <?php echo format_datetime($cancel['processed_at']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; padding: 2rem; color: var(--gray);">
                            Belum ada riwayat pembatalan.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
closeDBConnection($conn);
include '../includes/footer.php';
?>