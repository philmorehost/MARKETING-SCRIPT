<?php
session_start();
require_once '../config/db.php';

$token = $_GET['token'] ?? '';
$error_message = '';
$success_message = '';
$token_valid = false;
$email = null;

if (empty($token)) {
    $error_message = "Invalid password reset token.";
} else {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($mysqli->connect_error) {
        $error_message = "Database connection error.";
    } else {
        // Check if token is valid and not expired (e.g., within 1 hour)
        $stmt = $mysqli->prepare("SELECT email FROM password_resets WHERE token = ? AND created_at > (NOW() - INTERVAL 1 HOUR)");
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $token_valid = true;
            $email = $result->fetch_assoc()['email'];
        } else {
            $error_message = "This password reset token is invalid or has expired.";
        }
        $stmt->close();
    }
}

if ($token_valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($password) || $password !== $confirm_password) {
        $error_message = "Passwords do not match or are empty.";
    } else {
        // Update the password in the users table
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->bind_param('ss', $hashed_password, $email);

        if ($stmt->execute()) {
            // Delete the token so it can't be reused
            $delete_stmt = $mysqli->prepare("DELETE FROM password_resets WHERE email = ?");
            $delete_stmt->bind_param('s', $email);
            $delete_stmt->execute();
            $delete_stmt->close();

            $success_message = "Your password has been reset successfully! You can now log in.";
            $token_valid = false; // Hide the form after success
        } else {
            $error_message = "Failed to update password. Please try again.";
        }
        $stmt->close();
    }
    $mysqli->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
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
        <h2>Set a New Password</h2>

        <?php if ($error_message): ?><p class="message error"><?php echo $error_message; ?></p><?php endif; ?>
        <?php if ($success_message): ?><p class="message success"><?php echo $success_message; ?></p><?php endif; ?>

        <?php if ($token_valid): ?>
            <form action="reset-password.php?token=<?php echo htmlspecialchars($token); ?>" method="post">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" class="button">Reset Password</button>
            </form>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="footer-links">
                <a href="login.php">Proceed to Login</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
