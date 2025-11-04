<header class="admin-header">
    <div class="logo">
        <a href="dashboard.php">Admin Panel</a>
    </div>
    <div class="user-info">
        <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
        <a href="/logout.php">Logout</a>
    </div>
</header>
