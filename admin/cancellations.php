<?php
$page_title = 'Cancellation Management';
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

    // Validate input
    if (empty($admin_notes)) {
        $_SESSION['error'] = 'Admin notes are required!';
    } else {
        if ($action === 'approve') {
            // Get reservation ID
            $res_query = $conn->prepare("SELECT reservation_id FROM cancellation_requests WHERE id = ? AND status = 'pending'");
            $res_query->bind_param("i", $cancel_id);
            $res_query->execute();
            $result = $res_query->get_result();

            if ($result->num_rows > 0) {
                $res_id = $result->fetch_assoc()['reservation_id'];
                $res_query->close();

                // Start transaction for data consistency
                $conn->begin_transaction();

                try {
                    // Update cancellation request to approved
                    $stmt = $conn->prepare("UPDATE cancellation_requests SET status = 'approved', admin_notes = ?, processed_by = ?, processed_at = NOW() WHERE id = ?");
                    $stmt->bind_param("sii", $admin_notes, $admin_id, $cancel_id);
                    $stmt->execute();
                    $stmt->close();

                    // Update reservation status to cancelled AND set refund_status to 'refunded'
                    $update_res = $conn->prepare("UPDATE reservations SET booking_status = 'cancelled', refund_status = 'refunded' WHERE id = ?");
                    $update_res->bind_param("i", $res_id);
                    $update_res->execute();
                    $update_res->close();

                    // Commit transaction
                    $conn->commit();

                    log_activity($conn, $admin_id, 'approve_cancellation', "Approved cancellation request ID: $cancel_id");
                    $_SESSION['success'] = 'Cancellation approved! Refund will be processed.';
                } catch (Exception $e) {
                    // Rollback on error
                    $conn->rollback();
                    $_SESSION['error'] = 'Failed to approve cancellation: ' . $e->getMessage();
                }
            } else {
                $res_query->close();
                $_SESSION['error'] = 'Invalid cancellation request or already processed!';
            }
        } elseif ($action === 'reject') {
            // Update cancellation request to rejected
            $stmt = $conn->prepare("UPDATE cancellation_requests SET status = 'rejected', admin_notes = ?, processed_by = ?, processed_at = NOW() WHERE id = ? AND status = 'pending'");
            $stmt->bind_param("sii", $admin_notes, $admin_id, $cancel_id);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                log_activity($conn, $admin_id, 'reject_cancellation', "Rejected cancellation request ID: $cancel_id");
                $_SESSION['success'] = 'Cancellation rejected successfully!';
            } else {
                $_SESSION['error'] = 'Failed to reject cancellation or already processed!';
            }
            $stmt->close();
        }
    }

    // Redirect to prevent form resubmission
    $redirect_url = 'cancellations.php';
    if (isset($_GET['status'])) $redirect_url .= '?status=' . urlencode($_GET['status']);
    if (isset($_GET['date_from'])) $redirect_url .= '&date_from=' . urlencode($_GET['date_from']);
    if (isset($_GET['date_to'])) $redirect_url .= '&date_to=' . urlencode($_GET['date_to']);

    header("Location: $redirect_url");
    exit();
}

// Check for session messages
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Get filter parameters
$filter_status = isset($_GET['status']) ? clean_input($_GET['status']) : 'all';
$filter_date_from = isset($_GET['date_from']) ? clean_input($_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? clean_input($_GET['date_to']) : '';

// Build WHERE clause
$where_clauses = [];
if ($filter_status !== 'all') {
    $where_clauses[] = "cr.status = '" . $conn->real_escape_string($filter_status) . "'";
}
if ($filter_date_from) {
    $where_clauses[] = "DATE(cr.created_at) >= '" . $conn->real_escape_string($filter_date_from) . "'";
}
if ($filter_date_to) {
    $where_clauses[] = "DATE(cr.created_at) <= '" . $conn->real_escape_string($filter_date_to) . "'";
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get all cancellation requests
$cancellations = $conn->query("SELECT cr.*, r.booking_code, r.total_price, r.travel_date,
    s.service_name, s.route, u.full_name as customer_name, u.email as customer_email,
    admin.full_name as processed_by_name
    FROM cancellation_requests cr
    JOIN reservations r ON cr.reservation_id = r.id
    JOIN services s ON r.service_id = s.id
    JOIN users u ON cr.user_id = u.id
    LEFT JOIN users admin ON cr.processed_by = admin.id
    $where_sql
    ORDER BY 
        CASE cr.status 
            WHEN 'pending' THEN 1 
            WHEN 'approved' THEN 2 
            WHEN 'rejected' THEN 3 
        END,
        cr.created_at DESC");

// Get statistics
$total_pending = $conn->query("SELECT COUNT(*) as count FROM cancellation_requests WHERE status = 'pending'")->fetch_assoc()['count'];
$total_approved = $conn->query("SELECT COUNT(*) as count FROM cancellation_requests WHERE status = 'approved'")->fetch_assoc()['count'];
$total_rejected = $conn->query("SELECT COUNT(*) as count FROM cancellation_requests WHERE status = 'rejected'")->fetch_assoc()['count'];

include '../includes/header.php';
?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
        color: white;
        padding: 2rem 0;
        margin-bottom: 2rem;
    }

    .page-header h1 {
        margin: 0 0 0.5rem 0;
        font-size: 2rem;
    }

    .page-header p {
        margin: 0;
        opacity: 1;
        color: #fdfdfdff;
    }

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

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        border: 1px solid var(--light);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        text-align: center;
    }

    .stat-card h3 {
        font-size: 2rem;
        margin: 0.5rem 0;
        font-weight: bold;
    }

    .stat-card p {
        color: var(--gray);
        margin: 0;
        font-size: 0.9rem;
    }

    .stat-pending h3 {
        color: #f59e0b;
    }

    .stat-approved h3 {
        color: #10b981;
    }

    .stat-rejected h3 {
        color: #ef4444;
    }

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

    .table th,
    .table td {
        vertical-align: middle !important;
        padding: 0.75rem !important;
    }

    .desktop-table {
        display: table;
        width: 100%;
    }

    .mobile-cards {
        display: none;
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
        }

        .stat-card {
            padding: 1rem;
        }

        .stat-card h3 {
            font-size: 1.5rem;
        }

        .stat-card p {
            font-size: 0.75rem;
        }

        .mobile-cancel-card {
            background: white;
            border: 1px solid var(--light);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
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
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .mobile-cancel-card .cancel-actions {
            flex-direction: column;
        }

        .mobile-cancel-card .cancel-actions .btn {
            width: 100%;
            justify-content: center;
        }
    }

    .table .btn {
        margin: 2px;
        padding: 0.4rem 0.8rem !important;
    }

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

    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        align-items: end;
    }

    @media (max-width: 768px) {
        .filter-grid {
            grid-template-columns: 1fr;
        }

        .filter-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }
    }

    @media (max-width: 768px) {

        /* Make booking code smaller in mobile cards */
        .mobile-cancel-card .booking-code {
            font-size: 1.3rem !important;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 0.25rem;
            word-break: break-all;
            overflow-wrap: break-word;
            letter-spacing: -0.5px;
            padding: 2px 4px;
            background: #f0f7ff;
            border-radius: 4px;
            display: inline-block;
            max-width: 100%;
        }
        
        }

        /* Make the ID smaller too */
        .mobile-cancel-card .cancel-id {
            font-size: 0.9rem !important;
            color: var(--gray);
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
        }

    @media (max-width: 480px) {

        /* Even smaller on very small screens */
        .mobile-cancel-card .booking-code {
            font-size: 0.9rem !important;
            letter-spacing: -0.3px;
            padding: 1px 3px;
        }

        .mobile-cancel-card .cancel-id {
            font-size: 0.75rem !important;
        }
    }
</style>
<section class="page-header">
    <div class="container">
        <h1><i class="fas fa-ban"></i> Cancellation Management</h1>
        <p>Review and process customer cancellation requests</p>
    </div>
</section>

<section style="padding: 2rem 0;">
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

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card stat-pending">
                <i class="fas fa-clock" style="font-size: 2rem; color: #f59e0b;"></i>
                <h3><?php echo $total_pending; ?></h3>
                <p>Pending Review</p>
            </div>
            <div class="stat-card stat-approved">
                <i class="fas fa-check-circle" style="font-size: 2rem; color: #10b981;"></i>
                <h3><?php echo $total_approved; ?></h3>
                <p>Approved</p>
            </div>
            <div class="stat-card stat-rejected">
                <i class="fas fa-times-circle" style="font-size: 2rem; color: #ef4444;"></i>
                <h3><?php echo $total_rejected; ?></h3>
                <p>Rejected</p>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-filter"></i> Filter Search</h3>
            </div>
            <form method="GET" action="" style="padding: 1.5rem;">
                <div class="filter-grid">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="status">Status</label>
                        <select name="status" id="status" class="form-control">
                            <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="date_from">From Date</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo $filter_date_from; ?>">
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="date_to">To Date</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo $filter_date_to; ?>">
                    </div>

                    <div class="filter-actions" style="display: flex; gap: 0.5rem;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <a href="cancellations.php" class="btn btn-secondary" style="flex: 1; text-align: center;">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Cancellation Requests Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-list"></i> Cancellation Requests
                    <?php if ($cancellations->num_rows > 0): ?>
                        <span style="color: var(--gray); font-size: 0.9rem;">(<?php echo $cancellations->num_rows; ?> total)</span>
                    <?php endif; ?>
                </h3>
            </div>

            <?php if ($cancellations->num_rows > 0): ?>
                <!-- Desktop & Tablet Table -->
                <div class="table-container">
                    <table class="table desktop-table">
                        <thead>
                            <tr>
                                <th style="min-width: 50px; text-align: center;">ID</th>
                                <th style="min-width: 120px;">Booking Code</th>
                                <th style="min-width: 150px;">Customer</th>
                                <th style="min-width: 180px;">Service</th>
                                <th style="min-width: 100px; text-align: center;">Travel Date</th>
                                <th style="min-width: 100px; text-align: center;">Total Price</th>
                                <th style="min-width: 80px; text-align: center;">Reason</th>
                                <th style="min-width: 110px; text-align: center;">Status</th>
                                <th style="min-width: 120px; text-align: center;">Requested</th>
                                <th style="min-width: 200px; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $cancellations->data_seek(0);
                            while ($cancel = $cancellations->fetch_assoc()):
                            ?>
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
                                        $status_class = $cancel['status'] === 'pending' ? 'pending' : ($cancel['status'] === 'approved' ? 'approved' : 'rejected');
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
                                                class="btn btn-success" style="padding: 0.4rem 0.8rem; margin: 0.1rem; background-color: #16a34a; border-color: #16a34a;">
                                                <i class="fas fa-check"></i> Setujuian
                                            </button>
                                            <button onclick="processCancel(<?php echo $cancel['id']; ?>, 'reject')"
                                                class="btn btn-danger" style="padding: 0.4rem 0.8rem; margin: 0.1rem;">
                                                <i class="fas fa-times"></i> Tolak
                                            </button>
                                        <?php else: ?>
                                            <small style="color: var(--gray); display: block;">
                                                <strong>Processed by:</strong><br>
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

                            <div class="cancel-main" style="margin-bottom: 0.5rem;">
                                <div class="booking-code" style="font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; 
                                            font-size: 0.9rem; font-weight: bold; color: var(--primary); 
                                            background: #f0f7ff; padding: 4px 8px; border-radius: 4px; 
                                            border-left: 3px solid var(--primary); word-break: break-all;">
                                    <?php echo $cancel['booking_code']; ?>
                                </div>
                            </div>

                            <div class="cancel-details">
                                <div class="cancel-detail-item">
                                    <span class="detail-label">Customer</span>
                                    <span class="detail-value"><?php echo $cancel['customer_name']; ?></span>
                                    <small style="color: var(--gray);"><?php echo $cancel['customer_email']; ?></small>
                                </div>
                                <div class="cancel-detail-item">
                                    <span class="detail-label">Service</span>
                                    <span class="detail-value"><?php echo $cancel['service_name']; ?></span>
                                    <small style="color: var(--gray);"><?php echo $cancel['route']; ?></small>
                                </div>
                                <div class="cancel-detail-item">
                                    <span class="detail-label">Travel Date</span>
                                    <span class="detail-value"><?php echo format_date($cancel['travel_date']); ?></span>
                                </div>
                                <div class="cancel-detail-item">
                                    <span class="detail-label">Total Price</span>
                                    <span class="detail-value"><?php echo format_currency($cancel['total_price']); ?></span>
                                </div>
                                <div class="cancel-detail-item">
                                    <span class="detail-label">Reason</span>
                                    <button onclick="viewReason('<?php echo htmlspecialchars(addslashes($cancel['reason'])); ?>', '<?php echo htmlspecialchars(addslashes($cancel['admin_notes'] ?? '')); ?>')"
                                        class="btn btn-secondary" style="padding: 0.4rem 0.8rem; margin-top: 0.25rem; font-size: 0.85rem;">
                                        <i class="fas fa-eye"></i> View Reason
                                    </button>
                                </div>
                                <div class="cancel-detail-item">
                                    <span class="detail-label">Requested</span>
                                    <span class="detail-value"><?php echo format_datetime($cancel['created_at']); ?></span>
                                </div>
                            </div>

                            <?php if ($cancel['status'] !== 'pending'): ?>
                                <div class="admin-info">
                                    <strong>Processed by:</strong> <?php echo $cancel['processed_by_name'] ?: '-'; ?><br>
                                    <strong>On:</strong> <?php echo $cancel['processed_at'] ? format_datetime($cancel['processed_at']) : '-'; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($cancel['status'] === 'pending'): ?>
                                <div class="cancel-actions">
                                    <button onclick="processCancel(<?php echo $cancel['id']; ?>, 'approve')"
                                        class="btn btn-success">
                                        <i class="fas fa-check"></i> Setujuian
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
            <?php else: ?>
                <div style="padding: 3rem; text-align: center; color: var(--gray);">
                    <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.5; margin-bottom: 1rem;"></i>
                    <p>No cancellation requests found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Reason Modal -->
<div id="reasonModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card modal-content" style="max-width: 600px; margin: 2rem; width: 100%;">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-info-circle"></i> Cancellation Details</h3>
        </div>
        <div style="max-height: 70vh; overflow-y: auto; padding: 1.5rem;">
            <h4>Customer's Reason:</h4>
            <p id="cancelReason" style="color: var(--gray); white-space: pre-wrap; background: var(--light); padding: 1rem; border-radius: 8px;"></p>

            <div id="adminNotesSection" style="display: none; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--light);">
                <h4>Admin Notes:</h4>
                <p id="adminNotes" style="color: var(--gray); white-space: pre-wrap; background: #e0f2fe; padding: 1rem; border-radius: 8px;"></p>
            </div>

            <button type="button" onclick="closeReasonModal()" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>
</div>

<!-- Process Modal -->
<div id="processModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card modal-content" style="max-width: 500px; margin: 2rem; width: 100%;">
        <div class="card-header">
            <h3 class="card-title" id="modalTitle">Process Cancellation</h3>
        </div>
        <form method="POST" action="" style="padding: 1.5rem;">
            <input type="hidden" name="cancel_id" id="process_cancel_id">
            <input type="hidden" name="action" id="process_action">

            <div class="form-group">
                <label for="admin_notes">Admin Notes *</label>
                <textarea name="admin_notes" id="admin_notes" class="form-control" rows="4"
                    placeholder="Provide reason for your decision..." required></textarea>
                <small>This will be visible to the customer</small>
            </div>

            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <button type="submit" class="btn btn-primary" style="flex: 1; min-width: 120px;">
                    <i class="fas fa-check"></i> Confirm
                </button>
                <button type="button" onclick="closeProcessModal()" class="btn btn-secondary" style="flex: 1; min-width: 120px;">
                    <i class="fas fa-times"></i> Cancel
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
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-check-circle"></i> Approve Cancellation';
            document.getElementById('admin_notes').placeholder = 'Example: Cancellation approved. Refund will be processed within 3-7 business days.';
        } else {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-times-circle"></i> Reject Cancellation';
            document.getElementById('admin_notes').placeholder = 'Example: Cancellation rejected - request submitted less than 24 hours before departure as per policy.';
        }

        document.getElementById('admin_notes').value = '';
        document.getElementById('processModal').style.display = 'flex';
    }

    function closeProcessModal() {
        document.getElementById('processModal').style.display = 'none';
    }

    // Close modals when clicking outside
    window.onclick = function(event) {
        const reasonModal = document.getElementById('reasonModal');
        const processModal = document.getElementById('processModal');

        if (event.target === reasonModal) {
            closeReasonModal();
        }
        if (event.target === processModal) {
            closeProcessModal();
        }
    }

    // Close modals with ESC key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeReasonModal();
            closeProcessModal();
        }
    });

    // Auto-hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s ease';
                setTimeout(() => alert.remove(), 500);
            }, 5000);
        });
    });
</script>

<?php
$conn->close();
include '../includes/footer.php';
?>