<?php
// --- public/p/index.php ---
require_once '../../config/db.php';

// A simple slug router. In a real app, this logic would be in the main front controller.
$slug = basename($_SERVER['REQUEST_URI']);
if (empty($slug)) {
    http_response_code(404);
    die("Page not found.");
}

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    die("Service unavailable.");
}
$stmt_page = $mysqli->prepare("SELECT * FROM landing_pages WHERE page_slug = ? AND status = 'published'");
$stmt_page->bind_param('s', $slug);
$stmt_page->execute();
$page = $stmt_page->get_result()->fetch_assoc();

if (!$page) {
    http_response_code(404);
    die("Page not found.");
}

// Track a 'view' event
$ip_hash = md5($_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']); // Simple visitor hash
$stmt_stat = $mysqli->prepare("INSERT INTO landing_page_stats (landing_page_id, type, ip_address_hash) VALUES (?, 'view', ?)");
$stmt_stat->bind_param('is', $page['id'], $ip_hash);
$stmt_stat->execute();

$message = '';
$form_fields = json_decode($page['form_fields_json'] ?? '["email"]', true);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
    } else {
        $first_name = trim($_POST['first_name'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');

        $team_id = $page['team_id'];
        $user_id = $page['user_id'];
        $list_id = $page['list_id'];

        $stmt_find = $mysqli->prepare("SELECT id FROM contacts WHERE email = ? AND team_id = ?");
        $stmt_find->bind_param('si', $email, $team_id);
        $stmt_find->execute();
        $contact_result = $stmt_find->get_result();

        if ($contact_result->num_rows > 0) {
            $contact_id = $contact_result->fetch_assoc()['id'];
            // Optional: Update existing contact with new details
        } else {
            $stmt_insert = $mysqli->prepare("INSERT INTO contacts (user_id, team_id, email, first_name, phone_number) VALUES (?, ?, ?, ?, ?)");
            $stmt_insert->bind_param('iisss', $user_id, $team_id, $email, $first_name, $phone_number);
            $stmt_insert->execute();
            $contact_id = $stmt_insert->insert_id;
        }

        if ($contact_id) {
            $stmt_map = $mysqli->prepare("INSERT IGNORE INTO contact_list_map (contact_id, list_id) VALUES (?, ?)");
            $stmt_map->bind_param('ii', $contact_id, $list_id);
            $stmt_map->execute();

            // Track 'submission' event
            $stmt_stat_sub = $mysqli->prepare("INSERT INTO landing_page_stats (landing_page_id, type, ip_address_hash) VALUES (?, 'submission', ?)");
            $stmt_stat_sub->bind_param('is', $page['id'], $ip_hash);
            $stmt_stat_sub->execute();

            $message = "Thank you for subscribing!";
        } else {
            $message = "An error occurred. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page['headline']); ?></title>
    <link rel="stylesheet" href="/css/landing_page_style.css">
</head>
<body>
    <div class="container">
        <?php if (!empty($page['image_path'])): ?>
            <img src="/public<?php echo htmlspecialchars($page['image_path']); ?>" alt="Logo" class="logo">
        <?php endif; ?>
        <h1><?php echo htmlspecialchars($page['headline']); ?></h1>
        <div class="content"><?php echo nl2br(htmlspecialchars($page['content'])); ?></div>

        <div class="form-container">
            <?php if ($message): ?>
                <p class="message success"><?php echo $message; ?></p>
            <?php else: ?>
                <form action="" method="post">
                    <?php if (in_array('first_name', $form_fields)): ?>
                        <div class="form-group"><input type="text" name="first_name" placeholder="First Name" required></div>
                    <?php endif; ?>
                    <?php if (in_array('email', $form_fields)): ?>
                        <div class="form-group"><input type="email" name="email" placeholder="Email Address" required></div>
                    <?php endif; ?>
                    <?php if (in_array('phone_number', $form_fields)): ?>
                        <div class="form-group"><input type="tel" name="phone_number" placeholder="Phone Number"></div>
                    <?php endif; ?>
                    <button type="submit">Subscribe Now</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
