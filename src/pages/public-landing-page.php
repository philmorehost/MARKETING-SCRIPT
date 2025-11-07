<?php
// src/pages/public-landing-page.php
// This file is included by the main index.php, so DB connection is available.

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    http_response_code(404);
    echo "Page not found.";
    exit;
}

$stmt = $mysqli->prepare("SELECT * FROM landing_pages WHERE page_slug = ? AND status = 'published'");
$stmt->bind_param("s", $slug);
$stmt->execute();
$page = $stmt->get_result()->fetch_assoc();

if (!$page) {
    http_response_code(404);
    echo "Page not found.";
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Simple lead capture: add to contacts and map to list
        $contact_check = $mysqli->prepare("SELECT id FROM contacts WHERE email = ? AND user_id = ?");
        $contact_check->bind_param("si", $email, $page['user_id']);
        $contact_check->execute();
        $contact_id = $contact_check->get_result()->fetch_row()[0] ?? null;

        if (!$contact_id) {
            $insert = $mysqli->prepare("INSERT INTO contacts (user_id, team_id, email) VALUES (?, ?, ?)");
            $insert->bind_param("iis", $page['user_id'], $page['team_id'], $email);
            $insert->execute();
            $contact_id = $insert->insert_id;
        }

        $map = $mysqli->prepare("INSERT IGNORE INTO contact_list_map (contact_id, list_id) VALUES (?, ?)");
        $map->bind_param("ii", $contact_id, $page['list_id']);
        $map->execute();

        create_notification($page['user_id'], "You have a new lead ($email) from your landing page: " . $page['headline'], "/view-list?id=" . $page['list_id']);

        $success = true;

    } else {
        $errors[] = "Please enter a valid email address.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($page['headline']); ?></title>
    <style>
        body { font-family: sans-serif; background-color: #f4f4f9; color: #333; margin: 0; padding: 20px;}
        .container { max-width: 700px; margin: 40px auto; background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .content { margin-bottom: 30px; line-height: 1.6; }
        .form-group { margin-bottom: 15px; }
        input[type="email"] { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px; }
        button { background: #007bff; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        .alert { padding: 15px; margin-top: 20px; border-radius: 4px; }
        .alert-success { background: #d4edda; color: #155724; }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo htmlspecialchars($page['headline']); ?></h1>
        <div class="content">
            <?php echo nl2br(htmlspecialchars($page['content'])); ?>
        </div>

        <?php if($success): ?>
            <div class="alert alert-success">Thank you for subscribing!</div>
        <?php else: ?>
            <form action="/p/<?php echo $slug; ?>" method="POST">
                <div class="form-group">
                    <input type="email" name="email" placeholder="Enter your email address" required>
                </div>
                <button type="submit">Subscribe</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
