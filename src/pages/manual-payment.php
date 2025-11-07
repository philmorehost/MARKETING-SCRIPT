<?php
// src/pages/manual-payment.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php';
check_login();

$page_title = "Manual Payment Upload";
$errors = [];
$success = false;

// Fetch credit packages for the dropdown
$packages_query = $mysqli->query("SELECT id, name, price FROM credit_packages ORDER BY price ASC");
$packages = $packages_query->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $package_id = $_POST['package_id'] ?? null;
    $proof_file = $_FILES['proof_of_payment'] ?? null;

    // Validation
    if (!$package_id) {
        $errors[] = "Please select the credit package you paid for.";
    }
    if (!$proof_file || $proof_file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Please upload a valid proof of payment file.";
    }

    $selected_package = null;
    foreach ($packages as $pkg) {
        if ($pkg['id'] == $package_id) {
            $selected_package = $pkg;
            break;
        }
    }
    if (!$selected_package) {
        $errors[] = "Invalid credit package selected.";
    }

    if (empty($errors)) {
        // --- File Upload Logic ---
        $upload_dir = APP_ROOT . '/public/uploads/proofs/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $filename = uniqid('pop_', true) . '_' . basename($proof_file['name']);
        $upload_path = $upload_dir . $filename;
        $relative_path = '/uploads/proofs/' . $filename;

        // Check file type and size
        $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($proof_file['type'], $allowed_types) || $proof_file['size'] > 2097152) { // 2MB limit
            $errors[] = "Invalid file. Please upload a JPG, PNG, or PDF file smaller than 2MB.";
        } else {
            if (move_uploaded_file($proof_file['tmp_name'], $upload_path)) {
                // --- File uploaded successfully, create DB record ---
                $stmt = $mysqli->prepare(
                    "INSERT INTO manual_payments (user_id, credit_package_id, credit_package_name, amount, proof_path, status)
                     VALUES (?, ?, ?, ?, ?, 'pending')"
                );
                $stmt->bind_param("iisds",
                    $user['id'],
                    $selected_package['id'],
                    $selected_package['name'],
                    $selected_package['price'],
                    $relative_path
                );

                if ($stmt->execute()) {
                    $success = true;
                    // TODO: Notify admin about the new pending POP
                } else {
                    $errors[] = "Database error. Could not save your submission.";
                    // Clean up uploaded file
                    unlink($upload_path);
                }

            } else {
                $errors[] = "There was an error uploading your file.";
            }
        }
    }
}

include __DIR__ . '/../includes/header_app.php';
?>

<div class="container app-content">
    <h1>Upload Proof of Payment</h1>
    <p>Please upload a screenshot or document of your completed bank transfer. Your account will be credited once the payment is verified by our team.</p>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <strong>Thank You!</strong> Your proof of payment has been submitted. We will review it shortly. You can check the status on your billing page.
        </div>
    <?php else: ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="/manual-payment" method="POST" enctype="multipart/form-data" class="card">
             <div class="form-group">
                <label for="package_id">Credit Package</label>
                <select name="package_id" id="package_id" required>
                    <option value="">-- Select the package you paid for --</option>
                    <?php foreach ($packages as $pkg): ?>
                        <option value="<?php echo $pkg['id']; ?>"><?php echo htmlspecialchars($pkg['name'] . ' ($' . $pkg['price'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
             <div class="form-group">
                <label for="proof_of_payment">Proof of Payment File</label>
                <p class="form-hint">Accepted formats: JPG, PNG, PDF. Max size: 2MB.</p>
                <input type="file" name="proof_of_payment" id="proof_of_payment" required>
            </div>
            <button type="submit" class="btn btn-primary">Submit for Verification</button>
        </form>
    <?php endif; ?>
</div>

<?php
include __DIR__ . '/../includes/footer_app.php';
?>
