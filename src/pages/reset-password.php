<?php
// src/pages/reset-password.php
$page_title = "Reset Password";
$errors = [];
$success = false;
$token = $_GET['token'] ?? '';
$token_valid = false;
$email = '';

if (empty($token)) {
    $errors[] = "Invalid password reset token.";
} else {
    // Check if token is valid and not expired (e.g., within 1 hour)
    $stmt = $mysqli->prepare("SELECT email FROM password_resets WHERE token = ? AND created_at >= NOW() - INTERVAL 1 HOUR");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $token_valid = true;
        $row = $result->fetch_assoc();
        $email = $row['email'];
    } else {
        $errors[] = "Password reset token is invalid or has expired.";
    }
}

if ($token_valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Update user's password
        $stmt = $mysqli->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashed_password, $email);

        if ($stmt->execute()) {
            // Invalidate the token
            $delete_stmt = $mysqli->prepare("DELETE FROM password_resets WHERE email = ?");
            $delete_stmt->bind_param("s", $email);
            $delete_stmt->execute();

            $success = true;
        } else {
            $errors[] = "Failed to update password. Please try again.";
        }
    }
}


include __DIR__ . '/../includes/header_public.php';
?>

<div class="container page-content">
     <div class="auth-form">
        <h2>Choose a New Password</h2>

        <?php if ($success): ?>
            <div class="alert alert-success">
                Your password has been updated successfully. You can now <a href="/login">log in</a> with your new password.
            </div>
        <?php elseif (!$token_valid): ?>
             <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo $error; ?></p>
                <?php endforeach; ?>
                 <p>Please <a href="/forgot-password">request a new link</a>.</p>
            </div>
        <?php else: ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form action="/reset-password?token=<?php echo htmlspecialchars($token); ?>" method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                 <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" name="password" id="password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" required>
                </div>
                <button type="submit" class="cta-button">Update Password</button>
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