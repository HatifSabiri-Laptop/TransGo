<?php
session_start();   // ✔ REQUIRED
$page_title = 'Login';
require_once '../config/config.php';


// Redirect if already logged in
if (is_logged_in()) {
    header('Location: ' . SITE_URL . '/index.php');
    exit();
}

$error = '';
$success = '';

if (isset($_GET['registered'])) {
    if (isset($_GET['email_sent'])) {
        $success = 'Registrasi berhasil! Email konfirmasi telah dikirim. Silakan login.';
    } else {
        $success = 'Registrasi berhasil! Silakan login.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Email dan password harus diisi!';
    } else {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id, email, password, full_name, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                
                log_activity($conn, $user['id'], 'login', 'User logged in');
                
                $stmt->close();
                closeDBConnection($conn);
                
                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header('Location: ' . SITE_URL . '/admin/dashboard.php');
                } else {
                    header('Location: ' . SITE_URL . '/index.php');
                }
                exit();
            } else {
                $error = 'Password salah!';
            }
        } else {
            $error = 'Email tidak ditemukan!';
        }
        
        $stmt->close();
        closeDBConnection($conn);
    }
}

include '../includes/header.php';
?>

<section style="padding: 4rem 0; min-height: 70vh; display: flex; align-items: center; background: var(--light);">
    <div class="container">
        <div style="max-width: 450px; margin: 0 auto;">
            <div class="card">
                <div class="card-header" style="text-align: center;">
                    <h2 class="card-title">Login</h2>
                    <p style="color: var(--gray);">Masuk ke akun Anda</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               placeholder="nama@email.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" 
                               placeholder="Masukkan password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
                
                <div style="text-align: center; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--light);">
                    <p>Belum punya akun? <a href="register.php" style="color: var(--primary); font-weight: 600;">Daftar Sekarang</a></p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include '../includes/footer.php'; ?>
<?php
session_start();
$page_title = 'Login';
require_once __DIR__ . '/../config/config.php';