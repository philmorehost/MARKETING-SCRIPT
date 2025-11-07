<?php
// src/pages/login.php
$page_title = "Login";
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    }

    if (empty($errors)) {
        $stmt = $mysqli->prepare("SELECT id, password, role FROM users WHERE email = ? AND status = 'active'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];

            // Redirect based on role
            if ($user['role'] === 'admin') {
                header("Location: /admin/dashboard.php");
            } else {
                header("Location: /dashboard");
            }
            exit;
        } else {
            $errors[] = "Invalid email or password.";
        }
    }
}


include __DIR__ . '/../includes/header_public.php';
?>

<div class="container page-content">
    <div class="auth-form">
        <h2>Login to Your Account</h2>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo $error; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form action="/login" method="POST">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" name="email" id="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" required>
            </div>
            <div class="form-group-extra">
                <a href="/forgot-password">Forgot Password?</a>
            </div>
            <button type="submit" class="cta-button">Login</button>
        </form>
        <p class="auth-switch">Don't have an account? <a href="/register">Sign up here</a>.</p>

        <div class="social-login">
            <p>Or</p>
            <a href="/google-login.php" class="btn-google">
                <i class="fab fa-google"></i> Sign in with Google
            </a>
        </div>
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
.auth-form h2 {
    text-align: center;
    margin-bottom: 20px;
}
.form-group-extra {
    text-align: right;
    margin-bottom: 15px;
}
.auth-switch {
    text-align: center;
    margin-top: 20px;
}
</style>