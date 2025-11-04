<?php
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];
$list_id = (int)($_GET['id'] ?? 0);
if ($list_id === 0) {
    header('Location: contacts.php');
    exit;
}

$message = '';

// Verify the list belongs to the team
$stmt = $mysqli->prepare("SELECT list_name FROM contact_lists WHERE id = ? AND team_id = ?");
$stmt->bind_param('ii', $list_id, $team_id);
$stmt->execute();
$list = $stmt->get_result()->fetch_assoc();
if (!$list) {
    header('Location: contacts.php');
    exit;
}
$list_name = $list['list_name'];

// Handle Add/Edit Contact
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_contact'])) {
    $contact_id = (int)($_POST['contact_id'] ?? 0);
    $email = trim($_POST['email'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');

    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if ($contact_id > 0) { // Update existing contact
            $stmt = $mysqli->prepare("UPDATE contacts SET email = ?, first_name = ?, last_name = ? WHERE id = ? AND team_id = ?");
            $stmt->bind_param('sssii', $email, $first_name, $last_name, $contact_id, $team_id);
            $stmt->execute();
            $message = "Contact updated.";
        } else { // Add new contact
            // Check if contact already exists for the team
            $stmt_check = $mysqli->prepare("SELECT id FROM contacts WHERE email = ? AND team_id = ?");
            $stmt_check->bind_param('si', $email, $team_id);
            $stmt_check->execute();
            $existing_contact = $stmt_check->get_result()->fetch_assoc();

            if ($existing_contact) {
                $new_contact_id = $existing_contact['id'];
            } else {
                $stmt_insert = $mysqli->prepare("INSERT INTO contacts (user_id, team_id, email, first_name, last_name) VALUES (?, ?, ?, ?, ?)");
                $stmt_insert->bind_param('iisss', $user_id, $team_id, $email, $first_name, $last_name);
                $stmt_insert->execute();
                $new_contact_id = $stmt_insert->insert_id;
            }
            // Map the new or existing contact to the current list
            $stmt_map = $mysqli->prepare("INSERT IGNORE INTO contact_list_map (contact_id, list_id) VALUES (?, ?)");
            $stmt_map->bind_param('ii', $new_contact_id, $list_id);
            $stmt_map->execute();
            $message = "Contact added to list.";
        }
    } else {
        $message = "Invalid email provided.";
    }
}

// Handle Delete Contact from List
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_contact'])) {
    $contact_id = (int)($_POST['contact_id'] ?? 0);
    $stmt = $mysqli->prepare("DELETE FROM contact_list_map WHERE contact_id = ? AND list_id = ?");
    $stmt->bind_param('ii', $contact_id, $list_id);
    $stmt->execute();
    $message = "Contact removed from this list.";
}


// Handle CSV Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    $map_email = $_POST['map_email'] ?? -1;
    $map_fname = $_POST['map_fname'] ?? -1;
    $map_lname = $_POST['map_lname'] ?? -1;

    if ($map_email >= 0) {
        $handle = fopen($file, "r");
        fgetcsv($handle); // Skip header row

        $contacts_added = 0;
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $email = $data[$map_email] ?? null;
            $fname = ($map_fname >= 0) ? $data[$map_fname] : '';
            $lname = ($map_lname >= 0) ? $data[$map_lname] : '';

            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $stmt_check = $mysqli->prepare("SELECT id FROM contacts WHERE email = ? AND team_id = ?");
                $stmt_check->bind_param('si', $email, $team_id);
                $stmt_check->execute();
                $existing_contact = $stmt_check->get_result()->fetch_assoc();

                if ($existing_contact) {
                    $contact_id = $existing_contact['id'];
                } else {
                    $stmt_insert = $mysqli->prepare("INSERT INTO contacts (user_id, team_id, email, first_name, last_name) VALUES (?, ?, ?, ?, ?)");
                    $stmt_insert->bind_param('iisss', $user_id, $team_id, $email, $fname, $lname);
                    $stmt_insert->execute();
                    $contact_id = $stmt_insert->insert_id;
                }

                $stmt_map = $mysqli->prepare("INSERT IGNORE INTO contact_list_map (contact_id, list_id) VALUES (?, ?)");
                $stmt_map->bind_param('ii', $contact_id, $list_id);
                $stmt_map->execute();
                if ($stmt_map->affected_rows > 0) {
                    $contacts_added++;
                }
            }
        }
        fclose($handle);
        $message = "Import complete. {$contacts_added} new contacts added to the list.";
    } else {
        $message = "Error: You must map the 'email' column.";
    }
}


$search = $_GET['search'] ?? '';
$query = "SELECT c.id, c.email, c.first_name, c.last_name, c.created_at FROM contacts c JOIN contact_list_map clm ON c.id = clm.contact_id WHERE clm.list_id = ? AND c.team_id = ?";
$params = ['ii', $list_id, $team_id];
if (!empty($search)) {
    $query .= " AND (c.email LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ?)";
    $params[0] .= 'sss';
    $search_term = "%{$search}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}
$contacts_result = $mysqli->prepare($query);
$contacts_result->bind_param(...$params);
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
                <h2>Import & Export</h2>
                <button class="button-primary" onclick="document.getElementById('import-modal').style.display = 'flex'">Import from CSV</button>
                <a href="/public/export-contacts?list_id=<?php echo $list_id; ?>" class="button-secondary">Export to CSV</a>
            </div>

            <hr>

            <div class="card-header">
                 <h2>Contacts in this List</h2>
                 <button class="button-primary" onclick="openContactModal()">Add Contact</button>
            </div>
            <form method="get" class="filter-form">
                 <input type="hidden" name="id" value="<?php echo $list_id; ?>">
                 <input type="text" name="search" placeholder="Search contacts..." value="<?php echo htmlspecialchars($search); ?>">
                 <button type="submit">Search</button>
            </form>
            <table>
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Added On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($contact = $contacts->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($contact['email']); ?></td>
                        <td><?php echo htmlspecialchars($contact['first_name']); ?></td>
                        <td><?php echo htmlspecialchars($contact['last_name']); ?></td>
                        <td><?php echo $contact['created_at']; ?></td>
                        <td>
                            <button class="button-secondary" onclick='openContactModal(<?php echo json_encode($contact, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>Edit</button>
                            <form action="" method="post" style="display:inline;" onsubmit="return confirm('Remove this contact from the list?');">
                                <input type="hidden" name="contact_id" value="<?php echo $contact['id']; ?>">
                                <button type="submit" name="delete_contact" class="button-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </main>
    </div>

    <!-- Add/Edit Contact Modal -->
    <div id="contact-modal" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <h2 id="modal-title">Add New Contact</h2>
            <form action="" method="post">
                <input type="hidden" name="save_contact" value="1">
                <input type="hidden" id="contact-id" name="contact_id">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name">
                </div>
                 <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name">
                </div>
                <button type="submit">Save Contact</button>
                <button type="button" onclick="closeContactModal()">Cancel</button>
            </form>
        </div>
    </div>

    <?php include APP_ROOT . '/public/includes/footer.php'; ?>

    <!-- Import Modal -->
    <div id="import-modal" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <h2>Import Contacts from CSV</h2>
            <form id="import-form" action="" method="post" enctype="multipart/form-data">
                <p><b>Step 1:</b> Upload your CSV file.</p>
                <input type="file" id="csv-file-input" name="csv_file" accept=".csv" required>

                <div id="column-mapping" style="display:none;">
                    <p><b>Step 2:</b> Map the columns from your file.</p>
                    <div class="form-group">
                        <label>Email Column:</label>
                        <select id="map-email" name="map_email" required></select>
                    </div>
                     <div class="form-group">
                        <label>First Name Column:</label>
                        <select id="map-fname" name="map_fname"></select>
                    </div>
                     <div class="form-group">
                        <label>Last Name Column:</label>
                        <select id="map-lname" name="map_lname"></select>
                    </div>
                </div>

                <button type="submit" id="import-submit-btn" disabled>Import Contacts</button>
                <button type="button" onclick="document.getElementById('import-modal').style.display = 'none'">Cancel</button>
            </form>
        </div>
    </div>

    <script>
    document.getElementById('csv-file-input').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function(e) {
            const text = e.target.result;
            const firstLine = text.split('\n')[0];
            const headers = firstLine.split(',');

            const emailSelect = document.getElementById('map-email');
            const fnameSelect = document.getElementById('map-fname');
            const lnameSelect = document.getElementById('map-lname');

            emailSelect.innerHTML = '<option value="-1">-- Select --</option>';
            fnameSelect.innerHTML = '<option value="-1">-- Do not import --</option>';
            lnameSelect.innerHTML = '<option value="-1">-- Do not import --</option>';

            headers.forEach((header, index) => {
                emailSelect.innerHTML += `<option value="${index}">${header.trim()}</option>`;
                fnameSelect.innerHTML += `<option value="${index}">${header.trim()}</option>`;
                lnameSelect.innerHTML += `<option value="${index}">${header.trim()}</option>`;
            });

            document.getElementById('column-mapping').style.display = 'block';
            document.getElementById('import-submit-btn').disabled = false;
        };
        reader.readAsText(file);
    });


    function openContactModal(contact = null) {
        if (contact) {
            document.getElementById('modal-title').innerText = 'Edit Contact';
            document.getElementById('contact-id').value = contact.id;
            document.getElementById('email').value = contact.email;
            document.getElementById('first_name').value = contact.first_name;
            document.getElementById('last_name').value = contact.last_name;
        } else {
            document.getElementById('modal-title').innerText = 'Add New Contact';
            document.getElementById('contact-id').value = '';
            document.getElementById('email').value = '';
            document.getElementById('first_name').value = '';
            document.getElementById('last_name').value = '';
        }
        document.getElementById('contact-modal').style.display = 'flex';
    }

    function closeContactModal() {
        document.getElementById('contact-modal').style.display = 'none';
    }
    </script>
</body>
</html>
