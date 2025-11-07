<?php
// src/pages/forgot-password.php
$page_title = "Forgot Password";
$errors = [];
$success = false;

// We need PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    } else {
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $token = bin2hex(random_bytes(50));

            // Store token in the database
            $stmt = $mysqli->prepare("INSERT INTO password_resets (email, token) VALUES (?, ?)");
            $stmt->bind_param("ss", $email, $token);
            $stmt->execute();

            // Send the password reset email
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset-password.php?token=" . $token;
            $mail = new PHPMailer(true);
            try {
                //Server settings - configure in admin settings later
                $mail->isSMTP();
                $mail->Host       = get_setting('smtp_host', 'localhost');
                $mail->SMTPAuth   = false;
                $mail->Port       = get_setting('smtp_port', 1025);

                //Recipients
                $mail->setFrom(get_setting('site_email', 'noreply@example.com'), get_setting('site_name', 'Active Email Verifier'));
                $mail->addAddress($email);

                //Content
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request';
                $mail->Body    = "Hello,<br><br>Someone has requested a link to change your password. You can do this through the link below.<br><br>";
                $mail->Body   .= "<a href='{$reset_link}'>Change my password</a><br><br>";
                $mail->Body   .= "If you didn't request this, please ignore this email.<br><br>Your password won't change until you access the link above and create a new one.";

                $mail->send();
                $success = true;

            } catch (Exception $e) {
                $errors[] = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }

        } else {
             // To prevent user enumeration, show a generic success message even if the email doesn't exist.
             $success = true;
        }
    }
}

include __DIR__ . '/../includes/header_public.php';
?>

<div class="container page-content">
     <div class="auth-form">
        <h2>Reset Your Password</h2>
        <?php if ($success): ?>
            <div class="alert alert-success">
                If an account with that email exists, a password reset link has been sent to it. Please check your inbox.
            </div>
        <?php else: ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <p>Enter your email address and we will send you a link to reset your password.</p>
            <form action="/forgot-password" method="POST">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" name="email" id="email" required>
                </div>
                <button type="submit" class="cta-button">Send Password Reset Link</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer_public.php';
?>
<style>
.auth-form {
    max-width: 400px;
    margin: 40px auto;
    padding: 30px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    border-radius: 8px;
}
</style>