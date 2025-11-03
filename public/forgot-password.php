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
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #f4f7f6; }
        .container { background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 100%; max-width: 450px; }
        h2 { text-align: center; color: #333; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .button { width: 100%; padding: 12px; background-color: #106297; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        .message { padding: 10px; border-radius: 4px; margin-bottom: 15px; text-align: center; }
        .error { color: #D8000C; background-color: #FFD2D2; }
        .success { color: #4F8A10; background-color: #DFF2BF; }
        .footer-links { text-align: center; margin-top: 15px; font-size: 14px; }
        .footer-links a { color: #106297; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
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
