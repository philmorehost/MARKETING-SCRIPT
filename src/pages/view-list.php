<?php
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}
$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];
$list_id = (int)($_GET['id'] ?? 0);
if ($list_id === 0) {
    header('Location: /contacts');
    exit;
}

$message = '';

// Verify the list belongs to the team
$stmt = $mysqli->prepare("SELECT list_name FROM contact_lists WHERE id = ? AND team_id = ?");
$stmt->bind_param('ii', $list_id, $team_id);
$stmt->execute();
$list = $stmt->get_result()->fetch_assoc();
if (!$list) {
    header('Location: /contacts');
    exit;
}
$list_name = $list['list_name'];


// Handle CSV Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");
    $headers = fgetcsv($handle, 1000, ",");

    // Simple mapping (assumes column order for now)
    $email_col = array_search('email', array_map('strtolower', $headers));
    $name_col = array_search('name', array_map('strtolower', $headers));

    $contacts_added = 0;
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $email = $data[$email_col] ?? null;
        $name = $data[$name_col] ?? null;

        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Check if contact exists for this team, if not insert
            $stmt = $mysqli->prepare("SELECT id FROM contacts WHERE email = ? AND team_id = ?");
            $stmt->bind_param('si', $email, $team_id);
            $stmt->execute();
            $contact = $stmt->get_result()->fetch_assoc();

            if ($contact) {
                $contact_id = $contact['id'];
            } else {
                $stmt = $mysqli->prepare("INSERT INTO contacts (user_id, team_id, email, first_name) VALUES (?, ?, ?, ?)");
                $stmt->bind_param('iiss', $user_id, $team_id, $email, $name);
                $stmt->execute();
                $contact_id = $stmt->insert_id;
            }

            // Map contact to list
            $stmt = $mysqli->prepare("INSERT IGNORE INTO contact_list_map (contact_id, list_id) VALUES (?, ?)");
            $stmt->bind_param('ii', $contact_id, $list_id);
            $stmt->execute();
            $contacts_added++;
        }
    }
    fclose($handle);
    $message = "Import complete. {$contacts_added} contacts processed.";
}


// Fetch contacts in this list
$contacts_result = $mysqli->prepare("SELECT c.id, c.email, c.first_name, c.created_at FROM contacts c JOIN contact_list_map clm ON c.id = clm.contact_id WHERE clm.list_id = ? AND c.team_id = ?");
$contacts_result->bind_param('ii', $list_id, $team_id);
$contacts_result->execute();
$contacts = $contacts_result->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View List: <?php echo htmlspecialchars($list_name); ?></title>
    <link rel="stylesheet" href="css/dashboard_style.css">
</head>
<body>
    <?php include APP_ROOT . '/public/includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar">
            <?php include APP_ROOT . '/public/includes/sidebar.php'; ?>
        </aside>
        <main class="main-content">
            <h1>Viewing List: "<?php echo htmlspecialchars($list_name); ?>"</h1>
            <?php if ($message): ?><div class="message"><?php echo $message; ?></div><?php endif; ?>

            <div class="import-section">
                <h2>Import Contacts from CSV</h2>
                <form action="/view-list?id=<?php echo $list_id; ?>" method="post" enctype="multipart/form-data">
                    <p>Upload a CSV file with at least an 'email' column.</p>
                    <input type="file" name="csv_file" accept=".csv" required>
                    <button type="submit">Import</button>
                </form>
            </div>

            <hr>

             <h2>Contacts in this List</h2>
            <table>
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Name</th>
                        <th>Added On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($contact = $contacts->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($contact['email']); ?></td>
                        <td><?php echo htmlspecialchars($contact['first_name']); ?></td>
                        <td><?php echo $contact['created_at']; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </main>
    </div>
    <?php include APP_ROOT . '/public/includes/footer.php'; ?>
</body>
</html>
