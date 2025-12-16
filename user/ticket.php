<?php
$page_title = 'E-Ticket';
require_once '../config/config.php';
require_login();

$conn = getDBConnection();
$booking_code = isset($_GET['booking']) ? clean_input($_GET['booking']) : '';

if (empty($booking_code)) {
    header('Location: ' . SITE_URL . '/user/dashboard.php');
    exit();
}

// Get booking details
$stmt = $conn->prepare("SELECT r.*, s.service_name, s.route, s.departure_time, s.arrival_time, u.full_name as user_name
    FROM reservations r 
    JOIN services s ON r.service_id = s.id 
    JOIN users u ON r.user_id = u.id
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

// Check if payment is completed
if ($booking['payment_status'] !== 'paid') {
    header('Location: ' . SITE_URL . '/user/payment.php?booking=' . $booking_code);
    exit();
}

// Get passenger names
$passenger_names = json_decode($booking['passenger_names'], true);

closeDBConnection($conn);
include '../includes/header.php';
?>
<style>
@media print {
    /* Reset everything */
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* Hide everything except ticket */
    body * {
        visibility: hidden !important;
    }
    
    #ticket,
    #ticket * {
        visibility: visible !important;
    }

    /* Page setup */
    @page {
        size: A4;
        margin: 10mm;
    }

    html,
    body {
        margin: 0 !important;
        padding: 0 !important;
        height: auto !important;
        overflow: visible !important;
        width: 100%;
    }

    /* Position ticket */
    #ticket {
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        
        /* Prevent page breaks */
        page-break-before: avoid !important;
        page-break-after: avoid !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
        
        /* Scale to fit - adjusted for better fit */
        transform: scale(0.75) !important;
        transform-origin: top left !important;
        
        box-shadow: none !important;
        border: 2px solid #2563eb !important;
    }

    /* Remove all buttons and non-printable elements */
    .no-print,
    .btn,
    button,
    header,
    footer,
    nav,
    .action-buttons,
    .instructions {
        display: none !important;
    }

    /* Reduce all spacing significantly */
    #ticket > div:last-child {
        padding: 1rem !important;
    }

    /* Header section */
    #ticket div[style*="border-bottom"] {
        padding-bottom: 0.8rem !important;
        margin-bottom: 0.8rem !important;
    }

    #ticket h2 {
        font-size: 1.3rem !important;
        margin: 0.3rem 0 !important;
    }

    #ticket h3 {
        font-size: 1.1rem !important;
        margin: 0.3rem 0 !important;
    }

    #ticket h4 {
        font-size: 1rem !important;
        margin-bottom: 0.5rem !important;
    }

    /* Logo */
    #ticket img[alt="TransGo"] {
        height: 35px !important;
    }

    /* Status badge */
    #ticket div[style*="background: var(--secondary)"] {
        padding: 0.3rem 0.8rem !important;
        margin-top: 0.3rem !important;
        font-size: 0.85rem !important;
    }

    /* Booking code section */
    #ticket .booking-code {
        font-size: 1.5rem !important;
        margin-bottom: 0.5rem !important;
        letter-spacing: 1px !important;
    }

    /* Barcode - reduce size */
    #ticket img[alt="Barcode"] {
        height: 50px !important;
        margin: 0.5rem 0 !important;
    }

    /* QR Code - reduce size */
    #ticket img[alt="QR Code"] {
        width: 100px !important;
        height: 100px !important;
        margin: 0.5rem 0 !important;
    }

    /* Trip details grid */
    #ticket > div > div > div[style*="grid-template-columns: 1fr 1fr"] {
        gap: 1rem !important;
        margin-bottom: 1rem !important;
    }

    #ticket > div > div > div[style*="grid-template-columns: 1fr 1fr"] > div > div {
        margin-bottom: 0.6rem !important;
    }

    #ticket > div > div > div[style*="grid-template-columns: 1fr 1fr"] strong {
        font-size: 0.85rem !important;
    }

    #ticket > div > div > div[style*="grid-template-columns: 1fr 1fr"] span {
        font-size: 0.85rem !important;
    }

    /* Passenger list */
    #ticket div[style*="background: var(--light)"] {
        padding: 1rem !important;
        margin-bottom: 1rem !important;
    }

    #ticket div[style*="background: var(--light)"] > div[style*="display: grid"] {
        gap: 0.8rem !important;
    }

    #ticket div[style*="background: var(--light)"] > div[style*="display: grid"] > div {
        padding: 0.6rem !important;
    }

    /* Seat selection */
    #ticket div[style*="background: #dbeafe"] {
        padding: 0.8rem !important;
        margin-bottom: 1rem !important;
    }

    #ticket div[style*="background: #dbeafe"] > div {
        font-size: 1.1rem !important;
        margin-top: 0.3rem !important;
    }

    /* Price section */
    #ticket div[style*="border-top: 2px dashed"] {
        padding-top: 1rem !important;
        margin-top: 0 !important;
    }

    #ticket .total-price {
        font-size: 1.5rem !important;
    }

    #ticket div[style*="border-top: 2px dashed"] small {
        font-size: 0.75rem !important;
    }

    /* Footer */
    #ticket > div[style*="background: var(--primary)"] {
        padding: 0.8rem !important;
    }

    #ticket > div[style*="background: var(--primary)"] p {
        font-size: 0.8rem !important;
        margin: 0.2rem 0 !important;
    }

    /* Decorative corners - make smaller */
    #ticket > div:first-child,
    #ticket > div:nth-child(2) {
        width: 40px !important;
        height: 40px !important;
    }

    /* Force all child divs to avoid breaks */
    #ticket > * {
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }
}
</style>



<section class="no-print" style="padding: 2rem 0; background: linear-gradient(135deg, var(--secondary), #059669); color: white;">
    <div class="container">
        <div style="text-align: center;">
            <i class="fas fa-check-circle" style="font-size: 4rem; margin-bottom: 1rem;"></i>
            <h1>Pembayaran Berhasil!</h1>
            <p style="font-size: 1.2rem;">Tiket Anda sudah siap untuk diunduh</p>
        </div>
    </div>
</section>

<section style="padding: 2rem 0;">
    <div class="container">
        <!-- Inside your section where the ticket is -->
        <div style="max-width: 800px; margin: 0 auto;">
            <!-- E-Ticket -->
            <div class="card" id="ticket" style="border: 3px solid var(--primary); position: relative; overflow: hidden;">
                <!-- Decorative corners -->
                <div style="position: absolute; top: 0; left: 0; width: 50px; height: 50px; background: linear-gradient(135deg, var(--primary) 50%, transparent 50%);"></div>
                <div style="position: absolute; top: 0; right: 0; width: 50px; height: 50px; background: linear-gradient(225deg, var(--primary) 50%, transparent 50%);"></div>

                <div style="padding: 2rem; position: relative;">
                    <!-- Header -->
                    <div style="text-align: center; border-bottom: 2px dashed var(--gray); padding-bottom: 2rem; margin-bottom: 2rem;">
                        <div style="display: flex; align-items: center; justify-content: center; gap: 1rem; margin-bottom: 1rem;">
                            <?php if (file_exists(__DIR__ . '/../assets/images/logo.jpg')): ?>
                                <img src="<?php echo SITE_URL; ?>/assets/images/logo.jpg" alt="TransGo" style="height: 50px; border-radius: 8px;">
                            <?php endif; ?>
                            <h2 style="color: var(--primary); margin: 0;">TRANSGO</h2>
                        </div>
                        <h3 style="color: var(--dark); margin-top: 0.3rem;">E-TICKET PERJALANAN</h3>
                        <div style="background: var(--secondary); color: white; padding: 0.5rem 1rem; display: inline-block; border-radius: 20px; margin-top: 0.5rem;">
                            <i class="fas fa-check-circle"></i> LUNAS
                        </div>
                    </div>

                    <!-- Booking Code & Barcode -->
                    <div style="text-align: center; margin-bottom: 2rem;">
                        <div style="font-size: 0.9rem; color: var(--gray); margin-bottom: 0.5rem;">KODE BOOKING</div>
                        <div style="font-size: 2rem; font-weight: bold; color: var(--primary); letter-spacing: 2px; margin-bottom: 1rem;"
                            class="booking-code">
                            <?php echo $booking['booking_code']; ?>
                        </div>

                        <!-- Barcode -->
                        <div style="display: flex; justify-content: center; margin: 1.5rem 0;">
                            <img src="https://barcode.tec-it.com/barcode.ashx?data=<?php echo urlencode($booking['booking_code']); ?>&code=Code128&translate-esc=on"
                                alt="Barcode"
                                style="height: 80px;">
                        </div>

                        <!-- QR Code -->
                        <div style="margin: 1.5rem 0;">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode('TRANSGO-' . $booking['booking_code'] . '-' . $booking['contact_name']); ?>"
                                alt="QR Code">
                        </div>
                    </div>

                    <!-- Trip Details -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                        <div>
                            <h4 style="color: var(--primary); margin-bottom: 1rem;">Detail Perjalanan</h4>
                            <div style="margin-bottom: 1rem;">
                                <strong>Layanan:</strong><br>
                                <span style="color: var(--gray);"><?php echo $booking['service_name']; ?></span>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <strong>Rute:</strong><br>
                                <span style="color: var(--gray);"><?php echo $booking['route']; ?></span>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <strong>Tanggal Perjalanan:</strong><br>
                                <span style="color: var(--gray); font-size: 1.1rem; font-weight: 600;">
                                    <?php echo format_date($booking['travel_date']); ?>
                                </span>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div>
                                    <strong>Berangkat:</strong><br>
                                    <span style="color: var(--gray);"><?php echo date('H:i', strtotime($booking['departure_time'])); ?></span>
                                </div>
                                <div>
                                    <strong>Tiba:</strong><br>
                                    <span style="color: var(--gray);"><?php echo date('H:i', strtotime($booking['arrival_time'])); ?></span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h4 style="color: var(--primary); margin-bottom: 1rem;">Data Pemesan</h4>
                            <div style="margin-bottom: 1rem;">
                                <strong>Nama:</strong><br>
                                <span style="color: var(--gray);"><?php echo $booking['contact_name']; ?></span>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <strong>Email:</strong><br>
                                <span style="color: var(--gray);"><?php echo $booking['contact_email']; ?></span>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <strong>Telepon:</strong><br>
                                <span style="color: var(--gray);"><?php echo $booking['contact_phone']; ?></span>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <strong>Metode Pembayaran:</strong><br>
                                <span style="color: var(--gray);"><?php echo $booking['payment_method'] ?? 'Transfer Bank'; ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Passenger List -->
                    <div style="background: var(--light); padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
                        <h4 style="color: var(--primary); margin-bottom: 1rem;">
                            <i class="fas fa-users"></i> Daftar Penumpang (<?php echo count($passenger_names); ?> orang)
                        </h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                            <?php foreach ($passenger_names as $index => $name): ?>
                                <div style="background: white; padding: 1rem; border-radius: 8px; border-left: 4px solid var(--primary);">
                                    <div style="font-size: 0.85rem; color: var(--gray); margin-bottom: 0.25rem;">Penumpang <?php echo $index + 1; ?></div>
                                    <strong><?php echo htmlspecialchars($name); ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if (!empty($booking['selected_seats'])): ?>
                        <div style="background: #dbeafe; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; text-align: center;">
                            <strong style="color: var(--primary);"><i class="fas fa-chair"></i> Nomor Kursi:</strong>
                            <div style="font-size: 1.3rem; font-weight: bold; color: var(--primary); margin-top: 0.5rem;">
                                <?php echo $booking['selected_seats']; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Price -->
                    <div style="border-top: 2px dashed var(--gray); padding-top: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-size: 0.9rem; color: var(--gray); margin-bottom: 0.25rem;">TOTAL PEMBAYARAN</div>
                                <div style="font-size: 2rem; font-weight: bold; color: var(--primary);"
                                    class="total-price">
                                    <?php echo format_currency($booking['total_price']); ?>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="background: var(--secondary); color: white; padding: 0.5rem 1rem; border-radius: 8px; margin-bottom: 0.5rem;">
                                    <i class="fas fa-check"></i> DIBAYAR
                                </div>
                                <small style="color: var(--gray);">
                                    <?php echo format_datetime($booking['created_at']); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div style="background: var(--primary); color: white; padding: 1rem; text-align: center;">
                    <p style="margin: 0; font-size: 0.9rem;">
                        TransGo - Perjalanan Nyaman Dimulai Dari Sini
                    </p>
                    <p style="margin: 0.5rem 0 0 0; font-size: 0.85rem; opacity: 0.9;">
                        CS: +62 882006907493 | Email: support@transgo.com
                    </p>
                </div>
            </div>

            <!-- Instructions (Not Printed) -->
            <div class="instructions no-print" style="margin-top: 2rem; padding: 1.5rem; background: #fef3c7; border-left: 4px solid var(--accent); border-radius: 8px;">
                <h4 style="color: #92400e; margin-bottom: 1rem;">
                    <i class="fas fa-info-circle"></i> Petunjuk Penting
                </h4>
                <ul style="margin: 0; padding-left: 1.5rem; color: #92400e; line-height: 1.8;">
                    <li>Tunjukkan e-ticket ini (print atau digital) saat keberangkatan</li>
                    <li>Datang minimal 30 menit sebelum waktu keberangkatan</li>
                    <li>Bawa identitas yang sesuai dengan nama pemesan</li>
                    <li>Simpan kode booking untuk keperluan check-in</li>
                </ul>
            </div>

            <!-- Action Buttons (Not Printed) -->
            <div class="action-buttons no-print" style="display: flex; gap: 1rem; margin-top: 2rem; flex-wrap: wrap;">
                <button onclick="window.print()" class="btn btn-primary" style="flex: 1;">
                    <i class="fas fa-print"></i> Print Tiket
                </button>
                <button onclick="downloadTicket()" class="btn btn-secondary" style="flex: 1;">
                    <i class="fas fa-download"></i> Download PDF
                </button>
                <a href="<?php echo SITE_URL; ?>/user/dashboard.php" class="btn" style="flex: 1; background: var(--gray); color: white; text-align: center;">
                    <i class="fas fa-home"></i> Ke Dashboard
                </a>
            </div>
        </div>
    </div>
</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
    function downloadTicket() {
        const element = document.getElementById('ticket');
        const opt = {
            margin: 0.5,
            filename: 'TransGo_Ticket_<?php echo $booking['booking_code']; ?>.pdf',
            image: {
                type: 'jpeg',
                quality: 0.98
            },
            html2canvas: {
                scale: 2,
                useCORS: true
            },
            jsPDF: {
                unit: 'in',
                format: 'a4',
                orientation: 'portrait'
            }
        };

        html2pdf().set(opt).from(element).save();
    }
</script>

<?php include '../includes/footer.php'; ?>