<?php
$page_title = 'Bagikan Pengalaman Anda';
require_once '../config/config.php';
require_login();

$conn = getDBConnection();
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_experience'])) {
    $rating = (int)$_POST['rating'];
    $comment = clean_input($_POST['comment']);
    $user_id = $_SESSION['user_id'];
    
    // Validate
    if ($rating < 1 || $rating > 5) {
        $error_message = 'Rating harus antara 1-5 bintang';
    } elseif (empty($comment)) {
        $error_message = 'Komentar tidak boleh kosong';
    } else {
        // Insert experience
        $stmt = $conn->prepare("INSERT INTO experiences (user_id, rating, comment) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $user_id, $rating, $comment);
        
        if ($stmt->execute()) {
            $experience_id = $stmt->insert_id;
            $stmt->close();
            
            // Handle file uploads
            $upload_dir = '../uploads/experiences/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $total_size = 0;
            $photo_count = 0;
            $video_count = 0;
            
            // Process uploaded files
            if (!empty($_FILES['media']['name'][0])) {
                foreach ($_FILES['media']['name'] as $key => $filename) {
                    if ($_FILES['media']['error'][$key] === 0) {
                        $file_size = $_FILES['media']['size'][$key];
                        $file_tmp = $_FILES['media']['tmp_name'][$key];
                        $file_type = $_FILES['media']['type'][$key];
                        
                        // Check file type
                        $is_photo = strpos($file_type, 'image/') === 0;
                        $is_video = strpos($file_type, 'video/') === 0;
                        
                        if (!$is_photo && !$is_video) {
                            continue;
                        }
                        
                        // Count files
                        if ($is_photo) {
                            $photo_count++;
                            if ($photo_count > 3) continue;
                        } else {
                            $video_count++;
                            if ($video_count > 1) continue;
                        }
                        
                        // Check total size (100MB limit)
                        $total_size += $file_size;
                        if ($total_size > 100 * 1024 * 1024) {
                            $error_message = 'Total ukuran file melebihi 100MB';
                            break;
                        }
                        
                        // Generate unique filename
                        $ext = pathinfo($filename, PATHINFO_EXTENSION);
                        $new_filename = uniqid('exp_') . '_' . time() . '.' . $ext;
                        $file_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($file_tmp, $file_path)) {
                            $media_type = $is_photo ? 'photo' : 'video';
                            $relative_path = 'uploads/experiences/' . $new_filename;
                            
                            $stmt = $conn->prepare("INSERT INTO experience_media (experience_id, media_type, file_path, file_size) VALUES (?, ?, ?, ?)");
                            $stmt->bind_param("issi", $experience_id, $media_type, $relative_path, $file_size);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                }
            }
            
            $success_message = 'Terima kasih! Pengalaman Anda telah berhasil dibagikan.';
            log_activity($conn, $user_id, 'add_experience', 'Menambahkan pengalaman dengan rating ' . $rating);
            
            header('Location: experiences.php?success=1');
            exit();
        } else {
            $error_message = 'Gagal menyimpan pengalaman. Silakan coba lagi.';
        }
    }
}

include '../includes/header.php';
?>

<style>
.upload-container {
    max-width: 800px;
    margin: 0 auto;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--dark);
}

.rating-input {
    display: flex;
    gap: 0.5rem;
    font-size: 2.5rem;
}

.rating-input input[type="radio"] {
    display: none;
}

.rating-input label {
    cursor: pointer;
    color: #e7e7c6ff;
    transition: color 0.2s;
}

.rating-input label:hover,
.rating-input label:hover ~ label,
.rating-input input[type="radio"]:checked ~ label {
    color: #fbbf24;
}

.rating-input {
    flex-direction: row-reverse;
    justify-content: flex-end;
}

textarea {
    width: 100%;
    min-height: 150px;
    padding: 1rem;
    border: 1px solid var(--light);
    border-radius: 8px;
    font-family: inherit;
    font-size: 1rem;
    resize: vertical;
}

.file-upload-area {
    border: 2px dashed var(--light);
    border-radius: 8px;
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
}

.file-upload-area:hover {
    border-color: var(--primary);
    background: var(--light);
}

.file-upload-area.dragover {
    border-color: var(--secondary);
    background: #ecfdf5;
}

.file-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.file-item {
    position: relative;
    border-radius: 8px;
    overflow: hidden;
    aspect-ratio: 1;
}

.file-item img,
.file-item video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.file-item .remove-btn {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    background: rgba(239, 68, 68, 0.9);
    color: white;
    border: none;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.file-item .file-type {
    position: absolute;
    bottom: 0.5rem;
    left: 0.5rem;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
}

.upload-info {
    background: #fef3c7;
    border-left: 4px solid #f59e0b;
    padding: 1rem;
    border-radius: 4px;
    margin-top: 1rem;
}

.upload-info ul {
    margin: 0.5rem 0 0 0;
    padding-left: 1.5rem;
}

.upload-info li {
    margin-bottom: 0.25rem;
}

@media (max-width: 768px) {
    .rating-input {
        font-size: 2rem;
    }
    
    .file-list {
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    }
}
</style>

<section style="padding: 2rem 0; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white;">
    <div class="container">
        <h1><i class="fas fa-star"></i> Bagikan Pengalaman Anda</h1>
        <p>Ceritakan pengalaman perjalanan Anda bersama TransGo</p>
    </div>
</section>

<section style="padding: 2rem 0;">
    <div class="container">
        <div class="upload-container">
            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <form method="POST" enctype="multipart/form-data" id="experienceForm">
                    <div class="form-group">
                        <label>Berikan Rating <span style="color: var(--danger);">*</span></label>
                        <div class="rating-input" id="ratingInput">
                            <input type="radio" name="rating" value="5" id="star5" required>
                            <label for="star5"><i class="fas fa-star"></i></label>
                            
                            <input type="radio" name="rating" value="4" id="star4">
                            <label for="star4"><i class="fas fa-star"></i></label>
                            
                            <input type="radio" name="rating" value="3" id="star3">
                            <label for="star3"><i class="fas fa-star"></i></label>
                            
                            <input type="radio" name="rating" value="2" id="star2">
                            <label for="star2"><i class="fas fa-star"></i></label>
                            
                            <input type="radio" name="rating" value="1" id="star1">
                            <label for="star1"><i class="fas fa-star"></i></label>
                        </div>
                        <small style="color: var(--gray); display: block; margin-top: 0.5rem;">
                            Klik bintang untuk memberikan rating
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="comment">Ceritakan Pengalaman Anda <span style="color: var(--danger);">*</span></label>
                        <textarea name="comment" id="comment" required placeholder="Bagikan pengalaman perjalanan Anda bersama TransGo..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Upload Foto & Video (Opsional)</label>
                        <div class="file-upload-area" id="uploadArea">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; color: var(--gray); margin-bottom: 1rem;"></i>
                            <p style="color: var(--gray); margin-bottom: 0.5rem;">
                                Klik atau seret file ke sini
                            </p>
                            <input type="file" name="media[]" id="mediaInput" multiple accept="image/*,video/*" style="display: none;">
                            <button type="button" class="btn btn-outline" onclick="document.getElementById('mediaInput').click()">
                                <i class="fas fa-folder-open"></i> Pilih File
                            </button>
                        </div>
                        
                        <div class="upload-info">
                            <strong><i class="fas fa-info-circle"></i> Ketentuan Upload:</strong>
                            <ul>
                                <li>Maksimal 3 foto</li>
                                <li>Maksimal 1 video</li>
                                <li>Total ukuran semua file maksimal 100MB</li>
                                <li>Format: JPG, PNG, GIF untuk foto | MP4, AVI, MOV untuk video</li>
                            </ul>
                        </div>
                        
                        <div class="file-list" id="fileList"></div>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                        <button type="submit" name="submit_experience" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-paper-plane"></i> Kirim Pengalaman
                        </button>
                        <a href="experiences.php" class="btn" style="flex: 1; background: var(--gray); color: white; text-align: center;">
                            <i class="fas fa-times"></i> Batal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<script>
const mediaInput = document.getElementById('mediaInput');
const fileList = document.getElementById('fileList');
const uploadArea = document.getElementById('uploadArea');
let selectedFiles = [];

// Handle file selection
mediaInput.addEventListener('change', function(e) {
    handleFiles(e.target.files);
});

// Drag and drop
uploadArea.addEventListener('dragover', function(e) {
    e.preventDefault();
    uploadArea.classList.add('dragover');
});

uploadArea.addEventListener('dragleave', function(e) {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
});

uploadArea.addEventListener('drop', function(e) {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    handleFiles(e.dataTransfer.files);
});

function handleFiles(files) {
    let photoCount = selectedFiles.filter(f => f.type.startsWith('image/')).length;
    let videoCount = selectedFiles.filter(f => f.type.startsWith('video/')).length;
    let totalSize = selectedFiles.reduce((sum, f) => sum + f.size, 0);
    
    for (let file of files) {
        const isPhoto = file.type.startsWith('image/');
        const isVideo = file.type.startsWith('video/');
        
        if (!isPhoto && !isVideo) {
            alert('File ' + file.name + ' bukan foto atau video yang valid');
            continue;
        }
        
        if (isPhoto && photoCount >= 3) {
            alert('Maksimal 3 foto');
            break;
        }
        
        if (isVideo && videoCount >= 1) {
            alert('Maksimal 1 video');
            break;
        }
        
        if (totalSize + file.size > 100 * 1024 * 1024) {
            alert('Total ukuran file melebihi 100MB');
            break;
        }
        
        selectedFiles.push(file);
        totalSize += file.size;
        
        if (isPhoto) photoCount++;
        if (isVideo) videoCount++;
    }
    
    displayFiles();
    updateFileInput();
}

function displayFiles() {
    fileList.innerHTML = '';
    
    selectedFiles.forEach((file, index) => {
        const div = document.createElement('div');
        div.className = 'file-item';
        
        const url = URL.createObjectURL(file);
        const isVideo = file.type.startsWith('video/');
        
        if (isVideo) {
            div.innerHTML = `
                <video src="${url}"></video>
                <div class="file-type"><i class="fas fa-video"></i> Video</div>
                <button type="button" class="remove-btn" onclick="removeFile(${index})">
                    <i class="fas fa-times"></i>
                </button>
            `;
        } else {
            div.innerHTML = `
                <img src="${url}" alt="Preview">
                <div class="file-type"><i class="fas fa-image"></i> Foto</div>
                <button type="button" class="remove-btn" onclick="removeFile(${index})">
                    <i class="fas fa-times"></i>
                </button>
            `;
        }
        
        fileList.appendChild(div);
    });
}

function removeFile(index) {
    selectedFiles.splice(index, 1);
    displayFiles();
    updateFileInput();
}

function updateFileInput() {
    const dt = new DataTransfer();
    selectedFiles.forEach(file => dt.items.add(file));
    mediaInput.files = dt.files;
}

// Rating interaction
const ratingLabels = document.querySelectorAll('#ratingInput label');
const ratingInputs = document.querySelectorAll('#ratingInput input');

ratingInputs.forEach((input, index) => {
    input.addEventListener('change', function() {
        updateRatingDisplay();
    });
});

ratingLabels.forEach(label => {
    label.addEventListener('mouseenter', function() {
        const forInput = this.getAttribute('for');
        const rating = document.getElementById(forInput).value;
        highlightStars(rating);
    });
});

document.getElementById('ratingInput').addEventListener('mouseleave', function() {
    updateRatingDisplay();
});

function highlightStars(rating) {
    ratingInputs.forEach((input, index) => {
        const label = document.querySelector(`label[for="${input.id}"]`);
        if (input.value <= rating) {
            label.style.color = '#fbbf24';
        } else {
            label.style.color = '#e5e7eb';
        }
    });
}

function updateRatingDisplay() {
    const checked = document.querySelector('#ratingInput input:checked');
    if (checked) {
        highlightStars(checked.value);
    } else {
        ratingLabels.forEach(label => {
            label.style.color = '#e5e7eb';
        });
    }
}
</script>

<?php
closeDBConnection($conn);
include '../includes/footer.php';
?>