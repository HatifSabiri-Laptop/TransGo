<?php
// company/contact.php
require_once __DIR__ . '/../config/config.php';
include __DIR__ . '/../includes/header.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer
require __DIR__ . '/../PHPMailer-master/src/Exception.php';
require __DIR__ . '/../PHPMailer-master/src/PHPMailer.php';
require __DIR__ . '/../PHPMailer-master/src/SMTP.php';

$contact_success = '';
$contact_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean_input($_POST['name'] ?? '');
    $email = clean_input($_POST['email'] ?? '');
    $message = clean_input($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($message)) {
        $contact_error = 'Semua field harus diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $contact_error = 'Format email tidak valid.';
    } else {
        $mail = new PHPMailer(true);

        try {
            // SMTP Settings (Gmail)
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'hatifsabiri648@gmail.com';     // CHANGE THIS
            $mail->Password   = 'fuzq jbrt ohvp gkuz';  // CHANGE THIS
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Email settings
            $mail->setFrom('yourgmail@gmail.com', 'TransGo Contact');
            $mail->addAddress('hatifsabiri648@gmail.com');

            $mail->Subject = "Contact Form: $name";
            $mail->Body    = "Name: $name\nEmail: $email\n\nMessage:\n$message";

            $mail->send();

            $contact_success = 'Pesan terkirim. Terima kasih, kami akan menghubungi Anda segera.';

            // Save log to DB
            $conn = getDBConnection();
            if ($conn) {
                $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) 
                    VALUES (?, 'contact', ?, ?)");
                $uid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
                $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $desc = "Contact form from $name <$email>";

                if ($stmt) {
                    $stmt->bind_param("iss", $uid, $desc, $ip);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        } catch (Exception $e) {
            $contact_error = "Gagal mengirim email: " . $mail->ErrorInfo;
        }
    }
}
?>

<!-- Page Background -->
<section style="background:#dbeafe; min-height:100vh; padding:3rem 1rem;" data-aos="fade-up">
    <div class="container">
        <h1>Contact Us</h1>

        <?php if ($contact_error): ?>
            <div class="alert alert-error" style="margin-bottom:1rem;"><?php echo $contact_error; ?></div>
        <?php endif; ?>
        <?php if ($contact_success): ?>
            <div class="alert alert-success" style="margin-bottom:1rem;"><?php echo $contact_success; ?></div>
        <?php endif; ?>

        <form method="POST" class="card p-4" data-aos="zoom-in">
            <div class="form-group" style="margin-bottom:1rem;">
                <label>Name</label>
                <input type="text" name="name" class="form-control"
                    value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
            </div>

            <div class="form-group" style="margin-bottom:1rem;">
                <label>Email</label>
                <input type="email" name="email" class="form-control"
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>

            <div class="form-group" style="margin-bottom:1rem;">
                <label>Message</label>
                <textarea name="message" class="form-control" rows="6" required><?php
                                                                                echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '';
                                                                                ?></textarea>
            </div>

            <button class="btn btn-primary" type="submit">Send Message</button>
        </form>
    </div>
</section>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({
        duration: 800,
        once: true
    });
</script>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
include __DIR__ . '/../includes/footer.php';
?>