<?php
$page_title = 'Manajemen Pembatalan';
require_once '../config/config.php';
require_login();
require_admin();

$conn = getDBConnection();
$error = '';
$success = '';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cancel_id = intval($_POST['cancel_id']);
    $action = $_POST['action'];
    $admin_notes = clean_input($_POST['admin_notes']);
    $admin_id = $_SESSION['user_id'];
    
    if ($action === 'approve') {
        $res_query = $conn->prepare("SELECT reservation_id FROM cancellation_requests WHERE id = ?");
        $res_query->bind_param("i", $cancel_id);
        $res_query->execute();
        $res_id = $res_query->get_result()->fetch_assoc()['reservation_id'];
        $res_query->close();
        
        $stmt = $conn->prepare("UPDATE cancellation_requests SET status = 'approved', admin_notes = ?, processed_by = ?, processed_at = NOW() WHERE id = ?");
        $stmt->bind_param("sii", $admin_notes, $admin_id, $cancel_id);
        $stmt->execute();
        $stmt->close();
        
        $update_res = $conn->prepare("UPDATE reservations SET booking_status = 'cancelled' WHERE id = ?");
        $update_res->bind_param("i", $res_id);
        $update_res->execute();
        $update_res->close();
        
        log_activity($conn, $admin_id, 'approve_cancellation', "Approved cancellation request ID: $cancel_id");
        $success = 'Pembatalan disetujui!';
        
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE cancellation_requests SET status = 'rejected', admin_notes = ?, processed_by = ?, processed_at = NOW() WHERE id = ?");
        $stmt->bind_param("sii", $admin_notes, $admin_id, $cancel_id);
        
        if ($stmt->execute()) {
            log_activity($conn, $admin_id, 'reject_cancellation', "Rejected cancellation request ID: $cancel_id");
            $success = 'Pembatalan ditolak!';
        } else {
            $error = 'Gagal memproses pembatalan!';
        }
        $stmt->close();
    }
}

// Get all cancellation requests
$cancellations = $conn->query("SELECT cr.*, r.booking_code, r.total_price, r.travel_date,
    s.service_name, s.route, u.full_name as customer_name, u.email as customer_email,
    admin.full_name as processed_by_name
    FROM cancellation_requests cr
    JOIN reservations r ON cr.reservation_id = r.id
    JOIN services s ON r.service_id = s.id
    JOIN users u ON cr.user_id = u.id
    LEFT JOIN users admin ON cr.processed_by = admin.id
    ORDER BY 
        CASE cr.status 
            WHEN 'pending' THEN 1 
            WHEN 'approved' THEN 2 
            WHEN 'rejected' THEN 3 
        END,
        cr.created_at DESC");

include '../includes/header.php';
?>

<style>
@media (max-width: 968px) {
    .modal-content {
        margin: 1rem !important;
        max-width: 95% !important;
    }
}

.status-badge {
    display: inline-block;
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
    white-space: nowrap;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-approved {
    background: #d1fae5;
    color: #065f46;
}

.status-rejected {
    background: #fee2e2;
    color: #991b1b;
}

/* Table Responsive Styles */
.table-container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    width: 100%;
    display: block;
}

.table {
    min-width: 1200px;
    width: 100%;
    border-collapse: collapse;
}

/* Ensure table cells have proper alignment */
.table th,
.table td {
    vertical-align: middle !important;
    padding: 0.75rem !important;
}

/* Desktop and tablet table styles */
.desktop-table {
    display: table;
    width: 100%;
}

.mobile-cards {
    display: none;
}

/* Mobile card styles */
@media (max-width: 768px) {
    .mobile-cancel-card {
        background: white;
        border: 1px solid var(--light);
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    
    .mobile-cancel-card .cancel-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 0.75rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid var(--light);
    }
    
    .mobile-cancel-card .cancel-id {
        font-weight: bold;
        color: var(--primary);
        font-size: 0.9rem;
    }
    
    .mobile-cancel-card .cancel-status {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    .mobile-cancel-card .cancel-main {
        margin-bottom: 1rem;
    }
    
    .mobile-cancel-card .booking-code {
        font-weight: bold;
        font-size: 1.1rem;
        color: var(--primary);
        margin-bottom: 0.25rem;
    }
    
    .mobile-cancel-card .cancel-details {
        display: grid;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    
    .mobile-cancel-card .cancel-detail-item {
        display: flex;
        flex-direction: column;
        font-size: 0.9rem;
    }
    
    .mobile-cancel-card .detail-label {
        font-size: 0.8rem;
        color: var(--gray);
        margin-bottom: 0.25rem;
    }
    
    .mobile-cancel-card .detail-value {
        font-weight: 600;
        color: var(--dark);
    }
    
    .mobile-cancel-card .cancel-actions {
        display: flex;
        gap: 0.5rem;
        justify-content: flex-end;
        padding-top: 0.75rem;
        border-top: 1px solid var(--light);
    }
    
    .mobile-cancel-card .cancel-actions .btn {
        padding: 0.4rem 0.8rem !important;
        font-size: 0.9rem !important;
        min-width: auto;
    }
    
    .mobile-cancel-card .admin-info {
        background: #f8fafc;
        padding: 0.75rem;
        border-radius: 6px;
        margin-top: 1rem;
        font-size: 0.85rem;
    }
    
    .pending-bg {
        background: #fffbeb !important;
    }
}

/* Hide desktop table on mobile */
@media (max-width: 768px) {
    .desktop-table {
        display: none;
    }
    
    .mobile-cards {
        display: block;
    }
    
    .table-container {
        overflow-x: visible;
    }
}

@media (max-width: 480px) {
    .mobile-cancel-card .cancel-actions {
        flex-direction: column;
    }
    
    .mobile-cancel-card .cancel-actions .btn {
        width: 100%;
        justify-content: center;
    }
}

/* Make sure buttons in table are properly spaced */
.table .btn {
    margin: 2px;
    padding: 0.4rem 0.8rem !important;
}

/* Add scrollbar styling for desktop/tablet */
.table-container::-webkit-scrollbar {
    height: 8px;
}

.table-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.table-container::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}

.table-container::-webkit-scrollbar-thumb:hover {
    background: #555;
}
</style>

<section style="padding: 2rem 0; background: var(--light);">
    <div class="container">
        <h1><i class="fas fa-ban"></i> Manajemen Pembatalan</h1>
        <p style="color: var(--gray);">Tinjau dan proses permintaan pembatalan</p>
    </div>
</section>

<section style="padding: 2rem 0;">
    <div class="container">
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Permintaan Pembatalan</h3>
            </div>
            
            <!-- Desktop & Tablet Table -->
            <div class="table-container">
                <table class="table desktop-table">
                    <thead>
                        <tr>
                            <th style="min-width: 50px; text-align: center;">ID</th>
                            <th style="min-width: 120px;">Kode Booking</th>
                            <th style="min-width: 150px;">Customer</th>
                            <th style="min-width: 180px;">Layanan</th>
                            <th style="min-width: 100px; text-align: center;">Tanggal</th>
                            <th style="min-width: 100px; text-align: center;">Total</th>
                            <th style="min-width: 80px; text-align: center;">Alasan</th>
                            <th style="min-width: 110px; text-align: center;">Status</th>
                            <th style="min-width: 120px; text-align: center;">Diajukan</th>
                            <th style="min-width: 200px; text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($cancel = $cancellations->fetch_assoc()): ?>
                        <tr style="<?php echo $cancel['status'] === 'pending' ? 'background: #fffbeb;' : ''; ?>">
                            <td style="text-align: center;"><?php echo $cancel['id']; ?></td>
                            <td><strong><?php echo $cancel['booking_code']; ?></strong></td>
                            <td>
                                <?php echo $cancel['customer_name']; ?><br>
                                <small style="color: var(--gray);"><?php echo $cancel['customer_email']; ?></small>
                            </td>
                            <td>
                                <?php echo $cancel['service_name']; ?><br>
                                <small style="color: var(--gray);"><?php echo $cancel['route']; ?></small>
                            </td>
                            <td style="text-align: center;"><?php echo format_date($cancel['travel_date']); ?></td>
                            <td style="text-align: center;"><?php echo format_currency($cancel['total_price']); ?></td>
                            <td style="text-align: center;">
                                <button onclick="viewReason('<?php echo htmlspecialchars(addslashes($cancel['reason'])); ?>', '<?php echo htmlspecialchars(addslashes($cancel['admin_notes'] ?? '')); ?>')" 
                                        class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                            <td style="text-align: center;">
                                <?php 
                                $status_class = $cancel['status'] === 'pending' ? 'pending' : 
                                              ($cancel['status'] === 'approved' ? 'approved' : 'rejected');
                                ?>
                                <span class="status-badge status-<?php echo $status_class; ?>">
                                    <?php 
                                    if ($cancel['status'] === 'pending') echo '⏳ Pending';
                                    elseif ($cancel['status'] === 'approved') echo '✓ Approved';
                                    else echo '✗ Rejected';
                                    ?>
                                </span>
                            </td>
                            <td style="text-align: center;"><small><?php echo format_datetime($cancel['created_at']); ?></small></td>
                            <td style="white-space: nowrap; text-align: center; min-width: 200px;">
                                <?php if ($cancel['status'] === 'pending'): ?>
                                    <button onclick="processCancel(<?php echo $cancel['id']; ?>, 'approve')" 
                                            class="btn btn-secondary" style="padding: 0.4rem 0.8rem; margin: 0.1rem;">
                                        <i class="fas fa-check"></i> Setujui
                                    </button>
                                    <button onclick="processCancel(<?php echo $cancel['id']; ?>, 'reject')" 
                                            class="btn btn-danger" style="padding: 0.4rem 0.8rem; margin: 0.1rem;">
                                        <i class="fas fa-times"></i> Tolak
                                    </button>
                                <?php else: ?>
                                    <small style="color: var(--gray); display: block;">
                                        <strong>Diproses:</strong><br>
                                        <?php echo $cancel['processed_by_name'] ?: '-'; ?><br>
                                        <?php echo $cancel['processed_at'] ? format_datetime($cancel['processed_at']) : '-'; ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Mobile Cards -->
            <div class="mobile-cards" style="padding: 1rem;">
                <?php 
                // Reset pointer to iterate again
                $cancellations->data_seek(0);
                while ($cancel = $cancellations->fetch_assoc()): 
                ?>
                <div class="mobile-cancel-card <?php echo $cancel['status'] === 'pending' ? 'pending-bg' : ''; ?>">
                    <div class="cancel-header">
                        <div class="cancel-id">ID: <?php echo $cancel['id']; ?></div>
                        <div class="cancel-status status-<?php echo $cancel['status']; ?>">
                            <?php 
                            if ($cancel['status'] === 'pending') echo '⏳ Pending';
                            elseif ($cancel['status'] === 'approved') echo '✓ Approved';
                            else echo '✗ Rejected';
                            ?>
                        </div>
                    </div>
                    
                    <div class="cancel-main">
                        <div class="booking-code"><?php echo $cancel['booking_code']; ?></div>
                    </div>
                    
                    <div class="cancel-details">
                        <div class="cancel-detail-item">
                            <span class="detail-label">Customer</span>
                            <span class="detail-value"><?php echo $cancel['customer_name']; ?></span>
                            <small style="color: var(--gray);"><?php echo $cancel['customer_email']; ?></small>
                        </div>
                        <div class="cancel-detail-item">
                            <span class="detail-label">Layanan</span>
                            <span class="detail-value"><?php echo $cancel['service_name']; ?></span>
                            <small style="color: var(--gray);"><?php echo $cancel['route']; ?></small>
                        </div>
                        <div class="cancel-detail-item">
                            <span class="detail-label">Tanggal</span>
                            <span class="detail-value"><?php echo format_date($cancel['travel_date']); ?></span>
                        </div>
                        <div class="cancel-detail-item">
                            <span class="detail-label">Total</span>
                            <span class="detail-value"><?php echo format_currency($cancel['total_price']); ?></span>
                        </div>
                        <div class="cancel-detail-item">
                            <span class="detail-label">Alasan</span>
                            <button onclick="viewReason('<?php echo htmlspecialchars(addslashes($cancel['reason'])); ?>', '<?php echo htmlspecialchars(addslashes($cancel['admin_notes'] ?? '')); ?>')" 
                                    class="btn btn-secondary" style="padding: 0.4rem 0.8rem; margin-top: 0.25rem; font-size: 0.85rem;">
                                <i class="fas fa-eye"></i> Lihat Alasan
                            </button>
                        </div>
                        <div class="cancel-detail-item">
                            <span class="detail-label">Diajukan</span>
                            <span class="detail-value"><?php echo format_datetime($cancel['created_at']); ?></span>
                        </div>
                    </div>
                    
                    <?php if ($cancel['status'] !== 'pending'): ?>
                    <div class="admin-info">
                        <strong>Diproses oleh:</strong> <?php echo $cancel['processed_by_name'] ?: '-'; ?><br>
                        <strong>Pada:</strong> <?php echo $cancel['processed_at'] ? format_datetime($cancel['processed_at']) : '-'; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($cancel['status'] === 'pending'): ?>
                    <div class="cancel-actions">
                        <button onclick="processCancel(<?php echo $cancel['id']; ?>, 'approve')" 
                                class="btn btn-secondary">
                            <i class="fas fa-check"></i> Setujui
                        </button>
                        <button onclick="processCancel(<?php echo $cancel['id']; ?>, 'reject')" 
                                class="btn btn-danger">
                            <i class="fas fa-times"></i> Tolak
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</section>

<!-- Reason Modal -->
<div id="reasonModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card modal-content" style="max-width: 600px; margin: 2rem; width: 100%;">
        <div class="card-header">
            <h3 class="card-title">Detail Pembatalan</h3>
        </div>
        <div style="max-height: 70vh; overflow-y: auto;">
            <h4>Alasan Pembatalan:</h4>
            <p id="cancelReason" style="color: var(--gray); white-space: pre-wrap;"></p>
            
            <div id="adminNotesSection" style="display: none; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--light);">
                <h4>Catatan Admin:</h4>
                <p id="adminNotes" style="color: var(--gray); white-space: pre-wrap;"></p>
            </div>
            
            <button type="button" onclick="closeReasonModal()" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                Tutup
            </button>
        </div>
    </div>
</div>

<!-- Process Modal -->
<div id="processModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card modal-content" style="max-width: 500px; margin: 2rem; width: 100%;">
        <div class="card-header">
            <h3 class="card-title" id="modalTitle">Proses Pembatalan</h3>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="cancel_id" id="process_cancel_id">
            <input type="hidden" name="action" id="process_action">
            
            <div class="form-group">
                <label for="admin_notes">Catatan Admin *</label>
                <textarea name="admin_notes" id="admin_notes" class="form-control" rows="4" 
                          placeholder="Berikan catatan/alasan keputusan Anda..." required></textarea>
            </div>
            
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <button type="submit" class="btn btn-primary" style="flex: 1; min-width: 120px;">
                    <i class="fas fa-check"></i> Konfirmasi
                </button>
                <button type="button" onclick="closeProcessModal()" class="btn btn-danger" style="flex: 1; min-width: 120px;">
                    <i class="fas fa-times"></i> Batal
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function viewReason(reason, adminNotes) {
    document.getElementById('cancelReason').textContent = reason;
    
    if (adminNotes) {
        document.getElementById('adminNotes').textContent = adminNotes;
        document.getElementById('adminNotesSection').style.display = 'block';
    } else {
        document.getElementById('adminNotesSection').style.display = 'none';
    }
    
    document.getElementById('reasonModal').style.display = 'flex';
}

function closeReasonModal() {
    document.getElementById('reasonModal').style.display = 'none';
}

function processCancel(cancelId, action) {
    document.getElementById('process_cancel_id').value = cancelId;
    document.getElementById('process_action').value = action;
    
    if (action === 'approve') {
        document.getElementById('modalTitle').textContent = 'Setujui Pembatalan';
    } else {
        document.getElementById('modalTitle').textContent = 'Tolak Pembatalan';
    }
    
    document.getElementById('processModal').style.display = 'flex';
}

function closeProcessModal() {
    document.getElementById('processModal').style.display = 'none';
}

// Close modals when clicking outside
document.getElementById('reasonModal').addEventListener('click', function(e) {
    if (e.target === this) closeReasonModal();
});

document.getElementById('processModal').addEventListener('click', function(e) {
    if (e.target === this) closeProcessModal();
});
</script>

<?php
closeDBConnection($conn);
include '../includes/footer.php';
?>