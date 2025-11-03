<?php
require_once '../config/db.php';
require_once '../src/lib/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$message = '';

// Fetch user's contact lists
$lists_result = $mysqli->prepare("SELECT id, list_name FROM contact_lists WHERE user_id = ?");
$lists_result->bind_param('i', $user_id);
$lists_result->execute();
$lists = $lists_result->get_result();

// Fetch cost from settings
$price_to_publish = (float)get_setting('price_landing_page_publish', $mysqli, 50);

// Handle Page Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_page'])) {
    $page_name = trim($_POST['page_name'] ?? '');
    $headline = trim($_POST['headline'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $list_id = (int)$_POST['list_id'];

    // Check balance
    $stmt = $mysqli->prepare("SELECT credit_balance FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user_balance = $stmt->get_result()->fetch_assoc()['credit_balance'];

    if ($user_balance >= $price_to_publish) {
        $mysqli->begin_transaction();
        try {
            $update_credits_stmt = $mysqli->prepare("UPDATE users SET credit_balance = credit_balance - ? WHERE id = ?");
            $update_credits_stmt->bind_param('di', $price_to_publish, $user_id);
            $update_credits_stmt->execute();

            $page_slug = uniqid(); // Simple unique slug
            $stmt = $mysqli->prepare("INSERT INTO landing_pages (user_id, list_id, page_slug, headline, content, status, cost_in_credits) VALUES (?, ?, ?, ?, ?, 'published', ?)");
            $stmt->bind_param('iisssd', $user_id, $list_id, $page_slug, $headline, $content, $price_to_publish);
            $stmt->execute();

            $mysqli->commit();
            $message = "Landing page published successfully! URL: /p/{$page_slug}";

        } catch (Exception $e) {
            $mysqli->rollback();
            $message = "An error occurred during publishing.";
        }
    } else {
        $message = "Insufficient credits to publish a landing page.";
    }
}

// Fetch existing landing pages
$pages_result = $mysqli->prepare("SELECT page_slug, headline, status, created_at FROM landing_pages WHERE user_id = ?");
$pages_result->bind_param('i', $user_id);
$pages_result->execute();
$pages = $pages_result->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Landing Page Builder</title>
    <link rel="stylesheet" href="css/dashboard_style.css">
</head>
<body>
    <?php include APP_ROOT . '/public/includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar">
            <?php include APP_ROOT . '/public/includes/sidebar.php'; ?>
        </aside>
        <main class="main-content">
            <h1>Landing Page Builder</h1>
            <?php if ($message): ?><div class="message"><?php echo $message; ?></div><?php endif; ?>

            <h2>Create New Page</h2>
            <form action="/public/landing-pages" method="post">
                <input type="hidden" name="create_page" value="1">
                <p>Cost to Publish: <?php echo $price_to_publish; ?> credits</p>
                <input type="text" name="page_name" placeholder="Page Name (Internal)" required><br>
                <input type="text" name="headline" placeholder="Headline" required><br>
                <textarea name="content" rows="8" placeholder="Page Content" required></textarea><br>
                <select name="list_id" required>
                    <option value="">-- Add Leads to Contact List --</option>
                    <?php while($list = $lists->fetch_assoc()): ?>
                    <option value="<?php echo $list['id']; ?>"><?php echo htmlspecialchars($list['list_name']); ?></option>
                    <?php endwhile; ?>
                </select><br>
                <button type="submit">Publish Page</button>
            </form>

            <hr>
            <h2>Your Landing Pages</h2>
            <table>
                <thead><tr><th>Headline</th><th>URL</th><th>Status</th><th>Created</th></tr></thead>
                <tbody>
                <?php while($page = $pages->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($page['headline']); ?></td>
                    <td><a href="p/<?php echo $page['page_slug']; ?>" target="_blank">/p/<?php echo $page['page_slug']; ?></a></td>
                    <td><?php echo $page['status']; ?></td>
                    <td><?php echo $page['created_at']; ?></td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </main>
    </div>
    <?php include APP_ROOT . '/public/includes/footer.php'; ?>
</body>
</html>
