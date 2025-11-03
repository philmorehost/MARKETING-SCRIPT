<?php
session_start();
require_once '../config/db.php'; // Assumes installer created this

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error_message = "Email and password are required.";
    } else {
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($mysqli->connect_error) {
            $error_message = "Database connection error. Please try again later.";
        } else {
            $stmt = $mysqli->prepare("SELECT id, name, password, role, status FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    if ($user['status'] === 'active' || $user['status'] === 'pending') { // Allow pending for first login
                        // Login success
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_role'] = $user['role'];

                        // Redirect based on role
                        if ($user['role'] === 'admin') {
                            header('Location: ../admin/dashboard.php');
                        } else {
                            header('Location: dashboard.php');
                        }
                        exit;
                    } elseif ($user['status'] === 'suspended') {
                        $error_message = "Your account has been suspended.";
                    }
                } else {
                    $error_message = "Invalid email or password.";
                }
            } else {
                $error_message = "Invalid email or password.";
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
    <title>Login</title>
    <link rel="stylesheet" href="css/style.css"> <!-- We'll create this later -->
    <style>
        /* Basic styles for now */
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #f4f7f6; }
        .login-container { background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h2 { text-align: center; color: #333; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .button { width: 100%; padding: 12px; background-color: #106297; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        .button:hover { background-color: #0d4f7a; }
        .error { color: #D8000C; background-color: #FFD2D2; padding: 10px; border-radius: 4px; margin-bottom: 15px; text-align: center; }
        .footer-links { text-align: center; margin-top: 15px; font-size: 14px; }
        .footer-links a { color: #106297; text-decoration: none; }
        .footer-links a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Login to Your Account</h2>
        <?php if ($error_message): ?>
            <p class="error"><?php echo $error_message; ?></p>
        <?php endif; ?>
        <form action="login.php" method="post">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="button">Login</button>
        </form>
        <div class="footer-links">
            <a href="forgot-password.php">Forgot Password?</a> | <a href="register.php">Don't have an account?</a>
        </div>
    </div>
</body>
</html>
