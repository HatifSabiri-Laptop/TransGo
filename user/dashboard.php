<?php
$page_title = 'Dashboard';
require_once '../config/config.php';
require_login();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get user statistics
$total_bookings = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE user_id = $user_id")->fetch_assoc()['count'];
$active_bookings = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE user_id = $user_id AND booking_status = 'confirmed' AND travel_date >= CURDATE()")->fetch_assoc()['count'];
$cancelled_bookings = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE user_id = $user_id AND booking_status = 'cancelled'")->fetch_assoc()['count'];

// Get recent bookings
$recent_bookings = $conn->query("SELECT r.*, s.service_name, s.route 
    FROM reservations r 
    JOIN services s ON r.service_id = s.id 
    WHERE r.user_id = $user_id 
    ORDER BY r.created_at DESC 
    LIMIT 5");

// Get pending cancellation requests
$pending_cancellations = $conn->query("SELECT cr.*, r.booking_code 
    FROM cancellation_requests cr 
    JOIN reservations r ON cr.reservation_id = r.id 
    WHERE cr.user_id = $user_id AND cr.status = 'pending' 
    ORDER BY cr.created_at DESC");

include '../includes/header.php';
?>

<style>
    /* Dashboard Specific Styles - Flexible and Responsive */
    .dashboard-section {
        padding: clamp(1rem, 3vw, 2rem) 0;
    }

    /* Stats Grid - Fluid responsive grid */
    .stats-grid {
        display: grid !important;
        grid-template-columns: repeat(auto-fit, minmax(min(280px, 100%), 1fr)) !important;
        gap: clamp(1rem, 2vw, 1.5rem) !important;
        margin-bottom: 2rem !important;
        width: 100% !important;
    }

    .stats-grid .card {
        width: 100% !important;
        min-width: 0 !important;
        padding: clamp(1.25rem, 3vw, 2rem) !important;
        text-align: center;
        border-radius: 12px;
        box-shadow: var(--shadow);
    }

    .stats-grid .card i {
        font-size: clamp(2rem, 5vw, 3rem) !important;
        margin-bottom: clamp(0.75rem, 2vw, 1rem) !important;
        display: block;
    }

    .stats-grid .card h3 {
        font-size: clamp(1.75rem, 4vw, 2.5rem) !important;
        margin-bottom: 0.5rem !important;
        font-weight: 700;
    }

    .stats-grid .card p {
        font-size: clamp(0.875rem, 1.5vw, 1rem) !important;
        color: rgba(255, 255, 255, 0.9);
    }

    /* Quick Actions - Better responsive */
    .quick-actions {
        display: flex !important;
        flex-wrap: wrap !important;
        gap: clamp(0.75rem, 1.5vw, 1rem) !important;
        width: 100% !important;
        margin-bottom: 1.5rem !important;
    }

    .quick-actions a {
        flex: 1 1 auto !important;
        min-width: min(180px, calc(50% - 0.75rem)) !important;
        text-align: center !important;
        padding: clamp(0.75rem, 1.5vw, 0.875rem) clamp(0.5rem, 1vw, 1rem) !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        font-size: clamp(0.875rem, 1.5vw, 1rem) !important;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s;
    }

    /* Table improvements */
    .table-responsive {
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch !important;
        margin: 0 -0.5rem !important;
        padding: 0 0.5rem !important;
    }

    .table {
        width: 100% !important;
        min-width: 600px !important;
        border-collapse: collapse;
        background: var(--white);
        border-radius: 8px;
        overflow: hidden;
        box-shadow: var(--shadow);
    }

    .table th,
    .table td {
        padding: clamp(0.75rem, 1.5vw, 1rem) !important;
        text-align: left;
        border-bottom: 1px solid var(--light);
        white-space: nowrap;
        font-size: clamp(0.875rem, 1.5vw, 1rem);
    }

    .table th {
        background: var(--primary);
        color: var(--white);
        font-weight: 600;
    }

    /* Card styling */
    .dashboard-card {
        background: var(--white);
        border-radius: 12px;
        box-shadow: var(--shadow);
        padding: clamp(1.25rem, 3vw, 1.5rem) !important;
        margin-bottom: clamp(1.5rem, 3vw, 2rem) !important;
        width: 100% !important;
        overflow: hidden !important;
    }

    .dashboard-card h3 {
        font-size: clamp(1.25rem, 2.5vw, 1.5rem) !important;
        margin-bottom: clamp(1rem, 2vw, 1.5rem) !important;
        color: var(--dark);
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .dashboard-section {
            padding: 1rem 0 !important;
        }

        .stats-grid {
            grid-template-columns: 1fr !important;
            gap: 1rem !important;
        }

        .quick-actions {
            flex-direction: column !important;
        }

        .quick-actions a {
            width: 100% !important;
            min-width: 100% !important;
        }

        .table th,
        .table td {
            padding: 0.5rem 0.375rem !important;
        }

    }

    @media (max-width: 576px) {
        .container {
            padding: 0 0.75rem !important;
        }

        .table {
            min-width: 450px !important;
            font-size: 0.85rem !important;
        }
    }

    /* =========================
   MOBILE TABLE FIX (FINAL)
   ========================= */
    @media (max-width: 768px) {

        /* Hide ALL columns first */
        .table th,
        .table td {
            display: none !important;
        }

        /* 1️⃣ Kode Booking */
        .table th:nth-child(1),
        .table td:nth-child(1) {
            display: table-cell !important;
        }

        /* 7️⃣ Status */
        .table th:nth-child(7),
        .table td:nth-child(7) {
            display: table-cell !important;
        }

        /* 9️⃣ Detail button */
        .table th:nth-child(9),
        .table td:nth-child(9) {
            display: table-cell !important;
        }

        /* Layout polish */
        .table {
            width: 100% !important;
            min-width: unset !important;
        }

        .table th,
        .table td {
            text-align: center !important;
            vertical-align: middle !important;
            white-space: nowrap !important;
            padding: 0.5rem !important;
            font-size: 0.85rem !important;
        }
    }

    /* Desktop only */
    .mobile-only {
        display: none;
    }

    @media (max-width: 768px) {
        .mobile-only {
            display: table-cell;
        }
    }


    @media (max-width: 400px) {
        .container {
            padding: 0 0.5rem !important;
        }

        .table {
            min-width: 400px !important;
        }

        .stats-grid .card {
            padding: 1.25rem 1rem !important;
        }

        .stats-grid .card h3 {
            font-size: 1.5rem !important;
        }

        .stats-grid .card i {
            font-size: 2rem !important;
        }
    }

    /* Make status badges smaller so they fit inside the table */
    .table .badge {
        font-size: 14px !important;
        padding: 5px 7px !important;
        line-height: 1 !important;
        white-space: nowrap;
    }

    /* Make icon inside badge smaller */
    .table .badge i {
        font-size: 13px !important;
    }

    /* For mobile list view alternative */
    .mobile-list-view {
        display: none;
    }

    /* Compact check-in badge */
    .badge-checkin {
        font-size: 11px !important;
        padding: 2px 6px !important;
        line-height: 2.2 !important;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .badge-checkin i {
        font-size: 11px !important;

    }

    @media (max-width: 350px) {
        .table-responsive {
            display: none !important;
        }

        .mobile-list-view {
            display: block !important;
        }

        .mobile-list-view .list-item {
            padding: 0.75rem;
            border-bottom: 1px solid var(--light);
        }

        .mobile-list-view .list-item:last-child {
            border-bottom: none;
        }

        .mobile-list-view .badge {
            font-size: 0.8rem !important;
        }
    }

    /* Mobile-only column */
    .mobile-only {
        display: none;
    }

    @media (max-width: 768px) {
        .mobile-only {
            display: table-cell;
        }
    }
</style>
<div id="bookingModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999;">
    <div style="background:#fff; max-width:420px; margin:15% auto; padding:1.25rem; border-radius:12px;">
        <h4 style="margin-bottom:1rem;">Detail Pemesanan</h4>
        <div id="bookingModalContent"></div>

        <button class="btn btn-secondary" style="width:100%; margin-top:1rem;"
            onclick="closeBookingModal()">Tutup</button>
    </div>
</div>



<section class="dashboard-section" style="background: var(--light);">
    <div class="container">
        <h1 style="margin-bottom: 0.5rem; font-size: clamp(1.5rem, 3vw, 2rem);">Dashboard</h1>
        <p style="color: var(--gray); font-size: clamp(0.875rem, 1.5vw, 1rem);">
            Selamat datang, Welcome <?php echo htmlspecialchars($_SESSION['full_name']); ?>!
        </p>
    </div>
</section>

<section class="dashboard-section">
    <div class="container">
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="card" style="background: linear-gradient(135deg, var(--primary), #1d4ed8); color: white;">
                <i class="fas fa-ticket-alt"></i>
                <h3><?php echo $total_bookings; ?></h3>
                <p>Total Pemesanan</p>
            </div>

            <div class="card" style="background: linear-gradient(135deg, var(--secondary), #059669); color: white;">
                <i class="fas fa-calendar-check"></i>
                <h3><?php echo $active_bookings; ?></h3>
                <p>Booking Aktif</p>
            </div>

            <div class="card" style="background: linear-gradient(135deg, var(--danger), #dc2626); color: white;">
                <i class="fas fa-times-circle"></i>
                <h3><?php echo $cancelled_bookings; ?></h3>
                <p>Dibatalkan</p>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="dashboard-card">
            <h3>Aksi Cepat</h3>
            <div class="quick-actions">
                <a href="reservation.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Pemesanan Baru
                </a>
                <a href="check-in.php" class="btn btn-secondary">
                    <i class="fas fa-check"></i> Check-in
                </a>
                <a href="profile.php" class="btn btn-outline" style="color: var(--primary); border-color: var(--primary);">
                    <i class="fas fa-user"></i> Edit Profil
                </a>
                <a href="cancel-request.php" class="btn btn-outline" style="color: var(--danger); border-color: var(--danger);">
                    <i class="fas fa-ban"></i> Ajukan Pembatalan
                </a>
            </div>
        </div>

        <!-- Pending Cancellations -->
        <?php if ($pending_cancellations->num_rows > 0): ?>
            <div class="dashboard-card">
                <h3>Permintaan Pembatalan Pending</h3>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Kode Booking</th>
                                <th>Alasan</th>
                                <th>Status</th>
                                <th>Tanggal Pengajuan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($cancel = $pending_cancellations->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($cancel['booking_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars(substr($cancel['reason'], 0, 50)) . '...'; ?></td>
                                    <td><span class="badge badge-warning"><?php echo ucfirst($cancel['status']); ?></span></td>
                                    <td><?php echo format_datetime($cancel['created_at']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recent Bookings -->
        <?php
        // Reset recent bookings query for mobile view
        $recent_bookings = $conn->query("SELECT r.*, s.service_name, s.route 
            FROM reservations r 
            JOIN services s ON r.service_id = s.id 
            WHERE r.user_id = $user_id 
            ORDER BY r.created_at DESC 
            LIMIT 5");

        $mobile_bookings = array();
        if ($recent_bookings->num_rows > 0) {
            while ($row = $recent_bookings->fetch_assoc()) {
                $mobile_bookings[] = $row;
            }
        }
        ?>

        <div class="dashboard-card">
            <h3>Pemesanan Terakhir</h3>
            <?php if (!empty($mobile_bookings)): ?>
                <!-- Desktop/Tablet View -->
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Kode Booking</th>
                                <th>Layanan</th>
                                <th>Rute</th>
                                <th>Tanggal</th>
                                <th>Penumpang</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Check-in</th>
                                <th class="mobile-only">Detail</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($mobile_bookings as $booking): ?>
                                <?php
                                $status_class = $booking['booking_status'] === 'confirmed'
                                    ? 'success'
                                    : ($booking['booking_status'] === 'cancelled' ? 'danger' : 'info');
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($booking['booking_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($booking['service_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['route']); ?></td>
                                    <td><?php echo format_date($booking['travel_date']); ?></td>
                                    <td><?php echo $booking['num_passengers']; ?> orang</td>
                                    <td><?php echo format_currency($booking['total_price']); ?></td>

                                    <td>
                                        <span class="badge badge-<?php echo $status_class; ?>">
                                            <?php echo ucfirst($booking['booking_status']); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php if ($booking['checked_in']): ?>
                                            <span class="badge badge-success badge-checkin">
                                                <i class="fas fa-check"></i> Sudah
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-warning badge-checkin">Belum</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- ✅ NEW: Mobile Detail Button -->
                                    <td class="mobile-only">
                                        <button class="btn btn-primary btn-sm"
                                            onclick="showBookingDetail(
                    '<?php echo $booking['booking_code']; ?>',
                    '<?php echo $booking['service_name']; ?>',
                    '<?php echo $booking['route']; ?>',
                    '<?php echo format_date($booking['travel_date']); ?>',
                    '<?php echo $booking['num_passengers']; ?>',
                    '<?php echo format_currency($booking['total_price']); ?>',
                    '<?php echo ucfirst($booking['booking_status']); ?>',
                    '<?php echo $booking['checked_in'] ? 'Sudah' : 'Belum'; ?>'
                )">
                                            Detail
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>

                    </table>
                </div>

                <!-- Mobile Card View for very small screens -->
                <div class="mobile-list-view">
                    <?php foreach ($mobile_bookings as $booking):
                        $status_class = $booking['booking_status'] === 'confirmed' ? 'success' : ($booking['booking_status'] === 'cancelled' ? 'danger' : 'info');
                    ?>
                        <div class="list-item">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem; flex-wrap: wrap;">
                                <strong style="font-size: 0.95rem;"><?php echo htmlspecialchars($booking['booking_code']); ?></strong>
                                <span class="badge badge-<?php echo $status_class; ?>" style="font-size: 0.8rem;">
                                    <?php echo ucfirst($booking['booking_status']); ?>
                                </span>
                            </div>
                            <div style="font-size: 0.9rem; color: var(--gray);">
                                <div style="font-weight: 500; margin-bottom: 0.25rem;"><?php echo htmlspecialchars($booking['service_name']); ?></div>
                                <div style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($booking['route']); ?></div>
                                <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                                    <span><?php echo format_date($booking['travel_date']); ?></span>
                                    <span><?php echo $booking['num_passengers']; ?> orang</span>
                                    <span style="font-weight: 600;"><?php echo format_currency($booking['total_price']); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="text-align: center; padding: 2rem; color: var(--gray);">
                    Belum ada pemesanan. <a href="reservation.php" style="color: var(--primary);">Buat pemesanan pertama Anda</a>
                </p>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const dropdowns = document.querySelectorAll('.dropdown');

        dropdowns.forEach(dropdown => {
            const toggle = dropdown.querySelector('.dropdown-toggle');
            const menu = dropdown.querySelector('.dropdown-menu');

            if (!toggle) return;

            // Prevent clicks inside menu from bubbling up (so document click doesn't close it)
            if (menu) {
                menu.addEventListener('click', function(ev) {
                    ev.stopPropagation();
                });
            }

            // Click support (mobile + desktop)
            toggle.addEventListener('click', function(e) {
                // stop the click from bubbling to document click handler
                e.preventDefault();
                e.stopPropagation();

                // Toggle only this menu
                dropdowns.forEach(d => {
                    if (d !== dropdown) d.classList.remove('active');
                });

                dropdown.classList.toggle('active');
            });

            // Add slight delay before closing on mouse leave (desktop)
            let timeout;
            dropdown.addEventListener('mouseenter', function() {
                clearTimeout(timeout);
                // show on hover as well
                dropdown.classList.add('active');
            });

            dropdown.addEventListener('mouseleave', function() {
                timeout = setTimeout(() => {
                    dropdown.classList.remove('active');
                }, 250); // 250ms delay - smooth experience when moving pointer
            });

            // keyboard accessibility: Enter/Space toggles, Escape closes
            toggle.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    e.stopPropagation();
                    dropdown.classList.toggle('active');
                } else if (e.key === 'Escape') {
                    dropdown.classList.remove('active');
                }
            });
        });

        // Close when clicking outside - but allow clicks inside .navbar or inside any .dropdown
        document.addEventListener('click', function(e) {
            // If the click is inside navbar or any dropdown, do nothing
            if (e.target.closest('.navbar') || e.target.closest('.dropdown')) {
                return;
            }

            // Otherwise close all dropdowns
            dropdowns.forEach(dropdown => dropdown.classList.remove('active'));
        });

        // Also close on Escape globally
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                dropdowns.forEach(dropdown => dropdown.classList.remove('active'));
            }
        });
    });

    function showBookingDetail(kode, layanan, rute, tanggal, penumpang, total, status, checkin) {
        document.getElementById('bookingModalContent').innerHTML = `
        <p><strong>Kode:</strong> ${kode}</p>
        <p><strong>Layanan:</strong> ${layanan}</p>
        <p><strong>Rute:</strong> ${rute}</p>
        <p><strong>Tanggal:</strong> ${tanggal}</p>
        <p><strong>Penumpang:</strong> ${penumpang}</p>
        <p><strong>Total:</strong> ${total}</p>
        <p><strong>Status:</strong> ${status}</p>
        <p><strong>Check-in:</strong> ${checkin}</p>
    `;
        document.getElementById('bookingModal').style.display = 'block';
    }

    function closeBookingModal() {
        document.getElementById('bookingModal').style.display = 'none';
    }
</script>


<?php
closeDBConnection($conn);
include '../includes/footer.php';
?>