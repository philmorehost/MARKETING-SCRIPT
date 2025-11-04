<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}
$user_id = $_SESSION['user_id'];
$message = '';

// Handle Manual Payment Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_payment'])) {
    $package_id = $_POST['credit_package'] ?? 0;
    $pop_file = $_FILES['pop'] ?? null;

    if ($package_id && $pop_file && $pop_file['error'] === UPLOAD_ERR_OK) {
        // Fetch package details
        $stmt = $mysqli->prepare("SELECT name, price FROM credit_packages WHERE id = ?");
        $stmt->bind_param('i', $package_id);
        $stmt->execute();
        $package = $stmt->get_result()->fetch_assoc();

        if ($package) {
            $upload_dir = __DIR__ . '/../../uploads/pop/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $filename = uniqid('pop_', true) . '.' . pathinfo($pop_file['name'], PATHINFO_EXTENSION);
            $filepath = $upload_dir . $filename;

            if (move_uploaded_file($pop_file['tmp_name'], $filepath)) {
                $db_filepath = '/uploads/pop/' . $filename;
                $stmt = $mysqli->prepare("INSERT INTO manual_payments (user_id, credit_package_id, credit_package_name, amount, proof_path) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param('iisds', $user_id, $package_id, $package['name'], $package['price'], $db_filepath);
                if ($stmt->execute()) {
                    $message = "Proof of payment uploaded successfully. Please wait for an admin to verify it.";
                } else {
                    $message = "Error: Could not save payment details.";
                }
            } else {
                $message = "Error: Could not upload your file.";
            }
        } else {
            $message = "Error: Invalid credit package selected.";
        }
    } else {
        $message = "Error: Please select a package and upload a valid proof of payment.";
    }
}

// Fetch credit packages
$packages_result = $mysqli->query("SELECT id, name, description, price, credits, is_popular FROM credit_packages ORDER BY price");
?>
<!DOCTYPE html>
<html lang="en">
<head><title>Buy Credits</title><link rel="stylesheet" href="/css/dashboard_style.css"></head>
<body>
    <?php include APP_ROOT . '/public/includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar"><?php include APP_ROOT . '/public/includes/sidebar.php'; ?></aside>
        <main class="main-content">
            <h1>Buy Credits</h1>
            <p>Our platform operates on a simple pay-as-you-go credit system. Purchase a credit package below to get started.</p>
            <?php if ($message): ?><p class="notice"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>

            <div class="pricing-container">
                <?php while ($pkg = $packages_result->fetch_assoc()): ?>
                <div class="pricing-card <?php if ($pkg['is_popular']) echo 'popular'; ?>">
                    <h3><?php echo htmlspecialchars($pkg['name']); ?></h3>
                    <p class="price">$<?php echo number_format($pkg['price'], 2); ?></p>
                    <p class="credits"><?php echo number_format($pkg['credits']); ?> Credits</p>
                    <p><?php echo htmlspecialchars($pkg['description']); ?></p>
                    <form action="/paystack-initialize" method="post">
                         <input type="hidden" name="package_id" value="<?php echo $pkg['id']; ?>">
                         <input type="hidden" name="amount" value="<?php echo $pkg['price'] * 100; // Paystack expects amount in kobo ?>">
                         <button type="submit">Buy with Paystack</button>
                    </form>
                </div>
                <?php endwhile; ?>
            </div>

            <hr>

            <div class="manual-payment">
                <h2>Manual Bank Transfer</h2>
                <p>Please make a payment to the account details below and upload your proof of payment (POP).</p>
                <p><strong>Bank:</strong> First Bank | <strong>Account:</strong> 1234567890 | <strong>Name:</strong> Your Company Inc.</p>
                <form action="/buy-credits" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="manual_payment" value="1">
                    <div class="form-group">
                        <label for="credit_package">Select Package:</label>
                        <select id="credit_package" name="credit_package" required>
                            <?php
                            $packages_result->data_seek(0); // Reset pointer
                            while ($pkg = $packages_result->fetch_assoc()) {
                                echo "<option value='{$pkg['id']}'>" . htmlspecialchars($pkg['name']) . " (\$" . number_format($pkg['price'], 2) . ")</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="pop">Upload Proof of Payment:</label>
                        <input type="file" id="pop" name="pop" required accept="image/*,.pdf">
                    </div>
                    <button type="submit">Submit for Verification</button>
                </form>
            </div>
        </main>
    </div>
    <?php include APP_ROOT . '/public/includes/footer.php'; ?>
</body>
</html>
