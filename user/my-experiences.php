<?php
$page_title = 'Pengalaman Saya';
require_once '../config/config.php';
require_login();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Handle delete experience
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $exp_id = (int)$_GET['delete'];
    
    // Verify ownership
    $check = $conn->query("SELECT id FROM experiences WHERE id = $exp_id AND user_id = $user_id");
    if ($check->num_rows > 0) {
        // Delete media files
        $media = $conn->query("SELECT file_path FROM experience_media WHERE experience_id = $exp_id");
        while ($m = $media->fetch_assoc()) {
            $file = '../' . $m['file_path'];
            if (file_exists($file)) {
                unlink($file);
            }
        }
        
        // Delete from database (media will be deleted by CASCADE)
        $conn->query("DELETE FROM experiences WHERE id = $exp_id");
        
        log_activity($conn, $user_id, 'delete_experience', 'Menghapus pengalaman ID: ' . $exp_id);
        header('Location: my-experiences.php?deleted=1');
        exit();
    }
}

// Handle delete media
if (isset($_GET['delete_media']) && is_numeric($_GET['delete_media'])) {
    $media_id = (int)$_GET['delete_media'];
    
    // Verify ownership through experience
    $check = $conn->query("SELECT em.*, e.user_id 
        FROM experience_media em 
        JOIN experiences e ON em.experience_id = e.id 
        WHERE em.id = $media_id AND e.user_id = $user_id");
    
    if ($check->num_rows > 0) {
        $media = $check->fetch_assoc();
        $file = '../' . $media['file_path'];
        if (file_exists($file)) {
            unlink($file);
        }
        
        $conn->query("DELETE FROM experience_media WHERE id = $media_id");
        header('Location: my-experiences.php?media_deleted=1');
        exit();
    }
}

// Get user's experiences
$experiences_query = "SELECT e.*, 
    (SELECT COUNT(*) FROM experience_media WHERE experience_id = e.id AND media_type = 'photo') as photo_count,
    (SELECT COUNT(*) FROM experience_media WHERE experience_id = e.id AND media_type = 'video') as video_count
    FROM experiences e
    WHERE e.user_id = $user_id
    ORDER BY e.created_at DESC";

$experiences = $conn->query($experiences_query);

include '../includes/header.php';
?>

<style>
.experience-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.experience-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.rating-stars {
    color: #fbbf24;
    font-size: 1.2rem;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
}

.status-approved {
    background: #d1fae5;
    color: #065f46;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-rejected {
    background: #fee2e2;
    color: #991b1b;
}

.experience-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
}

.media-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.media-item {
    position: relative;
    border-radius: 8px;
    overflow: hidden;
    aspect-ratio: 1;
}

.media-item img,
.media-item video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.media-item .delete-media-btn {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    background: rgba(239, 68, 68, 0.9);
    color: white;
    border: none;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    opacity: 0;
    transition: opacity 0.3s;
}

.media-item:hover .delete-media-btn {
    opacity: 1;
}

@media (max-width: 768px) {
    .experience-header {
        flex-direction: column;
    }
    
    .experience-actions {
        width: 100%;
    }
    
    .btn-sm {
        flex: 1;
    }
}
</style>

<section style="padding: 2rem 0; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white;">
    <div class="container">
        <h1><i class="fas fa-history"></i> Pengalaman Saya</h1>
        <p>Kelola ulasan dan pengalaman yang Anda bagikan</p>
    </div>
</section>

<section style="padding: 2rem 0;">
    <div class="container">
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Pengalaman berhasil dihapus
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['media_deleted'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Media berhasil dihapus
            </div>
        <?php endif; ?>
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
            <h2 style="margin: 0;">Ulasan Anda (<?php echo $experiences->num_rows; ?>)</h2>
            <a href="add-experience.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Tambah Pengalaman Baru
            </a>
        </div>
        
        <?php if ($experiences->num_rows > 0): ?>
            <?php while ($exp = $experiences->fetch_assoc()): 
                // Get media for this experience
                $media_query = "SELECT * FROM experience_media WHERE experience_id = " . $exp['id'] . " ORDER BY created_at";
                $media = $conn->query($media_query);
                
                $status_class = 'status-' . $exp['status'];
            ?>
            <div class="experience-card">
                <div class="experience-header">
                    <div>
                        <div class="rating-stars" style="margin-bottom: 0.5rem;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?php echo $i <= $exp['rating'] ? '' : '-o'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <div style="font-size: 0.875rem; color: var(--gray);">
                            <?php echo format_datetime($exp['created_at']); ?>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <span class="status-badge <?php echo $status_class; ?>">
                            <?php 
                            if ($exp['status'] === 'approved') echo '✓ Disetujui';
                            elseif ($exp['status'] === 'pending') echo '⏳ Menunggu';
                            else echo '✗ Ditolak';
                            ?>
                        </span>
                    </div>
                </div>
                
                <p style="color: var(--dark); line-height: 1.6; margin-bottom: 1rem;">
                    <?php echo nl2br(htmlspecialchars($exp['comment'])); ?>
                </p>
                
                <?php if ($media->num_rows > 0): ?>
                <div class="media-grid">
                    <?php while ($m = $media->fetch_assoc()): ?>
                        <div class="media-item">
                            <?php if ($m['media_type'] === 'photo'): ?>
                                <img src="<?php echo SITE_URL . '/' . $m['file_path']; ?>" alt="Experience photo">
                            <?php else: ?>
                                <video src="<?php echo SITE_URL . '/' . $m['file_path']; ?>"></video>
                                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-size: 2rem;">
                                    <i class="fas fa-play-circle"></i>
                                </div>
                            <?php endif; ?>
                            <button class="delete-media-btn" onclick="deleteMedia(<?php echo $m['id']; ?>)" title="Hapus media">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    <?php endwhile; ?>
                </div>
                <?php endif; ?>
                
                <div class="experience-actions" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--light);">
                    <button class="btn btn-sm" style="background: var(--danger); color: white;" onclick="deleteExperience(<?php echo $exp['id']; ?>)">
                        <i class="fas fa-trash"></i> Hapus
                    </button>
                    <a href="experiences.php" class="btn btn-sm" style="background: var(--gray); color: white;">
                        <i class="fas fa-eye"></i> Lihat Semua Ulasan
                    </a>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="card" style="text-align: center; padding: 3rem;">
                <i class="fas fa-star" style="font-size: 4rem; color: var(--gray); margin-bottom: 1rem;"></i>
                <h3>Belum Ada Pengalaman</h3>
                <p style="color: var(--gray); margin-bottom: 2rem;">
                    Anda belum membagikan pengalaman perjalanan Anda
                </p>
                <a href="add-experience.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Bagikan Pengalaman Pertama
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
function deleteExperience(id) {
    if (confirm('Apakah Anda yakin ingin menghapus pengalaman ini? Semua media yang terkait juga akan dihapus.')) {
        window.location.href = 'my-experiences.php?delete=' + id;
    }
}

function deleteMedia(id) {
    if (confirm('Apakah Anda yakin ingin menghapus media ini?')) {
        window.location.href = 'my-experiences.php?delete_media=' + id;
    }
}
</script>

<?php
if ($conn instanceof mysqli) {
    $conn->close();
}

include '../includes/footer.php';
?>