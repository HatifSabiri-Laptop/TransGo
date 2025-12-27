<?php
require_once '../config/config.php';

$conn = getDBConnection();

// Get slug from URL
$slug = isset($_GET['slug']) ? clean_input($_GET['slug']) : '';

if (empty($slug)) {
    header('Location: ' . SITE_URL . '/blog/index.php');
    exit();
}

// Get article
$stmt = $conn->prepare("SELECT a.*, u.full_name as author_name 
    FROM blog_articles a 
    JOIN users u ON a.author_id = u.id 
    WHERE a.slug = ? AND a.status = 'published'");
$stmt->bind_param("s", $slug);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: ' . SITE_URL . '/blog/index.php');
    exit();
}

$article = $result->fetch_assoc();
$page_title = $article['title'];
$stmt->close();

// Get content images
$content_images = !empty($article['content_images']) ? json_decode($article['content_images'], true) : [];

// Get other recent articles
$recent_articles = $conn->query("SELECT title, slug, excerpt, published_at 
    FROM blog_articles 
    WHERE status = 'published' AND slug != '$slug' 
    ORDER BY published_at DESC 
    LIMIT 5");

include '../includes/header.php';
?>

<style>
  .cta-btn {
    background: var(--primary);
    color: white;
    width: 100%;
    font-weight: 600;
    transition: all 0.3s ease;
  }
  .cta-btn:hover {
    background: white;
    color: var(--primary);
    transform: translateY(-2px);
  }
.btn.cta-btn.btn-primary {
    background-color: var(--primary);
    color: white;
    transition: all 0.3s ease;
}

.btn.cta-btn.btn-primary:hover {
    background-color: #ffffffff;
    color: var(--primary);
    transform: translateY(-2px);
}


    .article-content {
        line-height: 1.8;
        font-size: 1.1rem;
        color: var(--dark);
    }

    .article-content p {
        margin-bottom: 1.5rem;
    }

    .article-content h2 {
        margin-top: 2rem;
        margin-bottom: 1rem;
        color: var(--primary);
    }

    .article-content h3 {
        margin-top: 1.5rem;
        margin-bottom: 0.75rem;
        color: var(--dark);
    }

    .article-content ul,
    .article-content ol {
        margin-bottom: 1.5rem;
        padding-left: 2rem;
    }

    .article-content li {
        margin-bottom: 0.5rem;
    }

    .article-meta {
        display: flex;
        align-items: center;
        gap: 2rem;
        padding: 1.5rem 0;
        border-bottom: 2px solid var(--light);
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }

    .content-image {
        width: 100%;
        max-width: 700px;
        height: auto;
        border-radius: 12px;
        margin: 2rem auto;
        display: block;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .image-caption {
        text-align: center;
        color: var(--gray);
        font-size: 0.9rem;
        font-style: italic;
        margin-top: 0.5rem;
        margin-bottom: 2rem;
    }

    .recent-article {
        padding: 1rem;
        border-bottom: 1px solid var(--light);
        transition: background 0.3s;
    }

    .recent-article:hover {
        background: var(--light);
    }

    @media (max-width: 968px) {
        .article-container {
            grid-template-columns: 1fr !important;
        }

        .article-content {
            font-size: 1rem;
        }

        .content-image {
            max-width: 100%;
        }
    }
</style>

<section style="padding: 3rem 0; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white;">
    <div class="container">
        <div style="max-width: 800px;">
            <div style="margin-bottom: 1rem;">
                <a href="<?php echo SITE_URL; ?>/blog/index.php" style="color: white; opacity: 1; text-decoration: none; font-weight: 600; background: rgba(66, 230, 148, 0.56); padding: 0.4rem 0.8rem; border-radius: 6px;">
                    <i class="fas fa-arrow-left"></i> Kembali ke Blog
                </a>
            </div>
            <h1 style="font-size: 2.5rem; margin-bottom: 1rem; line-height: 1.2;">
                <?php echo htmlspecialchars($article['title']); ?>
            </h1>
            <p style="font-size: 1.2rem; opacity: 0.9;">
                <?php echo htmlspecialchars($article['excerpt']); ?>
            </p>
        </div>
    </div>
</section>
<script>
    // Hide images that fail to load and their captions
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.content-image').forEach(function(img) {
            img.onerror = function() {
                this.style.display = 'none';
                var caption = this.nextElementSibling;
                if (caption && caption.classList.contains('image-caption')) {
                    caption.style.display = 'none';
                }
            };

            // Also check if image source is valid
            fetch(img.src)
                .then(response => {
                    if (!response.ok) {
                        img.style.display = 'none';
                        var caption = img.nextElementSibling;
                        if (caption && caption.classList.contains('image-caption')) {
                            caption.style.display = 'none';
                        }
                    }
                })
                .catch(() => {
                    img.style.display = 'none';
                    var caption = img.nextElementSibling;
                    if (caption && caption.classList.contains('image-caption')) {
                        caption.style.display = 'none';
                    }
                });
        });
    });
</script>
<section style="padding: 3rem 0;">
    <div class="container">
        <div class="article-container" style="display: grid; grid-template-columns: 2fr 1fr; gap: 3rem;">
            <!-- Main Article -->
            <div>
                <div class="card">
                    <!-- Article Meta -->
                    <div class="article-meta">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold;">
                                <?php echo strtoupper(substr($article['author_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <div style="font-weight: 600; color: var(--dark);">
                                    <?php echo htmlspecialchars($article['author_name']); ?>
                                </div>
                                <div style="font-size: 0.9rem; color: var(--gray);">
                                    <i class="fas fa-calendar"></i>
                                    <?php
                                    if (!empty($article['published_at']) && $article['published_at'] !== '0000-00-00 00:00:00') {
                                        echo format_datetime($article['published_at']);
                                    } else {
                                        echo 'Belum dipublikasikan';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Featured Image -->
                    <?php if (!empty($article['featured_image'])): ?>
                        <img src="<?php echo SITE_URL . '/' . $article['featured_image']; ?>"
                            alt="<?php echo htmlspecialchars($article['title']); ?>"
                            style="width: 100%; height: auto; max-height: 400px; object-fit: cover; border-radius: 8px; margin-bottom: 2rem;">
                    <?php endif; ?>

                    <!-- Article Content -->
                    <div class="article-content">
                        <?php echo nl2br(htmlspecialchars($article['content'])); ?>
                    </div>

                    <!-- Content Images -->
                    <?php
                    if (!empty($content_images)):
                        // Filter out empty or non-existent images
                        $valid_images = array_filter($content_images, function ($image) {
                            return !empty($image) && file_exists('../' . $image);
                        });

                        if (!empty($valid_images)):
                    ?>
                            <div style="margin-top: 3rem; padding-top: 2rem; border-top: 2px solid var(--light);">
                                <h3 style="margin-bottom: 2rem; color: var(--primary);">
                                    <i class="fas fa-images"></i> Galeri Gambar
                                </h3>
                                <?php foreach ($valid_images as $index => $image): ?>
                                    <?php if (!empty($image) && file_exists('../' . $image)): ?>
                                        <img src="<?php echo SITE_URL . '/' . $image; ?>"
                                            alt="Gambar <?php echo $index + 1; ?>"
                                            class="content-image"
                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='none';">
                                        <div class="image-caption">Gambar <?php echo $index + 1; ?></div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                    <?php
                        endif;
                    endif;
                    ?>

                    <!-- Share Buttons -->
                    <div style="margin-top: 3rem; padding-top: 2rem; border-top: 2px solid var(--light);">
                        <h4 style="margin-bottom: 1rem;"><i class="fas fa-share-alt"></i> Bagikan Artikel</h4>
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(SITE_URL . '/blog/article.php?slug=' . $slug); ?>"
                                target="_blank" class="btn" style="background: #1877f2; color: white;">
                                <i class="fab fa-facebook"></i> Facebook
                            </a>
                            <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(SITE_URL . '/blog/article.php?slug=' . $slug); ?>&text=<?php echo urlencode($article['title']); ?>"
                                target="_blank" class="btn" style="background: #1da1f2; color: white;">
                                <i class="fab fa-twitter"></i> Twitter
                            </a>
                            <a href="https://wa.me/?text=<?php echo urlencode($article['title'] . ' - ' . SITE_URL . '/blog/article.php?slug=' . $slug); ?>"
                                target="_blank" class="btn" style="background: #25d366; color: white;">
                                <i class="fab fa-whatsapp"></i> WhatsApp
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div>
                <!-- Recent Articles -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-newspaper"></i> Artikel Terbaru</h3>
                    </div>

                    <?php if ($recent_articles->num_rows > 0): ?>
                        <?php while ($recent = $recent_articles->fetch_assoc()): ?>
                            <a href="article.php?slug=<?php echo $recent['slug']; ?>"
                                class="recent-article"
                                style="display: block; text-decoration: none; color: inherit;">
                                <h5 style="margin-bottom: 0.5rem; color: var(--primary);">
                                    <?php echo htmlspecialchars($recent['title']); ?>
                                </h5>
                                <p style="font-size: 0.9rem; color: var(--gray); margin-bottom: 0.5rem;">
                                    <?php echo htmlspecialchars(substr($recent['excerpt'], 0, 80)) . '...'; ?>
                                </p>
                                <small style="color: var(--gray);">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo format_date($recent['published_at']); ?>
                                </small>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="text-align: center; padding: 2rem; color: var(--gray);">
                            Tidak ada artikel lain
                        </p>
                    <?php endif; ?>

                    <div style="padding: 1rem; text-align: center; border-top: 1px solid var(--light);">
                        <a href="<?php echo SITE_URL; ?>/blog/index.php" class="btn cta-btn btn-primary" style="width: 100%;">
                            <i class="fas fa-list"></i> Lihat Semua Artikel
                        </a>
                    </div>
                </div>
                <!-- CTA Card -->
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <div class="card" style="margin-top: 1.5rem; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white;">
                        <h4 style="color: white; margin-bottom: 1rem;">
                            <i class="fas fa-tools"></i> Admin Dashboard
                        </h4>
                        <p style="margin-bottom: 1.5rem; opacity: 0.9;">
                            Kelola sistem dan pantau aktivitas pengguna di sini.
                        </p>
                        <a href="<?php echo SITE_URL; ?>/admin/dashboard.php" class="btn cta-btn">
                            Admin Dashboard <i class="fas fa-arrow-right"></i>
                        </a>

                    <?php else: ?>
                        <div class="card" style="margin-top: 1.5rem; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white;">
                            <h4 style="color: white; margin-bottom: 1rem;">
                                <i class="fas fa-bus"></i> Pesan Tiket Sekarang
                            </h4>
                            <p style="margin-bottom: 1.5rem; opacity: 0.9;">
                                Dapatkan pengalaman perjalanan terbaik dengan layanan kami
                            </p>
                            <a href="<?php echo SITE_URL; ?>/user/reservation.php"
                                class="btn cta-btn btn-primary">
                                Pesan Tiket <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                    </div>
            </div>
        </div>
</section>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
include '../includes/footer.php';
?>