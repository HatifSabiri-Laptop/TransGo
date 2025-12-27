<?php
$page_title = 'Admin Dashboard';
require_once '../config/config.php';

require_login();
require_admin();

$conn = getDBConnection();

// Today's statistics
$today = date('Y-m-d');
$today_bookings = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE DATE(created_at) = '$today'")->fetch_assoc()['count'];
$today_revenue = $conn->query("SELECT SUM(total_price) as total FROM reservations WHERE DATE(created_at) = '$today' AND payment_status = 'paid'")->fetch_assoc()['total'] ?? 0;
$pending_cancellations = $conn->query("SELECT COUNT(*) as count FROM cancellation_requests WHERE status = 'pending'")->fetch_assoc()['count'];
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];

// This month statistics
$this_month = date('Y-m');
$month_bookings = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE DATE_FORMAT(created_at, '%Y-%m') = '$this_month'")->fetch_assoc()['count'];
$month_revenue = $conn->query("SELECT SUM(total_price) as total FROM reservations WHERE DATE_FORMAT(created_at, '%Y-%m') = '$this_month' AND payment_status = 'paid'")->fetch_assoc()['total'] ?? 0;

// Recent transactions
$recent_transactions = $conn->query("SELECT r.*, s.service_name, u.full_name 
    FROM reservations r 
    JOIN services s ON r.service_id = s.id 
    JOIN users u ON r.user_id = u.id 
    ORDER BY r.created_at DESC 
    LIMIT 10");

// Upcoming trips
$upcoming_trips = $conn->query("SELECT r.*, s.service_name, s.route, u.full_name 
    FROM reservations r 
    JOIN services s ON r.service_id = s.id 
    JOIN users u ON r.user_id = u.id 
    WHERE r.booking_status = 'confirmed' 
    AND r.travel_date >= CURDATE() 
    ORDER BY r.travel_date ASC 
    LIMIT 5");

include '../includes/header.php';
?>

<style>
    /* Base styles */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    /* Hover styles for action buttons */
    .btn-kelola-user {
        background: var(--primary) !important;
        color: white !important;
        border: none !important;
        font-weight: 600 !important;
        padding: 0.75rem 1.5rem !important;
        transition: all 0.3s ease;
        display: inline-block;
        text-align: center;
    }

    .btn-kelola-user:hover {
        background: #ffffffff !important;
        color: var(--primary) !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }

    .btn-pembatalan {
        background: var(--danger) !important;
        color: white !important;
        border: none !important;
        font-weight: 600 !important;
        padding: 0.75rem 1.5rem !important;
        transition: all 0.3s ease;
        display: inline-block;
        text-align: center;
    }
       .btn-pembatalan:hover {
        background: #ebe8e8ff !important;
        color: var(--danger) !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(220, 10, 10, 0.4);
    }

    .btn-activity-logs {
        background: #8b5cf6 !important;
        color: white !important;
        border: none !important;
        font-weight: 600 !important;
        padding: 0.75rem 1.5rem !important;
        transition: all 0.3s ease;
        display: inline-block;
        text-align: center;
    }

    .btn-activity-logs:hover {
        background: #7c3aed !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
    }

 
    .btn-activity-logs {
        background: #8b5cf6 !important;
        color: white !important;
        border: none !important;
        font-weight: 600 !important;
        padding: 0.75rem 1.5rem !important;
        transition: all 0.3s ease;
        display: inline-block;
        text-align: center;
    }
.btn-activity-logs:hover {
        background: #fdfdfdff !important;
        color: #2814dfff !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
        
}
    .card {
        border: 1px solid var(--light);
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(171, 25, 25, 0.08);
        background: white;
        margin-bottom: 1.5rem;
    }

    .card-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--light);
        background: var(--light);
    }

    .card-title {
        margin: 0;
        font-size: 1.25rem;
        color: var(--dark);
        display: flex;
        align-items: center;
        font-weight: 600;
    }

    .card-title i {
        margin-right: 0.5rem;
        color: rgba(29, 218, 32, 1);
        font-size: 1.25rem;
    }

    /* Desktop Table */
    .table-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
    }

    .table th {
        padding: 1rem;
        text-align: left;
        background: var(--light);
        font-weight: 600;
        border-bottom: 2px solid var(--light);
    }

    .table td {
        padding: 1rem;
        border-bottom: 1px solid var(--light);
    }

    .table tr:hover {
        background: #f8fafc;
    }

    /* Mobile Transactions */
    .mobile-transactions {
        display: none;
    }

    .mobile-transaction-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--light);
        background: white;
        transition: background 0.2s;
    }

    .mobile-transaction-item:hover {
        background: #f8fafc;
    }

    .mobile-transaction-item:last-child {
        border-bottom: none;
    }

    .transaction-code {
        flex: 1;
    }

    .transaction-code strong {
        color: var(--primary);
        font-size: 0.95rem;
        display: block;
        margin-bottom: 0.25rem;
    }

    .transaction-code small {
        color: var(--gray);
        font-size: 0.8rem;
        display: block;
    }

    /* Hide status badge in mobile list view */
    .mobile-transaction-item .badge {
        display: none;
    }

    .btn-details {
        background: var(--primary);
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s;
    }

    .btn-details:hover {
        background: #1d4ed8;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
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
        position: relative;
    }

    .modal-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--light);
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        position: sticky;
        top: 0;
        background: white;
        z-index: 10;
    }

    .modal-header-content {
        flex: 1;
        padding-right: 1rem;
    }

    .modal-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 0.5rem;
    }

    .modal-subtitle {
        font-size: 0.85rem;
        color: var(--gray);
    }

    .modal-status {
        margin-bottom: 0.5rem;
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
        flex-shrink: 0;
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

    /* Badge styles */
    .badge {
        display: inline-block;
        padding: 0.35rem 0.85rem;
        border-radius: 20px;
        font-size: 0.75rem;
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

    /* Mobile responsiveness */
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        /* Change button grid to 3 columns on mobile */
        div[style*="grid-template-columns: repeat(3, 1fr)"] {
            grid-template-columns: 1fr !important;
            gap: 0.75rem !important;
            max-width: 100% !important;
        }

        .btn-kelola-user,
        .btn-pembatalan,
        .btn-activity-logs {
            font-size: 0.9rem !important;
            padding: 0.65rem 1rem !important;
        }

        .btn-kelola-user i,
        .btn-pembatalan i,
        .btn-activity-logs i {
            font-size: 0.9rem !important;
        }

        .card[style*="max-width: 500px"]>div[style*="padding: 1.5rem"]>div[style*="grid-template-columns: 1fr 1fr"] {
            display: grid !important;
            grid-template-columns: 1fr !important;
            gap: 0.75rem !important;
        }

        .card[style*="max-width: 500px"] div[style*="text-align: center"][style*="padding: 1.5rem"] {
            padding: 1rem !important;
        }

        .card[style*="max-width: 500px"] h3 {
            font-size: 1.5rem !important;
        }

        .card[style*="max-width: 500px"] p {
            font-size: 0.85rem !important;
        }

        .btn-kelola-user {
            font-size: 0.9rem !important;
            padding: 0.65rem 1rem !important;
        }

        .btn-kelola-user i {
            font-size: 0.9rem !important;
        }

        div[style*="border-left: 4px solid var(--primary)"] {
            display: flex;
            flex-direction: column;
        }

        div[style*="border-left: 4px solid var(--primary)"]>div[style*="display: flex"] {
            flex-direction: column !important;
            gap: 0.75rem;
        }

        div[style*="border-left: 4px solid var(--primary)"] .badge {
            align-self: flex-start;
            margin-top: 0.5rem;
            font-size: 0.8rem;
        }

        .stat-card {
            padding: 1rem !important;
        }

        .stat-card h3 {
            font-size: 1.8rem !important;
        }

        .stat-card i {
            font-size: 2rem !important;
        }

        .desktop-transactions {
            display: none !important;
        }

        .mobile-transactions {
            display: block !important;
        }

        .mobile-transaction-item {
            padding: 1rem;
        }

        .card-header {
            padding: 1rem;
        }

        .card-title {
            font-size: 1.1rem !important;
        }

        .modal-content {
            margin: 0 1rem;
        }
    }

    @media (max-width: 576px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .stat-card h3 {
            font-size: 1.5rem !important;
        }

        .card[style*="max-width: 500px"] h3 {
            font-size: 1.3rem !important;
        }

        .card[style*="max-width: 500px"] p {
            font-size: 0.8rem !important;
        }

        .btn-kelola-user {
            font-size: 0.85rem !important;
            padding: 0.6rem 0.85rem !important;
        }

        .transaction-code strong {
            font-size: 0.85rem;
        }

        .btn-details {
            padding: 0.45rem 0.85rem;
            font-size: 0.8rem;
        }

        .modal-header {
            padding: 1.25rem;
        }

        .modal-title {
            font-size: 1rem;
        }

        .modal-body {
            padding: 1.25rem;
        }

        .detail-item {
            flex-direction: column;
            gap: 0.25rem;
        }

        .detail-value {
            text-align: left;
        }
    }

    @media (max-width: 480px) {
        .stat-card h3 {
            font-size: 1.3rem !important;
        }

        .card[style*="max-width: 500px"] h3 {
            font-size: 1.1rem !important;
        }

        .card[style*="max-width: 500px"] p {
            font-size: 0.75rem !important;
        }

        .btn-kelola-user {
            font-size: 0.8rem !important;
            padding: 0.55rem 0.75rem !important;
        }

        .btn-kelola-user i {
            font-size: 0.8rem !important;
            margin-right: 0.35rem !important;
        }

        div[style*="border-left: 4px solid var(--primary)"] small {
            font-size: 0.75rem !important;
        }
    }

    @media (max-width: 360px) {
        .card[style*="max-width: 500px"] h3 {
            font-size: 1rem !important;
        }

        .card[style*="max-width: 500px"] p {
            font-size: 0.7rem !important;
        }

        .btn-kelola-user {
            font-size: 0.75rem !important;
            padding: 0.5rem 0.65rem !important;
        }
    }
</style>

<section style="padding: 2rem 0; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white;">
    <div class="container">
        <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
        <p>Selamat datang, <?php echo $_SESSION['full_name']; ?>!</p>

        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-top: 1.5rem; max-width: 600px;">
            <a href="users.php" class="btn btn-primary btn-kelola-user">
                <i class="fas fa-users"></i> Kelola User
            </a>
            <a href="cancellations.php" class="btn btn-pembatalan">
                <i class="fas fa-ban"></i> Pembatalan
            </a>
            <a href="activity_logs.php" class="btn btn-activity-logs";>
                <i class="fas fa-history"></i> Activity Logs
            </a>
        </div>
    </div>
</section>

<section style="padding: 2rem 0;">
    <div class="container">
        <h3 style="margin-bottom: 1rem;">Statistik Hari Ini</h3>
        <div class="stats-grid">
            <div class="card stat-card" style="background: linear-gradient(135deg, #3b82f6, #2563eb);">
                <div style="color: white; text-align: center; padding: 1.5rem;">
                    <i class="fas fa-calendar-day" style="font-size: 2.5rem; margin-bottom: 0.5rem;"></i>
                    <h3 style="font-size: 2.5rem; margin-bottom: 0.5rem;"><?php echo $today_bookings; ?></h3>
                    <p>Pemesanan Hari Ini</p>
                </div>
            </div>

            <div class="card stat-card" style="background: linear-gradient(135deg, #10b981, #059669);">
                <div style="color: white; text-align: center; padding: 1.5rem;">
                    <i class="fas fa-money-bill-wave" style="font-size: 2.5rem; margin-bottom: 0.5rem;"></i>
                    <h3 style="font-size: 2rem; margin-bottom: 0.5rem;"><?php echo format_currency($today_revenue); ?></h3>
                    <p>Pendapatan Hari Ini</p>
                </div>
            </div>

            <div class="card stat-card" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <div style="color: white; text-align: center; padding: 1.5rem;">
                    <i class="fas fa-exclamation-circle" style="font-size: 2.5rem; margin-bottom: 0.5rem;"></i>
                    <h3 style="font-size: 2.5rem; margin-bottom: 0.5rem;"><?php echo $pending_cancellations; ?></h3>
                    <p>Pembatalan Pending</p>
                </div>
            </div>

            <div class="card stat-card" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                <div style="color: white; text-align: center; padding: 1.5rem;">
                    <i class="fas fa-users" style="font-size: 2.5rem; margin-bottom: 0.5rem;"></i>
                    <h3 style="font-size: 2.5rem; margin-bottom: 0.5rem;"><?php echo $total_users; ?></h3>
                    <p>Total Pengguna</p>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: center;">
            <div class="card monthly-stats-card" style="max-width: 500px; width: 100%;">
                <div class="card-header">
                    <h4 class="card-title"><i class="fas fa-chart-line"></i> Bulan Ini</h4>
                </div>
                <div class="monthly-stats-container" style="padding: 1.5rem;">
                    <div class="monthly-stats-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="monthly-stat-box" style="text-align: center; padding: 1.5rem; background: var(--light); border-radius: 12px;">
                            <h3 class="monthly-stat-number" style="color: var(--primary); margin: 0 0 0.5rem 0; font-size: 2rem;"><?php echo $month_bookings; ?></h3>
                            <p class="monthly-stat-label" style="color: var(--gray); margin: 0;">Pemesanan</p>
                        </div>
                        <div class="monthly-stat-box" style="text-align: center; padding: 1.5rem; background: var(--light); border-radius: 12px;">
                            <h3 class="monthly-stat-number" style="color: var(--secondary); margin: 0 0 0.5rem 0; font-size: 1.5rem;"><?php echo format_currency($month_revenue); ?></h3>
                            <p class="monthly-stat-label" style="color: var(--gray); margin: 0;">Pendapatan</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Desktop Table -->
        <div class="card desktop-transactions">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-exchange-alt"></i> Transaksi Terbaru</h3>
            </div>
            <div class="table-container" style="padding: 1.5rem;">
                <table class="table" style="min-width: 800px; color: black;">
                    <thead>
                        <tr>
                            <th style="color: black;">Kode</th>
                            <th style="color: black;">Customer</th>
                            <th style="color: black;">Layanan</th>
                            <th style="color: black;">Tanggal</th>
                            <th style="color: black;">Total</th>
                            <th style="color: black;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($trans = $recent_transactions->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo $trans['booking_code']; ?></strong></td>
                                <td><?php echo $trans['full_name']; ?></td>
                                <td><?php echo $trans['service_name']; ?></td>
                                <td><?php echo format_date($trans['travel_date']); ?></td>
                                <td><?php echo format_currency($trans['total_price']); ?></td>
                                <td>
                                    <span class="badge badge-<?php
                                                                if ($trans['booking_status'] === 'confirmed') echo 'success';
                                                                elseif ($trans['booking_status'] === 'pending') echo 'warning';
                                                                else echo 'danger';
                                                                ?>">
                                        <?php echo ucfirst($trans['booking_status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mobile Transactions -->
        <div class="card mobile-transactions">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-exchange-alt"></i> Transaksi Terbaru</h3>
            </div>
            <div>
                <?php
                $recent_transactions->data_seek(0);
                while ($trans = $recent_transactions->fetch_assoc()):
                    $status_class = 'success';
                    if ($trans['booking_status'] === 'pending') $status_class = 'warning';
                    elseif ($trans['booking_status'] === 'cancelled') $status_class = 'danger';
                ?>
                    <div class="mobile-transaction-item">
                        <div class="transaction-code">
                            <strong><?php echo $trans['booking_code']; ?></strong>
                            <small><?php echo format_date($trans['travel_date']); ?></small>
                        </div>
                        <button class="btn-details" onclick='openModal(<?php echo json_encode([
                                                                            "code" => $trans['booking_code'],
                                                                            "customer" => $trans['full_name'],
                                                                            "service" => $trans['service_name'],
                                                                            "date" => format_date($trans['travel_date']),
                                                                            "total" => format_currency($trans['total_price']),
                                                                            "status" => ucfirst($trans['booking_status']),
                                                                            "statusClass" => $status_class
                                                                        ]); ?>)'>
                            <span>Detail</span>
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Upcoming Trips -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-calendar-check"></i> Perjalanan Mendatang</h3>
            </div>
            <div style="padding: 1.5rem;">
                <?php if ($upcoming_trips->num_rows > 0): ?>
                    <?php while ($trip = $upcoming_trips->fetch_assoc()): ?>
                        <div style="background: var(--light); padding: 1.5rem; border-radius: 12px; border-left: 4px solid var(--primary); margin-bottom: 1rem;">
                            <strong style="color: var(--primary); display: block; margin-bottom: 0.5rem;">
                                <?php echo $trip['service_name']; ?>
                            </strong>
                            <p style="color: var(--gray); font-size: 0.9rem; margin: 0.25rem 0;">
                                <i class="fas fa-route"></i> <?php echo $trip['route']; ?>
                            </p>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid #cbd5e1;">
                                <div>
                                    <small style="color: var(--gray); display: block;">
                                        <i class="fas fa-user"></i> <?php echo $trip['full_name']; ?>
                                    </small>
                                    <small style="color: var(--gray);">
                                        <i class="fas fa-calendar"></i> <?php echo format_date($trip['travel_date']); ?>
                                    </small>
                                </div>
                                <span class="badge badge-info"><?php echo $trip['num_passengers']; ?> pax</span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align: center; padding: 2rem; color: var(--gray);">
                        <i class="fas fa-calendar-times" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                        Tidak ada perjalanan mendatang
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Modal -->
<div id="transactionModal" class="modal" onclick="closeModalOnBackdrop(event)">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-header-content">
                <div class="modal-title" id="modalCode"></div>
                <div class="modal-subtitle" id="modalDate"></div>
                <div class="modal-status">
                    <span class="badge" id="modalStatus"></span>
                </div>
            </div>
            <button class="close-modal" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="detail-item">
                <span class="detail-label">Customer</span>
                <span class="detail-value" id="modalCustomer"></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Layanan</span>
                <span class="detail-value" id="modalService"></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Tanggal Travel</span>
                <span class="detail-value" id="modalTravelDate"></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Total Harga</span>
                <span class="detail-value" id="modalTotal" style="color: var(--secondary); font-size: 1.1rem;"></span>
            </div>
        </div>
    </div>
</div>

<script>
    // Modal functionality
    function openModal(data) {
        const modal = document.getElementById('transactionModal');
        const statusBadge = document.getElementById('modalStatus');

        // Set modal content
        document.getElementById('modalCode').textContent = data.code;
        document.getElementById('modalDate').textContent = data.date;
        document.getElementById('modalCustomer').textContent = data.customer;
        document.getElementById('modalService').textContent = data.service;
        document.getElementById('modalTravelDate').textContent = data.date;
        document.getElementById('modalTotal').textContent = data.total;

        // Set status badge
        statusBadge.textContent = data.status;
        statusBadge.className = 'badge badge-' + data.statusClass;

        // Show modal
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        const modal = document.getElementById('transactionModal');
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
    }

    function closeModalOnBackdrop(event) {
        if (event.target.id === 'transactionModal') {
            closeModal();
        }
    }

    // Close modal on escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });

    // Prevent button click from bubbling to parent
    document.addEventListener('DOMContentLoaded', function() {
        const detailButtons = document.querySelectorAll('.btn-details');
        detailButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });
    });
</script>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

include '../includes/footer.php';
?>