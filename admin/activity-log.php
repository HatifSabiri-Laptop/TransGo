<?php
$page_title = 'Activity Log';
require_once '../config/config.php';
require_login();
require_admin();

$conn = getDBConnection();

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Get total logs
$total_logs = $conn->query("SELECT COUNT(*) as count FROM activity_logs")->fetch_assoc()['count'];
$total_pages = ceil($total_logs / $per_page);

// Get activity logs
$logs = $conn->query("SELECT al.*, u.full_name, u.email 
    FROM activity_logs al 
    JOIN users u ON al.user_id = u.id 
    ORDER BY al.created_at DESC 
    LIMIT $per_page OFFSET $offset");

include '../includes/header.php';
?>

<section style="padding: 2rem 0; background: var(--light);">
    <div class="container">
        <h1><i class="fas fa-history"></i> Activity Log</h1>
        <p style="color: var(--gray);">Riwayat aktivitas sistem dan admin</p>
    </div>
</section>

<section style="padding: 2rem 0;">
    <div class="container">
        <div class="card">
            <div class="card-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h3 class="card-title">Log Aktivitas</h3>
                    <div>
                        <span style="color: var(--gray);">Total: <?php echo number_format($total_logs); ?> records</span>
                    </div>
                </div>
            </div>
            
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>IP Address</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($log = $logs->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $log['id']; ?></td>
                            <td>
                                <strong><?php echo $log['full_name']; ?></strong><br>
                                <small style="color: var(--gray);"><?php echo $log['email']; ?></small>
                            </td>
                            <td>
                                <?php
                                $action_icons = [
                                    'login' => 'fas fa-sign-in-alt',
                                    'logout' => 'fas fa-sign-out-alt',
                                    'create_reservation' => 'fas fa-plus-circle',
                                    'check_in' => 'fas fa-check-circle',
                                    'cancel_request' => 'fas fa-ban',
                                    'approve_cancellation' => 'fas fa-check',
                                    'reject_cancellation' => 'fas fa-times',
                                    'add_user' => 'fas fa-user-plus',
                                    'edit_user' => 'fas fa-user-edit',
                                    'delete_user' => 'fas fa-user-minus',
                                    'add_service' => 'fas fa-plus',
                                    'edit_service' => 'fas fa-edit',
                                    'delete_service' => 'fas fa-trash',
                                    'add_article' => 'fas fa-newspaper',
                                    'edit_article' => 'fas fa-edit',
                                    'delete_article' => 'fas fa-trash-alt',
                                    'update_profile' => 'fas fa-user-circle',
                                    'change_password' => 'fas fa-key'
                                ];
                                
                                $icon = $action_icons[$log['action']] ?? 'fas fa-circle';
                                ?>
                                <i class="<?php echo $icon; ?>"></i>
                                <?php echo str_replace('_', ' ', ucfirst($log['action'])); ?>
                            </td>
                            <td>
                                <small style="color: var(--gray);">
                                    <?php echo $log['description'] ? htmlspecialchars($log['description']) : '-'; ?>
                                </small>
                            </td>
                            <td><?php echo $log['ip_address']; ?></td>
                            <td><?php echo format_datetime($log['created_at']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div style="display: flex; justify-content: center; align-items: center; gap: 0.5rem; padding: 1.5rem; border-top: 1px solid var(--light);">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>" class="btn btn-secondary">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <span style="color: var(--gray);">
                    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                </span>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>" class="btn btn-secondary">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php
closeDBConnection($conn);
include '../includes/footer.php';
?>