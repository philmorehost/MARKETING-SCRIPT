<?php
session_start();
require_once '../config/db.php';
// We will need PHPMailer, assuming it will be in vendor
// use PHPMailer\PHPMailer\PHPMailer;
// use PHPMailer\PHPMailer\Exception;
// require '../vendor/autoload.php';

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($name) || empty($email) || empty($password)) {
        $error_message = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    } else {
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($mysqli->connect_error) {
            $error_message = "Database connection error.";
        } else {
            // Check if email already exists
            $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error_message = "An account with this email already exists.";
            } else {
                // Create user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $mysqli->prepare("INSERT INTO users (name, email, password, status) VALUES (?, ?, ?, 'pending')");
                $stmt->bind_param('sss', $name, $email, $hashed_password);

                if ($stmt->execute()) {
                    // Send confirmation email (placeholder logic)
                    // $mail = new PHPMailer(true);
                    // try {
                    //     //Server settings
                    //     $mail->isSMTP();
                    //     // ... SMTP settings ...
                    //     $mail->setFrom('no-reply@yourdomain.com', 'Mailer');
                    //     $mail->addAddress($email, $name);
                    //     $mail->isHTML(true);
                    //     $mail->Subject = 'Confirm your registration';
                    //     $mail->Body    = 'Please click this link to confirm your email...';
                    //     $mail->send();
                    // } catch (Exception $e) {
                    //     // Handle mail error
                    // }
                    $success_message = "Registration successful! Please check your email to confirm your account.";
                } else {
                    $error_message = "Registration failed. Please try again.";
                }
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #f4f7f6; }
        .register-container { background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 100%; max-width: 450px; }
        h2 { text-align: center; color: #333; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .button { width: 100%; padding: 12px; background-color: #106297; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        .button:hover { background-color: #0d4f7a; }
        .message { padding: 10px; border-radius: 4px; margin-bottom: 15px; text-align: center; }
        .error { color: #D8000C; background-color: #FFD2D2; }
        .success { color: #4F8A10; background-color: #DFF2BF; }
        .footer-links { text-align: center; margin-top: 15px; font-size: 14px; }
        .footer-links a { color: #106297; text-decoration: none; }
    </style>
</head>
<body>
    <div class="register-container">
        <h2>Create a New Account</h2>
        <?php if ($error_message): ?><p class="message error"><?php echo $error_message; ?></p><?php endif; ?>
        <?php if ($success_message): ?><p class="message success"><?php echo $success_message; ?></p><?php endif; ?>

        <form action="register.php" method="post">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" class="button">Register</button>
        </form>
        <div class="footer-links">
            <a href="login.php">Already have an account? Login</a>
        </div>
    </div>
</body>
</html>
