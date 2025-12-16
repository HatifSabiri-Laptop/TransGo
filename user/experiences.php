<?php
$page_title = 'Pengalaman Anda';
require_once '../config/config.php';

$conn = getDBConnection();

// Get all approved experiences with user info, admin replies, and media
$experiences_query = "SELECT e.*, u.full_name, u.email,
    admin.full_name as admin_name,
    (SELECT COUNT(*) FROM experience_media WHERE experience_id = e.id AND media_type = 'photo') as photo_count,
    (SELECT COUNT(*) FROM experience_media WHERE experience_id = e.id AND media_type = 'video') as video_count
    FROM experiences e
    JOIN users u ON e.user_id = u.id
    LEFT JOIN users admin ON e.replied_by = admin.id
    WHERE e.status = 'approved'
    ORDER BY e.created_at DESC";

$experiences = $conn->query($experiences_query);

// Calculate average rating
$avg_rating_result = $conn->query("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM experiences WHERE status = 'approved'");
$rating_stats = $avg_rating_result->fetch_assoc();
$avg_rating = $rating_stats['avg_rating'] ? round($rating_stats['avg_rating'], 1) : 0;
$total_reviews = $rating_stats['total_reviews'];

// Get rating distribution
$rating_dist = $conn->query("SELECT rating, COUNT(*) as count FROM experiences WHERE status = 'approved' GROUP BY rating ORDER BY rating DESC");
$distribution = array_fill(1, 5, 0);
while ($row = $rating_dist->fetch_assoc()) {
    $distribution[$row['rating']] = $row['count'];
}

// Handle admin reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_reply']) && is_admin()) {
    $experience_id = intval($_POST['experience_id']);
    $admin_reply = clean_input($_POST['admin_reply']);
    $admin_id = $_SESSION['user_id'];
    
    if (!empty($admin_reply)) {
        $stmt = $conn->prepare("UPDATE experiences SET admin_reply = ?, admin_reply_at = NOW(), replied_by = ? WHERE id = ?");
        $stmt->bind_param("sii", $admin_reply, $admin_id, $experience_id);
        
        if ($stmt->execute()) {
            log_activity($conn, $admin_id, 'reply_experience', "Admin replied to experience ID: $experience_id");
            header("Location: " . $_SERVER['PHP_SELF'] . "?replied=1");
            exit();
        }
        $stmt->close();
    }
}

// Handle delete
if (isset($_GET['delete']) && is_admin()) {
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
    log_activity($conn, $_SESSION['user_id'], 'delete_experience', "Deleted experience ID: $exp_id");
    
    header("Location: " . $_SERVER['PHP_SELF'] . "?deleted=1");
    exit();
}

include '../includes/header.php';
?>

<style>
.tab-navigation {
    background: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.tab-navigation .container {
    display: flex;
    gap: 0;
}

.tab-btn {
    flex: 1;
    padding: 1.25rem 2rem;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    color: var(--gray);
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    text-decoration: none;
}

.tab-btn:hover {
    background: var(--light);
    color: var(--primary);
}

.tab-btn.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
    background: var(--light);
}

.rating-summary {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: white;
    padding: 2rem;
    border-radius: 12px;
    text-align: center;
    margin-bottom: 2rem;
}

.rating-number {
    font-size: 4rem;
    font-weight: bold;
}

.rating-bar {
    flex: 1;
    height: 8px;
    background: rgba(255,255,255,0.3);
    border-radius: 4px;
    overflow: hidden;
}

.rating-bar-fill {
    height: 100%;
    background: white;
    transition: width 0.5s;
}

.experience-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.experience-header {
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
}

.experience-media {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 1rem;
    margin: 1rem 0;
}

.media-item {
    position: relative;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    aspect-ratio: 1;
}

.media-item img,
.media-item video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.add-experience-btn {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    z-index: 100;
}

.experience-container {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 2rem;
}

/* Admin Reply Section */
.admin-reply-section {
    background: #eff6ff;
    border-left: 4px solid var(--primary);
    padding: 1rem;
    margin-top: 1rem;
    border-radius: 8px;
}

.admin-reply-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--primary);
}

.admin-reply-content {
    color: var(--dark);
    line-height: 1.6;
}

.admin-reply-time {
    font-size: 0.875rem;
    color: var(--gray);
    margin-top: 0.5rem;
}

/* Admin Actions */
.admin-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 2px solid var(--light);
}

.reply-form {
    background: var(--light);
    padding: 1rem;
    border-radius: 8px;
    margin-top: 1rem;
}

@media (max-width: 768px) {
    .experience-container {
        grid-template-columns: 1fr;
    }
    
    .admin-actions {
        flex-direction: column;
    }
    
    .tab-btn {
        padding: 1rem;
        font-size: 0.9rem;
    }
}
</style>

<section style="padding: 2rem 0; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white;">
    <div class="container">
        <h1>Statistik & Pengalaman</h1>
        <p>Bagikan pengalaman perjalanan Anda bersama kami</p>
    </div>
</section>

<!-- Tab Navigation -->
<div class="tab-navigation">
    <div class="container">
        <a href="<?php echo SITE_URL; ?>/user/infographics.php" class="tab-btn">
            <i class="fas fa-chart-bar"></i>
            <span>Statistik & Infografis</span>
        </a>
        <button class="tab-btn active">
            <i class="fas fa-star"></i>
            <span>Pengalaman Anda</span>
        </button>
    </div>
</div>

<section style="padding: 2rem 0;">
    <div class="container">
        <?php if (isset($_GET['replied'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Balasan berhasil ditambahkan!
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Pengalaman berhasil dihapus!
            </div>
        <?php endif; ?>
        
        <div class="experience-container">
            <!-- Rating Summary -->
            <div>
                <div class="rating-summary">
                    <div class="rating-number"><?php echo $avg_rating; ?></div>
                    <div style="font-size: 2rem; color: #fbbf24; margin: 1rem 0;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star<?php echo $i <= $avg_rating ? '' : '-o'; ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">
                        <?php echo number_format($total_reviews); ?> Ulasan
                    </p>
                </div>
                
                <div class="card">
                    <h4 style="margin-bottom: 1rem;">Distribusi Rating</h4>
                    <?php for ($i = 5; $i >= 1; $i--): 
                        $percentage = $total_reviews > 0 ? ($distribution[$i] / $total_reviews) * 100 : 0;
                    ?>
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                        <span style="width: 60px;"><?php echo $i; ?> <i class="fas fa-star" style="color: #fbbf24;"></i></span>
                        <div class="rating-bar">
                            <div class="rating-bar-fill" style="width: <?php echo $percentage; ?>%;"></div>
                        </div>
                        <span style="width: 40px; text-align: right;"><?php echo $distribution[$i]; ?></span>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <!-- Experiences List -->
            <div>
                <h2 style="margin-bottom: 1.5rem;">Pengalaman Pelanggan</h2>
                
                <?php if ($experiences->num_rows > 0): ?>
                    <?php while ($exp = $experiences->fetch_assoc()): 
                        $media_query = "SELECT * FROM experience_media WHERE experience_id = " . $exp['id'] . " ORDER BY created_at";
                        $media = $conn->query($media_query);
                    ?>
                    <div class="experience-card">
                        <div class="experience-header">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($exp['full_name'], 0, 1)); ?>
                            </div>
                            <div style="flex: 1;">
                                <h4 style="margin: 0;"><?php echo htmlspecialchars($exp['full_name']); ?></h4>
                                <div style="font-size: 0.875rem; color: var(--gray);">
                                    <?php echo format_datetime($exp['created_at']); ?>
                                </div>
                            </div>
                            <div class="rating-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?php echo $i <= $exp['rating'] ? '' : '-o'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <p style="color: var(--dark); line-height: 1.6; margin-bottom: 1rem;">
                            <?php echo nl2br(htmlspecialchars($exp['comment'])); ?>
                        </p>
                        
                        <?php if ($media->num_rows > 0): ?>
                        <div class="experience-media">
                            <?php while ($m = $media->fetch_assoc()): ?>
                                <div class="media-item" onclick="viewMedia('<?php echo SITE_URL . '/' . $m['file_path']; ?>', '<?php echo $m['media_type']; ?>')">
                                    <?php if ($m['media_type'] === 'photo'): ?>
                                        <img src="<?php echo SITE_URL . '/' . $m['file_path']; ?>" alt="Experience photo">
                                    <?php else: ?>
                                        <video src="<?php echo SITE_URL . '/' . $m['file_path']; ?>"></video>
                                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 3rem; color: white;">
                                            <i class="fas fa-play-circle"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Admin Reply Display -->
                        <?php if ($exp['admin_reply']): ?>
                        <div class="admin-reply-section">
                            <div class="admin-reply-header">
                                <i class="fas fa-reply"></i>
                                <span>Balasan dari Admin <?php echo $exp['admin_name'] ? '(' . htmlspecialchars($exp['admin_name']) . ')' : ''; ?></span>
                            </div>
                            <div class="admin-reply-content">
                                <?php echo nl2br(htmlspecialchars($exp['admin_reply'])); ?>
                            </div>
                            <div class="admin-reply-time">
                                <?php echo format_datetime($exp['admin_reply_at']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Admin Actions -->
                        <?php if (is_admin()): ?>
                        <div class="admin-actions">
                            <?php if (!$exp['admin_reply']): ?>
                            <button onclick="showReplyForm(<?php echo $exp['id']; ?>)" class="btn btn-primary" style="flex: 1;">
                                <i class="fas fa-reply"></i> Balas
                            </button>
                            <?php else: ?>
                            <button onclick="showReplyForm(<?php echo $exp['id']; ?>)" class="btn btn-secondary" style="flex: 1;">
                                <i class="fas fa-edit"></i> Edit Balasan
                            </button>
                            <?php endif; ?>
                            <a href="?delete=<?php echo $exp['id']; ?>" 
                               class="btn btn-danger" 
                               style="flex: 1;"
                               onclick="return confirm('Yakin ingin menghapus pengalaman ini beserta semua media?')">
                                <i class="fas fa-trash"></i> Hapus
                            </a>
                        </div>
                        
                        <!-- Reply Form (Hidden by default) -->
                        <div id="replyForm<?php echo $exp['id']; ?>" class="reply-form" style="display: none;">
                            <form method="POST" action="">
                                <input type="hidden" name="experience_id" value="<?php echo $exp['id']; ?>">
                                <div class="form-group">
                                    <label for="admin_reply<?php echo $exp['id']; ?>">Balasan Anda:</label>
                                    <textarea name="admin_reply" 
                                              id="admin_reply<?php echo $exp['id']; ?>" 
                                              class="form-control" 
                                              rows="3" 
                                              placeholder="Tulis balasan Anda..."
                                              required><?php echo $exp['admin_reply'] ? htmlspecialchars($exp['admin_reply']) : ''; ?></textarea>
                                </div>
                                <div style="display: flex; gap: 0.5rem;">
                                    <button type="submit" name="add_reply" class="btn btn-primary" style="flex: 1;">
                                        <i class="fas fa-paper-plane"></i> Kirim Balasan
                                    </button>
                                    <button type="button" onclick="hideReplyForm(<?php echo $exp['id']; ?>)" class="btn btn-secondary" style="flex: 1;">
                                        <i class="fas fa-times"></i> Batal
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="card" style="text-align: center; padding: 3rem;">
                        <i class="fas fa-star" style="font-size: 4rem; color: var(--gray); margin-bottom: 1rem;"></i>
                        <h3>Belum Ada Ulasan</h3>
                        <p style="color: var(--gray);">Jadilah yang pertama membagikan pengalaman Anda!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Add Experience Button (Only for non-admin users) -->
<?php if (is_logged_in() && !is_admin()): ?>
<button class="add-experience-btn" onclick="openAddExperienceModal()" title="Bagikan Pengalaman">
    <i class="fas fa-plus"></i>
</button>
<?php endif; ?>

<!-- Media Viewer Modal -->
<div id="mediaModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 12px; max-width: 1000px; width: 90%; max-height: 90vh; overflow: auto;">
        <div style="padding: 1.5rem; border-bottom: 1px solid var(--light); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;">Media</h3>
            <button onclick="closeMediaModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div style="padding: 1.5rem; text-align: center;" id="mediaViewer"></div>
    </div>
</div>

<script>
function viewMedia(url, type) {
    const modal = document.getElementById('mediaModal');
    const viewer = document.getElementById('mediaViewer');
    
    if (type === 'photo') {
        viewer.innerHTML = `<img src="${url}" style="max-width: 100%; height: auto; border-radius: 8px;">`;
    } else {
        viewer.innerHTML = `<video src="${url}" controls style="max-width: 100%; height: auto; border-radius: 8px;"></video>`;
    }
    
    modal.style.display = 'flex';
}

function closeMediaModal() {
    document.getElementById('mediaModal').style.display = 'none';
}

function openAddExperienceModal() {
    window.location.href = '<?php echo SITE_URL; ?>/user/add-experience.php';
}

function showReplyForm(expId) {
    document.getElementById('replyForm' + expId).style.display = 'block';
}

function hideReplyForm(expId) {
    document.getElementById('replyForm' + expId).style.display = 'none';
}

document.getElementById('mediaModal').addEventListener('click', function(e) {
    if (e.target === this) closeMediaModal();
});
</script>

<?php
closeDBConnection($conn);
include '../includes/footer.php';
?>