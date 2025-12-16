<?php
$page_title = 'Profil';
require_once '../config/config.php';
require_login();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = clean_input($_POST['full_name']);
        $phone = clean_input($_POST['phone']);
        $email = clean_input($_POST['email']);

        if (empty($full_name) || empty($phone) || empty($email)) {
            $error = 'Semua field harus diisi!';
        } else {
            // Check if email is already taken by another user
            $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->bind_param("si", $email, $user_id);
            $check->execute();

            if ($check->get_result()->num_rows > 0) {
                $error = 'Email sudah digunakan oleh pengguna lain!';
            } else {
                $update = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, email = ? WHERE id = ?");
                $update->bind_param("sssi", $full_name, $phone, $email, $user_id);

                if ($update->execute()) {
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['email'] = $email;
                    log_activity($conn, $user_id, 'update_profile', 'Updated profile information');
                    $success = 'Profil berhasil diperbarui!';

                    // Refresh user data
                    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                } else {
                    $error = 'Gagal memperbarui profil!';
                }
                $update->close();
            }
            $check->close();
        }
    } elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Semua field password harus diisi!';
        } elseif (!password_verify($current_password, $user['password'])) {
            $error = 'Password lama tidak sesuai!';
        } elseif (strlen($new_password) < 6) {
            $error = 'Password baru minimal 6 karakter!';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Password baru tidak cocok!';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->bind_param("si", $hashed_password, $user_id);

            if ($update->execute()) {
                log_activity($conn, $user_id, 'change_password', 'Changed password');
                $success = 'Password berhasil diubah!';
            } else {
                $error = 'Gagal mengubah password!';
            }
            $update->close();
        }
    }
}

// Get booking history
$bookings = $conn->query("SELECT r.*, s.service_name, s.route 
    FROM reservations r 
    JOIN services s ON r.service_id = s.id 
    WHERE r.user_id = $user_id 
    ORDER BY r.created_at DESC");

include '../includes/header.php';
?>

<style>
    .tab-navigation {
        background: white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        margin-bottom: 2rem;
    }

    .tab-navigation .container {
        display: flex;
        gap: 0;
    }

    .tab-navigation {
        background: white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        margin-bottom: 2rem;
    }

    .tab-navigation .container {
        display: flex;
        gap: 0;
    }

    .tab-btn {
        flex: 1;
        padding: 1.25rem 2rem;
        background: transparent;
        border: none;
        border-bottom: 3px solid transparent;
        color: var(--gray);
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .tab-btn:hover {
        background: var(--light);
        color: var(--primary);
    }

    .tab-btn.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
        background: var(--light);
    }

    .tab-btn i {
        font-size: 1.2rem;
    }

    /* Desktop table container */
    .table-responsive-container {
        width: 100%;
        overflow-x: auto;
        margin-bottom: 1rem;
        -webkit-overflow-scrolling: touch;
    }

    /* Table styles with optimized column widths */
    .table {
        min-width: 1100px;
        /* Increased from 900px to accommodate all columns */
        width: 100%;
        border-collapse: collapse;
        margin-top: 1rem;
        table-layout: auto;
        /* Allow columns to size based on content */
    }

    .table thead {
        background-color: var(--light);
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .table th {
        text-align: left;
        padding: 1rem 0.75rem;
        font-weight: 600;
        color: var(--dark);
        border-bottom: 2px solid #dee2e6;
        white-space: nowrap;
    }

    .table td {
        padding: 1rem 0.75rem;
        border-bottom: 1px solid #dee2e6;
        vertical-align: middle;
    }

    /* Optimized column widths */
    .table th:nth-child(1),
    .table td:nth-child(1) {
        /* Kode */
        min-width: 120px;
        max-width: 150px;
    }

    .table th:nth-child(2),
    .table td:nth-child(2) {
        /* Layanan */
        min-width: 140px;
        max-width: 180px;
    }

    .table th:nth-child(3),
    .table td:nth-child(3) {
        /* Rute */
        min-width: 180px;
        max-width: 220px;
        word-wrap: break-word;
    }

    .table th:nth-child(4),
    .table td:nth-child(4) {
        /* Tanggal */
        min-width: 100px;
        max-width: 120px;
        white-space: nowrap;
    }

    .table th:nth-child(5),
    .table td:nth-child(5) {
        /* Penumpang */
        min-width: 80px;
        max-width: 100px;
        text-align: center;
    }

    .table th:nth-child(6),
    .table td:nth-child(6) {
        /* Total */
        min-width: 120px;
        max-width: 140px;
        white-space: nowrap;
    }

    .table th:nth-child(7),
    .table td:nth-child(7) {
        /* Status Pembayaran */
        min-width: 140px;
        max-width: 160px;
    }

    .table th:nth-child(8),
    .table td:nth-child(8) {
        /* Status Booking */
        min-width: 130px;
        max-width: 150px;
    }

    .table th:nth-child(9),
    .table td:nth-child(9) {
        /* Tanggal Pesan - FIXED */
        min-width: 180px;
        /* Increased for date-time */
        max-width: 200px;
        white-space: nowrap;
    }

    .table tbody tr:hover {
        background-color: rgba(0, 0, 0, 0.02);
    }

    /* Mobile booking item styles */
    .mobile-booking-item {
        display: none;
        padding: 1rem;
        border-bottom: 1px solid #dee2e6;
    }

    .mobile-booking-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 0.5rem;
    }

    .mobile-booking-code {
        font-weight: bold;
        color: var(--primary);
        font-size: 1.1rem;
    }

    .mobile-booking-service {
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 0.25rem;
    }

    .mobile-booking-route {
        color: var(--gray);
        font-size: 0.9rem;
        margin-bottom: 0.5rem;
    }

    .mobile-booking-status {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
    }

    .mobile-booking-details-btn {
        background: var(--primary);
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.875rem;
        transition: background-color 0.3s;
        width: 100%;
        margin-top: 0.5rem;
    }

    .mobile-booking-details-btn:hover {
        background: var(--secondary);
    }

    /* Modal styles */
    .booking-details-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    .booking-details-modal.active {
        display: flex;
    }

    .modal-content {
        background: white;
        border-radius: 12px;
        max-width: 500px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
    }

    .modal-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--light);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--gray);
    }

    .detail-item {
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #f0f0f0;
    }

    .detail-item:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .detail-label {
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 0.25rem;
        font-size: 0.9rem;
    }

    .detail-value {
        color: var(--gray);
    }

    .badge {
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        display: inline-block;
        min-width: 80px;
        text-align: center;
    }

    .badge-success {
        background: #d1fae5;
        color: #065f46;
    }

    .badge-warning {
        background: #fef3c7;
        color: #92400e;
    }

    .badge-danger {
        background: #fee2e2;
        color: #991b1b;
    }

    /* Scrollbar styling */
    .table-responsive-container::-webkit-scrollbar {
        height: 8px;
    }

    .table-responsive-container::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    .table-responsive-container::-webkit-scrollbar-thumb {
        background: var(--primary);
        border-radius: 4px;
    }

    .table-responsive-container::-webkit-scrollbar-thumb:hover {
        background: var(--secondary);
    }

    /* Hide desktop table on mobile, show mobile view */
    @media (max-width: 768px) {
        .table-responsive-container {
            display: none;
        }

        .mobile-booking-item {
            display: block;
        }

        .tab-btn {
            padding: 1rem;
            font-size: 0.9rem;
        }

        .tab-btn span {
            display: inline;
        }

        .tab-btn i {
            font-size: 1rem;
            margin-right: 0.5rem;
        }

        /* Adjust profile layout for mobile */
        .container>div:first-child {
            grid-template-columns: 1fr !important;
        }
    }

    /* Show desktop table on desktop, hide mobile view */
    @media (min-width: 769px) {
        .mobile-booking-item {
            display: none;
        }

        .table-responsive-container {
            display: block;
        }

        /* Make table more compact on medium desktop screens */
        @media (max-width: 1200px) {

            .table th,
            .table td {
                padding: 0.75rem 0.5rem;
                font-size: 0.9rem;
            }

            .table {
                min-width: 1050px;
            }

            /* Adjust column widths for medium screens */
            .table th:nth-child(9),
            .table td:nth-child(9) {
                min-width: 160px;
                max-width: 180px;
            }
        }

        /* Force scroll on smaller desktop screens */
        @media (max-width: 1024px) {
            .table-responsive-container {
                border: 1px solid #eee;
                border-radius: 8px;
                padding: 0;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            }

            .table {
                min-width: 1100px;
            }
        }
    }

    /* Ticket card styles */
    .ticket-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
    }

    @media (max-width: 480px) {
        .tab-btn span {
            font-size: 0.8rem;
        }

        .tab-btn {
            padding: 0.75rem 0.5rem;
        }

        .mobile-booking-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .mobile-booking-code {
            font-size: 1rem;
        }
    }
</style>

<section style="padding: 2rem 0; background: var(--light);">
    <div class="container">
        <h1>Profil Pengguna</h1>
        <p style="color: var(--gray);">Kelola informasi akun Anda</p>
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
            <!-- Profile Information -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Informasi Profil</h3>
                    </div>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="full_name">Nama Lengkap</label>
                            <input type="text" name="full_name" id="full_name" class="form-control"
                                value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" name="email" id="email" class="form-control"
                                value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="phone">Nomor Telepon</label>
                            <input type="tel" name="phone" id="phone" class="form-control"
                                value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Role</label>
                            <input type="text" class="form-control" value="<?php echo ucfirst($user['role']); ?>" disabled>
                        </div>

                        <div class="form-group">
                            <label>Tanggal Bergabung</label>
                            <input type="text" class="form-control" value="<?php echo format_datetime($user['created_at']); ?>" disabled>
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Perubahan
                        </button>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Ubah Password</h3>
                    </div>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="current_password">Password Lama</label>
                            <input type="password" name="current_password" id="current_password"
                                class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">Password Baru</label>
                            <input type="password" name="new_password" id="new_password"
                                class="form-control" required>
                            <small style="color: var(--gray);">Minimal 6 karakter</small>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Konfirmasi Password Baru</label>
                            <input type="password" name="confirm_password" id="confirm_password"
                                class="form-control" required>
                        </div>

                        <button type="submit" name="change_password" class="btn btn-secondary">
                            <i class="fas fa-key"></i> Ubah Password
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Booking History -->
        <div class="card" style="margin-top: 2rem;">
            <div class="card-header">
                <h3 class="card-title">Riwayat Pemesanan</h3>
            </div>

            <?php if ($bookings->num_rows > 0):
                // Store bookings in array for JavaScript use
                $bookings_array = [];
                $booking_index = 0;
            ?>

                <!-- Desktop Table View -->
                <div class="table-responsive-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Layanan</th>
                                <th>Rute</th>
                                <th>Tanggal</th>
                                <th>Penumpang</th>
                                <th>Total</th>
                                <th>Status Pembayaran</th>
                                <th>Status Booking</th>
                                <th>Tanggal Pesan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($booking = $bookings->fetch_assoc()):
                                $bookings_array[] = $booking;
                            ?>
                                <tr>
                                    <td><strong><?php echo $booking['booking_code']; ?></strong></td>
                                    <td><?php echo $booking['service_name']; ?></td>
                                    <td><?php echo $booking['route']; ?></td>
                                    <td><?php echo format_date($booking['travel_date']); ?></td>
                                    <td><?php echo $booking['num_passengers']; ?></td>
                                    <td><?php echo format_currency($booking['total_price']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $booking['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($booking['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $booking['booking_status'] === 'confirmed' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($booking['booking_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo format_datetime($booking['created_at']); ?></td>
                                </tr>
                            <?php
                                $booking_index++;
                            endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <?php
                $booking_index = 0;
                foreach ($bookings_array as $booking):
                ?>
                    <div class="mobile-booking-item" data-index="<?php echo $booking_index; ?>">
                        <div class="mobile-booking-header">
                            <div>
                                <div class="mobile-booking-code"><?php echo $booking['booking_code']; ?></div>
                                <div class="mobile-booking-status">
                                    <span class="badge badge-<?php echo $booking['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($booking['payment_status']); ?>
                                    </span>
                                    <span class="badge badge-<?php echo $booking['booking_status'] === 'confirmed' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($booking['booking_status']); ?>
                                    </span>
                                </div>
                            </div>
                            <div>
                                <strong><?php echo format_currency($booking['total_price']); ?></strong>
                            </div>
                        </div>
                        <div class="mobile-booking-service"><?php echo $booking['service_name']; ?></div>
                        <div class="mobile-booking-route"><?php echo $booking['route']; ?></div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.9rem; color: var(--gray); margin-bottom: 0.5rem;">
                            <span><?php echo format_date($booking['travel_date']); ?></span>
                            <span><?php echo $booking['num_passengers']; ?> penumpang</span>
                        </div>
                        <button class="mobile-booking-details-btn" onclick="showBookingDetails(<?php echo $booking_index; ?>)">
                            <i class="fas fa-info-circle"></i> Lihat Detail Lengkap
                        </button>
                    </div>
                <?php
                    $booking_index++;
                endforeach;
                ?>

            <?php else: ?>
                <p style="text-align: center; padding: 2rem; color: var(--gray);">
                    Belum ada riwayat pemesanan.
                </p>
            <?php endif; ?>
        </div>

        <!-- My Tickets Section -->
        <div class="card" style="margin-top: 2rem;">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-ticket-alt"></i> Tiket Saya</h3>
            </div>

            <?php
            // Get paid bookings (tickets)
            $tickets = $conn->query("SELECT r.*, s.service_name, s.route, s.departure_time, s.arrival_time 
                FROM reservations r 
                JOIN services s ON r.service_id = s.id 
                WHERE r.user_id = $user_id AND r.payment_status = 'paid'
                ORDER BY r.travel_date DESC, r.created_at DESC");

            if ($tickets->num_rows > 0):
            ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem; padding: 1.5rem;">
                    <?php while ($ticket = $tickets->fetch_assoc()):
                        $passenger_names = json_decode($ticket['passenger_names'], true);
                        $is_upcoming = strtotime($ticket['travel_date']) >= strtotime(date('Y-m-d'));
                        $is_past = strtotime($ticket['travel_date']) < strtotime(date('Y-m-d'));
                        $is_cancelled = $ticket['booking_status'] === 'cancelled';
                    ?>
                        <div class="ticket-card" style="border: 2px solid <?php echo $is_cancelled ? '#dc2626' : ($is_upcoming ? 'var(--primary)' : 'var(--gray)'); ?>; border-radius: 12px overflow: hidden; background: white; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.3s; <?php echo $is_cancelled ? 'opacity: 0.85;' : ''; ?>">
                            <!-- Ticket Header -->
                            <div style="background: <?php echo $is_cancelled ? '#dc2626' : ($is_upcoming ? 'linear-gradient(135deg, var(--primary), #1d4ed8)' : 'var(--gray)'); ?>; color: white; padding: 1rem; position: relative;">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                                    <div>
                                        <div style="font-size: 0.85rem; opacity: 0.9;">Kode Booking</div>
                                        <div style="font-size: 1.3rem; font-weight: bold; letter-spacing: 1px;">
                                            <?php echo $ticket['booking_code']; ?>
                                        </div>
                                    </div>
                                    <?php if ($is_cancelled): ?>
                                        <span style="background: rgba(255,255,255,0.95); color: #dc2626; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
                                            <i class="fas fa-times-circle"></i> Cancelled
                                        </span>
                                    <?php elseif ($is_upcoming): ?>
                                        <span style="background: rgba(32, 172, 200, 0.2); margin-top:2rem; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.85rem;">
                                            <i class="fas fa-clock"></i> Upcoming
                                        </span>
                                    <?php else: ?>
                                        <span style="background: rgba(255,255,255,0.2); margin-top:2rem; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.85rem;">
                                            <i class="fas fa-history"></i> Past
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($ticket['checked_in'] && !$is_cancelled): ?>
                                    <div style="position: absolute; top: 10px; right: 10px;">
                                        <span style="background: var(--secondary); margin-right: 0.50rem; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem;">
                                            <i class="fas fa-check-circle"></i> Checked In
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Ticket Body -->
                            <div style="padding: 1.5rem; <?php echo $is_cancelled ? 'position: relative;' : ''; ?>">
                                <?php if ($is_cancelled): ?>
                                    <!-- Cancelled Overlay Effect -->
                                    <div style="position: absolute; top: 50%; left: 50%; margin-top:1.6rem; transform: translate(-50%, -50%) rotate(-15deg); font-size: 4rem; font-weight: bold; color: rgba(219, 23, 23, 0.06); pointer-events: none; z-index: 1; white-space: nowrap;">
                                        CANCELLED
                                    </div>
                                <?php endif; ?>

                                <div style="position: relative; z-index: 2;">
                                    <!-- Service Info -->
                                    <div style="margin-bottom: 1.5rem;">
                                        <h4 style="color: <?php echo $is_cancelled ? '#dc2626' : 'var(--primary)'; ?>; margin-bottom: 0.5rem; font-size: 1.1rem; <?php echo $is_cancelled ? 'text-decoration: line-through;' : ''; ?>">
                                            <?php echo $ticket['service_name']; ?>
                                        </h4>
                                        <div style="color: var(--gray); font-size: 0.9rem;">
                                            <i class="fas fa-route"></i> <?php echo $ticket['route']; ?>
                                        </div>
                                    </div>

                                    <!-- Travel Date & Time -->
                                    <div style="background: <?php echo $is_cancelled ? '#fee2e2' : 'var(--light)'; ?>; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                            <div>
                                                <div style="font-size: 0.8rem; color: var(--gray); margin-bottom: 0.25rem;">Tanggal</div>
                                                <div style="font-weight: 600; color: var(--dark);">
                                                    <?php echo format_date($ticket['travel_date']); ?>
                                                </div>
                                            </div>
                                            <div>
                                                <div style="font-size: 0.8rem; color: var(--gray); margin-bottom: 0.25rem;">Waktu</div>
                                                <div style="font-weight: 600; color: var(--dark);">
                                                    <?php echo date('H:i', strtotime($ticket['departure_time'])); ?> - <?php echo date('H:i', strtotime($ticket['arrival_time'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Passengers -->
                                    <div style="margin-bottom: 1rem;">
                                        <div style="font-size: 0.95rem; color: var(--gray); margin-bottom: 0.5rem;">
                                            <i class="fas fa-users"></i> <?php echo count($passenger_names); ?> Penumpang
                                        </div>
                                        <div style="font-size: 0.85rem; color: var(--dark);">
                                            <?php echo htmlspecialchars(implode(', ', array_slice($passenger_names, 0, 5))); ?>
                                            <?php if (count($passenger_names) > 5): ?>
                                                <span style="color: var(--gray);">+<?php echo count($passenger_names) - 5; ?>Lainnya</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Price -->
                                    <div style="border-top: 2px dashed var(--light); padding-top: 1rem; margin-bottom: 1rem;">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span style="color: var(--gray); font-size: 0.9rem;">Total Bayar</span>
                                            <span style="font-size: 1.2rem; font-weight: bold; color: <?php echo $is_cancelled ? '#dc2626' : 'var(--primary)'; ?>; <?php echo $is_cancelled ? 'text-decoration: line-through;' : ''; ?>">
                                                <?php echo format_currency($ticket['total_price']); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Actions or Cancelled Message -->
                                    <?php if ($is_cancelled): ?>
                                        <div style="text-align: center; padding: 1rem; background: #fee2e2; border-radius: 8px; border-left: 4px solid #dc2626;">
                                            <div style="color: #dc2626; font-weight: 600; margin-bottom: 0.5rem;">
                                                <i class="fas fa-ban"></i> Tiket Ini Telah Dibatalkan
                                            </div>
                                            <small style="color: #991b1b; display: block;">
                                                Pembatalan diproses dan dana akan dikembalikan sesuai kebijakan
                                            </small>
                                        </div>
                                    <?php else: ?>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <a href="<?php echo SITE_URL; ?>/user/ticket.php?booking=<?php echo $ticket['booking_code']; ?>"
                                                class="btn btn-primary"
                                                style="flex: 1; text-align: center; font-size: 1rem; padding: 0.6rem;">
                                                <i class="fas fa-ticket-alt"></i> Lihat Tiket
                                            </a>
                                            <?php if ($is_upcoming && !$ticket['checked_in']): ?>
                                                <a href="<?php echo SITE_URL; ?>/user/check-in.php?booking=<?php echo $ticket['booking_code']; ?>"
                                                    class="btn btn-secondary"
                                                    style="flex: 1;text-align: center; font-size: 1rem; padding: 0.6rem;">
                                                    <i class="fas fa-check"></i> Check-in
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

            <?php else: ?>
                <div style="text-align: center; padding: 3rem; color: var(--gray);">
                    <i class="fas fa-ticket-alt" style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                    <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">Belum ada tiket</p>
                    <p style="font-size: 0.9rem;">Tiket Anda akan muncul di sini setelah pembayaran berhasil</p>
                    <a href="<?php echo SITE_URL; ?>/user/reservation.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-plus"></i> Pesan Tiket Sekarang
                    </a>
                </div>
            <?php endif; ?>
        </div>

    </div>
</section>

<!-- Booking Details Modal -->
<div id="bookingDetailsModal" class="booking-details-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Detail Pemesanan</h3>
            <button class="modal-close" onclick="closeBookingDetails()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" id="bookingDetailsContent">
            <!-- Dynamic content will be inserted here -->
        </div>
    </div>
</div>

<script>
    // Booking data for mobile view
    const bookingData = <?php echo json_encode($bookings_array); ?>;

    function showBookingDetails(index) {
        const booking = bookingData[index];
        const passengerNames = booking.passenger_names ? JSON.parse(booking.passenger_names) : [];

        const modalContent = `
            <div class="detail-item">
                <div class="detail-label">Kode Booking</div>
                <div class="detail-value"><strong style="color: var(--primary); font-size: 1.1rem;">${booking.booking_code}</strong></div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Layanan</div>
                <div class="detail-value">${booking.service_name}</div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Rute</div>
                <div class="detail-value">${booking.route}</div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Tanggal Perjalanan</div>
                <div class="detail-value">${formatDate(booking.travel_date)}</div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Jumlah Penumpang</div>
                <div class="detail-value">${booking.num_passengers} orang</div>
            </div>
            
            ${passengerNames.length > 0 ? `
            <div class="detail-item">
                <div class="detail-label">Nama Penumpang</div>
                <div class="detail-value">${passengerNames.join(', ')}</div>
            </div>
            ` : ''}
            
            <div class="detail-item">
                <div class="detail-label">Total Harga</div>
                <div class="detail-value"><strong style="color: var(--primary); font-size: 1.1rem;">${formatCurrency(booking.total_price)}</strong></div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Status Pembayaran</div>
                <div class="detail-value">
                    <span class="badge badge-${booking.payment_status === 'paid' ? 'success' : 'warning'}">
                        ${booking.payment_status.charAt(0).toUpperCase() + booking.payment_status.slice(1)}
                    </span>
                </div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Status Booking</div>
                <div class="detail-value">
                    <span class="badge badge-${booking.booking_status === 'confirmed' ? 'success' : 'danger'}">
                        ${booking.booking_status.charAt(0).toUpperCase() + booking.booking_status.slice(1)}
                    </span>
                </div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Check-in Status</div>
                <div class="detail-value">
                    ${booking.checked_in ? 
                        '<span class="badge badge-success"><i class="fas fa-check-circle"></i> Sudah Check-in</span>' : 
                        '<span class="badge badge-warning"><i class="fas fa-clock"></i> Belum Check-in</span>'}
                </div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Tanggal Pesan</div>
                <div class="detail-value">${formatDateTime(booking.created_at)}</div>
            </div>
            
            ${booking.notes ? `
            <div class="detail-item">
                <div class="detail-label">Catatan</div>
                <div class="detail-value">${booking.notes}</div>
            </div>
            ` : ''}
            
            <div class="detail-item">
                <div class="detail-label">Opsi</div>
                <div class="detail-value" style="margin-top: 0.5rem;">
                    ${booking.payment_status === 'paid' ? `
                    <a href="${SITE_URL}/user/ticket.php?booking=${booking.booking_code}" 
                       class="btn btn-primary" 
                       style="display: block; text-align: center; margin-bottom: 0.5rem;">
                        <i class="fas fa-ticket-alt"></i> Lihat Tiket
                    </a>
                    ` : ''}
                    
                    ${booking.booking_status === 'confirmed' && booking.payment_status === 'paid' && new Date(booking.travel_date) >= new Date() ? `
                    <a href="${SITE_URL}/user/check-in.php?booking=${booking.booking_code}" 
                       class="btn btn-secondary" 
                       style="display: block; text-align: center;">
                        <i class="fas fa-check"></i> Check-in
                    </a>
                    ` : ''}
                </div>
            </div>
        `;

        document.getElementById('bookingDetailsContent').innerHTML = modalContent;
        document.getElementById('bookingDetailsModal').classList.add('active');
    }

    function closeBookingDetails() {
        document.getElementById('bookingDetailsModal').classList.remove('active');
    }

    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', {
            day: '2-digit',
            month: 'long',
            year: 'numeric'
        });
    }

    function formatDateTime(dateTimeString) {
        const date = new Date(dateTimeString);
        return date.toLocaleDateString('id-ID', {
            day: '2-digit',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    // Ensure table container is properly sized
    function adjustTableContainer() {
        const container = document.querySelector('.table-responsive-container');
        const table = document.querySelector('.table');

        if (container && table) {
            // Set container width to parent width
            const parentWidth = container.parentElement.clientWidth;
            container.style.width = parentWidth + 'px';

            // Adjust table width based on content
            const tableWidth = table.scrollWidth;
            table.style.minWidth = Math.max(900, tableWidth) + 'px';
        }
    }

    // Run on load and resize
    window.addEventListener('load', adjustTableContainer);
    window.addEventListener('resize', adjustTableContainer);

    // Run after modal closes (if table might be hidden during modal open)
    function closeBookingDetails() {
        document.getElementById('bookingDetailsModal').classList.remove('active');
        // Recalculate table size after modal closes
        setTimeout(adjustTableContainer, 100);
    }

    function formatCurrency(amount) {
        return 'Rp' + parseInt(amount).toLocaleString('id-ID');
    }

    // Close modal when clicking outside
    document.getElementById('bookingDetailsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeBookingDetails();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeBookingDetails();
        }
    });

    // Define SITE_URL for JavaScript
    const SITE_URL = '<?php echo SITE_URL; ?>';
</script>

<?php
closeDBConnection($conn);
include '../includes/footer.php';
?>