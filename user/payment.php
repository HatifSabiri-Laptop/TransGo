<?php
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
$stmt = $conn->prepare("SELECT r.*, s.service_name, s.route, s.departure_time, s.arrival_time 
    FROM reservations r 
    JOIN services s ON r.service_id = s.id 
    WHERE r.booking_code = ? AND r.user_id = ?");
$stmt->bind_param("si", $booking_code, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: ' . SITE_URL . '/user/dashboard.php');
    exit();
}

$booking = $result->fetch_assoc();
$stmt->close();

// Check if already paid
if ($booking['payment_status'] === 'paid') {
    header('Location: ' . SITE_URL . '/user/ticket.php?booking=' . $booking_code);
    exit();
}

// Get passenger names
$passenger_names = json_decode($booking['passenger_names'], true);

// Process payment with receipt upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_method'])) {
    $payment_method = clean_input($_POST['payment_method']);
    
    // Handle file upload
    $upload_dir = '../uploads/receipts/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $receipt_filename = '';
    if (isset($_FILES['payment_receipt']) && $_FILES['payment_receipt']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['payment_receipt']['tmp_name'];
        $file_name = $_FILES['payment_receipt']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Validate file type
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        if (in_array($file_ext, $allowed)) {
            $receipt_filename = $booking_code . '_' . time() . '.' . $file_ext;
            move_uploaded_file($file_tmp, $upload_dir . $receipt_filename);
        }
    }

    // Update reservation to paid
    $update = $conn->prepare("UPDATE reservations SET payment_status = 'paid', payment_method = ?, payment_receipt = ? WHERE booking_code = ?");
    $update->bind_param("sss", $payment_method, $receipt_filename, $booking_code);

    if ($update->execute()) {
        log_activity($conn, $_SESSION['user_id'], 'payment_completed', "Payment completed for: $booking_code via $payment_method");

        // Send confirmation email
        $booking_data = [
            'booking_code' => $booking_code,
            'service_name' => $booking['service_name'],
            'route' => $booking['route'],
            'travel_date' => format_date($booking['travel_date']),
            'num_passengers' => $booking['num_passengers'],
            'total_price' => format_currency($booking['total_price']),
            'passenger_names' => implode(', ', $passenger_names)
        ];

        send_booking_confirmation_email($booking['contact_email'], $booking['contact_name'], $booking_data);

        // Return success for AJAX
        if (isset($_POST['ajax'])) {
            echo json_encode(['success' => true, 'redirect' => SITE_URL . '/user/ticket.php?booking=' . $booking_code]);
            exit();
        }

        // Redirect to ticket page
        header('Location: ' . SITE_URL . '/user/ticket.php?booking=' . $booking_code);
        exit();
    } else {
        $error = 'Gagal memproses pembayaran.';
        if (isset($_POST['ajax'])) {
            echo json_encode(['success' => false, 'error' => $error]);
            exit();
        }
    }
    $update->close();
}

include '../includes/header.php';
?>

<style>
.upload-section {
    display: none;
    animation: fadeIn 0.5s;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.upload-area {
    border: 2px dashed var(--primary);
    border-radius: 12px;
    padding: 3rem;
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
    max-height: 400px;
    margin: 1rem auto;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.processing-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.8);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

.processing-content {
    background: white;
    padding: 3rem;
    border-radius: 16px;
    text-align: center;
    max-width: 400px;
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
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@media (max-width: 768px) {
    .payment-grid { grid-template-columns: 1fr !important; }
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
        <div class="payment-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
            <!-- Payment Methods -->
            <div>
                <div class="card" id="paymentMethodSection">
                    <div class="card-header" style="background: var(--primary); color: white;">
                        <h3 style="color: white; padding: 0.5rem; margin: 0;"><i class="fas fa-wallet"></i> Pilih Metode Pembayaran</h3>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form id="paymentForm">
                        <input type="hidden" name="ajax" value="1">
                        <input type="hidden" name="payment_method" id="selected_payment_method">
                        
                        <!-- Bank Transfer -->
                        <div class="payment-category">
                            <h4 style="margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--light);">
                                <i class="fas fa-university"></i> Transfer Bank
                            </h4>

                            <div class="payment-options">
                                <label class="payment-option">
                                    <input type="radio" name="payment_method_radio" value="BCA" required>
                                    <div class="payment-card">
                                        <div class="payment-logo" style="background: #003d7a;">
                                            <strong style="color: white; font-size: 1.5rem;">BCA</strong>
                                        </div>
                                        <div class="payment-info">
                                            <strong>Bank Central Asia</strong>
                                            <small>Transfer ke rekening BCA</small>
                                        </div>
                                        <i class="fas fa-check-circle payment-check"></i>
                                    </div>
                                </label>

                                <label class="payment-option">
                                    <input type="radio" name="payment_method_radio" value="Mandiri" required>
                                    <div class="payment-card">
                                        <div class="payment-logo" style="background: #003d7a;">
                                            <strong style="color: white; font-size: 1.1rem;">MANDIRI</strong>
                                        </div>
                                        <div class="payment-info">
                                            <strong>Bank Mandiri</strong>
                                            <small>Transfer ke rekening Mandiri</small>
                                        </div>
                                        <i class="fas fa-check-circle payment-check"></i>
                                    </div>
                                </label>

                                <label class="payment-option">
                                    <input type="radio" name="payment_method_radio" value="BRI" required>
                                    <div class="payment-card">
                                        <div class="payment-logo" style="background: #003d7a;">
                                            <strong style="color: white; font-size: 1.5rem;">BRI</strong>
                                        </div>
                                        <div class="payment-info">
                                            <strong>Bank Rakyat Indonesia</strong>
                                            <small>Transfer ke rekening BRI</small>
                                        </div>
                                        <i class="fas fa-check-circle payment-check"></i>
                                    </div>
                                </label>

                                <label class="payment-option">
                                    <input type="radio" name="payment_method_radio" value="BNI" required>
                                    <div class="payment-card">
                                        <div class="payment-logo" style="background: #e8750c;">
                                            <strong style="color: white; font-size: 1.5rem;">BNI</strong>
                                        </div>
                                        <div class="payment-info">
                                            <strong>Bank Negara Indonesia</strong>
                                            <small>Transfer ke rekening BNI</small>
                                        </div>
                                        <i class="fas fa-check-circle payment-check"></i>
                                    </div>
                                </label>

                                <label class="payment-option">
                                    <input type="radio" name="payment_method_radio" value="CIMB" required>
                                    <div class="payment-card">
                                        <div class="payment-logo" style="background: #c8102e;">
                                            <strong style="color: white; font-size: 1.2rem;">CIMB</strong>
                                        </div>
                                        <div class="payment-info">
                                            <strong>CIMB Niaga</strong>
                                            <small>Transfer ke rekening CIMB</small>
                                        </div>
                                        <i class="fas fa-check-circle payment-check"></i>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- E-Wallet -->
                        <div class="payment-category" style="margin-top: 2rem;">
                            <h4 style="margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--light);">
                                <i class="fas fa-qrcode"></i> E-Wallet & QRIS
                            </h4>

                            <div class="payment-options">
                                <label class="payment-option">
                                    <input type="radio" name="payment_method_radio" value="QRIS" required>
                                    <div class="payment-card">
                                        <div class="payment-logo" style="background: linear-gradient(135deg, #00a3ff, #00c9ff);">
                                            <i class="fas fa-qrcode" style="color: white; font-size: 1.5rem;"></i>
                                        </div>
                                        <div class="payment-info">
                                            <strong>QRIS</strong>
                                            <small>Semua e-wallet & mobile banking</small>
                                        </div>
                                        <i class="fas fa-check-circle payment-check"></i>
                                    </div>
                                </label>

                                <label class="payment-option">
                                    <input type="radio" name="payment_method_radio" value="GoPay" required>
                                    <div class="payment-card">
                                        <div class="payment-logo" style="background: #00aa13;">
                                            <strong style="color: white;">GoPay</strong>
                                        </div>
                                        <div class="payment-info">
                                            <strong>GoPay</strong>
                                            <small>Bayar pakai GoPay</small>
                                        </div>
                                        <i class="fas fa-check-circle payment-check"></i>
                                    </div>
                                </label>

                                <label class="payment-option">
                                    <input type="radio" name="payment_method_radio" value="OVO" required>
                                    <div class="payment-card">
                                        <div class="payment-logo" style="background: #4c2c92;">
                                            <strong style="color: white;">OVO</strong>
                                        </div>
                                        <div class="payment-info">
                                            <strong>OVO</strong>
                                            <small>Bayar pakai OVO</small>
                                        </div>
                                        <i class="fas fa-check-circle payment-check"></i>
                                    </div>
                                </label>

                                <label class="payment-option">
                                    <input type="radio" name="payment_method_radio" value="DANA" required>
                                    <div class="payment-card">
                                        <div class="payment-logo" style="background: #0081c9;">
                                            <strong style="color: white;">DANA</strong>
                                        </div>
                                        <div class="payment-info">
                                            <strong>DANA</strong>
                                            <small>Bayar pakai DANA</small>
                                        </div>
                                        <i class="fas fa-check-circle payment-check"></i>
                                    </div>
                                </label>

                                <label class="payment-option">
                                    <input type="radio" name="payment_method_radio" value="ShopeePay" required>
                                    <div class="payment-card">
                                        <div class="payment-logo" style="background: #ff5722;">
                                            <strong style="color: white; font-size: 0.9rem;">ShopeePay</strong>
                                        </div>
                                        <div class="payment-info">
                                            <strong>ShopeePay</strong>
                                            <small>Bayar pakai ShopeePay</small>
                                        </div>
                                        <i class="fas fa-check-circle payment-check"></i>
                                    </div>
                                </label>

                                <label class="payment-option">
                                    <input type="radio" name="payment_method_radio" value="LinkAja" required>
                                    <div class="payment-card">
                                        <div class="payment-logo" style="background: #e31e24;">
                                            <strong style="color: white;">LinkAja</strong>
                                        </div>
                                        <div class="payment-info">
                                            <strong>LinkAja</strong>
                                            <small>Bayar pakai LinkAja</small>
                                        </div>
                                        <i class="fas fa-check-circle payment-check"></i>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Retail -->
                        <div class="payment-category" style="margin-top: 2rem;">
                            <h4 style="margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--light);">
                                <i class="fas fa-store"></i> Bayar di Toko
                            </h4>

                            <div class="payment-options">
                                <label class="payment-option">
                                    <input type="radio" name="payment_method_radio" value="Indomaret" required>
                                    <div class="payment-card">
                                        <div class="payment-logo" style="background: #dc0a14;">
                                            <strong style="color: white; font-size: 0.7rem;">INDOMARET</strong>
                                        </div>
                                        <div class="payment-info">
                                            <strong>Indomaret</strong>
                                            <small>Bayar di kasir Indomaret</small>
                                        </div>
                                        <i class="fas fa-check-circle payment-check"></i>
                                    </div>
                                </label>

                                <label class="payment-option">
                                    <input type="radio" name="payment_method_radio" value="Alfamart" required>
                                    <div class="payment-card">
                                        <div class="payment-logo" style="background: #ed1c24;">
                                            <strong style="color: white; font-size: 0.8rem;">ALFAMART</strong>
                                        </div>
                                        <div class="payment-info">
                                            <strong>Alfamart</strong>
                                            <small>Bayar di kasir Alfamart</small>
                                        </div>
                                        <i class="fas fa-check-circle payment-check"></i>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <button type="button" onclick="showUploadSection()" class="btn btn-primary" style="width: 100%; margin-top: 2rem; padding: 1rem;">
                            <i class="fas fa-arrow-right"></i> Lanjut ke Upload Bukti
                        </button>
                    </form>
                </div>

                <!-- Upload Receipt Section -->
                <div class="card upload-section" id="uploadSection">
                    <div class="card-header" style="background: var(--secondary); color: white;">
                        <h3 style="color: white; margin: 0; padding: 0.6rem;"><i class="fas fa-upload"></i> Upload Bukti Pembayaran</h3>
                    </div>

                    <form id="uploadForm" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="payment_method" id="payment_method_hidden">
                        
                        <div class="upload-area" id="uploadArea" onclick="document.getElementById('fileInput').click()">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; color: var(--primary); margin-bottom: 1rem;"></i>
                            <h4>Klik atau Drag & Drop File</h4>
                            <p style="color: var(--gray); margin-top: 0.5rem;">Format: JPG, PNG, PDF (Max 5MB)</p>
                            <input type="file" id="fileInput" name="payment_receipt" accept="image/*,.pdf" style="display: none;" onchange="previewFile(this)" required>
                        </div>

                        <div id="preview" style="display: none; margin-top: 1rem;">
                            <h4>Preview:</h4>
                            <img id="previewImage" class="preview-image" alt="Preview">
                            <button type="button" onclick="removeFile()" class="btn btn-secondary" style="margin-top: 1rem;">
                                <i class="fas fa-times"></i> Ganti File
                            </button>
                        </div>

                        <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                            <button type="button" onclick="backToPaymentMethod()" class="btn btn-secondary" style="flex: 1;">
                                <i class="fas fa-arrow-left"></i> Kembali
                            </button>
                            <button type="submit" class="btn btn-primary" style="flex: 2;">
                                <i class="fas fa-check"></i> Konfirmasi Pembayaran
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Booking Summary -->
            <div>
                <div class="card" style="position: sticky; top: 100px;">
                    <div class="card-header">
                        <h3 class="card-title">Ringkasan Pesanan</h3>
                    </div>

                    <div style="background: var(--light); padding: 1rem; border-radius: 8px; margin-bottom: 1rem; text-align: center;">
                        <div style="font-size: 0.9rem; color: var(--gray); margin-bottom: 0.5rem;">Kode Booking</div>
                        <div style="font-size: 1.5rem; font-weight: bold; color: var(--primary);">
                            <?php echo $booking['booking_code']; ?>
                        </div>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <strong>Layanan:</strong>
                        <p style="color: var(--gray); margin-top: 0.25rem;"><?php echo $booking['service_name']; ?></p>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <strong>Rute:</strong>
                        <p style="color: var(--gray); margin-top: 0.25rem;"><?php echo $booking['route']; ?></p>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <strong>Tanggal:</strong>
                        <p style="color: var(--gray); margin-top: 0.25rem;"><?php echo format_date($booking['travel_date']); ?></p>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <strong>Penumpang:</strong>
                        <p style="color: var(--gray); margin-top: 0.25rem;"><?php echo $booking['num_passengers']; ?> orang</p>
                    </div>

                    <?php if (!empty($booking['selected_seats'])): ?>
                    <div style="margin-bottom: 1rem;">
                        <strong>Kursi:</strong>
                        <p style="color: var(--gray); margin-top: 0.25rem;"><?php echo $booking['selected_seats']; ?></p>
                    </div>
                    <?php endif; ?>

                    <div style="border-top: 2px solid var(--light); padding-top: 1rem; margin-top: 1rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <strong style="font-size: 1.25rem;">Total:</strong>
                            <strong style="font-size: 1.5rem; color: var(--primary);">
                                <?php echo format_currency($booking['total_price']); ?>
                            </strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Processing Overlay -->
<div class="processing-overlay" id="processingOverlay">
    <div class="processing-content">
        <div class="spinner"></div>
        <h3>Memproses Pembayaran...</h3>
        <p style="color: var(--gray); margin-top: 1rem;">Mohon tunggu sebentar</p>
        <div id="countdown" style="font-size: 2rem; color: var(--primary); margin-top: 1rem; font-weight: bold;">10</div>
    </div>
</div>

<style>
.payment-category { margin-bottom: 2rem; }
.payment-options { display: grid; gap: 1rem; }
.payment-option { cursor: pointer; }
.payment-option input[type="radio"] { display: none; }
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
    width: 80px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    flex-shrink: 0;
}
.payment-info { flex: 1; }
.payment-info strong { display: block; margin-bottom: 0.25rem; }
.payment-info small { color: var(--gray); }
.payment-check {
    color: transparent;
    font-size: 1.5rem;
    transition: all 0.3s;
}
.payment-option input[type="radio"]:checked+.payment-card .payment-check {
    color: var(--primary);
}
</style>

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
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function backToPaymentMethod() {
    document.getElementById('uploadSection').style.display = 'none';
    document.getElementById('paymentMethodSection').style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
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

// Form submit
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
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                window.location.href = data.redirect;
            } else {
                alert(data.error || 'Gagal memproses pembayaran');
                overlay.style.display = 'none';
                clearInterval(timer);
            }
        })
        .catch(err => {
            console.error('Error:', err);
            // Even if there's an error, redirect to ticket page
            window.location.href = '<?php echo SITE_URL; ?>/user/ticket.php?booking=<?php echo $booking_code; ?>';
        });
    }, 10000);
});
</script>

<?php
closeDBConnection($conn);
include '../includes/footer.php';
?>