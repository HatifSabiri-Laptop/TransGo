<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';

define('SITE_NAME', 'TransGo');
define('SITE_URL', 'http://localhost:8000');
define('ADMIN_EMAIL', 'admin@transport.com');
// Timezone
date_default_timezone_set('Asia/Jakarta');

// Enable error logging (for debugging - remove in production)
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Security: Prevent XSS
function clean_input($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Check if user is logged in
function is_logged_in()
{
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function is_admin()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Check if user is staff or admin
function is_staff()
{
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'staff']);
}

// Redirect if not logged in
function require_login()
{
    if (!is_logged_in()) {
        header('Location: ' . SITE_URL . '/auth/login.php');
        exit();
    }
}

// Redirect if not admin
function require_admin()
{
    if (
        !isset($_SESSION['user_id']) ||
        !isset($_SESSION['role']) ||
        $_SESSION['role'] !== 'admin'
    ) {
        header("Location: " . SITE_URL . "/auth/login.php");
        exit();
    }
}

// Generate booking code
function generate_booking_code()
{
    return 'TRN' . date('Ymd') . rand(1000, 9999);
}

// Format currency
function format_currency($amount)
{
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Format datetime
function format_datetime($datetime, $format = 'd M Y H:i')
{
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return '-';
    }
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return '-';
    }
    return date($format, $timestamp);
}

function format_date($date, $format = 'd M Y')
{
    if (empty($date) || $date === '0000-00-00') {
        return '-';
    }
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return '-';
    }
    return date($format, $timestamp);
}

// ✅ FIXED: Log activity using ActivityLogger class
function log_activity($conn, $userId, $action, $description, $metadata = null) {
    try {
        // Ensure ActivityLogger class is loaded
        if (!class_exists('ActivityLogger')) {
            require_once __DIR__ . '/activity_logger.php';
        }
        
        // Validate connection
        if (!$conn || !($conn instanceof mysqli)) {
            error_log("log_activity: Invalid database connection");
            return false;
        }
        
        // Check connection is alive
        if (!$conn->ping()) {
            error_log("log_activity: Database connection is dead");
            return false;
        }
        
        // Create logger instance
        $logger = new ActivityLogger($conn);
        
        // Log the activity
        $result = $logger->log($userId, $action, $description, null, $metadata);
        
        // Debug logging (comment out in production)
        if (!$result) {
            error_log("log_activity FAILED: User=$userId, Action=$action, Desc=$description");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("log_activity EXCEPTION: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

// ✅ FIXED: Check if current user has been deleted
function check_user_deleted() {
    if (isset($_SESSION['user_id'])) {
        $conn = getDBConnection();
        $user_id = $_SESSION['user_id'];
        
        $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // User has been deleted
            $stmt->close();
            $conn->close();
            
            // Clear session
            session_unset();
            session_destroy();
            
            // Set a flag to show alert on login page
            session_start();
            $_SESSION['user_deleted_alert'] = true;
            
            // Redirect without JavaScript
            header('Location: ' . SITE_URL . '/auth/login.php');
            exit();
        }
        
        $stmt->close();
        $conn->close();
    }
}

// Call this function on every page load (but not in login.php to avoid loop)
if (!isset($_GET['page']) || $_GET['page'] !== 'login') {
    $current_file = basename($_SERVER['PHP_SELF']);
    if ($current_file !== 'login.php' && $current_file !== 'register.php' && $current_file !== 'test_log.php') {
        check_user_deleted();
    }
}

// Email configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('FROM_EMAIL', 'noreply@transgo.com');
define('FROM_NAME', 'TransGo Transportation');

// Send welcome email
function send_welcome_email($to_email, $full_name)
{
    $subject = "Selamat Datang di TransGo!";

    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #2563eb, #10b981); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9fafb; padding: 30px; }
            .button { display: inline-block; padding: 12px 30px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
            .footer { background: #1e293b; color: white; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🚌 Selamat Datang di TransGo!</h1>
            </div>
            <div class='content'>
                <h2>Halo, {$full_name}!</h2>
                <p>Terima kasih telah mendaftar di TransGo. Akun Anda telah berhasil dibuat!</p>
                
                <p><strong>Keuntungan yang Anda dapatkan:</strong></p>
                <ul>
                    <li>✅ Booking tiket dengan cepat dan mudah</li>
                    <li>✅ Riwayat perjalanan tersimpan otomatis</li>
                    <li>✅ Online check-in untuk menghemat waktu</li>
                    <li>✅ Notifikasi email untuk setiap transaksi</li>
                    <li>✅ Akses ke promo dan diskon eksklusif</li>
                </ul>
                
                <p>Mulai perjalanan Anda sekarang!</p>
                <a href='" . SITE_URL . "/user/reservation.php' class='button'>Pesan Tiket Sekarang</a>
                
                <p style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;'>
                    <small>Email: {$to_email}</small>
                </p>
            </div>
            <div class='footer'>
                <p>TransGo - Perjalanan Nyaman Dimulai Dari Sini</p>
                <p style='font-size: 12px; margin-top: 10px;'>
                    Butuh bantuan? Hubungi kami di " . ADMIN_EMAIL . "
                </p>
            </div>
        </div>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . FROM_NAME . " <" . FROM_EMAIL . ">" . "\r\n";

    return mail($to_email, $subject, $message, $headers);
}

// Send booking confirmation email
function send_booking_confirmation_email($to_email, $full_name, $booking_data)
{
    $subject = "Konfirmasi Booking - " . $booking_data['booking_code'];

    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #2563eb, #10b981); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9fafb; padding: 30px; }
            .booking-info { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .booking-code { font-size: 24px; font-weight: bold; color: #2563eb; text-align: center; padding: 20px; background: #eff6ff; border-radius: 8px; margin: 20px 0; }
            .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e5e7eb; }
            .button { display: inline-block; padding: 12px 30px; background: #10b981; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
            .footer { background: #1e293b; color: white; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>✅ Booking Berhasil!</h1>
            </div>
            <div class='content'>
                <h2>Halo, {$full_name}!</h2>
                <p>Terima kasih telah memesan tiket di TransGo. Berikut detail booking Anda:</p>
                
                <div class='booking-code'>
                    <div style='font-size: 14px; color: #64748b; margin-bottom: 5px;'>Kode Booking</div>
                    {$booking_data['booking_code']}
                </div>
                
                <div class='booking-info'>
                    <div class='detail-row'>
                        <strong>Layanan:</strong>
                        <span>{$booking_data['service_name']}</span>
                    </div>
                    <div class='detail-row'>
                        <strong>Rute:</strong>
                        <span>{$booking_data['route']}</span>
                    </div>
                    <div class='detail-row'>
                        <strong>Tanggal Perjalanan:</strong>
                        <span>{$booking_data['travel_date']}</span>
                    </div>
                    <div class='detail-row'>
                        <strong>Jumlah Penumpang:</strong>
                        <span>{$booking_data['num_passengers']} orang</span>
                    </div>
                    <div class='detail-row' style='font-size: 18px; border-bottom: none;'>
                        <strong>Total Bayar:</strong>
                        <strong style='color: #2563eb;'>{$booking_data['total_price']}</strong>
                    </div>
                </div>
                
                <p><strong>Langkah Selanjutnya:</strong></p>
                <ol>
                    <li>Simpan kode booking Anda</li>
                    <li>Lakukan check-in online 24 jam sebelum keberangkatan</li>
                    <li>Tunjukkan e-ticket saat keberangkatan</li>
                    <li>Datang minimal 30 menit sebelum jadwal</li>
                </ol>
                
                <div style='text-align: center;'>
                    <a href='" . SITE_URL . "/user/check-in.php' class='button'>Check-in Online</a>
                </div>
                
                <p style='margin-top: 30px; padding: 20px; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 5px;'>
                    <strong>⚠️ Penting:</strong> Simpan email ini sebagai bukti pemesanan. Jika ingin membatalkan booking, ajukan pembatalan melalui dashboard Anda.
                </p>
            </div>
            <div class='footer'>
                <p>TransGo - Perjalanan Nyaman Dimulai Dari Sini</p>
                <p style='font-size: 12px; margin-top: 10px;'>
                    Pertanyaan? Hubungi: " . ADMIN_EMAIL . " | WhatsApp: +62 812-3456-7890
                </p>
            </div>
        </div>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . FROM_NAME . " <" . FROM_EMAIL . ">" . "\r\n";

    return mail($to_email, $subject, $message, $headers);
}

// Send check-in confirmation email
function send_checkin_confirmation_email($to_email, $full_name, $booking_code)
{
    $subject = "Check-in Berhasil - " . $booking_code;

    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9fafb; padding: 30px; }
            .success-icon { font-size: 60px; text-align: center; margin: 20px 0; }
            .footer { background: #1e293b; color: white; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>✅ Check-in Berhasil!</h1>
            </div>
            <div class='content'>
                <div class='success-icon'>✓</div>
                <h2 style='text-align: center;'>Check-in Anda Telah Dikonfirmasi</h2>
                <p style='text-align: center;'>Halo, {$full_name}!</p>
                
                <p>Check-in untuk booking <strong>{$booking_code}</strong> telah berhasil!</p>
                
                <p><strong>Yang perlu Anda lakukan:</strong></p>
                <ul>
                    <li>📱 Simpan email ini atau screenshot sebagai bukti check-in</li>
                    <li>🕐 Datang ke terminal minimal 30 menit sebelum keberangkatan</li>
                    <li>🎫 Tunjukkan e-ticket ini ke petugas</li>
                    <li>🆔 Bawa identitas yang sesuai dengan data booking</li>
                </ul>
                
                <p style='margin-top: 30px; padding: 20px; background: #dbeafe; border-left: 4px solid #2563eb; border-radius: 5px;'>
                    <strong>💡 Tips:</strong> Pastikan Anda tiba tepat waktu. Bus akan berangkat sesuai jadwal.
                </p>
            </div>
            <div class='footer'>
                <p>Selamat Jalan! Semoga perjalanan Anda menyenangkan 🚌</p>
                <p style='font-size: 12px; margin-top: 10px;'>TransGo</p>
            </div>
        </div>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . FROM_NAME . " <" . FROM_EMAIL . ">" . "\r\n";

    return mail($to_email, $subject, $message, $headers);
}