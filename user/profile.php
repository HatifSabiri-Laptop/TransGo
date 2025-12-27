<?php
$page_title = 'Profil';
require_once '../config/config.php';
require_login();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = clean_input($_POST['full_name']);
        $phone = clean_input($_POST['phone']);
        $email = clean_input($_POST['email']);

        if (empty($full_name) || empty($phone) || empty($email)) {
            $error = 'Semua field harus diisi!';
        } else {
            // Check if email is already taken by another user
            $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->bind_param("si", $email, $user_id);
            $check->execute();

            if ($check->get_result()->num_rows > 0) {
                $error = 'Email sudah digunakan oleh pengguna lain!';
            } else {
                $update = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, email = ? WHERE id = ?");
                $update->bind_param("sssi", $full_name, $phone, $email, $user_id);

                if ($update->execute()) {
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['email'] = $email;
                    log_activity($conn, $user_id, 'update_profile', 'Updated profile information');
                    $success = 'Profil berhasil diperbarui!';

                    // Refresh user data
                    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                } else {
                    $error = 'Gagal memperbarui profil!';
                }
                $update->close();
            }
            $check->close();
        }
    } elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Semua field password harus diisi!';
        } elseif (!password_verify($current_password, $user['password'])) {
            $error = 'Password lama tidak sesuai!';
        } elseif (strlen($new_password) < 6) {
            $error = 'Password baru minimal 6 karakter!';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Password baru tidak cocok!';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->bind_param("si", $hashed_password, $user_id);

            if ($update->execute()) {
                log_activity($conn, $user_id, 'change_password', 'Changed password');
                $success = 'Password berhasil diubah!';
            } else {
                $error = 'Gagal mengubah password!';
            }
            $update->close();
        }
    }
}

include '../includes/header.php';
?>

<style>
    /* Mobile responsive adjustments */
    @media (max-width: 768px) {
        .profile-grid {
            grid-template-columns: 1fr !important;
            gap: 1.5rem !important;
        }

        .card {
            margin-bottom: 0 !important;
        }
    }
</style>

<section style="padding: 2rem 0; background: var(--light);">
    <div class="container">
        <h1><i class="fas fa-user-circle"></i> Profil Pengguna</h1>
        <p style="color: var(--gray);">Kelola informasi akun Anda</p>
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

        <div class="profile-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
            <!-- Profile Information -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-user-edit"></i> Informasi Profil</h3>
                    </div>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="full_name">Nama Lengkap</label>
                            <input type="text" name="full_name" id="full_name" class="form-control"
                                value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" name="email" id="email" class="form-control"
                                value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="phone">Nomor Telepon</label>
                            <input type="tel" name="phone" id="phone" class="form-control"
                                value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Role</label>
                            <input type="text" class="form-control" value="<?php echo ucfirst($user['role']); ?>" disabled>
                        </div>

                        <div class="form-group">
                            <label>Tanggal Bergabung</label>
                            <input type="text" class="form-control" value="<?php echo format_datetime($user['created_at']); ?>" disabled>
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Perubahan
                        </button>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-lock"></i> Ubah Password</h3>
                    </div>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="current_password">Password Lama</label>
                            <input type="password" name="current_password" id="current_password"
                                class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">Password Baru</label>
                            <input type="password" name="new_password" id="new_password"
                                class="form-control" required>
                            <small style="color: var(--gray);">Minimal 6 karakter</small>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Konfirmasi Password Baru</label>
                            <input type="password" name="confirm_password" id="confirm_password"
                                class="form-control" required>
                        </div>

                        <button type="submit" name="change_password" class="btn btn-secondary">
                            <i class="fas fa-key"></i> Ubah Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

include '../includes/footer.php';
?>