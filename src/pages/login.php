<?php
// Note: session is started by index.php
// Note: $mysqli connection is provided by index.php

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error_message = "Email and password are required.";
    } else {
        // No need to check $mysqli->connect_error, front controller handles it.
        $stmt = $mysqli->prepare("SELECT id, name, password, role, status, team_id, team_role FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                if ($user['status'] === 'active' || $user['status'] === 'pending') {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['team_id'] = $user['team_id'];
                    $_SESSION['team_role'] = $user['team_role'];

                    if ($user['team_id']) {
                        $team_stmt = $mysqli->prepare("SELECT owner_user_id FROM teams WHERE id = ?");
                        $team_stmt->bind_param('i', $user['team_id']);
                        $team_stmt->execute();
                        $team_result = $team_stmt->get_result();
                        if ($team_result->num_rows > 0) {
                           $team = $team_result->fetch_assoc();
                           $_SESSION['team_owner_id'] = $team['owner_user_id'];
                        } else {
                           $_SESSION['team_owner_id'] = $user['id'];
                        }
                    } else {
                         $_SESSION['team_owner_id'] = $user['id'];
                    }

                    if ($user['role'] === 'admin') {
                        header('Location: /admin/dashboard.php');
                    } else {
                        header('Location: /dashboard');
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
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="stylesheet" href="/public/css/public_style.css">
    <link rel="stylesheet" href="/public/css/auth_style.css">
</head>
<body>
    <div class="auth-container">
        <h2>Login to Your Account</h2>
        <?php if ($error_message): ?>
            <p class="error"><?php echo $error_message; ?></p>
        <?php endif; ?>
        <form action="/public/login" method="post">
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
            <a href="/public/forgot-password">Forgot Password?</a> | <a href="/public/register">Don't have an account?</a>
        </div>
    </div>
</body>
</html>
