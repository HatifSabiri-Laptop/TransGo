<?php
$page_title = 'Dashboard';
require_once '../config/config.php';
require_login();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Check if user is admin
$is_admin_user = is_admin();

if ($is_admin_user) {
    // ADMIN STATISTICS
    // 1. Total tickets issued (all paid reservations)
    $total_tickets = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE payment_status = 'paid'")->fetch_assoc()['count'];
    
    // 2. Revenue this month (total of all paid bookings this month)
    $current_month = date('Y-m');
    $revenue_this_month = $conn->query("SELECT COALESCE(SUM(total_price), 0) as revenue FROM reservations WHERE payment_status = 'paid' AND DATE_FORMAT(created_at, '%Y-%m') = '$current_month'")->fetch_assoc()['revenue'];
    
    // 3. Net profit (revenue minus refunds this month)
    $refunds_this_month = $conn->query("SELECT COALESCE(SUM(r.total_price), 0) as refunds FROM reservations r JOIN cancellation_requests cr ON r.id = cr.reservation_id WHERE cr.status = 'approved' AND DATE_FORMAT(cr.processed_at, '%Y-%m') = '$current_month'")->fetch_assoc()['refunds'];
    $net_profit = $revenue_this_month - $refunds_this_month;
    
    // 4. New users this month
    $new_users_month = $conn->query("SELECT COUNT(*) as count FROM users WHERE DATE_FORMAT(created_at, '%Y-%m') = '$current_month'")->fetch_assoc()['count'];
    
    // 5. Routes with zero bookings
    $zero_booking_routes = $conn->query("SELECT s.id, s.service_name, s.route FROM services s LEFT JOIN reservations r ON s.id = r.service_id WHERE s.status = 'active' AND r.id IS NULL ORDER BY s.service_name");
    
    // 6. Refund reports (approved cancellations this month)
    $refund_reports = $conn->query("SELECT cr.*, r.booking_code, r.total_price, s.service_name, s.route, u.full_name FROM cancellation_requests cr JOIN reservations r ON cr.reservation_id = r.id JOIN services s ON r.service_id = s.id JOIN users u ON cr.user_id = u.id WHERE cr.status = 'approved' AND DATE_FORMAT(cr.processed_at, '%Y-%m') = '$current_month' ORDER BY cr.processed_at DESC LIMIT 10");
    
} else {
    // USER STATISTICS
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
}

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

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        animation: fadeIn 0.3s;
    }

    .modal.show {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    .modal-content {
        background: white;
        border-radius: 16px;
        width: 100%;
        max-width: 500px;
        max-height: 90vh;
        overflow-y: auto;
        animation: slideUp 0.3s;
    }

    .modal-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--light);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--dark);
    }

    .close-modal {
        background: var(--light);
        border: none;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }

    .close-modal:hover {
        background: #e5e7eb;
        transform: rotate(90deg);
    }

    .modal-body {
        padding: 1.5rem;
    }

    .detail-item {
        display: flex;
        justify-content: space-between;
        padding: 0.75rem 0;
        border-bottom: 1px solid var(--light);
    }

    .detail-item:last-child {
        border-bottom: none;
    }

    .detail-label {
        color: var(--gray);
        font-size: 0.9rem;
        font-weight: 500;
    }

    .detail-value {
        color: var(--dark);
        font-weight: 600;
        text-align: right;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    @keyframes slideUp {
        from {
            transform: translateY(50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    /* Mobile-only column */
    .mobile-only {
        display: none;
    }

    .desktop-only {
        display: table-cell;
    }

    /* Refund Modal Styles */
    .refund-modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        animation: fadeIn 0.3s;
    }

    .refund-modal.show {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    .refund-modal-content {
        background: white;
        border-radius: 16px;
        width: 100%;
        max-width: 500px;
        max-height: 90vh;
        overflow-y: auto;
        animation: slideUp 0.3s;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    }

    .refund-modal-header {
        background: linear-gradient(135deg, #dc2626, #991b1b);
        color: white;
        padding: 1.5rem;
        border-radius: 16px 16px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .refund-modal-title {
        font-size: 1.25rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .refund-modal-body {
        padding: 1.5rem;
    }

    .refund-detail-item {
        padding: 1rem;
        margin-bottom: 0.75rem;
        background: var(--light);
        border-radius: 8px;
        border-left: 4px solid var(--primary);
    }

    .refund-detail-item:last-child {
        margin-bottom: 0;
    }

    .refund-detail-label {
        font-size: 0.85rem;
        color: var(--gray);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
        display: block;
    }

    .refund-detail-value {
        color: var(--dark);
        font-weight: 600;
        font-size: 1rem;
        word-wrap: break-word;
    }

    .refund-amount-highlight {
        background: #fee2e2;
        border-left-color: #dc2626 !important;
    }

    .refund-amount-highlight .refund-detail-value {
        color: #dc2626;
        font-size: 1.3rem;
        font-weight: 700;
    }

    .refund-reason-box {
        background: #fef3c7;
        border-left-color: #f59e0b !important;
        padding: 1rem;
    }

    .refund-reason-box .refund-detail-value {
        font-weight: 400;
        line-height: 1.6;
        color: #78350f;
    }

    /* Alert/Warning Box for Zero Bookings */
    .alert-warning-box {
        background: #fef3c7;
        border-left: 4px solid #f59e0b;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
    }

    .alert-warning-box strong {
        color: #92400e;
        display: block;
        margin-bottom: 0.5rem;
    }

    .alert-warning-box ul {
        margin: 0.5rem 0 0 1.5rem;
        color: #78350f;
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

        /* Hide desktop columns, show mobile columns */
        .desktop-only {
            display: none !important;
        }

        .mobile-only {
            display: table-cell !important;
        }

        /* MOBILE TABLE: Show only Kode Booking and Detail button for user bookings */
        .table:not(.refund-table) th,
        .table:not(.refund-table) td {
            display: none !important;
        }

        /* Show only column 1 (Kode Booking) */
        .table:not(.refund-table) th:nth-child(1),
        .table:not(.refund-table) td:nth-child(1) {
            display: table-cell !important;
        }

        /* Show only column 9 (Detail button) */
        .table:not(.refund-table) th:nth-child(9),
        .table:not(.refund-table) td:nth-child(9) {
            display: table-cell !important;
        }

        /* Refund table mobile adjustments */
        .refund-table {
            width: 100% !important;
            min-width: unset !important;
        }

        .refund-table th,
        .refund-table td {
            text-align: center !important;
            vertical-align: middle !important;
            padding: 0.75rem 0.5rem !important;
        }

        .refund-table th:nth-child(1),
        .refund-table td:nth-child(1) {
            text-align: left !important;
        }

        .table {
            width: 100% !important;
            min-width: unset !important;
        }

        .table th,
        .table td {
            text-align: center !important;
            vertical-align: middle !important;
            padding: 0.75rem 0.5rem !important;
        }

        .table th:nth-child(1),
        .table td:nth-child(1) {
            text-align: left !important;
        }
    }

    @media (max-width: 576px) {
        .container {
            padding: 0 0.75rem !important;
        }

        .modal-content {
            margin: 0 0.5rem;
        }

        .detail-item {
            flex-direction: column;
            gap: 0.25rem;
        }

        .detail-value {
            text-align: left;
        }
    }

    @media (max-width: 400px) {
        .container {
            padding: 0 0.5rem !important;
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

    /* Compact badges */
    .badge {
        font-size: 0.75rem;
        padding: 0.35rem 0.85rem;
        border-radius: 20px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .badge-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }

    .badge-danger {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }

    .badge-warning {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
    }

    .badge-info {
        background: #dbeafe;
        color: #1e40af;
        border: 1px solid #bfdbfe;
    }

    .btn-detail {
        background: var(--primary);
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }

    .btn-detail:hover {
        background: #1d4ed8;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }
</style>

<section class="dashboard-section" style="background: var(--light);">
    <div class="container">
        <h1 style="margin-bottom: 0.5rem; font-size: clamp(1.5rem, 3vw, 2rem);">Dashboard</h1>
        <p style="color: var(--gray); font-size: clamp(0.875rem, 1.5vw, 1rem);">
            Selamat datang, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!
        </p>
    </div>
</section>

<section class="dashboard-section">
    <div class="container">
        <?php if ($is_admin_user): ?>
            <!-- ADMIN DASHBOARD -->
            
            <!-- Admin Statistics Cards -->
            <div class="stats-grid">
                <div class="card" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white;">
                    <i class="fas fa-ticket-alt"></i>
                    <h3><?php echo number_format($total_tickets); ?></h3>
                    <p>Total Tiket Diterbitkan</p>
                </div>

                <div class="card" style="background: linear-gradient(135deg, #10b981, #059669); color: white;">
                    <i class="fas fa-dollar-sign"></i>
                    <h3><?php echo format_currency($revenue_this_month); ?></h3>
                    <p>Pendapatan Bulan Ini</p>
                </div>

                <div class="card" style="background: linear-gradient(135deg, #3b82f6, #2563eb); color: white;">
                    <i class="fas fa-chart-line"></i>
                    <h3><?php echo format_currency($net_profit); ?></h3>
                    <p>Laba Bersih Bulan Ini</p>
                </div>

                <div class="card" style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white;">
                    <i class="fas fa-user-plus"></i>
                    <h3><?php echo number_format($new_users_month); ?></h3>
                    <p>Pengguna Baru Bulan Ini</p>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="dashboard-card">
                <h3>Aksi Cepat</h3>
                <div class="quick-actions">
                    <a href="../admin/dashboard.php" class="btn btn-primary">
                        <i class="fas fa-cog"></i> Admin Panel
                    </a>
                    <a href="../admin/services.php" class="btn btn-secondary">
                        <i class="fas fa-bus"></i> Kelola Layanan
                    </a>
                    <a href="../admin/ticket-check.php" class="btn btn-outline" style="color: var(--primary); border-color: var(--primary);">
                        <i class="fas fa-ticket-alt"></i> Semua Tiket
                    </a>
                    <a href="../admin/cancellations.php" class="btn btn-outline" style="color: var(--danger); border-color: var(--danger);">
                        <i class="fas fa-ban"></i> Pembatalan
                    </a>
                </div>
            </div>

            <!-- Routes with Zero Bookings -->
            <?php if ($zero_booking_routes->num_rows > 0): ?>
                <div class="dashboard-card">
                    <h3><i class="fas fa-exclamation-triangle" style="color: #f59e0b;"></i> Rute Tanpa Pemesanan</h3>
                    <div class="alert-warning-box">
                        <strong>Perhatian: Rute berikut belum memiliki pemesanan sama sekali</strong>
                        <ul>
                            <?php while ($route = $zero_booking_routes->fetch_assoc()): ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($route['service_name']); ?></strong> - 
                                    <?php echo htmlspecialchars($route['route']); ?>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Refund Reports -->
            <?php 
            // Store refund data for mobile view
            $refund_data = array();
            if ($refund_reports->num_rows > 0) {
                $refund_reports->data_seek(0); // Reset pointer
                while ($row = $refund_reports->fetch_assoc()) {
                    $refund_data[] = $row;
                }
            }
            ?>
            <div class="dashboard-card">
                <h3><i class="fas fa-undo-alt"></i> Laporan Refund Bulan Ini</h3>
                <?php if (!empty($refund_data)): ?>
                    <div class="table-responsive">
                        <table class="table refund-table">
                            <thead>
                                <tr>
                                    <th>Kode Booking</th>
                                    <th class="desktop-only">Penumpang</th>
                                    <th class="desktop-only">Layanan</th>
                                    <th class="desktop-only">Jumlah Refund</th>
                                    <th class="desktop-only">Diproses</th>
                                    <th class="mobile-only">Detail</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($refund_data as $refund): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($refund['booking_code']); ?></strong></td>
                                        <td class="desktop-only"><?php echo htmlspecialchars($refund['full_name']); ?></td>
                                        <td class="desktop-only"><?php echo htmlspecialchars($refund['service_name']); ?></td>
                                        <td class="desktop-only" style="color: var(--danger); font-weight: 600;">
                                            <?php echo format_currency($refund['total_price']); ?>
                                        </td>
                                        <td class="desktop-only"><?php echo format_datetime($refund['processed_at']); ?></td>
                                        <td class="mobile-only">
                                            <button class="btn-detail" onclick='showRefundDetail(<?php echo json_encode([
                                                "code" => $refund['booking_code'],
                                                "passenger" => $refund['full_name'],
                                                "service" => $refund['service_name'],
                                                "route" => $refund['route'],
                                                "amount" => format_currency($refund['total_price']),
                                                "processed" => format_datetime($refund['processed_at']),
                                                "reason" => $refund['reason']
                                            ]); ?>)'>
                                                Detail
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top: 1rem; padding: 1rem; background: #fee2e2; border-radius: 8px; text-align: center;">
                        <strong style="color: #991b1b;">Total Refund Bulan Ini: <?php echo format_currency($refunds_this_month); ?></strong>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; padding: 2rem; color: var(--gray);">
                        Tidak ada refund yang diproses bulan ini.
                    </p>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- USER DASHBOARD (ORIGINAL) -->
            
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
                                                <span class="badge badge-success">
                                                    <i class="fas fa-check"></i> Sudah
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">Belum</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="mobile-only">
                                            <button class="btn-detail" onclick='showBookingDetail(<?php echo json_encode([
                                                "code" => $booking['booking_code'],
                                                "service" => $booking['service_name'],
                                                "route" => $booking['route'],
                                                "date" => format_date($booking['travel_date']),
                                                "passengers" => $booking['num_passengers'] . ' orang',
                                                "total" => format_currency($booking['total_price']),
                                                "status" => ucfirst($booking['booking_status']),
                                                "statusClass" => $status_class,
                                                "checkin" => $booking['checked_in'] ? 'Sudah' : 'Belum'
                                            ]); ?>)'>
                                                Detail
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; padding: 2rem; color: var(--gray);">
                        Belum ada pemesanan. <a href="reservation.php" style="color: var(--primary);">Buat pemesanan pertama Anda</a>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Modal (for user dashboard) -->
<?php if (!$is_admin_user): ?>
<div id="bookingModal" class="modal" onclick="closeModalOnBackdrop(event)">
    <div class="modal-content">
        <div class="modal-header">
            <h4 class="modal-title">Detail Pemesanan</h4>
            <button class="close-modal" onclick="closeBookingModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" id="bookingModalContent">
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Refund Detail Modal (for admin) -->
<?php if ($is_admin_user): ?>
<div id="refundModal" class="refund-modal" onclick="closeRefundModalOnBackdrop(event)">
    <div class="refund-modal-content">
        <div class="refund-modal-header">
            <h4 class="refund-modal-title">
                <i class="fas fa-undo-alt"></i> Detail Refund
            </h4>
            <button class="close-modal" onclick="closeRefundModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="refund-modal-body" id="refundModalContent">
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    // User booking detail modal functions
    function showBookingDetail(data) {
        const modalContent = document.getElementById('bookingModalContent');

        modalContent.innerHTML = `
            <div class="detail-item">
                <span class="detail-label">Kode Booking</span>
                <span class="detail-value">${data.code}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Layanan</span>
                <span class="detail-value">${data.service}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Rute</span>
                <span class="detail-value">${data.route}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Tanggal</span>
                <span class="detail-value">${data.date}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Penumpang</span>
                <span class="detail-value">${data.passengers}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Total</span>
                <span class="detail-value" style="color: var(--secondary); font-size: 1.1rem;">${data.total}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Status</span>
                <span class="badge badge-${data.statusClass}">${data.status}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Check-in</span>
                <span class="detail-value">${data.checkin}</span>
            </div>
        `;

        document.getElementById('bookingModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeBookingModal() {
        document.getElementById('bookingModal').classList.remove('show');
        document.body.style.overflow = 'auto';
    }

    function closeModalOnBackdrop(event) {
        if (event.target.id === 'bookingModal') {
            closeBookingModal();
        }
    }

    // Admin refund detail modal functions
    function showRefundDetail(data) {
        const modalContent = document.getElementById('refundModalContent');

        modalContent.innerHTML = `
            <div class="refund-detail-item">
                <span class="refund-detail-label"><i class="fas fa-barcode"></i> Kode Booking</span>
                <span class="refund-detail-value">${data.code}</span>
            </div>
            <div class="refund-detail-item">
                <span class="refund-detail-label"><i class="fas fa-user"></i> Penumpang</span>
                <span class="refund-detail-value">${data.passenger}</span>
            </div>
            <div class="refund-detail-item">
                <span class="refund-detail-label"><i class="fas fa-bus"></i> Layanan</span>
                <span class="refund-detail-value">${data.service}</span>
            </div>
            <div class="refund-detail-item">
                <span class="refund-detail-label"><i class="fas fa-route"></i> Rute</span>
                <span class="refund-detail-value">${data.route}</span>
            </div>
            <div class="refund-detail-item refund-amount-highlight">
                <span class="refund-detail-label"><i class="fas fa-money-bill-wave"></i> Jumlah Refund</span>
                <span class="refund-detail-value">${data.amount}</span>
            </div>
            <div class="refund-detail-item">
                <span class="refund-detail-label"><i class="fas fa-calendar-check"></i> Diproses</span>
                <span class="refund-detail-value">${data.processed}</span>
            </div>
            <div class="refund-detail-item refund-reason-box">
                <span class="refund-detail-label"><i class="fas fa-comment-alt"></i> Alasan Pembatalan</span>
                <span class="refund-detail-value">${data.reason}</span>
            </div>
        `;

        document.getElementById('refundModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeRefundModal() {
        document.getElementById('refundModal').classList.remove('show');
        document.body.style.overflow = 'auto';
    }

    function closeRefundModalOnBackdrop(event) {
        if (event.target.id === 'refundModal') {
            closeRefundModal();
        }
    }

    // Close modal on escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeBookingModal();
            closeRefundModal();
        }
    });

    // Dropdown functionality
    document.addEventListener('DOMContentLoaded', function() {
        const dropdowns = document.querySelectorAll('.dropdown');

        dropdowns.forEach(dropdown => {
            const toggle = dropdown.querySelector('.dropdown-toggle');
            const menu = dropdown.querySelector('.dropdown-menu');

            if (!toggle) return;

            if (menu) {
                menu.addEventListener('click', function(ev) {
                    ev.stopPropagation();
                });
            }

            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                dropdowns.forEach(d => {
                    if (d !== dropdown) d.classList.remove('active');
                });

                dropdown.classList.toggle('active');
            });

            let timeout;
            dropdown.addEventListener('mouseenter', function() {
                clearTimeout(timeout);
                dropdown.classList.add('active');
            });

            dropdown.addEventListener('mouseleave', function() {
                timeout = setTimeout(() => {
                    dropdown.classList.remove('active');
                }, 250);
            });

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

        document.addEventListener('click', function(e) {
            if (e.target.closest('.navbar') || e.target.closest('.dropdown')) {
                return;
            }
            dropdowns.forEach(dropdown => dropdown.classList.remove('active'));
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                dropdowns.forEach(dropdown => dropdown.classList.remove('active'));
            }
        });
    });
</script>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

include '../includes/footer.php';
?>