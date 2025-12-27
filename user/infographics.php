<?php
$page_title = 'Statistik & Infografis';
require_once '../config/config.php';
require_login();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$is_admin = is_admin();

// Handle rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    $service_id = intval($_POST['service_id']);
    $rating = intval($_POST['rating']);
    $comment = clean_input($_POST['comment']);

    // Check if user already rated this service
    $existing = $conn->query("SELECT id FROM service_ratings WHERE user_id = $user_id AND service_id = $service_id");
    if ($existing->num_rows > 0) {
        $error = 'Anda sudah memberikan rating untuk layanan ini!';
    } else {
        // Insert rating
        $comment_escaped = $conn->real_escape_string($comment);
        $insert_query = "INSERT INTO service_ratings (user_id, service_id, rating, comment, created_at) 
                        VALUES ($user_id, $service_id, $rating, '$comment_escaped', NOW())";

        if ($conn->query($insert_query)) {
            $rating_id = $conn->insert_id;
            
            // Handle image upload
            if (!empty($_FILES['rating_image']['name']) && $_FILES['rating_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/ratings/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
                $file_type = $_FILES['rating_image']['type'];
                
                if (in_array($file_type, $allowed_types) && $_FILES['rating_image']['size'] <= 5242880) {
                    $file_extension = pathinfo($_FILES['rating_image']['name'], PATHINFO_EXTENSION);
                    $file_name = time() . '_' . uniqid() . '.' . $file_extension;
                    
                    if (move_uploaded_file($_FILES['rating_image']['tmp_name'], $upload_dir . $file_name)) {
                        $file_path = 'ratings/' . $file_name;
                        $file_size = $_FILES['rating_image']['size'];
                        
                        $media_query = "INSERT INTO experience_media (experience_id, media_type, file_path, file_size, created_at) 
                                       VALUES ($rating_id, 'photo', '$file_path', $file_size, NOW())";
                        $conn->query($media_query);
                    }
                }
            }

            // Handle video upload
            if (!empty($_FILES['rating_video']['name']) && $_FILES['rating_video']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/ratings/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                $allowed_types = ['video/mp4', 'video/mpeg', 'video/quicktime', 'video/x-msvideo'];
                $file_type = $_FILES['rating_video']['type'];
                
                if (in_array($file_type, $allowed_types) && $_FILES['rating_video']['size'] <= 52428800) {
                    $file_extension = pathinfo($_FILES['rating_video']['name'], PATHINFO_EXTENSION);
                    $file_name = time() . '_video_' . uniqid() . '.' . $file_extension;
                    
                    if (move_uploaded_file($_FILES['rating_video']['tmp_name'], $upload_dir . $file_name)) {
                        $file_path = 'ratings/' . $file_name;
                        $file_size = $_FILES['rating_video']['size'];
                        
                        $media_query = "INSERT INTO experience_media (experience_id, media_type, file_path, file_size, created_at) 
                                       VALUES ($rating_id, 'video', '$file_path', $file_size, NOW())";
                        $conn->query($media_query);
                    }
                }
            }

            $success = 'Rating dan komentar Anda telah dikirim!';
        } else {
            $error = 'Gagal mengirim rating: ' . $conn->error;
        }
    }
}

// Handle admin reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reply']) && $is_admin) {
    $rating_id = intval($_POST['rating_id']);
    $reply_text = clean_input($_POST['reply_text']);
    $reply_text_escaped = $conn->real_escape_string($reply_text);

    $insert_reply = "INSERT INTO rating_replies (rating_id, user_id, reply_text, created_at) 
                    VALUES ($rating_id, $user_id, '$reply_text_escaped', NOW())";

    if ($conn->query($insert_reply)) {
        $success = 'Balasan Anda telah dikirim!';
    } else {
        $error = 'Gagal mengirim balasan: ' . $conn->error;
    }
}

// Get user's services for rating form (only services they haven't rated yet)
$user_services = $conn->query("SELECT DISTINCT s.id, s.service_name, s.route 
                              FROM services s 
                              WHERE s.id NOT IN (SELECT service_id FROM service_ratings WHERE user_id = $user_id)
                              ORDER BY s.service_name");

// Get all ratings with media
$ratings_query = "SELECT sr.*, u.full_name, s.service_name, s.id as service_id
                 FROM service_ratings sr
                 JOIN users u ON sr.user_id = u.id
                 JOIN services s ON sr.service_id = s.id
                 ORDER BY sr.created_at DESC";
$ratings = $conn->query($ratings_query);

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

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    .statistics-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1.5rem;
        margin: 2rem 0;
    }
      @media (max-width: 768px) {
    #reviews h3 {
        font-size: 1.25rem;
        padding: 0 1rem;
        margin-left: 1rem;
    }
}

    .statistic-item {
        background: white;
        padding: 2rem;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s, box-shadow 0.3s;
    }

    .statistic-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
    }

    .statistic-item h3 {
        font-size: 2.5rem;
        color: var(--primary);
        margin-bottom: 0.5rem;
    }

    .statistic-item p {
        color: var(--gray);
        font-size: 1rem;
        margin: 0;
    }

    .rating-form {
        background: white;
        padding: 2rem;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        margin-bottom: 2rem;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: var(--dark);
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 1rem;
        font-family: inherit;
        box-sizing: border-box;
    }

    .form-group textarea {
        resize: vertical;
        min-height: 120px;
    }

    .star-rating {
        display: flex;
        gap: 0.5rem;
        font-size: 2rem;
    }

    .star {
        cursor: pointer;
        color: #ddd;
        transition: all 0.2s;
    }

    .star:hover,
    .star.active {
        color: #ffc107;
    }

    .ratings-list {
        margin-top: 2rem;
    }

    .rating-card {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        margin-bottom: 1.5rem;
    }

    .rating-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .rating-user {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .rating-user-info h4 {
        margin: 0 0 0.25rem 0;
        color: var(--dark);
    }

    .rating-user-info small {
        color: var(--gray);
    }

    .rating-stars {
        color: #ffc107;
        font-size: 1.2rem;
    }

    .rating-content {
        margin: 1rem 0;
    }

    .rating-comment {
        color: var(--dark);
        line-height: 1.6;
        margin-bottom: 1rem;
    }

    .rating-media {
        display: flex;
        gap: 1rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }

    .rating-image {
        max-width: 200px;
        border-radius: 8px;
        cursor: pointer;
        transition: transform 0.2s;
    }

    .rating-image:hover {
        transform: scale(1.05);
    }

    .rating-video {
        max-width: 300px;
        border-radius: 8px;
    }

    .rating-replies {
        background: var(--light);
        padding: 1rem;
        border-radius: 8px;
        margin-top: 1rem;
    }

    .reply-item {
        background: white;
        padding: 1rem;
        border-radius: 6px;
        margin-bottom: 0.75rem;
    }

    .reply-item:last-child {
        margin-bottom: 0;
    }

    .reply-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }

    .reply-header strong {
        color: var(--primary);
    }

    .reply-header small {
        color: var(--gray);
    }

    .reply-text {
        color: var(--dark);
        line-height: 1.6;
    }

    .reply-form {
        background: white;
        padding: 1rem;
        border-radius: 6px;
        margin-top: 1rem;
    }

    .reply-form textarea {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-family: inherit;
        font-size: 0.95rem;
        resize: vertical;
        min-height: 80px;
        box-sizing: border-box;
    }

    .reply-form button {
        background: var(--primary);
        color: white;
        border: none;
        padding: 0.6rem 1.5rem;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        margin-top: 0.5rem;
        transition: all 0.3s;
    }

    .reply-form button:hover {
        background: var(--secondary);
        transform: translateY(-2px);
    }

    .btn-submit {
        background: var(--primary);
        color: white;
        border: none;
        padding: 0.875rem 2rem;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        font-size: 1rem;
        transition: all 0.3s;
        width: 100%;
    }

    .btn-submit:hover {
        background: var(--secondary);
        transform: translateY(-2px);
    }

    .alert {
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }

    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }

    .alert-danger {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }

    .key-metrics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .card {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .card-header {
        margin-bottom: 1rem;
    }

    .card-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--dark);
        margin: 0;
    }

    .chart-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }

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

    .table-responsive-container {
        width: 100%;
        margin-top: 1rem;
        overflow-x: auto;
    }

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
        white-space: nowrap;
        min-width: 70px;
        min-height: 44px;
    }

    .mobile-route-details-btn:hover {
        background: var(--secondary);
    }

    .performance-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
        margin-top: 2rem;
    }

    .details-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
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
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }

    .modal-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--light);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: white;
        border-radius: 12px 12px 0 0;
        flex-shrink: 0;
    }

    .modal-header h3 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--dark);
    }

    .modal-body {
        padding: 1.5rem;
        overflow-y: auto;
        flex: 1;
    }

    .modal-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--light);
        background: white;
        flex-shrink: 0;
        display: flex;
        justify-content: center;
        border-radius: 0 0 12px 12px;
    }

    .modal-footer-btn {
        background: var(--primary);
        color: white;
        border: none;
        padding: 0.75rem 2rem;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        width: 100%;
        max-width: 200px;
    }

    .modal-footer-btn:hover {
        background: var(--secondary);
        transform: translateY(-2px);
    }

    .modal-close {
        background: var(--light);
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--gray);
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }

    .modal-close:hover {
        background: #e5e7eb;
        transform: rotate(90deg);
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
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }

    .detail-value {
        color: var(--gray);
        font-size: 1rem;
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

    @media (min-width: 769px) {
        .mobile-route-item {
            display: none !important;
        }
    }

    @media (max-width: 768px) {
        .tab-btn {
            padding: 1rem 0.5rem;
            font-size: 0.9rem;
        }

        .rating-form,
        .rating-card {
            padding: 1.25rem;
        }

        .key-metrics-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .chart-grid {
            grid-template-columns: 1fr;
        }

        .table-responsive-container {
            display: none !important;
        }

        .mobile-route-item {
            display: block !important;
        }

        .performance-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 480px) {
        .tab-btn span {
            font-size: 0.8rem;
        }

        .key-metrics-grid {
            grid-template-columns: 1fr;
        }

        .statistics-container {
            grid-template-columns: 1fr;
        }
    }
</style>
<!-- Hero Section -->
<section style="padding: 2rem 0; background: var(--light);">
    <div class="container">
        <h1 style="font-size: 2rem; margin-bottom: 0.5rem;">Statistik & Infografis</h1>
        <p style="color: var(--gray);">Lihat statistik platform dan berikan rating untuk layanan kami</p>
    </div>
</section>

<!-- Tab Navigation -->
<div class="tab-navigation">
    <div class="container">
        <button class="tab-btn active" onclick="switchTab('statistics')">
            <i class="fas fa-chart-bar"></i> <span>Statistik</span>
        </button>
        <?php if (!$is_admin): ?>
            <button class="tab-btn" onclick="switchTab('rating')">
                <i class="fas fa-star"></i> <span>Rating & Komentar</span>
            </button>
        <?php endif; ?>
        <button class="tab-btn" onclick="switchTab('reviews')">
            <i class="fas fa-comments"></i> <span>Ulasan Pengguna</span>
        </button>
    </div>
</div>

<!-- Statistics Tab -->
<section class="tab-content active" id="statistics">
    <div class="container" style="padding: 2rem 0;">
        <!-- Simple Statistics Display -->
        <div class="statistics-container">
            <div class="statistic-item">
                <h3>5.0</h3>
                <p>Rating Rata-rata</p>
            </div>
            <div class="statistic-item">
                <h3>100+</h3>
                <p>Ulasan</p>
            </div>
            <div class="statistic-item">
                <h3>50+</h3>
                <p>Penumpang</p>
            </div>
            <div class="statistic-item">
                <h3>15</h3>
                <p>Rute Aktif</p>
            </div>
            <div class="statistic-item">
                <h3>100%</h3>
                <p>Armada Terawat</p>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="key-metrics-grid">
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
        <div class="chart-grid">
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

<!-- Rating Tab (User Only) -->
<?php if (!$is_admin): ?>
    <section class="tab-content" id="rating">
        <div class="container" style="padding: 2rem 0;">
            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="rating-form">
                <h3><i class="fas fa-star"></i> Berikan Rating & Komentar</h3>
                <p style="color: var(--gray); margin-bottom: 1.5rem;">Bagikan pengalaman Anda menggunakan layanan kami</p>

                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="service_id">Pilih Layanan *</label>
                        <select name="service_id" id="service_id" required>
                            <option value="">-- Pilih Layanan --</option>
                            <?php while ($service = $user_services->fetch_assoc()): ?>
                                <option value="<?php echo $service['id']; ?>">
                                    <?php echo htmlspecialchars($service['service_name']); ?> (<?php echo htmlspecialchars($service['route']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Rating Layanan *</label>
                        <div class="star-rating" id="ratingStars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star" data-rating="<?php echo $i; ?>" onclick="setRating(<?php echo $i; ?>)">
                                    <i class="fas fa-star"></i>
                                </span>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="rating" id="ratingValue" value="0" required>
                    </div>

                    <div class="form-group">
                        <label for="comment">Komentar Anda</label>
                        <textarea name="comment" id="comment" placeholder="Ceritakan pengalaman Anda menggunakan layanan kami..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="rating_image">Upload Foto (Opsional)</label>
                        <input type="file" name="rating_image" id="rating_image" accept="image/*">
                        <small style="color: var(--gray); display: block; margin-top: 0.25rem;">Ukuran maksimal: 5MB</small>
                    </div>

                    <div class="form-group">
                        <label for="rating_video">Upload Video (Opsional)</label>
                        <input type="file" name="rating_video" id="rating_video" accept="video/*">
                        <small style="color: var(--gray); display: block; margin-top: 0.25rem;">Ukuran maksimal: 50MB</small>
                    </div>

                    <button type="submit" name="submit_rating" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Kirim Rating & Komentar
                    </button>
                </form>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- Reviews Tab -->
<section class="tab-content" id="reviews">
    <div class="container" style="padding: 2rem 0;">
        <h3 style="margin-bottom: 1.5rem;"><i class="fas fa-comments"></i> Ulasan Pengguna</h3>

        <div class="ratings-list">
            <?php if ($ratings->num_rows > 0): ?>
                <?php while ($rating = $ratings->fetch_assoc()): 
                    // Get media for this rating from experience_media table
                    $rating_id = $rating['id'];
                    $media_query = "SELECT * FROM experience_media WHERE experience_id = $rating_id ORDER BY created_at ASC";
                    $media_result = $conn->query($media_query);
                    $media_items = [];
                    if ($media_result) {
                        while ($media = $media_result->fetch_assoc()) {
                            $media_items[] = $media;
                        }
                    }
                ?>
                    <div class="rating-card">
                        <div class="rating-header">
                            <div class="rating-user">
                                <div class="rating-user-info">
                                    <h4><?php echo htmlspecialchars($rating['full_name']); ?></h4>
                                    <small><?php echo format_datetime($rating['created_at']); ?></small>
                                    <br>
                                    <small style="color: var(--primary);">
                                        <i class="fas fa-bus"></i> <?php echo htmlspecialchars($rating['service_name']); ?>
                                    </small>
                                </div>
                            </div>
                            <div class="rating-stars">
                                <?php for ($i = 0; $i < $rating['rating']; $i++): ?>
                                    <i class="fas fa-star"></i>
                                <?php endfor; ?>
                                <?php for ($i = $rating['rating']; $i < 5; $i++): ?>
                                    <i class="far fa-star"></i>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="rating-content">
                            <?php if (!empty($rating['comment'])): ?>
                                <div class="rating-comment">
                                    <?php echo nl2br(htmlspecialchars($rating['comment'])); ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($media_items)): ?>
                                <div class="rating-media">
                                    <?php foreach ($media_items as $media): ?>
                                        <?php if ($media['media_type'] === 'photo'): ?>
                                            <img src="<?php echo SITE_URL; ?>/uploads/<?php echo htmlspecialchars($media['file_path']); ?>"
                                                alt="Rating image" class="rating-image" onclick="openLightbox(this.src)">
                                        <?php elseif ($media['media_type'] === 'video'): ?>
                                            <video class="rating-video" controls>
                                                <source src="<?php echo SITE_URL; ?>/uploads/<?php echo htmlspecialchars($media['file_path']); ?>">
                                                Your browser does not support the video tag.
                                            </video>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Replies -->
                        <?php
                        $replies = $conn->query("SELECT rr.*, u.full_name FROM rating_replies rr 
                                               JOIN users u ON rr.user_id = u.id 
                                               WHERE rr.rating_id = " . $rating['id'] . " 
                                               ORDER BY rr.created_at ASC");
                        ?>

                        <?php if ($replies->num_rows > 0 || $is_admin): ?>
                            <div class="rating-replies">
                                <strong style="color: var(--dark);">
                                    <i class="fas fa-reply"></i> Balasan (<?php echo $replies->num_rows; ?>)
                                </strong>

                                <?php while ($reply = $replies->fetch_assoc()): ?>
                                    <div class="reply-item">
                                        <div class="reply-header">
                                            <strong><?php echo htmlspecialchars($reply['full_name']); ?></strong>
                                            <small><?php echo format_datetime($reply['created_at']); ?></small>
                                        </div>
                                        <div class="reply-text">
                                            <?php echo nl2br(htmlspecialchars($reply['reply_text'])); ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>

                                <?php if ($is_admin): ?>
                                    <form method="POST" class="reply-form">
                                        <input type="hidden" name="rating_id" value="<?php echo $rating['id']; ?>">
                                        <textarea name="reply_text" placeholder="Tulis balasan Anda..." required></textarea>
                                        <button type="submit" name="submit_reply">
                                            <i class="fas fa-paper-plane"></i> Kirim Balasan
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="card" style="text-align: center; padding: 3rem;">
                    <p style="color: var(--gray); font-size: 1.1rem;">
                        <i class="fas fa-inbox" style="font-size: 3rem; color: var(--light); margin-bottom: 1rem; display: block;"></i>
                        Belum ada ulasan pengguna
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Lightbox for image viewing -->
<div id="lightbox" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 10000; align-items: center; justify-content: center;">
    <div style="position: relative; max-width: 90%; max-height: 90%;">
        <img id="lightbox-image" src="" style="max-width: 100%; max-height: 90vh; border-radius: 8px;">
        <button onclick="closeLightbox()" style="position: absolute; top: -40px; right: 0; background: white; border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; font-size: 1.5rem;">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>

<!-- Chart.js Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

<?php include '../includes/footer.php'; ?>
<script>
    // Tab switching function
function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(tab).classList.add('active');
    event.target.closest('.tab-btn').classList.add('active');
}

// Star rating function
function setRating(rating) {
    document.getElementById('ratingValue').value = rating;
    const stars = document.querySelectorAll('.star');
    stars.forEach((star, index) => {
        if (index < rating) {
            star.classList.add('active');
        } else {
            star.classList.remove('active');
        }
    });
}

// Lightbox functions
function openLightbox(src) {
    document.getElementById('lightbox-image').src = src;
    document.getElementById('lightbox').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    document.getElementById('lightbox').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Close lightbox when clicking outside the image
document.getElementById('lightbox').addEventListener('click', function(e) {
    if (e.target === this) closeLightbox();
});

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeLightbox();
    }
});

// Chart.js - Monthly Bookings Chart
const monthlyCtx = document.getElementById('monthlyChart');
if (monthlyCtx) {
    new Chart(monthlyCtx.getContext('2d'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($months); ?>,
            datasets: [{
                label: 'Jumlah Pemesanan',
                data: <?php echo json_encode($counts); ?>,
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 3,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleFont: {
                        size: 14
                    },
                    bodyFont: {
                        size: 13
                    },
                    callbacks: {
                        label: function(context) {
                            return 'Pemesanan: ' + context.parsed.y;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        font: {
                            size: 12
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    ticks: {
                        font: {
                            size: 12
                        }
                    },
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

// Chart.js - Status Distribution Chart
const statusCtx = document.getElementById('statusChart');
if (statusCtx) {
    new Chart(statusCtx.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Terkonfirmasi', 'Dibatalkan', 'Check-in'],
            datasets: [{
                data: [
                    <?php echo $total_confirmed; ?>, 
                    <?php echo $total_cancelled; ?>, 
                    <?php echo $total_checked_in; ?>
                ],
                backgroundColor: [
                    '#10b981',
                    '#ef4444',
                    '#8b5cf6'
                ],
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        font: {
                            size: 12
                        },
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return label + ': ' + value + ' (' + percentage + '%)';
                        }
                    }
                }
            },
            cutout: '60%'
        }
    });
}

// Form validation for rating submission
document.addEventListener('DOMContentLoaded', function() {
    const ratingForm = document.querySelector('form[method="POST"]');
    
    if (ratingForm && ratingForm.querySelector('[name="submit_rating"]')) {
        ratingForm.addEventListener('submit', function(e) {
            const ratingValue = document.getElementById('ratingValue').value;
            const serviceId = document.getElementById('service_id').value;
            
            if (!serviceId) {
                e.preventDefault();
                alert('Silakan pilih layanan terlebih dahulu!');
                return false;
            }
            
            if (ratingValue === '0' || !ratingValue) {
                e.preventDefault();
                alert('Silakan berikan rating dengan mengklik bintang!');
                return false;
            }
        });
    }
});

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
});
</script>