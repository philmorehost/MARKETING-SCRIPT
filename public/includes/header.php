<?php
// Session check for user pages
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch credit balance for header
if (!isset($mysqli)) {
    // Ensure DB connection is available if header is included standalone
    require_once dirname(__FILE__) . '/../../config/db.php';
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
}
$header_user_id = $_SESSION['user_id'];
$header_stmt = $mysqli->prepare("SELECT credit_balance FROM users WHERE id = ?");
$header_stmt->bind_param('i', $header_user_id);
$header_stmt->execute();
$header_user = $header_stmt->get_result()->fetch_assoc();
$user_credit_balance = $header_user['credit_balance'] ?? 0;
$header_stmt->close();

?>
<header class="user-header">
    <div class="logo">
        <a href="dashboard.php">My Dashboard</a>
    </div>
    <div class="user-info">
        <span class="credit-balance">Credits: <?php echo number_format($user_credit_balance, 4); ?></span>
        <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
        <a href="logout.php">Logout</a>
    </div>
</header>
