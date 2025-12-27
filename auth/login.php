<?php
session_start();

$page_title = 'Login';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| Deleted user alert (shown ONCE)
|--------------------------------------------------------------------------
*/
$show_deleted_alert = false;
if (isset($_SESSION['user_deleted_alert'])) {
    $show_deleted_alert = true;
    unset($_SESSION['user_deleted_alert']);
}

/*
|--------------------------------------------------------------------------
| Redirect if already logged in
|--------------------------------------------------------------------------
*/
if (function_exists('is_logged_in') && is_logged_in()) {
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| Registration success message
|--------------------------------------------------------------------------
*/
if (isset($_GET['registered'])) {
    if (isset($_GET['email_sent'])) {
        $success = 'Registrasi berhasil! Email konfirmasi telah dikirim. Silakan login.';
    } else {
        $success = 'Registrasi berhasil! Silakan login.';
    }
}

/*
|--------------------------------------------------------------------------
| Handle login
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Email dan password harus diisi!';
    } else {

        $conn = getDBConnection();
        if (!$conn) {
            $error = 'Koneksi database gagal.';
        } else {

            $stmt = $conn->prepare(
                "SELECT id, email, password, full_name, role
                 FROM users
                 WHERE email = ?
                 LIMIT 1"
            );

            if (!$stmt) {
                $error = 'Terjadi kesalahan sistem.';
            } else {

                $stmt->bind_param('s', $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    if (password_verify($password, $user['password'])) {

                        // ✅ Login success
                        $_SESSION['user_id']   = $user['id'];
                        $_SESSION['email']     = $user['email'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['role']      = $user['role'];

                        // 🔥 LOG THE LOGIN (ONLY ONCE!)
                        log_activity($conn, $user['id'], 'login', 'User logged in successfully');

                        $stmt->close();
                        if ($conn instanceof mysqli) {
                            $conn->close();
                        }

                        // Redirect by role
                        if ($user['role'] === 'admin') {
                            header('Location: ' . SITE_URL . '/admin/dashboard.php');
                        } else {
                            header('Location: ' . SITE_URL . '/index.php');
                        }
                        exit;
                    } else {
                        // 🔥 LOG FAILED LOGIN
                        log_activity($conn, null, 'failed_login', "Failed login attempt for email: $email");
                        $error = 'Password salah!';
                    }
                } else {
                    $error = 'Email tidak ditemukan!';
                }

                $stmt->close();
                if ($conn instanceof mysqli) {
                    $conn->close();
                }
            }
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<?php if ($show_deleted_alert): ?>
    <div class="alert alert-error" style="margin: 2rem auto; max-width: 500px;">
        <strong>⚠️ Akun Tidak Valid</strong><br>
        Akun Anda telah dihapus dari sistem. Silakan hubungi administrator jika ini adalah kesalahan.
    </div>
<?php endif; ?>

<section style="padding:4rem 0; min-height:70vh; display:flex; align-items:center; background:var(--light);">
    <div class="container">
        <div style="max-width:450px; margin:0 auto;">
            <div class="card">

                <div class="card-header" style="text-align:center;">
                    <h2 class="card-title">Login</h2>
                    <p style="color:var(--gray);">Masuk ke akun Anda</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <form method="POST" autocomplete="off">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%;">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>

                <div style="text-align:center; margin-top:1.5rem;">
                    <p>Belum punya akun?
                        <a href="register.php" style="color:var(--primary); font-weight:600;">
                            Daftar Sekarang
                        </a>
                    </p>
                </div>

            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>