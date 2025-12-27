<?php
$page_title = 'Semua Tiket';
require_once '../config/config.php';
require_admin();

$conn = getDBConnection();

// Handle ticket deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ticket'])) {
    $ticket_id = (int)$_POST['ticket_id'];
    
    // Verify ticket can be deleted (must be past or cancelled)
    $check_stmt = $conn->prepare("SELECT travel_date, booking_status FROM reservations WHERE id = ?");
    $check_stmt->bind_param("i", $ticket_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $ticket_data = $check_result->fetch_assoc();
        $is_past = strtotime($ticket_data['travel_date']) < strtotime(date('Y-m-d'));
        $is_cancelled = $ticket_data['booking_status'] === 'cancelled';
        
        if ($is_past || $is_cancelled) {
            // Delete the ticket
            $delete_stmt = $conn->prepare("DELETE FROM reservations WHERE id = ?");
            $delete_stmt->bind_param("i", $ticket_id);
            
            if ($delete_stmt->execute()) {
                $_SESSION['success_message'] = "Tiket berhasil dihapus";
            } else {
                $_SESSION['error_message'] = "Gagal menghapus tiket";
            }
            $delete_stmt->close();
        } else {
            $_SESSION['error_message'] = "Tidak dapat menghapus tiket yang akan datang";
        }
    }
    $check_stmt->close();
    
    header("Location: " . SITE_URL . "/admin/ticket-check.php");
    exit();
}

// Get all paid tickets from all users
$tickets = $conn->query("SELECT r.*, s.service_name, s.route, s.departure_time, s.arrival_time, 
    u.email as user_email, u.full_name as user_name,
    cr.status as cancel_status, cr.processed_at as cancel_processed_at
    FROM reservations r 
    JOIN services s ON r.service_id = s.id 
    JOIN users u ON r.user_id = u.id
    LEFT JOIN cancellation_requests cr ON r.id = cr.reservation_id AND cr.status = 'approved'
    WHERE r.payment_status = 'paid'
    ORDER BY r.travel_date DESC, r.created_at DESC");

include '../includes/header.php';
?>

<style>
    /* Desktop table container */
    .table-responsive-container {
        width: 100%;
        overflow-x: auto;
        margin-bottom: 1rem;
        -webkit-overflow-scrolling: touch;
    }

    /* Table styles */
    .table {
        min-width: 1200px;
        width: 100%;
        border-collapse: collapse;
        margin-top: 1rem;
        table-layout: auto;
    }

    .table thead {
        background-color: var(--primary);
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .table th {
        text-align: left;
        padding: 1rem 0.75rem;
        font-weight: 600;
        color: white;
        border-bottom: 2px solid #dee2e6;
        white-space: nowrap;
    }

    .table td {
        padding: 1rem 0.75rem;
        border-bottom: 1px solid #dee2e6;
        vertical-align: middle;
    }

    /* Column widths */
    .table th:nth-child(1), .table td:nth-child(1) { min-width: 120px; max-width: 150px; }
    .table th:nth-child(2), .table td:nth-child(2) { min-width: 180px; max-width: 220px; }
    .table th:nth-child(3), .table td:nth-child(3) { min-width: 140px; max-width: 180px; }
    .table th:nth-child(4), .table td:nth-child(4) { min-width: 180px; max-width: 220px; word-wrap: break-word; }
    .table th:nth-child(5), .table td:nth-child(5) { min-width: 100px; max-width: 120px; white-space: nowrap; }
    .table th:nth-child(6), .table td:nth-child(6) { min-width: 80px; max-width: 100px; text-align: center; }
    .table th:nth-child(7), .table td:nth-child(7) { min-width: 120px; max-width: 140px; white-space: nowrap; }
    .table th:nth-child(8), .table td:nth-child(8) { min-width: 140px; max-width: 160px; }
    .table th:nth-child(9), .table td:nth-child(9) { min-width: 130px; max-width: 150px; }
    .table th:nth-child(10), .table td:nth-child(10) { min-width: 200px; max-width: 220px; white-space: nowrap; }

    .table tbody tr:hover {
        background-color: rgba(0, 0, 0, 0.02);
    }

    .badge {
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        display: inline-block;
        min-width: 80px;
        text-align: center;
    }

    .badge-success {
        background: #d1fae5;
        color: #065f46;
    }

    .badge-warning {
        background: #fef3c7;
        color: #92400e;
    }

    .badge-danger {
        background: #fee2e2;
        color: #991b1b;
    }

    .refund-badge {
        padding: 0.35rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
        text-align: center;
        margin-top: 0.25rem;
    }

    .refund-processing {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fbbf24;
    }

    .refund-completed {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #10b981;
    }

    .status-badges-container {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
        align-items: flex-start;
    }

    /* Action buttons container */
    .action-buttons {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .btn-delete {
        background: #dc2626;
        color: white;
        border: none;
        padding: 0.5rem 0.75rem;
        border-radius: 6px;
        font-size: 0.85rem;
        cursor: pointer;
        transition: background 0.3s;
    }

    .btn-delete:hover {
        background: #991b1b;
    }

    .btn-delete:disabled {
        background: #d1d5db;
        cursor: not-allowed;
    }

    /* Scrollbar styling */
    .table-responsive-container::-webkit-scrollbar {
        height: 8px;
    }

    .table-responsive-container::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    .table-responsive-container::-webkit-scrollbar-thumb {
        background: var(--primary);
        border-radius: 4px;
    }

    .table-responsive-container::-webkit-scrollbar-thumb:hover {
        background: var(--secondary);
    }

    /* Mobile ticket card */
    .mobile-ticket-list {
        display: none;
    }

    .mobile-ticket-card {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1rem;
    }

    .mobile-ticket-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #e5e7eb;
    }

    .mobile-ticket-email {
        font-weight: 600;
        color: var(--dark);
        font-size: 0.95rem;
        word-break: break-word;
        flex: 1;
        margin-right: 0.5rem;
    }

    .mobile-ticket-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
        flex-wrap: wrap;
    }

    .mobile-details-btn {
        background: var(--primary);
        color: white;
        border: none;
        padding: 0.6rem 1rem;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.9rem;
        flex: 1;
        min-width: 120px;
        transition: background 0.3s;
    }

    .mobile-details-btn:hover {
        background: var(--secondary);
    }

    /* Modal styles */
    .ticket-details-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 10000;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    .ticket-details-modal.active {
        display: flex;
    }

    .modal-content {
        background: white;
        border-radius: 12px;
        max-width: 500px;
        width: 100%;
        max-height: 85vh;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    }

    .modal-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 2px solid var(--light);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--light);
        position: sticky;
        top: 0;
        z-index: 100;
        flex-shrink: 0;
    }

    .modal-header h3 {
        margin: 0;
        font-size: 1.1rem;
        color: var(--dark);
    }

    .modal-body {
        padding: 1.5rem;
        overflow-y: auto;
        flex: 1;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--gray);
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background-color 0.2s;
    }

    .modal-close:hover {
        background: rgba(0, 0, 0, 0.1);
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
        font-size: 0.9rem;
    }

    .detail-value {
        color: var(--gray);
    }

    /* Alert messages */
    .alert {
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
    }

    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #10b981;
    }

    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #dc2626;
    }

    /* Mobile responsive */
    @media (max-width: 768px) {
        .table-responsive-container {
            display: none;
        }

        .mobile-ticket-list {
            display: block;
            padding: 1rem;
        }

        .modal-content {
            max-height: 90vh;
            max-width: 100%;
        }

        .modal-header {
            padding: 1rem 1.25rem;
        }

        .modal-body {
            padding: 1.25rem;
        }
    }

    @media (max-width: 480px) {
        .mobile-ticket-card {
            padding: 0.875rem;
        }

        .mobile-ticket-email {
            font-size: 0.85rem;
        }

        .mobile-ticket-actions {
            flex-direction: column;
        }

        .mobile-details-btn,
        .btn-delete {
            width: 100%;
            min-width: unset;
        }
    }
</style>

<section style="padding: 2rem 0; background: var(--light);">
    <div class="container">
        <h1><i class="fas fa-ticket-alt"></i> Semua Tiket</h1>
        <p style="color: var(--gray);">Kelola semua tiket yang telah dibeli pengguna</p>
    </div>
</section>

<section style="padding: 2rem 0;">
    <div class="container">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-list"></i> Daftar Semua Tiket</h3>
            </div>

            <?php if ($tickets->num_rows > 0): ?>
                <!-- Desktop Table View -->
                <div class="table-responsive-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Kode Booking</th>
                                <th>Email Pengguna</th>
                                <th>Layanan</th>
                                <th>Rute</th>
                                <th>Tanggal</th>
                                <th>Penumpang</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Check-in</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $tickets->data_seek(0); // Reset pointer
                            while ($ticket = $tickets->fetch_assoc()): 
                                $passenger_names = json_decode($ticket['passenger_names'], true);
                                $is_upcoming = strtotime($ticket['travel_date']) >= strtotime(date('Y-m-d'));
                                $is_cancelled = $ticket['booking_status'] === 'cancelled';
                                $can_delete = !$is_upcoming || $is_cancelled;
                                
                                // Calculate refund status
                                $refund_status = 'none';
                                if ($is_cancelled && $ticket['cancel_processed_at']) {
                                    $cancel_timestamp = strtotime($ticket['cancel_processed_at']);
                                    $current_timestamp = time();
                                    $time_diff_minutes = ($current_timestamp - $cancel_timestamp) / 60;
                                    
                                    if ($time_diff_minutes >= 2) {
                                        $refund_status = 'completed';
                                    } else {
                                        $refund_status = 'processing';
                                    }
                                }
                            ?>
                                <tr>
                                    <td><strong style="color: var(--primary);"><?php echo $ticket['booking_code']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($ticket['user_email']); ?></td>
                                    <td><?php echo $ticket['service_name']; ?></td>
                                    <td style="font-size: 0.9rem;"><?php echo $ticket['route']; ?></td>
                                    <td><?php echo format_date($ticket['travel_date']); ?></td>
                                    <td style="text-align: center;"><?php echo count($passenger_names); ?></td>
                                    <td><?php echo format_currency($ticket['total_price']); ?></td>
                                    <td>
                                        <div class="status-badges-container">
                                            <?php if ($is_cancelled): ?>
                                                <span class="badge badge-danger">
                                                    <i class="fas fa-times-circle"></i> Cancelled
                                                </span>
                                                <?php if ($refund_status !== 'none'): ?>
                                                    <span class="refund-badge refund-<?php echo $refund_status; ?>">
                                                        <?php if ($refund_status === 'processing'): ?>
                                                            <i class="fas fa-spinner fa-spin"></i> Refund
                                                        <?php else: ?>
                                                            <i class="fas fa-check-circle"></i> Refunded
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php elseif ($is_upcoming): ?>
                                                <span class="badge badge-success">
                                                    <i class="fas fa-clock"></i> Upcoming
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">
                                                    <i class="fas fa-history"></i> Past
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($ticket['checked_in'] && !$is_cancelled): ?>
                                            <span class="badge badge-success">
                                                <i class="fas fa-check"></i> Yes
                                            </span>
                                        <?php elseif (!$is_cancelled): ?>
                                            <span class="badge badge-warning">
                                                <i class="fas fa-times"></i> No
                                            </span>
                                        <?php else: ?>
                                            <span style="color: var(--gray);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
    
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus tiket ini?');">
                                                <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                                <button type="submit" 
                                                        name="delete_ticket" 
                                                        class="btn-delete"
                                                        <?php echo !$can_delete ? 'disabled title="Tidak dapat menghapus tiket yang akan datang"' : ''; ?>>
                                                    <i class="fas fa-trash"></i> Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div class="mobile-ticket-list">
                    <?php 
                    $tickets->data_seek(0); // Reset pointer
                    while ($ticket = $tickets->fetch_assoc()): 
                        $passenger_names = json_decode($ticket['passenger_names'], true);
                        $is_upcoming = strtotime($ticket['travel_date']) >= strtotime(date('Y-m-d'));
                        $is_cancelled = $ticket['booking_status'] === 'cancelled';
                        $can_delete = !$is_upcoming || $is_cancelled;
                        
                        // Calculate refund status
                        $refund_status = 'none';
                        if ($is_cancelled && $ticket['cancel_processed_at']) {
                            $cancel_timestamp = strtotime($ticket['cancel_processed_at']);
                            $current_timestamp = time();
                            $time_diff_minutes = ($current_timestamp - $cancel_timestamp) / 60;
                            
                            if ($time_diff_minutes >= 2) {
                                $refund_status = 'completed';
                            } else {
                                $refund_status = 'processing';
                            }
                        }
                    ?>
                        <div class="mobile-ticket-card">
                            <div class="mobile-ticket-header">
                                <div class="mobile-ticket-email">
                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($ticket['user_email']); ?>
                                </div>
                                <div class="status-badges-container">
                                    <?php if ($is_cancelled): ?>
                                        <span class="badge badge-danger">
                                            <i class="fas fa-times-circle"></i> Cancelled
                                        </span>
                                        <?php if ($refund_status !== 'none'): ?>
                                            <span class="refund-badge refund-<?php echo $refund_status; ?>">
                                                <?php if ($refund_status === 'processing'): ?>
                                                    <i class="fas fa-spinner"></i> Refund
                                                <?php else: ?>
                                                    <i class="fas fa-check-circle"></i> Refunded
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php elseif ($is_upcoming): ?>
                                        <span class="badge badge-success">
                                            <i class="fas fa-clock"></i> Upcoming
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">
                                            <i class="fas fa-history"></i> Past
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mobile-ticket-actions">
                                <button class="mobile-details-btn" onclick="showTicketDetails(<?php echo htmlspecialchars(json_encode($ticket)); ?>, <?php echo count($passenger_names); ?>, '<?php echo $is_cancelled ? 'cancelled' : ($is_upcoming ? 'upcoming' : 'past'); ?>', <?php echo $can_delete ? 'true' : 'false'; ?>)">
                                    <i class="fas fa-info-circle"></i> Lihat Detail
                                </button>
                                <form method="POST" style="flex: 1; min-width: 120px;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus tiket ini?');">
                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                    <button type="submit" 
                                            name="delete_ticket" 
                                            class="btn-delete"
                                            style="width: 100%;"
                                            <?php echo !$can_delete ? 'disabled title="Tidak dapat menghapus tiket yang akan datang"' : ''; ?>>
                                        <i class="fas fa-trash"></i> Hapus
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

            <?php else: ?>
                <div style="text-align: center; padding: 3rem; color: var(--gray);">
                    <i class="fas fa-ticket-alt" style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                    <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">Belum ada tiket</p>
                    <p style="font-size: 0.9rem;">Tiket yang dibeli pengguna akan muncul di sini</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Ticket Details Modal -->
<div id="ticketDetailsModal" class="ticket-details-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-ticket-alt"></i> Detail Tiket</h3>
            <button class="modal-close" onclick="closeTicketDetails()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Content will be populated by JavaScript -->
        </div>
    </div>
</div>

<script>
    const SITE_URL = '<?php echo SITE_URL; ?>';

    function showTicketDetails(ticket, passengerCount, status, canDelete) {
        const modal = document.getElementById('ticketDetailsModal');
        const modalBody = document.getElementById('modalBody');
        
        const statusBadge = status === 'cancelled' 
            ? '<span class="badge badge-danger"><i class="fas fa-times-circle"></i> Cancelled</span>'
            : status === 'upcoming'
            ? '<span class="badge badge-success"><i class="fas fa-clock"></i> Upcoming</span>'
            : '<span class="badge badge-warning"><i class="fas fa-history"></i> Past</span>';

        const checkinBadge = ticket.checked_in && status !== 'cancelled'
            ? '<span class="badge badge-success"><i class="fas fa-check"></i> Checked In</span>'
            : status !== 'cancelled'
            ? '<span class="badge badge-warning"><i class="fas fa-times"></i> Not Checked In</span>'
            : '<span style="color: var(--gray);">-</span>';

        const deleteButton = canDelete
            ? `<form method="POST" style="margin-top: 1rem;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus tiket ini?');">
                <input type="hidden" name="ticket_id" value="${ticket.id}">
                <button type="submit" name="delete_ticket" class="btn-delete" style="width: 100%; padding: 0.75rem;">
                    <i class="fas fa-trash"></i> Hapus Tiket
                </button>
               </form>`
            : '';

        modalBody.innerHTML = `
            <div class="detail-item">
                <div class="detail-label">Kode Booking</div>
                <div class="detail-value" style="font-size: 1.2rem; font-weight: bold; color: var(--primary);">
                    ${ticket.booking_code}
                </div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Email Pengguna</div>
                <div class="detail-value">${ticket.user_email}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Nama Pengguna</div>
                <div class="detail-value">${ticket.user_name}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Layanan</div>
                <div class="detail-value">${ticket.service_name}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Rute</div>
                <div class="detail-value">${ticket.route}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Tanggal Perjalanan</div>
                <div class="detail-value">${formatDate(ticket.travel_date)}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Waktu Keberangkatan</div>
                <div class="detail-value">${formatTime(ticket.departure_time)} - ${formatTime(ticket.arrival_time)}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Jumlah Penumpang</div>
                <div class="detail-value">${passengerCount} orang</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Kursi</div>
                <div class="detail-value">${ticket.selected_seats || '-'}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Total Pembayaran</div>
                <div class="detail-value" style="font-size: 1.2rem; font-weight: bold; color: var(--primary);">
                    ${formatCurrency(ticket.total_price)}
                </div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Status Tiket</div>
                <div class="detail-value">${statusBadge}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Check-in</div>
                <div class="detail-value">${checkinBadge}</div>
            </div>
            <div class="detail-item"></div>
                <div class="detail-label">Dibuat Pada</div>
                <div class="detail-value">${formatDateTime(ticket.created_at)}</div>
            </div>
            ${deleteButton}
        `;          
        modal.classList.add('active');
    }
    function closeTicketDetails() {
        const modal = document.getElementById('ticketDetailsModal');
        modal.classList.remove('active');
    }
    function formatDate(dateStr) {
        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        return new Date(dateStr).toLocaleDateString('id-ID', options);
    }
    function formatTime(timeStr) {
        const [hours, minutes, seconds] = timeStr.split(':');
        return `${hours.padStart(2, '0')}:${minutes.padStart(2, '0')}`;
    }
    function formatCurrency(amount) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(amount);
    }

    function formatDateTime(dateTimeStr) {
        const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
        return new Date(dateTimeStr).toLocaleDateString('id-ID', options);
    }
</script>
<?php
include '../includes/footer.php';
$conn->close();
?>
