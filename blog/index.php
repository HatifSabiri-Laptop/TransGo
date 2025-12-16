<?php
$page_title = 'Blog TransGo';
require_once '../config/config.php';

$conn = getDBConnection();

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 9;
$offset = ($page - 1) * $per_page;

// Get total articles
$total_articles = $conn->query("SELECT COUNT(*) as count FROM blog_articles WHERE status = 'published'")->fetch_assoc()['count'];
$total_pages = ceil($total_articles / $per_page);

// Get articles
$articles = $conn->query("SELECT a.*, u.full_name as author_name 
    FROM blog_articles a 
    JOIN users u ON a.author_id = u.id 
    WHERE a.status = 'published'
    ORDER BY a.published_at DESC 
    LIMIT $per_page OFFSET $offset");

include '../includes/header.php';
?>

<style>
.article-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 2rem;
}

.article-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transition: transform 0.3s, box-shadow 0.3s;
    display: flex;
    flex-direction: column;
}

.article-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.article-image {
    width: 100%;
    height: 200px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 3rem;
}

.article-body {
    padding: 1.5rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.article-meta {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--light);
}

.author-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

@media (max-width: 768px) {
    .article-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<section style="padding: 3rem 0; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white;">
    <div class="container">
        <div style="text-align: center; max-width: 800px; margin: 0 auto;">
            <h1 style="font-size: 3rem; margin-bottom: 1rem;">
                <i class="fas fa-blog"></i> Blog TransGo
            </h1>
            <p style="font-size: 1.2rem; opacity: 0.9;">
                Tips perjalanan, berita, dan informasi terbaru seputar transportasi
            </p>
        </div>
    </div>
</section>

<section style="padding: 3rem 0;">
    <div class="container">
        <?php if ($articles->num_rows > 0): ?>
            <div class="article-grid">
                <?php while ($article = $articles->fetch_assoc()): ?>
                <div class="article-card">
                    <div class="article-image">
                        <?php if (!empty($article['featured_image'])): ?>
                            <img src="<?php echo SITE_URL . '/' . $article['featured_image']; ?>" 
                                 style="width: 100%; height: 100%; object-fit: cover;" 
                                 alt="<?php echo htmlspecialchars($article['title']); ?>">
                        <?php else: ?>
                            <i class="fas fa-newspaper"></i>
                        <?php endif; ?>
                    </div>
                    
                    <div class="article-body">
                        <div class="article-meta">
                            <div class="author-avatar">
                                <?php echo strtoupper(substr($article['author_name'], 0, 1)); ?>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-size: 0.9rem; font-weight: 600;">
                                    <?php echo htmlspecialchars($article['author_name']); ?>
                                </div>
                                <div style="font-size: 0.85rem; color: var(--gray);">
                                    <i class="fas fa-calendar"></i> 
                                    <?php echo format_date($article['published_at']); ?>
                                </div>
                            </div>
                        </div>
                        
                        <h3 style="margin-bottom: 1rem; color: var(--dark); line-height: 1.4;">
                            <?php echo htmlspecialchars($article['title']); ?>
                        </h3>
                        
                        <p style="color: var(--gray); margin-bottom: 1.5rem; flex: 1; line-height: 1.6;">
                            <?php echo htmlspecialchars(substr($article['excerpt'], 0, 120)) . '...'; ?>
                        </p>
                        
                        <a href="/blog/article.php?slug=<?php echo $article['slug']; ?>" 
                           class="btn btn-primary" 
                           style="width: 100%; text-align: center;">
                            Baca Selengkapnya <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div style="display: flex; justify-content: center; align-items: center; gap: 1rem; margin-top: 3rem; flex-wrap: wrap;">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>" class="btn btn-secondary">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="btn btn-primary"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>" class="btn" style="background: white; color: var(--primary); border: 2px solid var(--primary);">
                                <?php echo $i; ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>" class="btn btn-secondary">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="card" style="text-align: center; padding: 4rem;">
                <i class="fas fa-inbox" style="font-size: 5rem; color: var(--gray); margin-bottom: 1.5rem;"></i>
                <h3>Belum Ada Artikel</h3>
                <p style="color: var(--gray); margin-top: 0.5rem;">
                    Artikel akan muncul di sini setelah dipublikasikan
                </p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php
closeDBConnection($conn);
include '../includes/footer.php';
?>