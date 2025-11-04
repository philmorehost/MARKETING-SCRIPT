<?php
// This is the public-facing page for the landing pages.
// It will be routed via the front controller.
require_once dirname(__DIR__) . '/config/db.php';
$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    http_response_code(404);
    echo "Page not found.";
    exit;
}

$stmt = $mysqli->prepare("SELECT * FROM landing_pages WHERE page_slug = ? AND status = 'published'");
$stmt->bind_param('s', $slug);
$stmt->execute();
$page = $stmt->get_result()->fetch_assoc();

if (!$page) {
    http_response_code(404);
    echo "Page not found or is no longer active.";
    exit;
}

$message = '';
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $first_name = $_POST['first_name'] ?? '';
        $phone_number = $_POST['phone_number'] ?? '';

        // Find or create contact
        $stmt_contact = $mysqli->prepare("SELECT id FROM contacts WHERE email = ? AND team_id = ?");
        $stmt_contact->bind_param('si', $email, $page['team_id']);
        $stmt_contact->execute();
        $contact = $stmt_contact->get_result()->fetch_assoc();

        if ($contact) {
            $contact_id = $contact['id'];
        } else {
            $stmt_insert = $mysqli->prepare("INSERT INTO contacts (user_id, team_id, email, first_name, phone_number) VALUES (?, ?, ?, ?, ?)");
            $stmt_insert->bind_param('iisss', $page['user_id'], $page['team_id'], $email, $first_name, $phone_number);
            $stmt_insert->execute();
            $contact_id = $stmt_insert->insert_id;
        }

        // Add to list
        $stmt_map = $mysqli->prepare("INSERT IGNORE INTO contact_list_map (contact_id, list_id) VALUES (?, ?)");
        $stmt_map->bind_param('ii', $contact_id, $page['list_id']);
        $stmt_map->execute();

        // Log submission stat
        $stmt_stat = $mysqli->prepare("INSERT INTO landing_page_stats (landing_page_id, type) VALUES (?, 'submission')");
        $stmt_stat->bind_param('i', $page['id']);
        $stmt_stat->execute();

        $message = "Thank you for subscribing!";
    } else {
        $message = "A valid email is required.";
    }
}

// Log view stat
$stmt_stat_view = $mysqli->prepare("INSERT INTO landing_page_stats (landing_page_id, type) VALUES (?, 'view')");
$stmt_stat_view->bind_param('i', $page['id']);
$stmt_stat_view->execute();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($page['headline']); ?></title>
    <link rel="stylesheet" href="/public/css/landing_page.css">
</head>
<body>
    <div class="container">
        <?php if ($page['image_path']): ?>
            <img src="/public/<?php echo htmlspecialchars($page['image_path']); ?>" alt="Logo" class="logo">
        <?php endif; ?>
        <h1><?php echo htmlspecialchars($page['headline']); ?></h1>
        <div><?php echo nl2br(htmlspecialchars($page['content'])); ?></div>

        <?php if ($message): ?>
            <p class="message"><?php echo $message; ?></p>
        <?php else: ?>
            <form action="" method="post">
                <?php
                $fields = json_decode($page['form_fields_json'], true);
                if(in_array('email', $fields)): ?>
                <input type="email" name="email" placeholder="Your Email Address" required>
                <?php endif; if(in_array('first_name', $fields)): ?>
                <input type="text" name="first_name" placeholder="Your First Name">
                <?php endif; if(in_array('phone_number', $fields)): ?>
                <input type="tel" name="phone_number" placeholder="Your Phone Number">
                <?php endif; ?>
                <button type="submit">Subscribe</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
