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

    .monthly-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .quick-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-top: 1rem;
    }

    /* Card improvements */
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

    /* Table styling for desktop */
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

    /* Mobile transaction cards */
    .mobile-transactions {
        display: none;
    }

    .transaction-card {
        background: white;
        border: 1px solid var(--light);
        border-radius: 12px;
        padding: 1.25rem;
        margin-bottom: 1rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .transaction-card-item {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 0.75rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid #f1f5f9;
    }

    .transaction-card-item:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .transaction-card-label {
        font-weight: 600;
        color: var(--gray);
        font-size: 0.85rem;
        min-width: 100px;
    }

    .transaction-card-value {
        text-align: right;
        flex: 1;
    }

    /* Badge styles */
    .badge {
        display: inline-block;
        padding: 0.35rem 0.85rem;
        border-radius: 20px;
        font-size: 0.8rem;
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

    /* Mobile responsiveness */
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .monthly-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .quick-actions {
            grid-template-columns: 1fr;
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

        /* Show mobile transactions, hide desktop table */
        .desktop-transactions {
            display: none;
        }

        .mobile-transactions {
            display: block;
            padding: 1rem;
        }

        .transaction-card-label {
            min-width: 80px;
        }

        .transaction-card-value {
            text-align: left;
        }

        .badge {
            padding: 0.3rem 0.7rem;
            font-size: 0.75rem;
        }
    }

    @media (max-width: 576px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .stat-card h3 {
            font-size: 1.5rem !important;
        }

        .card-header {
            padding: 1rem;
        }

        .btn {
            padding: 0.75rem 1rem !important;
            font-size: 0.9rem !important;
            width: 100%;
            text-align: center;
        }

        .btn i {
            margin-right: 0.5rem;
        }

        .transaction-card {
            padding: 1rem;
        }

        .transaction-card-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.25rem;
        }

        .transaction-card-label {
            min-width: auto;
            font-size: 0.8rem;
        }

        .transaction-card-value {
            text-align: left;
            width: 100%;
        }
    }

    /* Additional responsive improvements */
    @media (max-width: 640px) {
        .stats-grid {
            gap: 0.75rem;
        }

        .stat-card p {
            font-size: 0.9rem !important;
        }
    }

    @media (max-width: 480px) {
        .stat-card h3 {
            font-size: 2rem !important;
        }

        .monthly-grid h4 {
            font-size: 1.1rem;
        }
    }
</style>

<section style="padding: 2rem 0; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white;">
    <div class="container">
        <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
        <p>Selamat datang, <?php echo $_SESSION['full_name']; ?>!</p>
    </div>
</section>

<section style="padding: 2rem 0;">
    <div class="container">
        <!-- Quick Stats -->
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

        <!-- Monthly Stats -->
        <div class="monthly-grid">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="fas fa-chart-line"></i> Bulan Ini</h4>
                </div>
                <div style="padding: 1.5rem;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div style="text-align: center; padding: 1.5rem; background: var(--light); border-radius: 12px;">
                            <h3 style="color: var(--primary); margin: 0 0 0.5rem 0; font-size: 2rem;"><?php echo $month_bookings; ?></h3>
                            <p style="color: var(--gray); margin: 0;">Pemesanan</p>
                        </div>
                        <div style="text-align: center; padding: 1.5rem; background: var(--light); border-radius: 12px;">
                            <h3 style="color: var(--secondary); margin: 0 0 0.5rem 0; font-size: 1.5rem;"><?php echo format_currency($month_revenue); ?></h3>
                            <p style="color: var(--gray); margin: 0;">Pendapatan</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="fas fa-tools"></i> Aksi Cepat</h4>
                </div>
                <div style="padding: 1.5rem;">
                    <div class="quick-actions">
                        <a href="users.php" class="btn btn-primary">
                            <i class="fas fa-users"></i> Kelola User
                        </a>
                        <a href="services.php" class="btn btn-secondary">
                            <i class="fas fa-bus"></i> Kelola Layanan
                        </a>
                        <a href="cancellations.php" class="btn" style="background: var(--accent); color: white;">
                            <i class="fas fa-ban"></i> Pembatalan
                        </a>
                        <a href="blog-management.php" class="btn" style="background: var(--danger); color: white;">
                            <i class="fas fa-blog"></i> Kelola Blog
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions (Desktop Table) -->
        <div class="card desktop-transactions">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-exchange-alt"></i> Transaksi Terbaru</h3>
            </div>
            <div class="table-container" style="padding: 1.5rem;">
                <table class="table" style="min-width: 800px; color: black;" >
                    <thead>
                        <tr>
                            <th style="color: black; text-align: left;">Kode</th>
                            <th style="color: black; text-align: left;">Customer</th>
                            <th style="color: black; text-align: left;">Layanan</th>
                            <th style="color: black; text-align: left;">Tanggal</th>
                            <th style="color: black; text-align: left;">Total</th>
                            <th style="color: black; text-align: left;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $recent_transactions->data_seek(0); // Reset pointer
                        while ($trans = $recent_transactions->fetch_assoc()): ?>
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

        <!-- Recent Transactions (Mobile Cards) -->
        <div class="card mobile-transactions">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-exchange-alt"></i> Transaksi Terbaru</h3>
            </div>
            <div class="mobile-transactions">
                <?php
                $recent_transactions->data_seek(0); // Reset pointer
                while ($trans = $recent_transactions->fetch_assoc()): ?>
                    <div class="transaction-card">
                        <div class="transaction-card-item">
                            <div class="transaction-card-label">Kode Booking:</div>
                            <div class="transaction-card-value">
                                <strong style="color: var(--primary);"><?php echo $trans['booking_code']; ?></strong>
                            </div>
                        </div>
                        <div class="transaction-card-item">
                            <div class="transaction-card-label">Customer:</div>
                            <div class="transaction-card-value"><?php echo $trans['full_name']; ?></div>
                        </div>
                        <div class="transaction-card-item">
                            <div class="transaction-card-label">Layanan:</div>
                            <div class="transaction-card-value"><?php echo $trans['service_name']; ?></div>
                        </div>
                        <div class="transaction-card-item">
                            <div class="transaction-card-label">Tanggal:</div>
                            <div class="transaction-card-value"><?php echo format_date($trans['travel_date']); ?></div>
                        </div>
                        <div class="transaction-card-item">
                            <div class="transaction-card-label">Total:</div>
                            <div class="transaction-card-value">
                                <strong style="color: var(--secondary);"><?php echo format_currency($trans['total_price']); ?></strong>
                            </div>
                        </div>
                        <div class="transaction-card-item">
                            <div class="transaction-card-label">Status:</div>
                            <div class="transaction-card-value">
                                <span class="badge badge-<?php
                                                            if ($trans['booking_status'] === 'confirmed') echo 'success';
                                                            elseif ($trans['booking_status'] === 'pending') echo 'warning';
                                                            else echo 'danger';
                                                            ?>">
                                    <?php echo ucfirst($trans['booking_status']); ?>
                                </span>
                            </div>
                        </div>
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Function to handle responsive switching
        function handleResponsiveDisplay() {
            const desktopTable = document.querySelector('.desktop-transactions');
            const mobileCards = document.querySelector('.mobile-transactions');

            if (window.innerWidth <= 768) {
                // Mobile view - hide desktop table
                if (desktopTable) {
                    desktopTable.style.display = 'none';
                }
                // Show mobile cards (already shown via CSS but ensure it's visible)
                if (mobileCards) {
                    mobileCards.style.display = 'block';
                }
            } else {
                // Desktop view - show table
                if (desktopTable) {
                    desktopTable.style.display = 'block';
                }
                // Hide mobile cards (already hidden via CSS but ensure it's hidden)
                if (mobileCards) {
                    mobileCards.style.display = 'none';
                }
            }
        }

        // Check on load
        handleResponsiveDisplay();

        // Check on resize
        window.addEventListener('resize', handleResponsiveDisplay);
    });
</script>

<?php
closeDBConnection($conn);
include '../includes/footer.php';
?>