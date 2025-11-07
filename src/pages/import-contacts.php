<?php
// src/pages/import-contacts.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

$list_id = $_GET['list_id'] ?? null;
if (!$list_id) {
    header('Location: /contact-lists');
    exit;
}
// User ownership of the list is verified similarly to view-list.php

$page_title = "Import Contacts";
$step = 1;
$errors = [];
$file_path = '';
$headers = [];

// Step 1: File Upload
if (isset($_POST['action']) && $_POST['action'] === 'upload') {
    $file = $_FILES['csv_file'];
    if ($file['error'] === UPLOAD_ERR_OK && $file['type'] === 'text/csv') {
        $upload_dir = APP_ROOT . '/public/uploads/imports/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $file_path = $upload_dir . uniqid() . '.csv';

        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            // File uploaded, now read headers for mapping
            if (($handle = fopen($file_path, "r")) !== FALSE) {
                $headers = fgetcsv($handle);
                fclose($handle);
                $step = 2;
                $_SESSION['import_file_path'] = $file_path;
                 $_SESSION['import_list_id'] = $list_id;
            }
        } else {
            $errors[] = "Error uploading file.";
        }
    } else {
        $errors[] = "Please upload a valid CSV file.";
    }
}

// Step 2: Column Mapping & Import
if (isset($_POST['action']) && $_POST['action'] === 'import') {
    $file_path = $_SESSION['import_file_path'] ?? '';
    $list_id = $_SESSION['import_list_id'] ?? null;
    $mapping = $_POST['map'];

    if ($file_path && $list_id && !empty($mapping['email'])) {
        // Prepare statements
        $contact_check_stmt = $mysqli->prepare("SELECT id FROM contacts WHERE email = ? AND user_id = ?");
        $contact_insert_stmt = $mysqli->prepare("INSERT INTO contacts (user_id, team_id, email, first_name, last_name, phone_number) VALUES (?, ?, ?, ?, ?, ?)");
        $map_stmt = $mysqli->prepare("INSERT IGNORE INTO contact_list_map (contact_id, list_id) VALUES (?, ?)");

        if (($handle = fopen($file_path, "r")) !== FALSE) {
            fgetcsv($handle); // Skip header row
            while (($data = fgetcsv($handle)) !== FALSE) {
                $email = $data[$mapping['email']] ?? null;
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;

                $first_name = $data[$mapping['first_name']] ?? '';
                $last_name = $data[$mapping['last_name']] ?? '';
                $phone = $data[$mapping['phone_number']] ?? '';

                $contact_check_stmt->bind_param("si", $email, $user['id']);
                $contact_check_stmt->execute();
                $result = $contact_check_stmt->get_result();
                $contact_id = null;

                if ($existing = $result->fetch_assoc()) {
                    $contact_id = $existing['id'];
                } else {
                    $contact_insert_stmt->bind_param("iissss", $user['id'], $user['team_id'], $email, $first_name, $last_name, $phone);
                    $contact_insert_stmt->execute();
                    $contact_id = $contact_insert_stmt->insert_id;
                }

                if ($contact_id) {
                    $map_stmt->bind_param("ii", $contact_id, $list_id);
                    $map_stmt->execute();
                }
            }
            fclose($handle);
        }

        unlink($file_path);
        unset($_SESSION['import_file_path'], $_SESSION['import_list_id']);
        header('Location: /view-list?id=' . $list_id . '&import=success');
        exit;

    } else {
        $errors[] = "Email column mapping is required.";
        $step = 2; // Stay on step 2
    }
}


include __DIR__ . '/../includes/header_app.php';
?>
<div class="container app-content">
    <h1>Import Contacts</h1>
    <a href="/view-list?id=<?php echo $list_id; ?>">&larr; Back to list</a>

    <div class="card">
    <?php if ($step === 1): ?>
        <h2>Step 1: Upload CSV File</h2>
        <form action="/import-contacts?list_id=<?php echo $list_id; ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload">
            <div class="form-group">
                <label for="csv_file">Select CSV file</label>
                <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
            </div>
            <button type="submit" class="btn btn-primary">Upload & Next</button>
        </form>
    <?php elseif ($step === 2): ?>
        <h2>Step 2: Map Columns</h2>
        <p>Map the columns from your CSV to the contact fields.</p>
        <form action="/import-contacts" method="POST">
            <input type="hidden" name="action" value="import">
            <table class="table">
                <?php $fields = ['email', 'first_name', 'last_name', 'phone_number']; ?>
                <?php foreach($fields as $field): ?>
                <tr>
                    <td><strong><?php echo ucfirst(str_replace('_', ' ', $field)); ?></strong> <?php if($field === 'email') echo '(Required)'; ?></td>
                    <td>
                        <select name="map[<?php echo $field; ?>]">
                            <option value="">-- Don't Import --</option>
                            <?php foreach($headers as $i => $header): ?>
                            <option value="<?php echo $i; ?>"><?php echo htmlspecialchars($header); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                 <?php endforeach; ?>
            </table>
            <button type="submit" class="btn btn-success">Start Import</button>
        </form>
    <?php endif; ?>
    </div>
</div>
<?php
include __DIR__ . '/../includes/footer_app.php';
?>
