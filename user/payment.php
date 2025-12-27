<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$page_title = 'Pembayaran';
require_once '../config/config.php';
require_login();

$conn = getDBConnection();
$error = '';

$booking_code = isset($_GET['booking']) ? clean_input($_GET['booking']) : '';

if (empty($booking_code)) {
    header('Location: ' . SITE_URL . '/user/reservation.php');
    exit();
}

// Get booking details
$stmt = $conn->prepare("
    SELECT r.*, s.service_name, s.route, s.departure_time, s.arrival_time 
    FROM reservations r 
    JOIN services s ON r.service_id = s.id 
    WHERE r.booking_code = ? AND r.user_id = ?
");
$stmt->bind_param("si", $booking_code, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: ' . SITE_URL . '/user/dashboard.php');
    exit();
}

$booking = $result->fetch_assoc();
$stmt->close();

// If already paid → go to ticket
if ($booking['payment_status'] === 'paid') {
    header('Location: ' . SITE_URL . '/user/ticket.php?booking=' . $booking_code);
    exit();
}

// Process payment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_code = isset($_POST['booking_code']) ? clean_input($_POST['booking_code']) : $booking_code;
    $payment_method = isset($_POST['payment_method']) ? clean_input($_POST['payment_method']) : '';
    $receipt_file = null;

    // Upload receipt
    if (!empty($_FILES['payment_receipt']['name'])) {
        $upload_dir = __DIR__ . '/../uploads/receipts/';

        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                $error = 'Gagal membuat direktori upload: ' . error_get_last()['message'];
            }
        }

        if (empty($error) && !is_writable($upload_dir)) {
            $error = 'Direktori upload tidak bisa ditulisi. Periksa permission folder.';
        }

        if (empty($error)) {
            if ($_FILES['payment_receipt']['error'] !== UPLOAD_ERR_OK) {
                $upload_errors = [
                    UPLOAD_ERR_INI_SIZE => 'File terlalu besar',
                    UPLOAD_ERR_FORM_SIZE => 'File terlalu besar',
                    UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
                    UPLOAD_ERR_NO_FILE => 'Tidak ada file yang dipilih',
                    UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ada',
                    UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file',
                    UPLOAD_ERR_EXTENSION => 'Ekstensi file tidak diizinkan'
                ];
                $error = $upload_errors[$_FILES['payment_receipt']['error']] ?? 'Upload error: ' . $_FILES['payment_receipt']['error'];
            } else {
                if ($_FILES['payment_receipt']['size'] > 5242880) {
                    $error = 'Ukuran file maksimal 5MB';
                } else {
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
                    $file_extension = strtolower(pathinfo($_FILES['payment_receipt']['name'], PATHINFO_EXTENSION));

                    if (!in_array($file_extension, $allowed_extensions)) {
                        $error = 'Format file tidak didukung. Hanya JPG, PNG, GIF, PDF yang diperbolehkan';
                    } else {
                        $filename = 'receipt_' . time() . '_' . uniqid() . '.' . $file_extension;
                        $target = $upload_dir . $filename;

                        if (move_uploaded_file($_FILES['payment_receipt']['tmp_name'], $target)) {
                            $receipt_file = $filename;
                            chmod($target, 0644);
                        } else {
                            $error = 'Gagal menyimpan file. Error: ' . error_get_last()['message'];
                        }
                    }
                }
            }
        }
    } else {
        $error = 'Harap pilih file bukti pembayaran';
    }

    // If no errors, update database
    if (empty($error)) {
        // Check if paid_at column exists
        $check_column = $conn->query("SHOW COLUMNS FROM reservations LIKE 'paid_at'");

        if ($check_column->num_rows > 0) {
            $sql = "UPDATE reservations
                    SET payment_status = 'paid',
                        payment_method = ?,
                        payment_receipt = ?,
                        paid_at = NOW()
                    WHERE booking_code = ?
                    AND user_id = ?
                    LIMIT 1";
        } else {
            $sql = "UPDATE reservations
                    SET payment_status = 'paid',
                        payment_method = ?,
                        payment_receipt = ?
                    WHERE booking_code = ?
                    AND user_id = ?
                    LIMIT 1";
        }

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $error = 'Database error: ' . $conn->error;
        } else {
            if ($check_column->num_rows > 0) {
                $stmt->bind_param(
                    "sssi",
                    $payment_method,
                    $receipt_file,
                    $booking_code,
                    $_SESSION['user_id']
                );
            } else {
                $stmt->bind_param(
                    "sssi",
                    $payment_method,
                    $receipt_file,
                    $booking_code,
                    $_SESSION['user_id']
                );
            }

            if (!$stmt->execute()) {
                $error = 'Gagal update database: ' . $stmt->error;
            } else if ($stmt->affected_rows === 0) {
                $error = 'Tidak ada data yang diupdate. Mungkin booking sudah dibayar atau tidak ditemukan.';
            }
            $stmt->close();
        }
    }

    // Handle AJAX response
    if (isset($_POST['ajax'])) {
        if (empty($error)) {
            echo json_encode([
                'success' => true,
                'redirect' => SITE_URL . '/user/ticket.php?booking=' . $booking_code
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => $error
            ]);
        }
        exit();
    } else {
        if (empty($error)) {
            header('Location: ' . SITE_URL . '/user/ticket.php?booking=' . $booking_code);
        } else {
            $_SESSION['payment_error'] = $error;
            header('Location: ' . SITE_URL . '/user/payment.php?booking=' . $booking_code);
        }
        exit();
    }
}

include '../includes/header.php';

if (isset($_SESSION['payment_error'])) {
    echo '<div class="alert alert-error" style="margin: 20px;">' . $_SESSION['payment_error'] . '</div>';
    unset($_SESSION['payment_error']);
}
?>

<style>
    .upload-section {
        display: none;
        animation: fadeIn 0.5s;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .upload-area {
        border: 2px dashed var(--primary);
        border-radius: 12px;
        padding: 2rem;
        text-align: center;
        background: #eff6ff;
        cursor: pointer;
        transition: all 0.3s;
    }

    .upload-area:hover {
        background: #dbeafe;
        border-color: #1d4ed8;
    }

    .upload-area.dragover {
        background: #dbeafe;
        border-color: #10b981;
    }

    .preview-image {
        max-width: 100%;
        max-height: 300px;
        margin: 1rem auto;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        object-fit: contain;
    }

    .processing-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.9);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }

    .processing-content {
        background: white;
        padding: 2rem;
        border-radius: 16px;
        text-align: center;
        max-width: 400px;
        width: 90%;
        margin: 0 1rem;
    }

    .spinner {
        border: 4px solid #f3f4f6;
        border-top: 4px solid var(--primary);
        border-radius: 50%;
        width: 60px;
        height: 60px;
        animation: spin 1s linear infinite;
        margin: 0 auto 1rem;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    /* Responsive Grid Layout */
    .payment-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
    }

    /* Desktop: payment methods (col1) + upload (col1), ringkasan (col2) */
    .payment-method-col {
        grid-column: 1;
        order: 1;
    }

    .booking-summary-col {
        grid-column: 2;
        order: 2;
    }

    #uploadSection {
        grid-column: 1;
        order: 2;
        margin-top: 1.5rem;
    }

    /* Payment Options Styling */
    .payment-category {
        margin-bottom: 2rem;
    }

    .payment-options {
        display: grid;
        gap: 1rem;
    }

    .payment-option {
        cursor: pointer;
    }

    .payment-option input[type="radio"] {
        display: none;
    }

    .payment-card {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        border: 2px solid var(--light);
        border-radius: 8px;
        transition: all 0.3s;
        background: white;
    }

    .payment-option:hover .payment-card {
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
    }

    .payment-option input[type="radio"]:checked+.payment-card {
        border-color: var(--primary);
        background: #eff6ff;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
    }

    .payment-logo {
        width: 85px;
        height: 53px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        flex-shrink: 0;
    }

    .payment-info {
        flex: 1;
        min-width: 0;
    }

    .payment-info strong {
        display: block;
        margin-bottom: 0.25rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .payment-info small {
        color: var(--gray);
        display: block;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .payment-check {
        color: transparent;
        font-size: 1.5rem;
        transition: all 0.3s;
        flex-shrink: 0;
    }

    .payment-option input[type="radio"]:checked+.payment-card .payment-check {
        color: var(--primary);
    }

    .truncate-text {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .summary-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.75rem;
        flex-wrap: wrap;
    }

    .summary-label {
        font-weight: bold;
        color: var(--dark);
        min-width: 120px;
    }

    .summary-value {
        color: var(--gray);
        text-align: right;
        flex: 1;
        min-width: 0;
        word-break: break-word;
    }

    /* Enhanced Mobile Responsiveness for Payment Page */
    @media (max-width: 768px) {
        .payment-logo strong {
            font-size: 0.75rem !important;
            padding: 0 3px;
        }


        /* FIX #1: Title positioning - prevent navbar overlap */
        section[style*="background: var(--light);"] {
            padding-top: 90px !important;
            padding-bottom: 1.5rem !important;
        }

        section[style*="background: var(--light);"] .container h1 {
            padding-left: 15px !important;
            padding-right: 15px !important;
        }

        section[style*="background: var(--light);"] .container p {
            padding-left: 15px !important;
            padding-right: 15px !important;
        }

        /* Fix payment method container */
        .payment-grid {
            grid-template-columns: 1fr !important;
            gap: 1.5rem;
            padding: 0;
        }

        /* FIX #3: Payment method names - ensure they fit properly */
        .payment-options {
            grid-template-columns: 1fr !important;
            gap: 0.70rem;
            padding: 0;
        }

        .payment-card {
            padding: 0.875rem !important;
            gap: 0.875rem !important;
            min-width: 0;
            overflow: visible;
            margin: 0;
        }

        /* FIX: Payment logo sizing for long names */
        .payment-logo {
            width: 85px !important;
            height: 50px !important;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.27rem;
        }

        .payment-logo strong {
            font-size: 0.80rem;
            text-align: center;
            line-height: 1.2;
            word-wrap: break-word;
            max-width: 100%;
            overflow: visible !important;
            white-space: normal !important;
            display: block;
            padding: 0 2px;
        }

        .payment-info {
            flex: 1;
            min-width: 0;
            overflow: hidden;
            padding-right: 0.5rem;
        }

        /* Allow text to wrap if needed on mobile */
        .payment-info strong,
        .payment-info small {
            font-size: 0.7rem !important;
            white-space: normal !important;
            overflow: visible !important;
            text-overflow: unset !important;
            display: block;
            word-break: break-word;
            line-height: 1.3;
        }

        .payment-info small {
            font-size: 0.8rem !important;
            margin-top: 0.25rem;
        }

        /* FIX: Keep payment method names on single line on mobile */
        .payment-option:has(input[value="Alfamart"]) .payment-info strong,
        .payment-option:has(input[value="Indomaret"]) .payment-info strong {
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }

        /* FIXED: "Pilih Metode Pembayaran" container */
        #paymentMethodSection .card {
            margin: 0;
            width: 100%;
            border-radius: 0;
        }

        #paymentMethodSection .card-header {
            border-radius: 0;
            padding: 1rem 15px !important;
        }

        /* Fix summary section */
        .payment-grid>div:last-child .card {
            margin: 0 auto;
            width: 100%;
            max-width: 400px;
        }

        .summary-item {
            flex-direction: row !important;
            align-items: center;
            gap: 0.5rem;
            padding: 10px 15px !important;
            border-bottom: 1px solid #f0f0f0;
        }

        .summary-label {
            width: 120px !important;
            text-align: left !important;
            flex-shrink: 0;
            font-size: 14px;
        }

        .summary-value {
            width: auto !important;
            text-align: right !important;
            flex: 1;
            font-size: 14px;
            word-break: break-word;
            min-width: 0;
        }

        /* FIXED: Center total price in summary */
        .payment-grid>div:last-child .card>div:last-child {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            text-align: center !important;
            padding: 15px !important;
        }

        .payment-grid>div:last-child .card>div:last-child .summary-item {
            justify-content: space-between;
            width: 100%;
            border-bottom: none;
            margin-bottom: 10px;
        }

        .payment-grid>div:last-child .card>div:last-child strong {
            font-size: 1.3rem !important;
            text-align: center;
            width: 100%;
            display: block;
        }

        /* Fix upload section */
        .upload-area {
            padding: 1.5rem;
            margin: 0 10px;
        }

        /* Fix booking code display */
        .summary-value[style*="word-break"] {
            font-size: 1.1rem !important;
            padding: 8px;
            text-align: center !important;
            display: block;
            margin: 0 auto;
        }

        /* FIXED: Form buttons */
        .upload-section>form>div:last-child {
            display: flex !important;
            flex-direction: column !important;
            gap: 1rem !important;
            padding: 20px 15px !important;
            background: #f8f9fa;
        }

        /* Ensure upload section stays on the left (same column as payment methods) */
        .upload-section {
            grid-column: 1;
            grid-row: 2;
        }

        .booking-summary {
            grid-column: 2;
            grid-row: 1 / 3;
        }

        .upload-section button,
        #paymentMethodSection button {
            width: 100% !important;
            margin: 0 !important;
            padding: 12px 16px !important;
            font-size: 14px;
            border-radius: 8px;
        }

        /* Fix processing overlay */
        .processing-content {
            width: 90% !important;
            margin: 0 5%;
            padding: 1.5rem;
        }

        #countdown {
            font-size: 1.5rem !important;
        }

        /* Fix card headers */
        .card-header h3 {
            font-size: 1.1rem !important;
            padding: 0.5rem 15px !important;
        }

        /* Adjust spacing */
        .payment-category {
            margin-bottom: 1.5rem !important;
            padding: 0 10px;
        }

        .payment-category h4 {
            font-size: 1rem !important;
            padding-bottom: 0.5rem;
            padding-left: 5px;
        }

        /* Mobile reorder */
        .payment-grid {
            display: grid;
            grid-template-columns: 1fr;
        }

        .payment-method-col {
            grid-column: auto;
            order: 2;
        }

        .booking-summary-col {
            grid-column: auto;
            order: 1;
        }

        #uploadSection {
            grid-column: auto;
            order: 3;
            margin-top: 1rem !important;
        }


        #paymentMethodSection .card-header {
            margin-top: 0.5rem;
        }

        .payment-card {
            min-width: 0;
            overflow: hidden;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .payment-info {
            word-break: break-word;
            white-space: normal;
        }

        /* Prevent horizontal scrolling */
        body {
            overflow-x: hidden;
            padding: 0;
            margin: 0;
        }

        .container {
            max-width: 100%;
            padding-left: 0;
            padding-right: 0;
            width: 100%;
        }

        section[style*="padding: 2rem 0;"] .container {
            padding-left: 15px;
            padding-right: 15px;
        }

        /* Footer text margin for mobile */
        footer .container p,
        footer .container a,
        footer .container h3,
        footer .container h4 {
            margin-left: 1rem !important;
        }

        footer .container ul li {
            margin-left: 1rem !important;
        }
    }

    /* For very small phones (iPhone SE, etc) */
    @media (max-width: 480px) {
        .card-body {
            padding: 1rem 0.75rem !important;
        }

        .upload-area {
            padding: 1rem;
        }

        .upload-area h4 {
            font-size: 1rem;
        }

        .upload-area p {
            font-size: 0.8rem;
        }

        #uploadSection {
            margin-top: 1.5rem !important;
        }

        .payment-grid>div .card {
            margin: 0;
            border-radius: 0;
            width: 100%;
        }

        .summary-item {
            padding: 8px 10px !important;
        }

        .summary-label {
            width: 100px !important;
            font-size: 13px;
        }

        .summary-value {
            font-size: 13px;
        }

        .payment-grid>div:last-child .card>div:last-child strong:last-child {
            font-size: 1.4rem !important;
            color: var(--primary);
            text-align: center;
            display: block;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-top: 5px;
            width: 100%;
        }

        /* Extra small logo adjustments for very long names */
        .payment-logo {
            width: 65px !important;
            height: 40px !important;
            padding: 0.2rem;
        }

        .payment-logo strong {
            font-size: 0.65rem !important;
            line-height: 1.1;
        }

        .payment-card {
            padding: 0.75rem !important;
            gap: 0.65rem !important;
        }

        .payment-info strong {
            font-size: 0.85rem !important;
        }

        .payment-info small {
            font-size: 0.75rem !important;
        }

        .payment-check {
            font-size: 1.2rem !important;
        }

        /* Ensure payment method section has proper spacing on top */
        #paymentMethodSection .card-header {
            margin-top: 0.75rem !important;
            padding-top: 0.75rem !important;
        }
    }

    /* Extra small devices (iPhone 5/SE, Galaxy Fold) */
    @media (max-width: 360px) {
        .payment-logo {
            width: 60px !important;
            height: 38px !important;
        }

        .payment-logo strong {
            font-size: 0.6rem !important;
        }

        .payment-card {
            padding: 0.65rem !important;
            gap: 0.6rem !important;
        }

        .payment-info strong {
            font-size: 0.8rem !important;
        }

        .payment-info small {
            font-size: 0.7rem !important;
        }

        .summary-label {
            width: 90px !important;
            font-size: 12px;
        }

        .summary-value {
            font-size: 12px;
        }
    }

    /* Desktop - ensure payment logos look good */
    @media (min-width: 769px) {
        .payment-logo strong {
            font-size: 0.9rem;
            white-space: nowrap;
        }
    }

    /* FIX #2: Upload section alignment - move to left on desktop */
    @media (min-width: 769px) {
        .upload-section {
            grid-column: 1;
            grid-row: 1;
        }

        .payment-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 480px) {
            .payment-logo strong {
                font-size: 0.65rem !important;
                line-height: 1.15;
                letter-spacing: -0.3px;
                padding: 0 4px;
            }
        }

        @media (max-width: 360px) {
            .payment-logo strong {
                font-size: 0.6rem !important;
                letter-spacing: -0.5px;
                padding: 0 3px;
            }
        }
</style>
<section style="padding: 2rem 0; background: var(--light);">
    <div class="container">
        <h1><i class="fas fa-credit-card"></i> Pembayaran</h1>
        <p style="color: var(--gray);">Pilih metode pembayaran dan upload bukti transfer</p>
    </div>
</section>

<section style="padding: 2rem 0;">
    <div class="container">
        <div class="payment-grid">
            <!-- Payment Methods -->
            <div class="payment-method-col">
                <div class="card" id="paymentMethodSection">
                    <div class="card-header" style="background: var(--primary); color: white;">
                        <h3 style="color: white; padding: 0.5rem; margin: 0; font-size: 1.25rem;">
                            <i class="fas fa-wallet"></i> Pilih Metode Pembayaran
                        </h3>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form id="paymentForm">
                        <input type="hidden" name="ajax" value="1">
                        <input type="hidden" name="booking_code" value="<?php echo htmlspecialchars($booking['booking_code']); ?>">
                        <input type="hidden" name="payment_method" id="selected_payment_method">

                        <!-- Bank Transfer -->
                        <div class="payment-category">
                            <h4 style="margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--light); font-size: 1.1rem;">
                                <i class="fas fa-university"></i> Transfer Bank
                            </h4>

                            <div class="payment-options">
                                <!-- BCA -->
                                <label class="payment-option">
                                    <input type="radio" name="payment_method_radio" value="BCA" required>
                                    <div class="payment-card">
                                        <div class="payment-logo" style="background: #003d7a;">
                                            <strong style="color: white; font-size: 1.2rem;">BCA</strong>
                                        </div>
                                        <div class="payment-info">
                                            <strong class="truncate-text">Bank Central Asia</strong>
                                            <small class="truncate-text">Transfer ke rekening BCA</small>
                                        </div>
                                        <i class="fas fa-check-circle payment-check"></i>
                                    </div>
                                </label>

                                <!-- Mandiri -->
                                <label class="payment-option">
                                    <input type="radio" name="payment_method_radio" value="Mandiri" required>
                                    <div class="payment-card">
                                        <div class="payment-logo" style="background: #003d7a;">
                                            <strong style="color: white; font-size: 0.9rem;">MANDIRI</strong>
                                        </div>
                                        <div class="payment-info">
                                            <strong class="truncate-text">Bank Mandiri</strong>
                                            <small class="truncate-text">Transfer ke rekening Mandiri</small>
                                        </div>
                                        <i class="fas fa-check-circle payment-check"></i>
                                    </div>
                                </label>

                                <!-- BRI -->
                                <label class="payment-option">
                                    <input type="radio" name="payment_method_radio" value="BRI" required>
                                    <div class="payment-card">
                                        <div class="payment-logo" style="background: #003d7a;">
                                            <strong style="color: white; font-size: 1.2rem;">BRI</strong>
                                        </div>
                                        <div class="payment-info">
                                            <strong class="truncate-text">Bank Rakyat Indonesia</strong>
                                            <small class="truncate-text">Transfer ke rekening BRI</small>
                                        </div>
                                        <i class="fas fa-check-circle payment-check"></i>
                                    </div>
                                </label>

                                <!-- BNI -->
                                <label class="payment-option">
                                    <input type="radio" name="payment_method_radio" value="BNI" required>
                                    <div class="payment-card">
                                        <div class="payment-logo" style="background: #e8750c;">
                                            <strong style="color: white; font-size: 1.2rem;">BNI</strong>
                                        </div>
                                        <div class="payment-info">
                                            <strong class="truncate-text">Bank Negara Indonesia</strong>
                                            <small class="truncate-text">Transfer ke rekening BNI</small>
                                        </div>
                                        <i class="fas fa-check-circle payment-check"></i>
                                    </div>
                                </label>

                                <!-- CIMB -->
                                <label class="payment-option">
                                    <input type="radio" name="payment_method_radio" value="CIMB" required>
                                    <div class="payment-card">
                                        <div class="payment-logo" style="background: #c8102e;">
                                            <strong style="color: white; font-size: 1rem;">CIMB</strong>
                                        </div>
                                        <div class="payment-info">
                                            <strong class="truncate-text">CIMB Niaga</strong>
                                            <small class="truncate-text">Transfer ke rekening CIMB</small>
                                        </div>
                                        <i class="fas fa-check-circle payment-check"></i>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- E-Wallet -->
                        <div class="payment-category" style="margin-top: 2rem;">
                            <h4 style="margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--light); font-size: 1.1rem;">
                                <i class="fas fa-qrcode"></i> E-Wallet & QRIS
                            </h4>

                            <div class="payment-options">
                                <!-- QRIS -->
                                <label class="payment-option">
                                    <input type="radio" name="payment_method_radio" value="QRIS" required>
                                    <div class="payment-card">
                                        <div class="payment-logo" style="background: linear-gradient(135deg, #00a3ff, #00c9ff);">
                                            <i class="fas fa-qrcode" style="color: white; font-size: 1.3rem;"></i>
                                        </div>
                                        <div class="payment-info">
                                            <strong class="truncate-text">QRIS</strong>
                                            <small class="truncate-text">Semua e-wallet & mobile banking</small>
                                        </div>
                                        <i class="fas fa-check-circle payment-check"></i>
                                    </div>
                                </label>

                                <!-- GoPay -->
                                <label class="payment-option">
                                    <input type="radio" name="payment_method_radio" value="GoPay" required>
                                    <div class="payment-card">
                                        <div class="payment-logo" style="background: #00aa13;">
                                            <strong style="color: white; font-size: 0.9rem;">GoPay</strong>
                                        </div>
                                        <div class="payment-info">
                                            <strong class="truncate-text">GoPay</strong>
                                            <small class="truncate-text">Bayar pakai GoPay</small>
                                        </div>
                                        <i class="fas fa-check-circle payment-check"></i>
                                    </div>
                                </label>

                                <!-- OVO -->
                                <label class="payment-option">
                                    <input type="radio" name="payment_method_radio" value="OVO" required>
                                    <div class="payment-card">
                                        <div class="payment-logo" style="background: #4c2c92;">
                                            <strong style="color: white; font-size: 0.9rem;">OVO</strong>
                                        </div>
                                        <div class="payment-info">
                                            <strong class="truncate-text">OVO</strong>
                                            <small class="truncate-text">Bayar pakai OVO</small>
                                        </div>
                                        <i class="fas fa-check-circle payment-check"></i>
                                    </div>
                                </label>

                                <!-- DANA -->
                                <label class="payment-option">
                                    <input type="radio" name="payment_method_radio" value="DANA" required>
                                    <div class="payment-card">
                                        <div class="payment-logo" style="background: #0081c9;">
                                            <strong style="color: white; font-size: 0.9rem;">DANA</strong>
                                        </div>
                                        <div class="payment-info">
                                            <strong class="truncate-text">DANA</strong>
                                            <small class="truncate-text">Bayar pakai DANA</small>
                                        </div>
                                        <i class="fas fa-check-circle payment-check"></i>
                                    </div>
                                </label>

                                <!-- ShopeePay -->
                                <label class="payment-option">
                                    <input type="radio" name="payment_method_radio" value="ShopeePay" required>
                                    <div class="payment-card">
                                        <div class="payment-logo" style="background: #ff5722;">
                                            <strong style="color: white; font-size: 0.8rem;">ShopeePay</strong>
                                        </div>
                                        <div class="payment-info">
                                            <strong class="truncate-text">ShopeePay</strong>
                                            <small class="truncate-text">Bayar pakai ShopeePay</small>
                                        </div>
                                        <i class="fas fa-check-circle payment-check"></i>
                                    </div>
                                </label>

                                <!-- LinkAja -->
                                <label class="payment-option">
                                    <input type="radio" name="payment_method_radio" value="LinkAja" required>
                                    <div class="payment-card">
                                        <div class="payment-logo" style="background: #e31e24;">
                                            <strong style="color: white; font-size: 0.8rem;">LinkAja</strong>
                                        </div>
                                        <div class="payment-info">
                                            <strong class="truncate-text">LinkAja</strong>
                                            <small class="truncate-text">Bayar pakai LinkAja</small>
                                        </div>
                                        <i class="fas fa-check-circle payment-check"></i>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Retail -->
                        <div class="payment-category" style="margin-top: 2rem;">
                            <h4 style="margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--light); font-size: 1.1rem;">
                                <i class="fas fa-store"></i> Bayar di Toko
                            </h4>

                            <div class="payment-options">
                                <!-- Lawson -->
                                <label class="payment-option">
                                    <input type="radio" name="payment_method_radio" value="Lawson" required>
                                    <div class="payment-card">
                                        <div class="payment-logo" style="background: #003da5;">
                                            <strong style="color: white; font-size: 0.7rem;">LAWSON</strong>
                                        </div>
                                        <div class="payment-info">
                                            <strong class="truncate-text">Lawson</strong>
                                            <small class="truncate-text">Bayar di kasir Lawson</small>
                                        </div>
                                        <i class="fas fa-check-circle payment-check"></i>
                                    </div>
                                </label>

                                <!-- Alfamart -->
                                <label class="payment-option">
                                    <input type="radio" name="payment_method_radio" value="Alfamart" required>
                                    <div class="payment-card">
                                        <div class="payment-logo" style="background: #ed1c24;">
                                            <strong style="color: white; font-size: 0.7rem;">ALFAMART</strong>
                                        </div>
                                        <div class="payment-info">
                                            <strong class="truncate-text">Alfamart</strong>
                                            <small class="truncate-text">Bayar di kasir Alfamart</small>
                                        </div>
                                        <i class="fas fa-check-circle payment-check"></i>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <button type="button" onclick="showUploadSection()" class="btn btn-primary" style="width: 100%; margin-top: 2rem; padding: 1rem; font-size: 1rem;">
                            <i class="fas fa-arrow-right"></i> Lanjut ke Upload Bukti
                        </button>
                    </form>
                </div>

                <!-- Upload Receipt Section -->
                <div class="card upload-section" id="uploadSection" style="margin-top: 1.5rem;">
                    <div class="card-header" style="background: var(--secondary); color: white;">
                        <h3 style="color: white; margin: 0; padding: 0.6rem; font-size: 1.25rem;">
                            <i class="fas fa-upload"></i> Upload Bukti Pembayaran
                        </h3>
                    </div>

                    <form id="uploadForm" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="booking_code" value="<?php echo htmlspecialchars($booking['booking_code']); ?>">
                        <input type="hidden" name="payment_method" id="payment_method_hidden">

                        <div class="upload-area" id="uploadArea" onclick="document.getElementById('fileInput').click()">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 2.5rem; color: var(--primary); margin-bottom: 1rem;"></i>
                            <h4 style="margin-bottom: 0.5rem; font-size: 1.1rem;">Klik atau Drag & Drop File</h4>
                            <p style="color: var(--gray); margin-top: 0.5rem; font-size: 0.9rem;">Format: JPG, PNG, PDF (Max 5MB)</p>
                            <input type="file" id="fileInput" name="payment_receipt" accept="image/*,.pdf" style="display: none;" onchange="previewFile(this)" required>
                        </div>

                        <div id="preview" style="display: none; margin-top: 1rem;">
                            <h4 style="font-size: 1rem; margin-bottom: 0.5rem;">Preview:</h4>
                            <img id="previewImage" class="preview-image" alt="Preview">
                            <button type="button" onclick="removeFile()" class="btn btn-secondary" style="margin-top: 1rem; font-size: 0.9rem;">
                                <i class="fas fa-times"></i> Ganti File
                            </button>
                        </div>

                        <div style="display: flex; gap: 1rem; margin-top: 2rem; flex-wrap: wrap;">
                            <button type="button" onclick="backToPaymentMethod()" class="btn btn-secondary" style="flex: 1; min-width: 120px; font-size: 0.9rem;">
                                <i class="fas fa-arrow-left"></i> Kembali
                            </button>
                            <button type="submit" class="btn btn-primary" style="flex: 2; min-width: 150px; font-size: 0.9rem;">
                                <i class="fas fa-check"></i> Konfirmasi Pembayaran
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Booking Summary -->
            <div class="booking-summary-col">
                <div class="card booking-summary" style="position: sticky; top: 100px;">
                    <div class="card-header">
                        <h3 class="card-title" style="font-size: 1.25rem;">Ringkasan Pesanan</h3>
                    </div>

                    <div style="background: var(--light); padding: 1rem; border-radius: 8px; margin-bottom: 1rem; text-align: center;">
                        <div style="font-size: 0.8rem; color: var(--gray); margin-bottom: 0.5rem;">Kode Booking</div>
                        <div style="font-size: 1.3rem; font-weight: bold; color: var(--primary); word-break: break-all;">
                            <?php echo htmlspecialchars($booking['booking_code']); ?>
                        </div>
                    </div>

                    <div class="summary-item">
                        <span class="summary-label">Layanan:</span>
                        <span class="summary-value truncate-text"><?php echo htmlspecialchars($booking['service_name']); ?></span>
                    </div>

                    <div class="summary-item">
                        <span class="summary-label">Rute:</span>
                        <span class="summary-value truncate-text"><?php echo htmlspecialchars($booking['route']); ?></span>
                    </div>

                    <div class="summary-item">
                        <span class="summary-label">Tanggal:</span>
                        <span class="summary-value truncate-text">
                            <?php
                            if (function_exists('format_date')) {
                                echo format_date($booking['travel_date']);
                            } else {
                                echo date('d F Y', strtotime($booking['travel_date']));
                            }
                            ?>
                        </span>
                    </div>

                    <div class="summary-item">
                        <span class="summary-label">Penumpang:</span>
                        <span class="summary-value truncate-text"><?php echo $booking['num_passengers']; ?> orang</span>
                    </div>

                    <?php if (!empty($booking['selected_seats'])): ?>
                        <div class="summary-item">
                            <span class="summary-label">Kursi:</span>
                            <span class="summary-value truncate-text"><?php echo htmlspecialchars($booking['selected_seats']); ?></span>
                        </div>
                    <?php endif; ?>

                    <div style="border-top: 2px solid var(--light); padding-top: 1rem; margin-top: 1rem;">
                        <div class="summary-item" style="margin-bottom: 0;">
                            <strong style="font-size: 1.1rem;">Total:</strong>
                            <strong style="font-size: 1.3rem; color: var(--primary); text-align: right;">
                                <?php
                                if (function_exists('format_currency')) {
                                    echo format_currency($booking['total_price']);
                                } else {
                                    echo 'Rp ' . number_format($booking['total_price'], 0, ',', '.');
                                }
                                ?>
                            </strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Processing Overlay -->
            <div class="processing-overlay" id="processingOverlay">
                <div class="processing-content">
                    <div class="spinner"></div>
                    <h3 style="font-size: 1.3rem; margin-bottom: 0.5rem;">Memproses Pembayaran...</h3>
                    <p style="color: var(--gray); margin-top: 1rem; font-size: 0.9rem;">Mohon tunggu sebentar</p>
                    <div id="countdown" style="font-size: 2rem; color: var(--primary); margin-top: 1rem; font-weight: bold;">10</div>
                </div>
            </div>

            <script>
                function showUploadSection() {
                    const selected = document.querySelector('input[name="payment_method_radio"]:checked');
                    if (!selected) {
                        alert('Pilih metode pembayaran terlebih dahulu!');
                        return;
                    }

                    document.getElementById('selected_payment_method').value = selected.value;
                    document.getElementById('payment_method_hidden').value = selected.value;
                    document.getElementById('paymentMethodSection').style.display = 'none';
                    document.getElementById('uploadSection').style.display = 'block';
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                }

                function backToPaymentMethod() {
                    document.getElementById('uploadSection').style.display = 'none';
                    document.getElementById('paymentMethodSection').style.display = 'block';
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                }

                function previewFile(input) {
                    if (input.files && input.files[0]) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            document.getElementById('previewImage').src = e.target.result;
                            document.getElementById('preview').style.display = 'block';
                            document.getElementById('uploadArea').style.display = 'none';
                        }
                        reader.readAsDataURL(input.files[0]);
                    }
                }

                function removeFile() {
                    document.getElementById('fileInput').value = '';
                    document.getElementById('preview').style.display = 'none';
                    document.getElementById('uploadArea').style.display = 'block';
                }

                // Drag & Drop
                const uploadArea = document.getElementById('uploadArea');
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    uploadArea.addEventListener(eventName, preventDefaults, false);
                });

                function preventDefaults(e) {
                    e.preventDefault();
                    e.stopPropagation();
                }

                ['dragenter', 'dragover'].forEach(eventName => {
                    uploadArea.addEventListener(eventName, () => uploadArea.classList.add('dragover'), false);
                });

                ['dragleave', 'drop'].forEach(eventName => {
                    uploadArea.addEventListener(eventName, () => uploadArea.classList.remove('dragover'), false);
                });

                uploadArea.addEventListener('drop', function(e) {
                    const dt = e.dataTransfer;
                    const files = dt.files;
                    document.getElementById('fileInput').files = files;
                    previewFile(document.getElementById('fileInput'));
                });

                // Form submit with 10-second wait RESTORED
                document.getElementById('uploadForm').addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);
                    formData.append('ajax', '1');

                    const overlay = document.getElementById('processingOverlay');
                    const countdown = document.getElementById('countdown');

                    overlay.style.display = 'flex';

                    let seconds = 10;
                    countdown.textContent = seconds;

                    const timer = setInterval(() => {
                        seconds--;
                        countdown.textContent = seconds;
                        if (seconds <= 0) {
                            clearInterval(timer);
                        }
                    }, 1000);

                    // Submit after 10 seconds
                    setTimeout(() => {
                        fetch(window.location.href, {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('Network response was not ok: ' + response.status);
                                }
                                return response.json();
                            })
                            .then(data => {
                                clearInterval(timer);
                                if (data.success) {
                                    window.location.href = data.redirect;
                                } else {
                                    alert(data.error || 'Gagal memproses pembayaran');
                                    overlay.style.display = 'none';
                                }
                            })
                            .catch(err => {
                                clearInterval(timer);
                                alert('Pembayaran gagal. Silakan coba lagi.');
                                overlay.style.display = 'none';
                                console.error('Error:', err);
                            });
                    }, 10000); // 10 seconds wait
                });
            </script>

            <?php
            if ($conn instanceof mysqli) {
                $conn->close();
            }
            include '../includes/footer.php';
            ?>