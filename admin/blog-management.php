<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$page_title = 'Manajemen Blog';
require_once '../config/config.php';
require_login();
require_admin();

$conn = getDBConnection();
$error = '';
$success = '';

// Check if content_images column exists
$column_check = $conn->query("SHOW COLUMNS FROM blog_articles LIKE 'content_images'");
$has_content_images = $column_check->num_rows > 0;

// Handle delete featured image
if (isset($_GET['delete_featured']) && isset($_GET['article_id'])) {
    $article_id = (int)$_GET['article_id'];

    $result = $conn->query("SELECT featured_image FROM blog_articles WHERE id = $article_id");
    if ($result && $row = $result->fetch_assoc()) {
        $image_path = '../' . $row['featured_image'];
        if (file_exists($image_path) && $row['featured_image']) {
            unlink($image_path);
        }

        $conn->query("UPDATE blog_articles SET featured_image = '' WHERE id = $article_id");
        log_activity($conn, $_SESSION['user_id'], 'delete_article_image', "Deleted featured image from article ID: $article_id");
        header("Location: blog-management.php?deleted_image=1");
        exit();
    }
}

// Handle delete content image (only if column exists)
if ($has_content_images && isset($_GET['delete_content_image']) && isset($_GET['article_id']) && isset($_GET['image_index'])) {
    $article_id = (int)$_GET['article_id'];
    $image_index = (int)$_GET['image_index'];

    $result = $conn->query("SELECT content_images FROM blog_articles WHERE id = $article_id");
    if ($result && $row = $result->fetch_assoc()) {
        $content_images_json = $row['content_images'];
        $images = !empty($content_images_json) && $content_images_json !== 'null' ? json_decode($content_images_json, true) : [];

        if (isset($images[$image_index])) {
            $image_path = '../' . $images[$image_index];
            if (file_exists($image_path)) {
                unlink($image_path);
            }

            unset($images[$image_index]);
            $images = array_values($images);

            $images_json = json_encode($images);
            $stmt = $conn->prepare("UPDATE blog_articles SET content_images = ? WHERE id = ?");
            $stmt->bind_param("si", $images_json, $article_id);
            $stmt->execute();
            $stmt->close();

            log_activity($conn, $_SESSION['user_id'], 'delete_article_image', "Deleted content image from article ID: $article_id");
            header("Location: blog-management.php?deleted_image=1");
            exit();
        }
    }
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_article'])) {
        $title = clean_input($_POST['title']);
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        $content = $_POST['content'];
        $excerpt = clean_input($_POST['excerpt']);
        $status = clean_input($_POST['status']);
        $author_id = $_SESSION['user_id'];

        $published_at = ($status === 'published') ? date('Y-m-d H:i:s') : NULL;

        // Handle featured image upload
        $featured_image = '';
        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/blog/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_ext = strtolower(pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($file_ext, $allowed)) {
                $featured_image = 'uploads/blog/' . uniqid() . '.' . $file_ext;
                move_uploaded_file($_FILES['featured_image']['tmp_name'], '../' . $featured_image);
            }
        }

        // Handle content images if column exists
        $content_images_json = '[]'; // Default empty array
        if ($has_content_images) {
            $content_images = [];
            for ($i = 1; $i <= 3; $i++) {
                if (isset($_FILES["content_image_$i"]) && $_FILES["content_image_$i"]['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../uploads/blog/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    $file_ext = strtolower(pathinfo($_FILES["content_image_$i"]['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                    if (in_array($file_ext, $allowed)) {
                        $img_path = 'uploads/blog/' . uniqid() . '.' . $file_ext;
                        move_uploaded_file($_FILES["content_image_$i"]['tmp_name'], '../' . $img_path);
                        $content_images[] = $img_path;
                    }
                }
            }
            $content_images_json = json_encode($content_images);
        }

        if ($has_content_images) {
            $stmt = $conn->prepare("INSERT INTO blog_articles (title, slug, content, excerpt, featured_image, content_images, author_id, status, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssss", $title, $slug, $content, $excerpt, $featured_image, $content_images_json, $author_id, $status, $published_at);
        } else {
            $stmt = $conn->prepare("INSERT INTO blog_articles (title, slug, content, excerpt, featured_image, author_id, status, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssiss", $title, $slug, $content, $excerpt, $featured_image, $author_id, $status, $published_at);
        }

        if ($stmt->execute()) {
            log_activity($conn, $author_id, 'add_article', "Added article: $title");
            $success = 'Artikel berhasil ditambahkan!';
        } else {
            $error = 'Gagal menambahkan artikel: ' . $stmt->error;
        }
        $stmt->close();
    } elseif (isset($_POST['edit_article'])) {
        $article_id = intval($_POST['article_id']);
        $title = clean_input($_POST['title']);
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        $content = $_POST['content'];
        $excerpt = clean_input($_POST['excerpt']);
        $status = clean_input($_POST['status']);

        $current = $conn->query("SELECT status, published_at, featured_image" . ($has_content_images ? ", content_images" : "") . " FROM blog_articles WHERE id = $article_id")->fetch_assoc();

        // Handle featured image update
        $featured_image = $current['featured_image'] ?? '';
        if (isset($_FILES['edit_featured_image']) && $_FILES['edit_featured_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/blog/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_ext = strtolower(pathinfo($_FILES['edit_featured_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($file_ext, $allowed)) {
                if ($featured_image && file_exists('../' . $featured_image)) {
                    unlink('../' . $featured_image);
                }

                $featured_image = 'uploads/blog/' . uniqid() . '.' . $file_ext;
                move_uploaded_file($_FILES['edit_featured_image']['tmp_name'], '../' . $featured_image);
            }
        }

        // Handle content images update if column exists
        $content_images_json = $current['content_images'] ?? '[]';
        if ($has_content_images) {
            $content_images = !empty($content_images_json) && $content_images_json !== 'null' ? json_decode($content_images_json, true) : [];
            for ($i = 1; $i <= 3; $i++) {
                if (isset($_FILES["edit_content_image_$i"]) && $_FILES["edit_content_image_$i"]['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../uploads/blog/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    $file_ext = strtolower(pathinfo($_FILES["edit_content_image_$i"]['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                    if (in_array($file_ext, $allowed)) {
                        $img_path = 'uploads/blog/' . uniqid() . '.' . $file_ext;
                        move_uploaded_file($_FILES["edit_content_image_$i"]['tmp_name'], '../' . $img_path);
                        $content_images[] = $img_path;
                    }
                }
            }
            $content_images_json = json_encode($content_images);
        }

        if ($status === 'published' && $current['status'] !== 'published') {
            $published_at = date('Y-m-d H:i:s');
            if ($has_content_images) {
                $stmt = $conn->prepare("UPDATE blog_articles SET title = ?, slug = ?, content = ?, excerpt = ?, featured_image = ?, content_images = ?, status = ?, published_at = ? WHERE id = ?");
                $stmt->bind_param("ssssssssi", $title, $slug, $content, $excerpt, $featured_image, $content_images_json, $status, $published_at, $article_id);
            } else {
                $stmt = $conn->prepare("UPDATE blog_articles SET title = ?, slug = ?, content = ?, excerpt = ?, featured_image = ?, status = ?, published_at = ? WHERE id = ?");
                $stmt->bind_param("sssssssi", $title, $slug, $content, $excerpt, $featured_image, $status, $published_at, $article_id);
            }
        } else {
            if ($has_content_images) {
                $stmt = $conn->prepare("UPDATE blog_articles SET title = ?, slug = ?, content = ?, excerpt = ?, featured_image = ?, content_images = ?, status = ? WHERE id = ?");
                $stmt->bind_param("sssssssi", $title, $slug, $content, $excerpt, $featured_image, $content_images_json, $status, $article_id);
            } else {
                $stmt = $conn->prepare("UPDATE blog_articles SET title = ?, slug = ?, content = ?, excerpt = ?, featured_image = ?, status = ? WHERE id = ?");
                $stmt->bind_param("ssssssi", $title, $slug, $content, $excerpt, $featured_image, $status, $article_id);
            }
        }

        if ($stmt->execute()) {
            log_activity($conn, $_SESSION['user_id'], 'edit_article', "Edited article ID: $article_id");
            $success = 'Artikel berhasil diupdate!';
        } else {
            $error = 'Gagal mengupdate artikel: ' . $stmt->error;
        }
        $stmt->close();
    } elseif (isset($_POST['delete_article'])) {
        $article_id = intval($_POST['article_id']);

        $result = $conn->query("SELECT featured_image" . ($has_content_images ? ", content_images" : "") . " FROM blog_articles WHERE id = $article_id");
        if ($result && $row = $result->fetch_assoc()) {
            // Delete featured image
            if (!empty($row['featured_image']) && file_exists('../' . $row['featured_image'])) {
                unlink('../' . $row['featured_image']);
            }

            // Delete content images if column exists
            if ($has_content_images && !empty($row['content_images']) && $row['content_images'] !== 'null') {
                $content_images = json_decode($row['content_images'], true);
                if (is_array($content_images)) {
                    foreach ($content_images as $img) {
                        if (!empty($img) && file_exists('../' . $img)) {
                            unlink('../' . $img);
                        }
                    }
                }
            }
        }

        $stmt = $conn->prepare("DELETE FROM blog_articles WHERE id = ?");
        $stmt->bind_param("i", $article_id);

        if ($stmt->execute()) {
            log_activity($conn, $_SESSION['user_id'], 'delete_article', "Deleted article ID: $article_id");
            $success = 'Artikel berhasil dihapus!';
        } else {
            $error = 'Gagal menghapus artikel!';
        }
        $stmt->close();
    }
}

// Get all articles
$articles = $conn->query("SELECT a.*, u.full_name as author_name 
    FROM blog_articles a 
    JOIN users u ON a.author_id = u.id 
    ORDER BY a.created_at DESC");

include '../includes/header.php';
?>

<style>
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 1.5rem;
    }

    /* Card styling with better centering */
    .card {
        margin-left: auto;
        margin-right: auto;
        width: 100%;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .card-header {
        background: linear-gradient(135deg, #64a07fb0 0%, #75d370ff 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 12px 12px 0 0;
        margin: 0;
        border: none;
    }

    .card-header .card-title {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 600;
        color: white;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .card-header .card-title i {
        font-size: 1.3rem;
    }

    /* Form styling */
    .card form {
        padding: 1.5rem;
    }

    .image-preview {
        max-width: 200px;
        max-height: 150px;
        margin-top: 0.5rem;
        border-radius: 8px;
        display: none;
    }

    .image-preview.show {
        display: block;
    }

    .current-images {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        margin-top: 1rem;
    }

    .image-item {
        position: relative;
        border: 2px solid var(--light);
        border-radius: 8px;
        padding: 0.5rem;
        background: white;
    }

    .image-item img {
        max-width: 150px;
        max-height: 100px;
        border-radius: 4px;
        display: block;
    }

    .image-item .delete-image-btn {
        position: absolute;
        top: -8px;
        right: -8px;
        background: var(--danger);
        color: white;
        border: none;
        border-radius: 50%;
        width: 28px;
        height: 28px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .image-item .delete-image-btn:hover {
        background: #dc2626;
    }

    .image-item .image-label {
        font-size: 0.8rem;
        color: var(--gray);
        margin-top: 0.25rem;
        text-align: center;
    }

    /* Desktop Table */
    @media (min-width: 769px) {
        .articles-desktop {
            display: block;
        }

        .articles-mobile {
            display: none;
        }

        /* Table container padding */
        .articles-desktop {
            padding: 1.5rem;
        }
    }

    /* Mobile Cards */
    @media (max-width: 768px) {
        .articles-desktop {
            display: none;
        }

        .articles-mobile {
            display: block;
        }

        .container {
            padding: 0 1rem;
        }

        .card-header .card-title {
            font-size: 1.2rem;
        }
    }

    .article-card {
        background: white;
        border: 1px solid var(--light);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .article-card-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid var(--light);
    }

    .article-card-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 0.5rem;
    }

    .article-card-excerpt {
        font-size: 0.9rem;
        color: var(--gray);
        margin-bottom: 1rem;
    }

    .article-card-info {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .article-card-info-item {
        font-size: 0.85rem;
    }

    .article-card-info-label {
        color: var(--gray);
        margin-bottom: 0.25rem;
    }

    .article-card-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .badge {
        display: inline-block;
        padding: 0.35rem 0.75rem;
        border-radius: 20px;
        font-size: 0.875rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .badge-success {
        background: #d1fae5;
        color: #065f46;
    }

    .badge-warning {
        background: #fef3c7;
        color: #92400e;
    }

    .modal-backdrop {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        overflow-y: auto;
        padding: 1rem;
    }

    .modal-backdrop.show {
        display: flex;
    }

    .modal-container {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
    }

    .modal-backdrop .card {
        max-width: 800px;
        width: 95%;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
    }

    /* Enhanced table styling */
    .table {
        width: 100%;
        margin: 0;
    }

    .table thead tr {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    }

    .table thead th {
        font-weight: 600;
        color: #ffffffff;
        padding: 1rem;
    }

    .table tbody tr {
        transition: background-color 0.2s;
    }

    .table tbody tr:hover {
        background-color: #f8f9fa;
    }

    /* Action buttons styling - Desktop */
    .action-buttons-container {
        display: flex;
        gap: 1px;
        justify-content: center;
        align-items: center;
    }

    .action-buttons-container .btn {
        padding: 0.4rem 0.6rem;
        margin: 0;
    }

    .action-buttons-container form {
        margin: 0;
        display: inline;
    }

    /* Table action column */
    .table td.action-column {
        white-space: nowrap;
        text-align: center;
        padding: 0.75rem;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .card form {
            padding: 1rem;
        }

        /* Mobile badge sizing */
        .badge {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        /* Mobile table adjustments */
        .table {
            font-size: 0.85rem;
        }

        .table td,
        .table th {
            padding: 0.5rem 0.25rem;
        }

        /* Action buttons in mobile table if visible */
        .action-buttons-container {
            flex-direction: column;
            gap: 2px;
        }

        .action-buttons-container .btn {
            padding: 0.3rem 0.6rem;
            font-size: 0.8rem;
        }
    }

    .action-buttons-container {
        display: flex;
        gap: 0.5rem;
        justify-content: center;
        align-items: center;
        flex-wrap: nowrap;
    }

    .action-buttons-container .btn {
        padding: 0.4rem 0.6rem;
        margin: 0;
        white-space: nowrap;
        /* Added to prevent text wrapping */
    }

    .action-buttons-container form {
        margin: 0;
        display: inline;
    }

    .modal-container {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        min-height: 100%;
        padding: 2rem 0;

    }

    .modal-backdrop .card {
        max-width: 800px;
        width: 95%;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        margin: auto;
    }

    @media (max-width: 768px) {
        .modal-container {
            padding: 1rem 0;
        }

        .modal-backdrop .card {
            width: 100%;
            max-height: 95vh;
        }

        #editModal #edit_title {
            min-height: 60px;
        }

        #editModal #edit_excerpt {
            min-height: 100px;
        }

        #editModal #edit_content {
            min-height: 200px;
        }

        #editModal .card>div:last-child {
            padding: 1rem;
        }

        #editModal .card>div:last-child>div {
            flex-direction: column;
        }

        #editModal .card>div:last-child .btn {
            width: 100%;
            margin-bottom: 0.5rem;
        }

        #editModal .card>div:last-child .btn:last-child {
            margin-bottom: 0;
        }
    }
</style>
<section style="padding: 2rem 0; background: var(--light);">
    <div class="container">
        <h1><i class="fas fa-blog"></i> Manajemen Blog</h1>
        <p style="color: var(--gray);">Kelola artikel dan konten blog</p>
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

        <?php if (isset($_GET['deleted_image'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Gambar berhasil dihapus!
            </div>
        <?php endif; ?>

        <!-- Add Article Form -->
        <div class="card" style="margin-bottom: 2rem;">
            <div class="card-header">
                <h3 class="card-title">Tambah Artikel Baru</h3>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title">Judul Artikel *</label>
                    <input type="text" name="title" id="title" class="form-control" placeholder="Judul artikel yang menarik..." required>
                </div>

                <div class="form-group">
                    <label for="excerpt">Ringkasan *</label>
                    <textarea name="excerpt" id="excerpt" class="form-control" rows="2" placeholder="Ringkasan singkat artikel" required></textarea>
                </div>

                <div class="form-group">
                    <label for="featured_image">Gambar Utama *</label>
                    <input type="file" name="featured_image" id="featured_image" class="form-control" accept="image/*" required onchange="previewImage(this, 'featured_preview')">
                    <small style="color: var(--gray);">JPG, PNG, GIF, WebP (Max 2MB)</small>
                    <img id="featured_preview" class="image-preview" alt="Preview">
                </div>

                <div class="form-group">
                    <label for="content">Konten Artikel *</label>
                    <textarea name="content" id="content" class="form-control" rows="8" placeholder="Tulis konten artikel..." required></textarea>
                </div>

                <?php if ($has_content_images): ?>
                    <div style="background: var(--light); padding: 1.5rem; border-radius: 8px; margin: 1.5rem 0;">
                        <h4 style="margin-bottom: 1rem;"><i class="fas fa-images"></i> Gambar Dalam Artikel (Opsional)</h4>

                        <div class="form-group">
                            <label for="content_image_1">Gambar 1</label>
                            <input type="file" name="content_image_1" id="content_image_1" class="form-control" accept="image/*" onchange="previewImage(this, 'content_preview_1')">
                            <img id="content_preview_1" class="image-preview" alt="Preview 1">
                        </div>

                        <div class="form-group">
                            <label for="content_image_2">Gambar 2</label>
                            <input type="file" name="content_image_2" id="content_image_2" class="form-control" accept="image/*" onchange="previewImage(this, 'content_preview_2')">
                            <img id="content_preview_2" class="image-preview" alt="Preview 2">
                        </div>

                        <div class="form-group">
                            <label for="content_image_3">Gambar 3</label>
                            <input type="file" name="content_image_3" id="content_image_3" class="form-control" accept="image/*" onchange="previewImage(this, 'content_preview_3')">
                            <img id="content_preview_3" class="image-preview" alt="Preview 3">
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="status">Status *</label>
                    <select name="status" id="status" class="form-control" required>
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                    </select>
                </div>

                <button type="submit" name="add_article" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Tambah Artikel
                </button>
            </form>
        </div>

        <!-- Articles List -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Daftar Artikel</h3>
            </div>

            <!-- Desktop Table -->
            <div class="articles-desktop" style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="text-align: center;">ID</th>
                            <th>Judul</th>
                            <th>Author</th>
                            <th style="text-align: center;">Status</th>
                            <th>Published</th>
                            <th style="text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $articles_data = [];
                        while ($article = $articles->fetch_assoc()) {
                            $articles_data[] = $article;
                        }

                        foreach ($articles_data as $article):
                        ?>
                            <tr>
                                <td style="text-align: center;"><?php echo $article['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars(substr($article['title'], 0, 50)) . (strlen($article['title']) > 50 ? '...' : ''); ?></strong><br>
                                    <small style="color: var(--gray);"><?php echo htmlspecialchars(substr($article['excerpt'], 0, 60)) . '...'; ?></small>
                                </td>
                                <td><?php echo $article['author_name']; ?></td>
                                <td style="text-align: center;">
                                    <span class="badge badge-<?php echo $article['status'] === 'published' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($article['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    if ($article['published_at']) {
                                        // Use format_date if exists, otherwise format manually
                                        if (function_exists('format_date')) {
                                            echo format_date($article['published_at']);
                                        } else {
                                            echo date('d M Y', strtotime($article['published_at']));
                                        }
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td class="action-column">
                                    <div class="action-buttons-container">
                                        <a href="<?php echo SITE_URL; ?>/blog/article.php?slug=<?php echo $article['slug']; ?>" target="_blank" class="btn btn-secondary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button onclick='editArticle(<?php echo json_encode($article, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="btn btn-secondary">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" action="" onsubmit="return confirm('Yakin ingin menghapus artikel ini?')">
                                            <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">
                                            <button type="submit" name="delete_article" class="btn btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Cards -->
            <div class="articles-mobile" style="padding: 1rem;">
                <?php foreach ($articles_data as $article): ?>
                    <div class="article-card">
                        <div class="article-card-header">
                            <div style="flex: 1;">
                                <div style="font-size: 0.8rem; color: var(--gray); margin-bottom: 0.25rem;">ID #<?php echo $article['id']; ?></div>
                                <div class="article-card-title"><?php echo htmlspecialchars($article['title']); ?></div>
                            </div>
                            <span class="badge badge-<?php echo $article['status'] === 'published' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($article['status']); ?>
                            </span>
                        </div>

                        <div class="article-card-excerpt">
                            <?php echo htmlspecialchars(substr($article['excerpt'], 0, 100)) . '...'; ?>
                        </div>

                        <div class="article-card-info">
                            <div class="article-card-info-item">
                                <div class="article-card-info-label">Author:</div>
                                <div><strong><?php echo $article['author_name']; ?></strong></div>
                            </div>
                            <div class="article-card-info-item">
                                <div class="article-card-info-label">Published:</div>
                                <div>
                                    <?php
                                    if ($article['published_at']) {
                                        if (function_exists('format_date')) {
                                            echo format_date($article['published_at']);
                                        } else {
                                            echo date('d M Y', strtotime($article['published_at']));
                                        }
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>

                        <div class="article-card-actions">
                            <a href="<?php echo SITE_URL; ?>/blog/article.php?slug=<?php echo $article['slug']; ?>" target="_blank" class="btn btn-secondary" style="flex: 1;">
                                <i class="fas fa-eye"></i> Lihat
                            </a>
                            <button onclick='editArticle(<?php echo json_encode($article, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="btn btn-secondary" style="flex: 1;">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <form method="POST" action="" style="flex: 1; display: inline;" onsubmit="return confirm('Yakin ingin menghapus?')">
                                <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">
                                <button type="submit" name="delete_article" class="btn btn-danger" style="width: 100%;">
                                    <i class="fas fa-trash"></i> Hapus
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- Edit Modal -->
<div id="editModal" class="modal-backdrop">
    <div class="modal-container">
        <div class="card">
            <div class="card-header" style="position: sticky; top: 0; background: white; z-index: 10;">
                <h3 class="card-title">Edit Artikel</h3>
            </div>

            <div style="overflow-y: auto; flex: 1; padding: 1.5rem;">
                <form method="POST" action="" enctype="multipart/form-data" id="editForm">
                    <input type="hidden" name="article_id" id="edit_article_id">
                    <input type="hidden" name="edit_article" value="1">

                    <div class="form-group">
                        <label for="edit_title">Judul</label>
                        <input type="text" name="title" id="edit_title" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_excerpt">Ringkasan</label>
                        <textarea name="excerpt" id="edit_excerpt" class="form-control" rows="2" required></textarea>
                    </div>

                    <div class="form-group">
                        <label>Gambar Utama Saat Ini</label>
                        <div id="current_featured_image_container" class="current-images"></div>

                        <label for="edit_featured_image" style="margin-top: 1rem;">Upload Gambar Baru (Opsional)</label>
                        <input type="file" name="edit_featured_image" id="edit_featured_image" class="form-control" accept="image/*" onchange="previewImage(this, 'edit_featured_preview')">
                        <small style="color: var(--gray);">Upload untuk mengganti gambar saat ini</small>
                        <img id="edit_featured_preview" class="image-preview" alt="Preview">
                    </div>

                    <div class="form-group">
                        <label for="edit_content">Konten</label>
                        <textarea name="content" id="edit_content" class="form-control" rows="10" required></textarea>
                    </div>

                    <?php if ($has_content_images): ?>
                        <div style="background: var(--light); padding: 1.5rem; border-radius: 8px; margin: 1.5rem 0;">
                            <h4 style="margin-bottom: 1rem;"><i class="fas fa-images"></i> Gambar Dalam Artikel</h4>

                            <div class="form-group">
                                <label>Gambar Saat Ini</label>
                                <div id="current_content_images_container" class="current-images"></div>
                            </div>

                            <div class="form-group">
                                <label>Tambah Gambar Baru</label>

                                <div class="form-group">
                                    <input type="file" name="edit_content_image_1" id="edit_content_image_1" class="form-control" accept="image/*" onchange="previewImage(this, 'edit_content_preview_1')">
                                    <img id="edit_content_preview_1" class="image-preview" alt="Preview 1">
                                </div>

                                <div class="form-group">
                                    <input type="file" name="edit_content_image_2" id="edit_content_image_2" class="form-control" accept="image/*" onchange="previewImage(this, 'edit_content_preview_2')">
                                    <img id="edit_content_preview_2" class="image-preview" alt="Preview 2">
                                </div>

                                <div class="form-group">
                                    <input type="file" name="edit_content_image_3" id="edit_content_image_3" class="form-control" accept="image/*" onchange="previewImage(this, 'edit_content_preview_3')">
                                    <img id="edit_content_preview_3" class="image-preview" alt="Preview 3">
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select name="status" id="edit_status" class="form-control" required>
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                        </select>
                    </div>
                </form>
            </div>

            <!-- Action Buttons - Sticky at bottom -->
            <div style="position: sticky; bottom: 0; background: white; padding: 1.5rem; border-top: 2px solid var(--light); z-index: 10; margin-top: auto;">
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" name="edit_article" form="editForm" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>

                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()" style="flex: 1;">
                        <i class="fas fa-times"></i> Batal
                    </button>

                    <button type="button" class="btn btn-danger" onclick="confirmDeleteArticle()" style="flex: 1;">
                        <i class="fas fa-trash"></i> Hapus Artikel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Delete Form -->
<form method="POST" action="" id="deleteForm" style="display: none;">
    <input type="hidden" name="article_id" id="delete_article_id">
    <input type="hidden" name="delete_article" value="1">
</form>

<script>
    function previewImage(input, previewId) {
        const preview = document.getElementById(previewId);
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.classList.add('show');
            }
            reader.readAsDataURL(input.files[0]);
        } else {
            preview.src = '';
            preview.classList.remove('show');
        }
    }

    function editArticle(article) {
        document.getElementById('edit_article_id').value = article.id;
        document.getElementById('delete_article_id').value = article.id;
        document.getElementById('edit_title').value = article.title;
        document.getElementById('edit_excerpt').value = article.excerpt;
        document.getElementById('edit_content').value = article.content;
        document.getElementById('edit_status').value = article.status;

        // Featured Image
        const featuredContainer = document.getElementById('current_featured_image_container');
        featuredContainer.innerHTML = '';
        if (article.featured_image) {
            const imgDiv = document.createElement('div');
            imgDiv.classList.add('image-item');

            const img = document.createElement('img');
            img.src = '../' + article.featured_image;
            img.alt = 'Featured Image';

            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.classList.add('delete-image-btn');
            deleteBtn.innerHTML = '&times;';
            deleteBtn.onclick = function() {
                if (confirm('Yakin ingin menghapus gambar utama?')) {
                    window.location.href = 'blog-management.php?delete_featured=1&article_id=' + article.id;
                }
            };

            imgDiv.appendChild(img);
            imgDiv.appendChild(deleteBtn);
            featuredContainer.appendChild(imgDiv);
        } else {
            featuredContainer.innerHTML = '<p style="color: var(--gray);">Tidak ada gambar utama.</p>';
        }

        // Content Images (only if column exists)
        const contentContainer = document.getElementById('current_content_images_container');
        if (contentContainer) {
            contentContainer.innerHTML = '';

            // Safely parse content_images
            let contentImages = [];
            try {
                if (article.content_images &&
                    article.content_images !== 'null' &&
                    article.content_images !== 'NULL' &&
                    article.content_images !== '' &&
                    article.content_images !== '[]') {

                    const parsed = JSON.parse(article.content_images);
                    contentImages = Array.isArray(parsed) ? parsed.filter(img => img && img.trim() !== '') : [];
                }
            } catch (e) {
                console.warn('Could not parse content_images:', article.content_images);
                contentImages = [];
            }

            if (contentImages.length > 0) {
                contentImages.forEach((imgPath, index) => {
                    const imgDiv = document.createElement('div');
                    imgDiv.classList.add('image-item');

                    const img = document.createElement('img');
                    img.src = '../' + imgPath;
                    img.alt = 'Content Image ' + (index + 1);

                    const deleteBtn = document.createElement('button');
                    deleteBtn.type = 'button';
                    deleteBtn.classList.add('delete-image-btn');
                    deleteBtn.innerHTML = '&times;';
                    deleteBtn.onclick = function() {
                        if (confirm('Yakin ingin menghapus gambar ini?')) {
                            window.location.href = 'blog-management.php?delete_content_image=1&article_id=' + article.id + '&image_index=' + index;
                        }
                    };

                    const label = document.createElement('div');
                    label.classList.add('image-label');
                    label.innerText = 'Gambar ' + (index + 1);

                    imgDiv.appendChild(img);
                    imgDiv.appendChild(deleteBtn);
                    imgDiv.appendChild(label);
                    contentContainer.appendChild(imgDiv);
                });
            } else {
                contentContainer.innerHTML = '<p style="color: var(--gray);">Tidak ada gambar dalam artikel.</p>';
            }
        }

        // Show modal
        document.getElementById('editModal').classList.add('show');
        document.body.style.overflow = 'hidden';

        // Scroll to top of form
        setTimeout(() => {
            const formContainer = document.querySelector('#editModal .card > div:first-child');
            if (formContainer) {
                formContainer.scrollTop = 0;
            }
        }, 100);
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.remove('show');
        document.body.style.overflow = 'auto';
    }

    function confirmDeleteArticle() {
        const articleId = document.getElementById('edit_article_id').value;
        const articleTitle = document.getElementById('edit_title').value;

        if (confirm('Apakah Anda yakin ingin menghapus artikel "' + articleTitle + '"? Tindakan ini tidak dapat dibatalkan!')) {
            document.getElementById('delete_article_id').value = articleId;
            document.getElementById('deleteForm').submit();
        }
    }

    // Close modal when clicking outside
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeEditModal();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('editModal').classList.contains('show')) {
            closeEditModal();
        }
    });
</script>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

include '../includes/footer.php';
?>