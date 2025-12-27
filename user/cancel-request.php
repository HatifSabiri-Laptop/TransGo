<?php
$page_title = 'Cancellation Request';
require_once '../config/config.php';
require_login();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get active bookings (exclude those with pending cancellation requests)
$active_bookings = $conn->query("SELECT r.*, s.service_name, s.route 
    FROM reservations r 
    JOIN services s ON r.service_id = s.id 
    WHERE r.user_id = $user_id 
    AND r.booking_status = 'confirmed' 
    AND r.travel_date >= CURDATE()
    AND r.id NOT IN (SELECT reservation_id FROM cancellation_requests WHERE user_id = $user_id AND status = 'pending')
    ORDER BY r.travel_date ASC");

// Get cancellation history
$cancellation_history = $conn->query("
    SELECT cr.*, r.booking_code, s.service_name, s.route, r.total_price
    FROM cancellation_requests cr
    JOIN reservations r ON cr.reservation_id = r.id
    JOIN services s ON r.service_id = s.id
    WHERE cr.user_id = $user_id
    ORDER BY cr.created_at DESC
");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reservation_id = intval($_POST['reservation_id']);
    $reason = clean_input($_POST['reason']);

    if (empty($reservation_id) || empty($reason)) {
        $error = 'Please select a booking and provide cancellation reason!';
    } elseif (strlen($reason) < 10) {
        $error = 'Cancellation reason must be at least 10 characters!';
    } else {
        // Check ownership and status
        $check = $conn->prepare("
            SELECT id 
            FROM reservations 
            WHERE id = ? 
            AND user_id = ? 
            AND booking_status = 'confirmed'
        ");
        $check->bind_param("ii", $reservation_id, $user_id);
        $check->execute();

        if ($check->get_result()->num_rows === 0) {
            $error = 'Invalid booking or already cancelled!';
        } else {
            // Check if already has pending/approved cancellation
            $check_cancel = $conn->prepare("
                SELECT id 
                FROM cancellation_requests 
                WHERE reservation_id = ? 
                AND status IN ('pending', 'approved')
            ");
            $check_cancel->bind_param("i", $reservation_id);
            $check_cancel->execute();

            if ($check_cancel->get_result()->num_rows > 0) {
                $error = 'This booking already has a pending or approved cancellation request!';
            } else {
                // Insert cancellation request with PENDING status (not auto-approved)
                $stmt = $conn->prepare("
                    INSERT INTO cancellation_requests 
                    (reservation_id, user_id, reason, status) 
                    VALUES (?, ?, ?, 'pending')
                ");
                $stmt->bind_param("iis", $reservation_id, $user_id, $reason);

                if ($stmt->execute()) {
                    log_activity($conn, $user_id, 'cancel_request', "Submitted cancellation request for reservation ID: $reservation_id");
                    $success = 'Cancellation request submitted successfully! Please wait for admin approval.';
                } else {
                    $error = 'Failed to submit cancellation request. Please try again.';
                }

                $stmt->close();
            }

            $check_cancel->close();
        }

        $check->close();
    }

    // Refresh data
    $active_bookings = $conn->query("
        SELECT r.*, s.service_name, s.route 
        FROM reservations r 
        JOIN services s ON r.service_id = s.id 
        WHERE r.user_id = $user_id 
        AND r.booking_status = 'confirmed' 
        AND r.travel_date >= CURDATE()
        AND r.id NOT IN (SELECT reservation_id FROM cancellation_requests WHERE user_id = $user_id AND status = 'pending')
        ORDER BY r.travel_date ASC
    ");

    $cancellation_history = $conn->query("
        SELECT cr.*, r.booking_code, s.service_name, s.route, r.total_price
        FROM cancellation_requests cr
        JOIN reservations r ON cr.reservation_id = r.id
        JOIN services s ON r.service_id = s.id
        WHERE cr.user_id = $user_id
        ORDER BY cr.created_at DESC
    ");
}

include '../includes/header.php';
?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        color: white;
        padding: 3rem 0;
        text-align: center;
        margin-bottom: 2rem;
    }

    .page-header h1 {
        margin: 0 0 0.5rem 0;
        font-size: 2.5rem;
    }

    .page-header p {
        margin: 0;
        opacity: 0.9;
        color: #f8f8f8ff;
    }

    .cancellation-section {
        padding: 2rem 0;
    }

    .cancellation-layout {
        display: grid;
        grid-template-columns: 1fr 1.5fr;
        gap: 2rem;
        margin-top: 2rem;
    }

    .card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        overflow: hidden;
    }

    .card-header {
        background: var(--light);
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid #e5e7eb;
    }

    .card-title {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--dark);
    }

    .cancellation-form {
        padding: 1.5rem;
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

    .form-control {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 1rem;
        transition: border-color 0.2s;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
    }

    .form-control textarea {
        resize: vertical;
        min-height: 120px;
    }

    .form-group small {
        display: block;
        margin-top: 0.25rem;
        color: var(--gray);
        font-size: 0.875rem;
    }

    .no-bookings {
        padding: 3rem 1.5rem;
        text-align: center;
        color: var(--gray);
    }

    .no-bookings i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    .policy-card {
        background: #fff8e1;
        border: 1px solid #ffd54f;
        border-radius: 12px;
        padding: 1.5rem;
        margin-top: 1.5rem;
    }

    .policy-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
        color: #f57f17;
    }

    .policy-header i {
        font-size: 1.5rem;
    }

    .policy-header h4 {
        margin: 0;
        font-size: 1.1rem;
    }

    .policy-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .policy-list li {
        padding: 0.75rem 0;
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        color: #5d4037;
    }

    .policy-list li i {
        color: #f57f17;
        margin-top: 0.25rem;
    }

    .history-list {
        padding: 1rem;
    }

    .history-item {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 1.25rem;
        margin-bottom: 1rem;
    }

    .history-item:last-child {
        margin-bottom: 0;
    }

    .history-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #e5e7eb;
    }

    .booking-code {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 0.25rem;
    }

    .booking-service {
        display: block;
        color: var(--gray);
        font-size: 0.9rem;
    }

    .badge {
        padding: 0.4rem 0.9rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .badge-warning {
        background: #fef3c7;
        color: #92400e;
    }

    .badge-success {
        background: #d1fae5;
        color: #065f46;
    }

    .badge-danger {
        background: #fee2e2;
        color: #991b1b;
    }

    .history-details {
        color: var(--dark);
    }

    .history-details .reason,
    .history-details .admin-notes {
        margin-bottom: 1rem;
    }

    .history-details strong {
        display: block;
        margin-bottom: 0.5rem;
        color: var(--dark);
    }

    .history-details p {
        margin: 0;
        padding: 0.75rem;
        background: var(--light);
        border-radius: 6px;
        color: var(--gray);
        line-height: 1.6;
    }

    .admin-notes {
        background: #e0f2fe;
        border-left: 3px solid #0284c7;
        padding: 0.75rem;
        border-radius: 6px;
    }

    .history-footer {
        display: flex;
        gap: 1.5rem;
        flex-wrap: wrap;
        padding-top: 1rem;
        border-top: 1px solid #e5e7eb;
        margin-top: 1rem;
    }

    .history-footer .date {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--gray);
        font-size: 0.875rem;
    }

    .history-footer .date i {
        color: var(--primary);
    }

    .no-history {
        padding: 3rem 1.5rem;
        text-align: center;
        color: var(--gray);
    }

    .no-history i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    @media (max-width: 968px) {
        .cancellation-layout {
            grid-template-columns: 1fr;
        }

        .page-header h1 {
            font-size: 2rem;
        }
    }

    @media (max-width: 576px) {
        .page-header {
            padding: 2rem 0;
        }

        .page-header h1 {
            font-size: 1.75rem;
        }

        .history-header {
            flex-direction: column;
            gap: 0.75rem;
        }

        .history-footer {
            flex-direction: column;
            gap: 0.5rem;
        }
    }
</style>
<section class="page-header">
    <div class="container">
        <h1><i class="fas fa-ban"></i> permintaan pembatalan</h1>
        <p>Kirimkan permintaan pembatalan untuk pemesanan Anda</p>
    </div>
</section>

<section class="cancellation-section">
    <div class="container">
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <div class="cancellation-layout">
            <!-- Cancellation Form -->
            <div class="cancellation-form-container">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-file-alt"></i> Cancellation Form</h3>
                    </div>

                    <?php if ($active_bookings->num_rows > 0): ?>
                        <form method="POST" action="" class="cancellation-form">
                            <div class="form-group">
                                <label for="reservation_id">Select Booking *</label>
                                <select name="reservation_id" id="reservation_id" class="form-control" required>
                                    <option value="">-- Select Booking --</option>
                                    <?php while ($booking = $active_bookings->fetch_assoc()): ?>
                                        <option value="<?php echo $booking['id']; ?>">
                                            <?php echo $booking['booking_code']; ?> -
                                            <?php echo $booking['service_name']; ?>
                                            (<?php echo format_date($booking['travel_date']); ?>) -
                                            <?php echo format_currency($booking['total_price']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="reason">Cancellation Reason *</label>
                                <textarea name="reason" id="reason" class="form-control" rows="5"
                                    placeholder="Tolong jelaskan alasan pembatalan tiket ini (misalnya: perubahan rencana, keadaan darurat, dll.)..." required></textarea>
                                <small>Minimum 10 characters required</small>
                            </div>

                            <button type="submit" class="btn btn-danger btn-block">
                                <i class="fas fa-paper-plane"></i> Submit Cancellation Request
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="no-bookings">
                            <i class="fas fa-calendar-times"></i>
                            <p>No active bookings available for cancellation.</p>
                            <a href="tickets.php" class="btn btn-primary" style="margin-top: 1rem;">
                                <i class="fas fa-calendar"></i> Lihat Tiket Saya
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="policy-card">
                    <div class="policy-header">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h4>Cancellation Policy</h4>
                    </div>
                    <ul class="policy-list">
                        <li>
                            <i class="fas fa-clock"></i>
                            <span>Cancellation requests will be reviewed by admin within 1-2 business days</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>You will be notified once your request is approved or rejected</span>
                        </li>
                        <li>
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Refunds will be processed within 3-7 business days after approval</span>
                        </li>
                        <li>
                            <i class="fas fa-hourglass-end"></i>
                            <span>Cancellations within 24 hours of departure may not be approved</span>
                        </li>
                        <li>
                            <i class="fas fa-receipt"></i>
                            <span>Administrative fees may apply depending on cancellation timing</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Cancellation History -->
            <div class="cancellation-history-container">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-history"></i> Cancellation History</h3>
                    </div>

                    <?php if ($cancellation_history->num_rows > 0): ?>
                        <div class="history-list">
                            <?php while ($cancel = $cancellation_history->fetch_assoc()): ?>
                                <div class="history-item">
                                    <div class="history-header">
                                        <div class="booking-info">
                                            <strong class="booking-code"><?php echo $cancel['booking_code']; ?></strong>
                                            <span class="booking-service">
                                                <?php echo $cancel['service_name']; ?> - <?php echo $cancel['route']; ?>
                                            </span>
                                        </div>
                                        <?php
                                        $badge_class = $cancel['status'] === 'pending' ? 'warning' : ($cancel['status'] === 'approved' ? 'success' : 'danger');
                                        ?>
                                        <span class="badge badge-<?php echo $badge_class; ?>">
                                            <?php
                                            if ($cancel['status'] === 'pending') echo '⏳ Pending';
                                            elseif ($cancel['status'] === 'approved') echo '✓ Approved';
                                            else echo '✗ Rejected';
                                            ?>
                                        </span>
                                    </div>

                                    <div class="history-details">
                                        <div class="reason">
                                            <strong>Your Reason:</strong>
                                            <p><?php echo htmlspecialchars($cancel['reason']); ?></p>
                                        </div>

                                        <?php if ($cancel['admin_notes']): ?>
                                            <div class="admin-notes">
                                                <strong><i class="fas fa-user-shield"></i> Admin Response:</strong>
                                                <p><?php echo htmlspecialchars($cancel['admin_notes']); ?></p>
                                            </div>
                                        <?php endif; ?>

                                        <div class="history-footer">
                                            <span class="date">
                                                <i class="fas fa-calendar-plus"></i>
                                                Requested: <?php echo format_datetime($cancel['created_at']); ?>
                                            </span>
                                            <?php if ($cancel['processed_at']): ?>
                                                <span class="date">
                                                    <i class="fas fa-calendar-check"></i>
                                                    Processed: <?php echo format_datetime($cancel['processed_at']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-history">
                            <i class="fas fa-history"></i>
                            <p>No cancellation requests yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-hide alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s ease';
                setTimeout(() => alert.remove(), 500);
            }, 5000);
        });

        // Form validation
        const form = document.querySelector('.cancellation-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const reason = document.getElementById('reason').value.trim();
                const reservation = document.getElementById('reservation_id').value;

                if (!reservation) {
                    e.preventDefault();
                    alert('Please select a booking to cancel');
                    return false;
                }

                if (reason.length < 10) {
                    e.preventDefault();
                    alert('Cancellation reason must be at least 10 characters');
                    return false;
                }

                // Confirm submission
                if (!confirm('Are you sure you want to submit this cancellation request? This action cannot be undone.')) {
                    e.preventDefault();
                    return false;
                }
            });

            // Character counter for reason textarea
            const reasonTextarea = document.getElementById('reason');
            if (reasonTextarea) {
                const small = reasonTextarea.parentElement.querySelector('small');
                const originalText = small.textContent;

                reasonTextarea.addEventListener('input', function() {
                    const length = this.value.trim().length;
                    if (length > 0) {
                        small.textContent = `${length} characters (minimum 10 required)`;
                        if (length >= 10) {
                            small.style.color = 'var(--success)';
                        } else {
                            small.style.color = 'var(--danger)';
                        }
                    } else {
                        small.textContent = originalText;
                        small.style.color = 'var(--gray)';
                    }
                });
            }
        }
    });
</script>

<?php
if ($conn instanceof mysqli) {
    $conn->close();
}

include '../includes/footer.php';
?>