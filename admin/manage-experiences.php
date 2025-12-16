<?php
$page_title = 'Kelola Pengalaman';
require_once '../config/config.php';
require_admin();

$conn = getDBConnection();

// Handle status change
if (isset($_POST['change_status'])) {
    $exp_id = (int)$_POST['experience_id'];
    $new_status = clean_input($_POST['status']);
    
    if (in_array($new_status, ['approved', 'pending', 'rejected'])) {
        $stmt = $conn->prepare("UPDATE experiences SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $exp_id);
        $stmt->execute();
        $stmt->close();
        
        log_activity($conn, $_SESSION['user_id'], 'moderate_experience', "Status pengalaman ID $exp_id diubah ke $new_status");
        header('Location: manage-experiences.php?updated=1');
        exit();
    }
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $exp_id = (int)$_GET['delete'];
    
    // Delete media files
    $media = $conn->query("SELECT file_path FROM experience_media WHERE experience_id = $exp_id");
    while ($m = $media->fetch_assoc()) {
        $file = '../' . $m['file_path'];
        if (file_exists($file)) {
            unlink($file);
        }
    }
    
    // Delete from database
    $conn->query("DELETE FROM experiences WHERE id = $exp_id");
    
    log_activity($conn, $_SESSION['user_id'], 'delete_experience', 'Admin menghapus pengalaman ID: ' . $exp_id);
    header('Location: manage-experiences.php?deleted=1');
    exit();
}

// Get filter
$filter = isset($_GET['filter']) ? clean_input($_GET['filter']) : 'all';
$where_clause = $filter !== 'all' ? "WHERE e.status = '$filter'" : '';

// Get experiences
$experiences_query = "SELECT e.*, u.full_name, u.email,
    (SELECT COUNT(*) FROM experience_media WHERE experience_id = e.id AND media_type = 'photo') as photo_count,
    (SELECT COUNT(*) FROM experience_media WHERE experience_id = e.id AND media_type = 'video') as video_count
    FROM experiences e
    JOIN users u ON e.user_id = u.id
    $where_clause
    ORDER BY e.created_at DESC";

$experiences = $conn->query($experiences_query);

// Get counts for filters
$total_count = $conn->query("SELECT COUNT(*) as count FROM experiences")->fetch_assoc()['count'];
$pending_count = $conn->query("SELECT COUNT(*) as count FROM experiences WHERE status = 'pending'")->fetch_assoc()['count'];
$approved_count = $conn->query("SELECT COUNT(*) as count FROM experiences WHERE status = 'approved'")->fetch_assoc()['count'];
$rejected_count = $conn->query("SELECT COUNT(*) as count FROM experiences WHERE status = 'rejected'")->fetch_assoc()['count'];

include '../includes/header.php';
?>

<style>
.filter-tabs {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 0.75rem 1.5rem;
    background: white;
    border: 2px solid var(--light);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    color: var(--dark);
    font-weight: 600;
}

.filter-tab:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.filter-tab.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.filter-tab .count {
    display: inline-block;
    background: rgba(0,0,0,0.1);
    padding: 0.125rem 0.5rem;
    border-radius: 12px;
    font-size: 0.875rem;
    margin-left: 0.5rem;
}

.experience-item {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.experience-grid {
    display: grid;
    grid-template-columns: 1fr 250px;
    gap: 2rem;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.user-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: bold;
}

.rating-stars {
    color: #fbbf24;
    font-size: 1.2rem;
    margin-bottom: 1rem;
}

.media-preview {
    display: flex;
    gap: 0.5rem;
    margin: 1rem 0;
}

.media-thumb {
    width: 80px;
    height: 80px;
    border-radius: 8px;
    object-fit: cover;
    cursor: pointer;
}

.action-panel {
    background: var(--light);
    padding: 1.5rem;
    border-radius: 8px;
}

.status-select {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid var(--light);
    border-radius: 8px;
    margin-bottom: 1rem;
    font-size: 1rem;
}

@media (max-width: 968px) {
    .experience-grid {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 968px) {
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .table {
        min-width: 800px;
        font-size: 0.875rem;
    }
    
    .btn {
        padding: 0.4rem 0.6rem !important;
        font-size: 0.8rem !important;
    }
}

</style>

<section style="padding: 2rem 0; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white;">
    <div class="container">
        <h1><i class="fas fa-star"></i> Kelola Pengalaman Pelanggan</h1>
        <p>Moderasi ulasan dan testimoni dari pelanggan</p>
    </div>
</section>

<section style="padding: 2rem 0;">
    <div class="container">
        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Status pengalaman berhasil diperbarui
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Pengalaman berhasil dihapus
            </div>
        <?php endif; ?>
        
        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                Semua <span class="count"><?php echo $total_count; ?></span>
            </a>
            <a href="?filter=pending" class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                Menunggu <span class="count"><?php echo $pending_count; ?></span>
            </a>
            <a href="?filter=approved" class="filter-tab <?php echo $filter === 'approved' ? 'active' : ''; ?>">
                Disetujui <span class="count"><?php echo $approved_count; ?></span>
            </a>
            <a href="?filter=rejected" class="filter-tab <?php echo $filter === 'rejected' ? 'active' : ''; ?>">
                Ditolak <span class="count"><?php echo $rejected_count; ?></span>
            </a>
        </div>
        
        <!-- Experiences List -->
        <?php if ($experiences->num_rows > 0): ?>
            <?php while ($exp = $experiences->fetch_assoc()): 
                // Get media
                $media_query = "SELECT * FROM experience_media WHERE experience_id = " . $exp['id'] . " ORDER BY created_at LIMIT 4";
                $media = $conn->query($media_query);
            ?>
            <div class="experience-item">
                <div class="experience-grid">
                    <div>
                        <div class="user-info">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($exp['full_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <h4 style="margin: 0;"><?php echo htmlspecialchars($exp['full_name']); ?></h4>
                                <div style="font-size: 0.875rem; color: var(--gray);">
                                    <?php echo htmlspecialchars($exp['email']); ?>
                                </div>
                                <div style="font-size: 0.875rem; color: var(--gray);">
                                    <?php echo format_datetime($exp['created_at']); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?php echo $i <= $exp['rating'] ? '' : '-o'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        
                        <p style="color: var(--dark); line-height: 1.6; margin-bottom: 1rem;">
                            <?php echo nl2br(htmlspecialchars($exp['comment'])); ?>
                        </p>
                        
                        <?php if ($media->num_rows > 0): ?>
                        <div class="media-preview">
                            <?php while ($m = $media->fetch_assoc()): ?>
                                <?php if ($m['media_type'] === 'photo'): ?>
                                    <img src="<?php echo SITE_URL . '/' . $m['file_path']; ?>" 
                                         class="media-thumb" 
                                         onclick="window.open('<?php echo SITE_URL . '/' . $m['file_path']; ?>', '_blank')">
                                <?php else: ?>
                                    <video src="<?php echo SITE_URL . '/' . $m['file_path']; ?>" 
                                           class="media-thumb"
                                           onclick="window.open('<?php echo SITE_URL . '/' . $m['file_path']; ?>', '_blank')"></video>
                                <?php endif; ?>
                            <?php endwhile; ?>
                            <?php if ($exp['photo_count'] + $exp['video_count'] > 4): ?>
                                <div style="width: 80px; height: 80px; background: var(--light); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--gray); font-weight: bold;">
                                    +<?php echo ($exp['photo_count'] + $exp['video_count'] - 4); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div style="display: flex; gap: 0.5rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--light);">
                            <span style="font-size: 0.875rem; color: var(--gray);">
                                <i class="fas fa-image"></i> <?php echo $exp['photo_count']; ?> foto
                            </span>
                            <span style="font-size: 0.875rem; color: var(--gray);">
                                <i class="fas fa-video"></i> <?php echo $exp['video_count']; ?> video
                            </span>
                        </div>
                    </div>
                    
                    <div class="action-panel">
                        <h4 style="margin-bottom: 1rem;">Moderasi</h4>
                        <form method="POST" style="margin-bottom: 1rem;">
                            <input type="hidden" name="experience_id" value="<?php echo $exp['id']; ?>">
                            <select name="status" class="status-select">
                                <option value="approved" <?php echo $exp['status'] === 'approved' ? 'selected' : ''; ?>>
                                    ✓ Setujui
                                </option>
                                <option value="pending" <?php echo $exp['status'] === 'pending' ? 'selected' : ''; ?>>
                                    ⏳ Pending
                                </option>
                                <option value="rejected" <?php echo $exp['status'] === 'rejected' ? 'selected' : ''; ?>>
                                    ✗ Tolak
                                </option>
                            </select>
                            <button type="submit" name="change_status" class="btn btn-primary" style="width: 100%; margin-bottom: 0.5rem;">
                                <i class="fas fa-save"></i> Simpan Status
                            </button>
                        </form>
                        
                        <button onclick="deleteExperience(<?php echo $exp['id']; ?>)" 
                                class="btn" 
                                style="width: 100%; background: var(--danger); color: white;">
                            <i class="fas fa-trash"></i> Hapus
                        </button>
                        
                        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
                            <div style="font-size: 0.875rem; color: var(--gray);">
                                <strong>Status Saat Ini:</strong><br>
                                <span style="display: inline-block; margin-top: 0.5rem; padding: 0.25rem 0.75rem; border-radius: 20px; font-weight: 600;
                                    <?php 
                                    if ($exp['status'] === 'approved') echo 'background: #d1fae5; color: #065f46;';
                                    elseif ($exp['status'] === 'pending') echo 'background: #fef3c7; color: #92400e;';
                                    else echo 'background: #fee2e2; color: #991b1b;';
                                    ?>">
                                    <?php 
                                    if ($exp['status'] === 'approved') echo '✓ Disetujui';
                                    elseif ($exp['status'] === 'pending') echo '⏳ Menunggu';
                                    else echo '✗ Ditolak';
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="card" style="text-align: center; padding: 3rem;">
                <i class="fas fa-inbox" style="font-size: 4rem; color: var(--gray); margin-bottom: 1rem;"></i>
                <h3>Tidak Ada Data</h3>
                <p style="color: var(--gray);">
                    Belum ada pengalaman dengan filter yang dipilih
                </p>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
function deleteExperience(id) {
    if (confirm('Apakah Anda yakin ingin menghapus pengalaman ini? Semua media yang terkait juga akan dihapus.')) {
        window.location.href = 'manage-experiences.php?delete=' + id;
    }
}
</script>

<?php
closeDBConnection($conn);
include '../includes/footer.php';
?>