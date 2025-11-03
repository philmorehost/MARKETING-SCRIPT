<?php
session_start();
require_once '../config/db.php';
require_once '../vendor/autoload.php';
require_once '../src/lib/functions.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
                    // Send confirmation email
                    $mail = new PHPMailer(true);
                    try {
                        //Server settings from DB
                        $mail->isSMTP();
                        $mail->Host = get_setting('smtp_host', $mysqli);
                        $mail->SMTPAuth = true;
                        $mail->Username = get_setting('smtp_user', $mysqli);
                        $mail->Password = get_setting('smtp_pass', $mysqli);
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;

                        $mail->setFrom('no-reply@yourdomain.com', get_setting('site_name', $mysqli));
                        $mail->addAddress($email, $name);
                        $mail->isHTML(true);
                        $mail->Subject = 'Confirm your registration';
                        $mail->Body    = 'Please click this link to confirm your email... (link generation logic to be added)';
                        $mail->send();
                        $success_message = "Registration successful! Please check your email to confirm your account.";
                    } catch (Exception $e) {
                        // Handle mail error - maybe log it but don't show user
                        $success_message = "Registration successful! Confirmation email could not be sent.";
                    }
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
    <link rel="stylesheet" href="css/public_style.css">
    <link rel="stylesheet" href="css/auth_style.css">
</head>
<body>
    <div class="auth-container">
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
