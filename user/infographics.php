<?php
$page_title = 'Statistik & Infografis';
require_once '../config/config.php';

$conn = getDBConnection();

// Get statistics
$total_reservations = $conn->query("SELECT COUNT(*) as count FROM reservations")->fetch_assoc()['count'];
$total_confirmed = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE booking_status = 'confirmed'")->fetch_assoc()['count'];
$total_cancelled = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE booking_status = 'cancelled'")->fetch_assoc()['count'];
$total_checked_in = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE checked_in = 1")->fetch_assoc()['count'];
$total_revenue = $conn->query("SELECT SUM(total_price) as total FROM reservations WHERE payment_status = 'paid'")->fetch_assoc()['total'] ?? 0;

// Monthly bookings
$monthly_bookings = $conn->query("SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(*) as count
    FROM reservations
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month ASC");

$months = [];
$counts = [];
while ($row = $monthly_bookings->fetch_assoc()) {
    $months[] = date('M Y', strtotime($row['month'] . '-01'));
    $counts[] = $row['count'];
}

// Popular routes
$popular_routes = $conn->query("SELECT 
    s.route,
    s.service_name,
    COUNT(r.id) as total_bookings
    FROM services s
    LEFT JOIN reservations r ON s.id = r.service_id
    GROUP BY s.id
    ORDER BY total_bookings DESC
    LIMIT 5");

// Cancellation rate
$cancellation_rate = $total_reservations > 0 ? ($total_cancelled / $total_reservations) * 100 : 0;
$confirmation_rate = $total_reservations > 0 ? ($total_confirmed / $total_reservations) * 100 : 0;

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

    @media (max-width: 768px) {
        .tab-btn {
            padding: 1rem;
            font-size: 0.9rem;
        }

        .tab-btn span {
            display: inline; /* Changed from "none" to "inline" */
        }

        .tab-btn i {
            font-size: 1rem;
            margin-right: 0.5rem;
        }
    }

    @media (max-width: 480px) {
        .tab-btn span {
            font-size: 0.8rem;
        }
        
        .tab-btn {
            padding: 0.75rem 0.5rem;
        }
    }

    /* Responsive adjustments for statistics page */
    @media (max-width: 1024px) {
        /* Tablet: Adjust grid layout */
        .key-metrics-grid {
            grid-template-columns: repeat(2, 1fr) !important;
        }

        .chart-grid {
            grid-template-columns: 1fr !important;
        }

        /* Adjust chart heights for tablet */
        #monthlyChart,
        #statusChart {
            max-height: 250px !important;
        }
    }

    @media (max-width: 768px) {
        /* Mobile: Stack all elements vertically */
        .key-metrics-grid {
            grid-template-columns: 1fr !important;
            gap: 1rem !important;
        }

        .chart-grid {
            grid-template-columns: 1fr !important;
            gap: 1.5rem !important;
        }

        .chart-grid .card:last-child {
            margin-top: 0;
        }

        /* Adjust card margins for mobile */
        .card {
            margin: 0.5rem 0;
        }

        /* Performance indicators - stack vertically */
        .performance-grid {
            grid-template-columns: 1fr !important;
            gap: 1rem !important;
        }

        /* Adjust chart heights for mobile */
        #monthlyChart,
        #statusChart {
            max-height: 200px !important;
        }

        /* Adjust font sizes for mobile */
        h3 {
            font-size: 1.2rem;
        }

        h4 {
            font-size: 1.1rem;
        }

        /* Reduce padding in cards */
        .card-header {
            padding: 1rem;
        }

        .card-body {
            padding: 1rem;
        }
        
        /* Smaller font for key metrics */
        .card h3 {
            font-size: 1.5rem !important;
        }

        .card p {
            font-size: 0.9rem;
        }
    }

    @media (max-width: 480px) {
        /* Extra small devices */
        .container {
            padding-left: 1rem !important;
            padding-right: 1rem !important;
        }
        
        /* Smaller font for key metrics */
        .card h3 {
            font-size: 1.3rem !important;
        }

        .card p {
            font-size: 0.8rem;
        }
    }

    /* Default desktop styles for the Popular Routes table */
    .table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1rem;
    }

    .table thead {
        background-color: var(--light);
    }

    .table th {
        text-align: left;
        padding: 1rem;
        font-weight: 600;
        color: var(--dark);
        border-bottom: 2px solid #dee2e6;
    }

    .table td {
        padding: 1rem;
        border-bottom: 1px solid #dee2e6;
        vertical-align: middle;
    }

    .table tbody tr:hover {
        background-color: rgba(0, 0, 0, 0.02);
    }

    /* Responsive container for the Popular Routes table - Desktop default */
    .table-responsive-container {
        width: 100%;
        margin-top: 1rem;
    }

    /* Mobile table styles */
    .mobile-route-item {
        display: none;
        padding: 1rem;
        border-bottom: 1px solid #dee2e6;
    }

    .mobile-route-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .mobile-route-rank {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        min-width: 60px;
    }

    .mobile-route-service {
        flex: 1;
    }

    .mobile-route-details-btn {
        background: var(--primary);
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.875rem;
        transition: background-color 0.3s;
    }

    .mobile-route-details-btn:hover {
        background: var(--secondary);
    }

    /* Performance indicators grid */
    .performance-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
        margin-top: 2rem;
    }

    /* Chart grid layout */
    .chart-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }

    /* Modal styles */
    .details-modal {
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

    .details-modal.active {
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
    }

    .detail-value {
        color: var(--gray);
    }

    .progress-container {
        margin-top: 0.5rem;
    }

    .progress-bar {
        height: 20px;
        background: var(--light);
        border-radius: 10px;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        background: var(--primary);
        transition: width 0.5s;
    }

    /* Hide desktop table on mobile, show mobile view */
    @media (max-width: 768px) {
        .table-responsive-container {
            display: none;
        }
        
        .mobile-route-item {
            display: block;
        }
    }

    /* Show desktop table on desktop, hide mobile view */
    @media (min-width: 769px) {
        .mobile-route-item {
            display: none;
        }
        
        .table-responsive-container {
            display: block;
        }
    }
</style>

<section style="padding: 2rem 0; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white;">
    <div class="container">
        <h1>Statistik & Pengalaman</h1>
        <p>Data pemesanan dan testimoni pelanggan</p>
    </div>
</section>

<!-- Tab Navigation -->
<div class="tab-navigation">
    <div class="container">
        <button class="tab-btn active">
            <i class="fas fa-chart-bar"></i>
            <span>Statistik & Infografis</span>
        </button>
        <a href="<?php echo SITE_URL; ?>/user/experiences.php" class="tab-btn" style="text-decoration: none;">
            <i class="fas fa-star"></i>
            <span>Pengalaman Anda</span>
        </a>
    </div>
</div>

<section style="padding: 2rem 0;">
    <div class="container">
        <!-- Key Metrics -->
        <div class="key-metrics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            <div class="card" style="text-align: center; background: linear-gradient(135deg, #3b82f6, #2563eb);">
                <div style="color: white;">
                    <i class="fas fa-ticket-alt" style="font-size: 2.5rem; margin-bottom: 1rem;"></i>
                    <h3 style="font-size: 2rem; margin-bottom: 0.5rem;"><?php echo number_format($total_reservations); ?></h3>
                    <p>Total Reservasi</p>
                </div>
            </div>

            <div class="card" style="text-align: center; background: linear-gradient(135deg, #10b981, #059669);">
                <div style="color: white;">
                    <i class="fas fa-check-circle" style="font-size: 2.5rem; margin-bottom: 1rem;"></i>
                    <h3 style="font-size: 2rem; margin-bottom: 0.5rem;"><?php echo number_format($total_confirmed); ?></h3>
                    <p>Terkonfirmasi</p>
                </div>
            </div>

            <div class="card" style="text-align: center; background: linear-gradient(135deg, #ef4444, #dc2626);">
                <div style="color: white;">
                    <i class="fas fa-times-circle" style="font-size: 2.5rem; margin-bottom: 1rem;"></i>
                    <h3 style="font-size: 2rem; margin-bottom: 0.5rem;"><?php echo number_format($total_cancelled); ?></h3>
                    <p>Dibatalkan</p>
                </div>
            </div>

            <div class="card" style="text-align: center; background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                <div style="color: white;">
                    <i class="fas fa-user-check" style="font-size: 2.5rem; margin-bottom: 1rem;"></i>
                    <h3 style="font-size: 2rem; margin-bottom: 0.5rem;"><?php echo number_format($total_checked_in); ?></h3>
                    <p>Check-in</p>
                </div>
            </div>

            <div class="card" style="text-align: center; background: linear-gradient(135deg, #f59e0b, #d97706);">
                <div style="color: white;">
                    <i class="fas fa-money-bill-wave" style="font-size: 2.5rem; margin-bottom: 1rem;"></i>
                    <h3 style="font-size: 1.5rem; margin-bottom: 0.5rem;"><?php echo format_currency($total_revenue); ?></h3>
                    <p>Total Pendapatan</p>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="chart-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
            <!-- Monthly Bookings Chart -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Tren Pemesanan (6 Bulan Terakhir)</h3>
                </div>
                <canvas id="monthlyChart" style="max-height: 300px;"></canvas>
            </div>

            <!-- Status Distribution -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Distribusi Status</h3>
                </div>
                <canvas id="statusChart" style="max-height: 300px;"></canvas>
            </div>
        </div>

        <!-- Popular Routes -->
        <div class="card" style="margin-top: 2rem;">
            <div class="card-header">
                <h3 class="card-title">Rute Paling Populer</h3>
            </div>
            
            <!-- Desktop Table View -->
            <div class="table-responsive-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Peringkat</th>
                            <th>Layanan</th>
                            <th>Rute</th>
                            <th>Total Pemesanan</th>
                            <th>Grafik</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rank = 1;
                        $max_bookings = 0;

                        // Find max for percentage calculation
                        $temp_routes = [];
                        while ($route = $popular_routes->fetch_assoc()) {
                            $temp_routes[] = $route;
                            if ($route['total_bookings'] > $max_bookings) {
                                $max_bookings = $route['total_bookings'];
                            }
                        }

                        foreach ($temp_routes as $route):
                            $percentage = $max_bookings > 0 ? ($route['total_bookings'] / $max_bookings) * 100 : 0;
                        ?>
                            <tr>
                                <td>
                                    <?php if ($rank === 1): ?>
                                        <span style="color: gold; font-size: 1.5rem;"><i class="fas fa-trophy"></i></span>
                                    <?php elseif ($rank === 2): ?>
                                        <span style="color: silver; font-size: 1.3rem;"><i class="fas fa-medal"></i></span>
                                    <?php elseif ($rank === 3): ?>
                                        <span style="color: #cd7f32; font-size: 1.2rem;"><i class="fas fa-award"></i></span>
                                    <?php else: ?>
                                        <strong><?php echo $rank; ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $route['service_name']; ?></td>
                                <td><strong><?php echo $route['route']; ?></strong></td>
                                <td><?php echo number_format($route['total_bookings']); ?> pemesanan</td>
                                <td>
                                    <div style="background: var(--light); height: 20px; border-radius: 10px; overflow: hidden;">
                                        <div style="background: var(--primary); height: 100%; width: <?php echo $percentage; ?>%; transition: width 0.5s;"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php
                            $rank++;
                        endforeach;
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card View -->
            <?php
            $rank = 1;
            foreach ($temp_routes as $route):
                $percentage = $max_bookings > 0 ? ($route['total_bookings'] / $max_bookings) * 100 : 0;
            ?>
            <div class="mobile-route-item" data-rank="<?php echo $rank; ?>">
                <div class="mobile-route-header">
                    <div class="mobile-route-rank">
                        <?php if ($rank === 1): ?>
                            <span style="color: gold; font-size: 1.5rem;"><i class="fas fa-trophy"></i></span>
                            <strong>#<?php echo $rank; ?></strong>
                        <?php elseif ($rank === 2): ?>
                            <span style="color: silver; font-size: 1.3rem;"><i class="fas fa-medal"></i></span>
                            <strong>#<?php echo $rank; ?></strong>
                        <?php elseif ($rank === 3): ?>
                            <span style="color: #cd7f32; font-size: 1.2rem;"><i class="fas fa-award"></i></span>
                            <strong>#<?php echo $rank; ?></strong>
                        <?php else: ?>
                            <strong>#<?php echo $rank; ?></strong>
                        <?php endif; ?>
                    </div>
                    <div class="mobile-route-service">
                        <strong><?php echo $route['service_name']; ?></strong>
                    </div>
                    <button class="mobile-route-details-btn" onclick="showRouteDetails(<?php echo $rank; ?>)">
                        Lihat Detail
                    </button>
                </div>
            </div>
            <?php
                $rank++;
            endforeach;
            ?>
        </div>

        <!-- Performance Indicators -->
        <div class="performance-grid">
            <div class="card">
                <h4 style="margin-bottom: 1rem;"><i class="fas fa-chart-line"></i> Tingkat Konfirmasi</h4>
                <div style="position: relative; height: 150px;">
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
                        <h2 style="font-size: 3rem; color: var(--secondary); margin-bottom: 0.5rem;">
                            <?php echo number_format($confirmation_rate, 1); ?>%
                        </h2>
                        <p style="color: var(--gray);">Booking Terkonfirmasi</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <h4 style="margin-bottom: 1rem;"><i class="fas fa-chart-pie"></i> Tingkat Pembatalan</h4>
                <div style="position: relative; height: 150px;">
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
                        <h2 style="font-size: 3rem; color: var(--danger); margin-bottom: 0.5rem;">
                            <?php echo number_format($cancellation_rate, 1); ?>%
                        </h2>
                        <p style="color: var(--gray);">Booking Dibatalkan</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <h4 style="margin-bottom: 1rem;"><i class="fas fa-users"></i> Check-in Rate</h4>
                <div style="position: relative; height: 150px;">
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
                        <h2 style="font-size: 3rem; color: var(--primary); margin-bottom: 0.5rem;">
                            <?php echo $total_confirmed > 0 ? number_format(($total_checked_in / $total_confirmed) * 100, 1) : 0; ?>%
                        </h2>
                        <p style="color: var(--gray);">Penumpang Check-in</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Route Details Modal -->
<div id="routeDetailsModal" class="details-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Detail Rute</h3>
            <button class="modal-close" onclick="closeRouteDetails()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" id="routeDetailsContent">
            <!-- Dynamic content will be inserted here -->
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
    // Monthly Bookings Chart
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($months); ?>,
            datasets: [{
                label: 'Jumlah Pemesanan',
                data: <?php echo json_encode($counts); ?>,
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });

    // Status Distribution Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Terkonfirmasi', 'Dibatalkan', 'Check-in'],
            datasets: [{
                data: [<?php echo $total_confirmed; ?>, <?php echo $total_cancelled; ?>, <?php echo $total_checked_in; ?>],
                backgroundColor: ['#10b981', '#ef4444', '#8b5cf6']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });

    // Route data for mobile view
    const routeData = <?php echo json_encode($temp_routes); ?>;
    const maxBookings = <?php echo $max_bookings; ?>;

    function showRouteDetails(rank) {
        const route = routeData[rank - 1];
        const percentage = maxBookings > 0 ? (route.total_bookings / maxBookings) * 100 : 0;
        
        let rankIcon = '';
        if (rank === 1) {
            rankIcon = '<span style="color: gold; font-size: 2rem;"><i class="fas fa-trophy"></i></span>';
        } else if (rank === 2) {
            rankIcon = '<span style="color: silver; font-size: 1.8rem;"><i class="fas fa-medal"></i></span>';
        } else if (rank === 3) {
            rankIcon = '<span style="color: #cd7f32; font-size: 1.6rem;"><i class="fas fa-award"></i></span>';
        } else {
            rankIcon = `<strong style="font-size: 1.5rem;">#${rank}</strong>`;
        }
        
        const modalContent = `
            <div class="detail-item">
                <div class="detail-label">Peringkat</div>
                <div class="detail-value" style="font-size: 1.2rem; margin-top: 0.5rem;">
                    ${rankIcon}
                </div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Layanan</div>
                <div class="detail-value">${route.service_name}</div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Rute</div>
                <div class="detail-value"><strong>${route.route}</strong></div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Total Pemesanan</div>
                <div class="detail-value">${route.total_bookings.toLocaleString()} pemesanan</div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Popularitas</div>
                <div class="progress-container">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ${percentage}%"></div>
                    </div>
                    <div style="margin-top: 0.5rem; color: var(--gray); font-size: 0.875rem;">
                        ${percentage.toFixed(1)}% dari rute paling populer
                    </div>
                </div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Status Popularitas</div>
                <div class="detail-value">
                    ${rank === 1 ? 'Paling Populer' : 
                      rank === 2 ? 'Sangat Populer' : 
                      rank === 3 ? 'Cukup Populer' : 'Populer'}
                </div>
            </div>
        `;
        
        document.getElementById('routeDetailsContent').innerHTML = modalContent;
        document.getElementById('routeDetailsModal').classList.add('active');
    }

    function closeRouteDetails() {
        document.getElementById('routeDetailsModal').classList.remove('active');
    }

    // Close modal when clicking outside
    document.getElementById('routeDetailsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeRouteDetails();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeRouteDetails();
        }
    });
</script>

<?php
closeDBConnection($conn);
include '../includes/footer.php';
?>