<?php
$page_title = 'Activity Logs';
require_once '../config/config.php';
require_login();
require_admin();

$conn = getDBConnection();
require_once '../config/activity_logger.php';
$logger = new ActivityLogger($conn);

// Filters
$user_filter = $_GET['user_id'] ?? '';
$action_filter = $_GET['action'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$limit = 100;

// Get logs
$filters = [
    'user_id' => $user_filter,
    'action' => $action_filter,
    'date_from' => $date_from,
    'date_to' => $date_to,
    'limit' => $limit
];

$logs = $logger->getLogs($filters);

// Get all users for filter
$users = $conn->query("SELECT id, full_name, email FROM users ORDER BY full_name");

// Get unique actions
$actions = [
    'login' => 'Login',
    'logout' => 'Logout',
    'register' => 'Register',
    'create_reservation' => 'Create Reservation',
    'create' => 'Create',
    'update' => 'Update',
    'delete' => 'Delete',
    'payment' => 'Payment',
    'failed_login' => 'Failed Login'
];

include '../includes/header.php';
?>

<style>
    .filter-card {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        margin-bottom: 2rem;
    }

    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .log-table {
        width: 100%;
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .log-table table {
        width: 100%;
        border-collapse: collapse;
    }

    .log-table th {
        background: var(--primary);
        color: white;
        padding: 1rem;
        text-align: left;
        font-weight: 600;
    }

    .log-table td {
        padding: 1rem;
        border-bottom: 1px solid var(--light);
    }

    .log-table tr:hover {
        background: #f8fafc;
    }

    .action-badge {
        display: inline-block;
        padding: 0.35rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .action-login { background: #d1fae5; color: #065f46; }
    .action-logout { background: #fee2e2; color: #991b1b; }
    .action-create_reservation { background: #dbeafe; color: #1e40af; }
    .action-create { background: #d1fae5; color: #065f46; }
    .action-failed_login { background: #fee2e2; color: #991b1b; }
    .action-delete { background: #fecaca; color: #7f1d1d; }
    .action-update { background: #fef3c7; color: #92400e; }
    .action-register { background: #dbeafe; color: #1e40af; }
    .action-payment { background: #d1fae5; color: #065f46; }
    .action-test { background: #e5e7eb; color: #374151; }

    .stats-mini {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .stat-mini-card {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        text-align: center;
    }

    .stat-mini-card h3 {
        font-size: 2rem;
        margin-bottom: 0.5rem;
        color: var(--primary);
    }

    .stat-mini-card p {
        color: var(--gray);
        margin: 0;
    }

    /* Mobile Table */
    .mobile-log-table {
        display: none;
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .mobile-log-table table {
        width: 100%;
        border-collapse: collapse;
    }

    .mobile-log-table th {
        background: var(--primary);
        color: white;
        padding: 0.75rem 0.5rem;
        text-align: left;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .mobile-log-table td {
        padding: 0.75rem 0.5rem;
        border-bottom: 1px solid var(--light);
        vertical-align: top;
    }

    .mobile-log-table tr:hover {
        background: #f8fafc;
    }

    .mobile-user-info {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .mobile-user-name {
        font-weight: bold;
        font-size: 0.9rem;
        color: var(--dark);
    }

    .mobile-user-id {
        font-size: 0.75rem;
        color: var(--gray);
    }

    .mobile-detail-btn {
        background: var(--primary);
        color: white;
        border: none;
        padding: 0.4rem 0.8rem;
        border-radius: 6px;
        font-size: 0.85rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.4rem;
        white-space: nowrap;
        transition: background 0.3s;
    }

    .mobile-detail-btn:hover {
        background: #1e40af;
    }

    .mobile-detail-btn i {
        transition: transform 0.3s;
    }

    .mobile-detail-btn.active i {
        transform: rotate(180deg);
    }

    .mobile-detail-row {
        display: none;
    }

    .mobile-detail-row.show {
        display: table-row;
    }

    .mobile-detail-content {
        padding: 1rem;
        background: #f9fafb;
        border-left: 3px solid var(--primary);
    }

    .mobile-detail-item {
        display: flex;
        margin-bottom: 0.75rem;
        font-size: 0.9rem;
    }

    .mobile-detail-item:last-child {
        margin-bottom: 0;
    }

    .mobile-detail-label {
        font-weight: 600;
        color: var(--gray);
        min-width: 100px;
        flex-shrink: 0;
    }

    .mobile-detail-value {
        color: var(--dark);
        word-break: break-word;
    }

    @media (max-width: 768px) {
        .log-table {
            display: none;
        }

        .mobile-log-table {
            display: block;
        }

        .filter-grid {
            grid-template-columns: 1fr;
        }

        .stats-mini {
            grid-template-columns: repeat(2, 1fr);
        }

        .stat-mini-card h3 {
            font-size: 1.5rem;
        }

        .filter-card {
            padding: 1rem;
        }

        .mobile-log-table th:first-child {
            width: 60px;
        }

        .mobile-log-table th:nth-child(2) {
            width: 35%;
        }

        .mobile-log-table th:last-child {
            width: auto;
            text-align: right;
        }

        .mobile-log-table td:last-child {
            text-align: right;
        }
    }

    @media (max-width: 480px) {
        .stats-mini {
            grid-template-columns: 1fr;
        }

        .mobile-log-table th,
        .mobile-log-table td {
            padding: 0.6rem 0.4rem;
            font-size: 0.85rem;
        }

        .mobile-user-name {
            font-size: 0.85rem;
        }

        .mobile-detail-btn {
            padding: 0.35rem 0.6rem;
            font-size: 0.8rem;
        }

        .mobile-detail-label {
            min-width: 80px;
            font-size: 0.85rem;
        }

        .mobile-detail-value {
            font-size: 0.85rem;
        }
    }
</style>

<section style="padding: 2rem 0; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white;">
    <div class="container">
        <h1><i class="fas fa-history"></i> Activity Logs</h1>
        <p>Monitor semua aktivitas pengguna dan admin</p>
    </div>
</section>

<section style="padding: 2rem 0;">
    <div class="container">

        <!-- Statistics -->
        <div class="stats-mini">
            <div class="stat-mini-card" style="background: linear-gradient(135deg, #3b82f6, #2563eb); color: white;">
                <h3 style="color: white;"><?php echo count($logs); ?></h3>
                <p style="color: rgba(255,255,255,0.9);">Total Aktivitas</p>
            </div>

            <div class="stat-mini-card" style="background: linear-gradient(135deg, #10b981, #059669); color: white;">
                <h3 style="color: white;">
                    <?php echo count(array_filter($logs, fn($l) => $l['action'] === 'login')); ?>
                </h3>
                <p style="color: rgba(255,255,255,0.9);">Login Berhasil</p>
            </div>

            <div class="stat-mini-card" style="background: linear-gradient(135deg, #ef4444, #dc2626); color: white;">
                <h3 style="color: white;">
                    <?php echo count(array_filter($logs, fn($l) => $l['action'] === 'failed_login')); ?>
                </h3>
                <p style="color: rgba(255,255,255,0.9);">Failed Login</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card">
            <h3 style="margin-bottom: 1rem;"><i class="fas fa-filter"></i> Filter</h3>

            <form method="GET" action="">
                <div class="filter-grid">
                    <div class="form-group">
                        <label>User</label>
                        <select name="user_id" class="form-control">
                            <option value="">Semua User</option>
                            <?php while ($user = $users->fetch_assoc()): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['full_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Action</label>
                        <select name="action" class="form-control">
                            <option value="">Semua Action</option>
                            <?php foreach ($actions as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo $action_filter == $key ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Dari Tanggal</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                    </div>

                    <div class="form-group">
                        <label>Sampai Tanggal</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 1rem; flex-wrap: wrap;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="activity_logs.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Desktop Logs Table -->
        <div class="log-table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Waktu</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 3rem; color: var(--gray);">
                                <i class="fas fa-inbox" style="font-size: 3rem; display: block; margin-bottom: 1rem;"></i>
                                Tidak ada data activity log
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td style="color: var(--gray); font-size: 0.9rem;">#<?php echo $log['id']; ?></td>
                                <td style="white-space: nowrap;">
                                    <?php echo date('d M Y H:i', strtotime($log['created_at'])); ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($log['username'] ?? 'Guest'); ?></strong><br>
                                    <small style="color: var(--gray);">ID: <?php echo $log['user_id'] ?? '-'; ?></small>
                                </td>
                                <td>
                                    <span class="action-badge action-<?php echo $log['action']; ?>">
                                        <?php echo strtoupper(str_replace('_', ' ', $log['action'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($log['description']); ?></td>
                                <td style="font-family: monospace; font-size: 0.85rem;">
                                    <?php echo htmlspecialchars($log['ip_address']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Logs Table -->
        <div class="mobile-log-table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="3" style="text-align: center; padding: 2rem; color: var(--gray);">
                                <i class="fas fa-inbox" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                                <div style="font-size: 0.9rem;">Tidak ada data activity log</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $index => $log): ?>
                            <!-- Main Row -->
                            <tr>
                                <td style="font-weight: 600; color: var(--primary);">#<?php echo $log['id']; ?></td>
                                <td>
                                    <div class="mobile-user-info">
                                        <div class="mobile-user-name">
                                            <?php echo htmlspecialchars($log['username'] ?? 'Guest'); ?>
                                        </div>
                                        <div class="mobile-user-id">
                                            ID: <?php echo $log['user_id'] ?? '-'; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <button class="mobile-detail-btn" onclick="toggleDetails(<?php echo $index; ?>)">
                                        <span>Details</span>
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                </td>
                            </tr>
                            <!-- Detail Row -->
                            <tr class="mobile-detail-row" id="detail-<?php echo $index; ?>">
                                <td colspan="3">
                                    <div class="mobile-detail-content">
                                        <div class="mobile-detail-item">
                                            <div class="mobile-detail-label">
                                                <i class="fas fa-clock"></i> Waktu:
                                            </div>
                                            <div class="mobile-detail-value">
                                                <?php echo date('d M Y H:i:s', strtotime($log['created_at'])); ?>
                                            </div>
                                        </div>
                                        <div class="mobile-detail-item">
                                            <div class="mobile-detail-label">
                                                <i class="fas fa-tag"></i> Action:
                                            </div>
                                            <div class="mobile-detail-value">
                                                <span class="action-badge action-<?php echo $log['action']; ?>">
                                                    <?php echo strtoupper(str_replace('_', ' ', $log['action'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="mobile-detail-item">
                                            <div class="mobile-detail-label">
                                                <i class="fas fa-align-left"></i> Deskripsi:
                                            </div>
                                            <div class="mobile-detail-value">
                                                <?php echo htmlspecialchars($log['description']); ?>
                                            </div>
                                        </div>
                                        <div class="mobile-detail-item">
                                            <div class="mobile-detail-label">
                                                <i class="fas fa-network-wired"></i> IP Address:
                                            </div>
                                            <div class="mobile-detail-value" style="font-family: monospace;">
                                                <?php echo htmlspecialchars($log['ip_address']); ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($log['user_agent'])): ?>
                                        <div class="mobile-detail-item">
                                            <div class="mobile-detail-label">
                                                <i class="fas fa-desktop"></i> User Agent:
                                            </div>
                                            <div class="mobile-detail-value" style="font-size: 0.8rem; color: var(--gray);">
                                                <?php echo htmlspecialchars(substr($log['user_agent'], 0, 50)) . '...'; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</section>

<script>
function toggleDetails(index) {
    const detailRow = document.getElementById('detail-' + index);
    const button = event.currentTarget;
    
    if (detailRow.classList.contains('show')) {
        detailRow.classList.remove('show');
        button.classList.remove('active');
    } else {
        // Close all other details
        document.querySelectorAll('.mobile-detail-row').forEach(row => {
            row.classList.remove('show');
        });
        document.querySelectorAll('.mobile-detail-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Open this detail
        detailRow.classList.add('show');
        button.classList.add('active');
    }
}
</script>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
include '../includes/footer.php';
?>