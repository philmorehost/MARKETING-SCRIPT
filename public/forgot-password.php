<?php
session_start();
require_once '../config/db.php';
// Again, assuming PHPMailer
// use PHPMailer\PHPMailer\PHPMailer;
// use PHPMailer\PHPMailer\Exception;
// require '../vendor/autoload.php';

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($mysqli->connect_error) {
            $error_message = "Database connection error.";
        } else {
            $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 1) {
                // Generate a secure, unique token
                $token = bin2hex(random_bytes(50));

                // Store token in the password_resets table
                $stmt = $mysqli->prepare("INSERT INTO password_resets (email, token) VALUES (?, ?)");
                $stmt->bind_param('ss', $email, $token);
                $stmt->execute();

                // Send the password reset link (placeholder)
                $reset_link = "http://{$_SERVER['HTTP_HOST']}/public/reset-password.php?token={$token}";
                // $mail = new PHPMailer(true);
                // ... mail sending logic ...
                // $mail->Body = "Click here to reset your password: <a href='{$reset_link}'>{$reset_link}</a>";
                // $mail->send();

                $success_message = "If an account with that email exists, a password reset link has been sent.";
            } else {
                // Show the same message to prevent user enumeration
                $success_message = "If an account with that email exists, a password reset link has been sent.";
            }
            $stmt->close();
            $mysqli->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="css/public_style.css">
    <link rel="stylesheet" href="css/auth_style.css">
</head>
<body>
    <div class="auth-container">
        <h2>Reset Your Password</h2>
        <p>Enter your email address and we will send you a link to reset your password.</p>

        <?php if ($error_message): ?><p class="message error"><?php echo $error_message; ?></p><?php endif; ?>
        <?php if ($success_message): ?><p class="message success"><?php echo $success_message; ?></p><?php endif; ?>

        <form action="forgot-password.php" method="post">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <button type="submit" class="button">Send Reset Link</button>
        </form>
        <div class="footer-links">
            <a href="login.php">Back to Login</a>
        </div>
    </div>
</body>
</html>
