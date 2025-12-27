<?php
session_start();
$page_title = 'Registrasi';
require_once __DIR__ . '/../config/config.php';

if (is_logged_in()) {
    header('Location: ' . SITE_URL . '/user/dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = clean_input($_POST['full_name']);
    $email = clean_input($_POST['email']);
    $phone = clean_input($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($full_name) || empty($email) || empty($phone) || empty($password)) {
        $error = 'Semua field harus diisi!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid!';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } elseif ($password !== $confirm_password) {
        $error = 'Password tidak cocok!';
    } else {
        $conn = getDBConnection();

        // Check if email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = 'Email sudah terdaftar!';
        } else {
            // Hash password and insert
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (email, password, full_name, phone, role) VALUES (?, ?, ?, ?, 'user')");
            $stmt->bind_param("ssss", $email, $hashed_password, $full_name, $phone);

            if ($stmt->execute()) {
                $newUserId = $stmt->insert_id;
                log_activity($conn, $newUserId, 'register', "New user registered: $email");

                // Send welcome email
                $email_sent = send_welcome_email($email, $full_name);

                $stmt->close();
                if ($conn instanceof mysqli) {
                    $conn->close();
                }

                if ($email_sent) {
                    header('Location: ' . SITE_URL . '/auth/login.php?registered=1&email_sent=1');
                } else {
                    header('Location: ' . SITE_URL . '/auth/login.php?registered=1');
                }
                exit();
            } else {
                $error = 'Terjadi kesalahan. Silakan coba lagi.';
            }
        }

        $stmt->close();
        if ($conn instanceof mysqli) {
            $conn->close();
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<section style="padding: 4rem 0; min-height: 100vh; background: linear-gradient(135deg, rgba(37, 99, 235, 0.05), rgba(16, 185, 129, 0.05));">
    <div class="container">
        <div style="max-width: 500px; margin: 0 auto;">
            <div class="card" style="box-shadow: 0 10px 40px rgba(171, 135, 135, 0.1);">
                <div class="card-header" style="text-align: center; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; padding: 2rem; border-radius: 12px 12px 0 0;">
                    <i class="fas fa-user-plus" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <h2 class="card-title" style="color: white; margin-bottom: 0.5rem;">Daftar Akun Baru</h2>
                    <p style="color: rgba(255,255,255,0.9); margin: 0;">Bergabunglah dengan TransGo untuk kemudahan booking</p>
                </div>

                <div style="padding: 2rem;">
                    <?php if ($error): ?>
                        <div class="alert alert-error" style="animation: shake 0.5s;">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" id="registerForm">
                        <div class="form-group">
                            <label for="full_name"><i class="fas fa-user"></i> Nama Lengkap *</label>
                            <input type="text" id="full_name" name="full_name" class="form-control"
                                placeholder="Nama lengkap Anda" required
                                value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email *</label>
                            <input type="email" id="email" name="email" class="form-control"
                                placeholder="nama@email.com" required
                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            <small style="color: var(--gray);">Email akan digunakan untuk notifikasi booking</small>
                        </div>

                        <div class="form-group">
                            <label for="phone"><i class="fas fa-phone"></i> Nomor Telepon *</label>
                            <input type="tel" id="phone" name="phone" class="form-control"
                                placeholder="08123456789" required
                                value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="password"><i class="fas fa-lock"></i> Password *</label>
                            <div style="position: relative;">
                                <input type="password" id="password" name="password" class="form-control"
                                    placeholder="Minimal 6 karakter" required>
                                <button type="button" onclick="togglePassword('password')"
                                    style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--gray); cursor: pointer;">
                                    <i class="fas fa-eye" id="password-icon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password"><i class="fas fa-lock"></i> Konfirmasi Password *</label>
                            <div style="position: relative;">
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                                    placeholder="Ulangi password" required>
                                <button type="button" onclick="togglePassword('confirm_password')"
                                    style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--gray); cursor: pointer;">
                                    <i class="fas fa-eye" id="confirm_password-icon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group" style="margin-top: 1.5rem;">
                            <label style="display: flex; align-items: start; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" required style="margin-top: 0.25rem;">
                                <span style="font-size: 0.9rem; color: var(--gray);">
                                    Saya menyetujui <a href="#" style="color: var(--primary);">Syarat & Ketentuan</a>
                                    serta <a href="#" style="color: var(--primary);">Kebijakan Privasi</a>
                                </span>
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem; font-size: 1.1rem; margin-top: 1rem;">
                            <i class="fas fa-user-plus"></i> Daftar Sekarang
                        </button>
                    </form>

                    <div style="text-align: center; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--light);">
                        <p style="color: var(--gray);">Sudah punya akun?
                            <a href="<?php echo SITE_URL; ?>/auth/login.php" style="color: var(--primary); font-weight: 600; text-decoration: none;">
                                Login di sini <i class="fas fa-arrow-right"></i>
                            </a>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Benefits Section -->
            <div class="card" style="margin-top: 2rem; background: var(--primary); color: white;">
                <h4 style="margin-bottom: 1rem;"><i class="fas fa-gift"></i> Keuntungan Mendaftar</h4>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <li style="padding: 0.5rem 0; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-check-circle"></i>
                        <span>Booking lebih cepat dengan data tersimpan</span>
                    </li>
                    <li style="padding: 0.5rem 0; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-check-circle"></i>
                        <span>Riwayat perjalanan tersimpan otomatis</span>
                    </li>
                    <li style="padding: 0.5rem 0; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-check-circle"></i>
                        <span>Notifikasi email untuk setiap booking</span>
                    </li>
                    <li style="padding: 0.5rem 0; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-check-circle"></i>
                        <span>Akses promo dan diskon eksklusif</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</section>

<script>
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        const icon = document.getElementById(fieldId + '-icon');

        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            field.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    // Form validation
    document.getElementById('registerForm').addEventListener('submit', function(e) {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;

        if (password !== confirmPassword) {
            e.preventDefault();
            alert('Password tidak cocok!');
            return false;
        }

        if (password.length < 6) {
            e.preventDefault();
            alert('Password minimal 6 karakter!');
            return false;
        }
    });

    // Shake animation for errors
    const style = document.createElement('style');
    style.textContent = `
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-10px); }
    75% { transform: translateX(10px); }
}
`;
    document.head.appendChild(style);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>