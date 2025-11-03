<?php
// Note: Session is already started by the front controller (index.php)
// The $mysqli connection is also expected to be available from the front controller.

if (!isset($_SESSION['user_id'])) {
    // This is a safeguard, but redirection should be handled by individual pages
    // to prevent header injection issues.
    // header('Location: /public/login'); // Front-controller friendly URL
    exit('User not authenticated.');
}

// Fetch credit balance for header
$header_user_id = $_SESSION['user_id'];
$header_stmt = $mysqli->prepare("SELECT credit_balance FROM users WHERE id = ?");
$header_stmt->bind_param('i', $header_user_id);
$header_stmt->execute();
$header_user_result = $header_stmt->get_result();
$header_user = $header_user_result->fetch_assoc();
$user_credit_balance = $header_user['credit_balance'] ?? 0;
$header_stmt->close();
?>
<header class="user-header">
    <div class="logo">
        <a href="/public/dashboard">My Dashboard</a>
    </div>
    <div class="header-right">
         <a href="/public/buy-credits" class="button-small">Buy Credits</a>
        <div class="user-info">
            <span class="credit-balance">Credits: <?php echo number_format($user_credit_balance, 4); ?></span>
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
            <a href="/public/logout">Logout</a>
        </div>
    </div>
</header>
