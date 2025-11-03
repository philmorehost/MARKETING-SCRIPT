<?php
// --- public/p/index.php ---
require_once '../../config/db.php';

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    http_response_code(404);
    die("Page not found.");
}

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$stmt = $mysqli->prepare("SELECT * FROM landing_pages WHERE page_slug = ? AND status = 'published'");
$stmt->bind_param('s', $slug);
$stmt->execute();
$page = $stmt->get_result()->fetch_assoc();

if (!$page) {
    http_response_code(404);
    die("Page not found.");
}

$message = '';
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Add to contacts and map to list
        $user_id = $page['user_id'];
        $list_id = $page['list_id'];

        // Check if contact exists for this user
        $stmt = $mysqli->prepare("SELECT id FROM contacts WHERE email = ? AND user_id = ?");
        $stmt->bind_param('si', $email, $user_id);
        $stmt->execute();
        $contact = $stmt->get_result()->fetch_assoc();

        if ($contact) {
            $contact_id = $contact['id'];
        } else {
            $stmt = $mysqli->prepare("INSERT INTO contacts (user_id, email) VALUES (?, ?)");
            $stmt->bind_param('is', $user_id, $email);
            $stmt->execute();
            $contact_id = $stmt->insert_id;
        }

        $stmt = $mysqli->prepare("INSERT IGNORE INTO contact_list_map (contact_id, list_id) VALUES (?, ?)");
        $stmt->bind_param('ii', $contact_id, $list_id);
        $stmt->execute();

        $message = "Thank you for subscribing!";
    } else {
        $message = "Please enter a valid email address.";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($page['headline']); ?></title>
    <style>
        /* Basic styling for the public landing page */
        body { font-family: sans-serif; text-align: center; padding-top: 50px; }
        .container { max-width: 600px; margin: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo htmlspecialchars($page['headline']); ?></h1>
        <div><?php echo nl2br(htmlspecialchars($page['content'])); ?></div>

        <hr>

        <?php if ($message): ?>
            <p><?php echo $message; ?></p>
        <?php else: ?>
            <form action="" method="post">
                <input type="email" name="email" placeholder="Enter your email address" required>
                <button type="submit">Subscribe</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
