<?php
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
    <link rel="stylesheet" href="css/public_style.css">
    <link rel="stylesheet" href="css/auth_style.css">
</head>
<body>
    <div class="auth-container">
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
