<?php
$error_message = '';
$success_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // In a real app, you would generate a token, save it, and email a reset link.
    $success_message = "If an account with that email exists, a password reset link has been sent.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="/css/public_style.css">
</head>
<body>
    <div class="auth-container">
        <h2>Reset Your Password</h2>
        <?php if ($error_message): ?><p class="message error"><?php echo $error_message; ?></p><?php endif; ?>
        <?php if ($success_message): ?><p class="message success"><?php echo $success_message; ?></p><?php endif; ?>

        <form action="/forgot-password" method="post">
            <div class="form-group">
                <label for="email">Enter your email address</label>
                <input type="email" id="email" name="email" required>
            </div>
            <button type="submit" class="button">Send Reset Link</button>
        </form>
        <div class="footer-links">
            <a href="/login">Back to Login</a>
        </div>
    </div>
</body>
</html>
