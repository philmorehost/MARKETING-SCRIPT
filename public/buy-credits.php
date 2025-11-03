<?php
session_start();
require_once '../config/db.php';
require_once '../src/lib/functions.php'; // We'll create this for helper functions

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

$message = '';

// Handle Manual Payment Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_payment'])) {
    $package_id = (int)($_POST['package_id'] ?? 0);
    $amount = $_POST['amount'] ?? 0;
    $pop_file = $_FILES['pop'] ?? null;

    if ($package_id > 0 && $amount > 0 && $pop_file && $pop_file['error'] === UPLOAD_ERR_OK) {
        // Get package details to verify amount and name
        $pkg_stmt = $mysqli->prepare("SELECT name, price FROM credit_packages WHERE id = ?");
        $pkg_stmt->bind_param('i', $package_id);
        $pkg_stmt->execute();
        $package = $pkg_stmt->get_result()->fetch_assoc();

        if ($package && $package['price'] == $amount) {
            $package_name = $package['name'];
            $upload_dir = 'uploads/pop/';
            $filename = uniqid() . '-' . basename($pop_file['name']);
            $target_path = $upload_dir . $filename;

            if (move_uploaded_file($pop_file['tmp_name'], $target_path)) {
                $stmt = $mysqli->prepare("INSERT INTO manual_payments (user_id, credit_package_id, credit_package_name, amount, proof_path) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param('iisds', $user_id, $package_id, $package_name, $amount, $target_path);
                if ($stmt->execute()) {
                    $message = "Proof of payment uploaded successfully. Please wait for an admin to verify it.";
                } else {
                    $message = "Error submitting your proof of payment.";
                }
            } else {
                $message = "Error submitting your proof of payment.";
            }
        } else {
            $message = "Error uploading file.";
        }
    } else {
        $message = "Please provide the amount and a valid proof of payment file.";
    }
}


// Fetch credit packages
$packages_result = $mysqli->query("SELECT id, name, description, price, credits, is_popular FROM credit_packages ORDER BY price");

// Fetch bank details from settings
$bank_details = get_setting('bank_details', $mysqli);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Buy Credits</title>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="user-container">
        <aside class="sidebar">
            <?php include 'includes/sidebar.php'; ?>
        </aside>
        <main class="main-content">
            <h1>Buy Credits</h1>
            <?php if ($message): ?><div class="message"><?php echo $message; ?></div><?php endif; ?>

            <h2>Credit Packages</h2>
            <div class="pricing-grid">
                <?php
                // Reset pointer to loop through packages again for the form
                $packages_result->data_seek(0);
                while($pkg = $packages_result->fetch_assoc()):
                ?>
                <div class="package-card <?php if($pkg['is_popular']) echo 'popular'; ?>">
                    <h3><?php echo htmlspecialchars($pkg['name']); ?></h3>
                    <p class="price">$<?php echo number_format($pkg['price'], 2); ?></p>
                    <p class="credits"><?php echo number_format($pkg['credits']); ?> Credits</p>
                    <p class="description"><?php echo htmlspecialchars($pkg['description']); ?></p>
                    <form action="paystack-initialize.php" method="post">
                        <input type="hidden" name="package_id" value="<?php echo $pkg['id']; ?>">
                        <button type="submit">Buy with Paystack</button>
                    </form>
                </div>
                <?php endwhile; ?>
            </div>

            <hr>

            <h2>Manual Bank Transfer</h2>
            <div class="manual-payment-box">
                <p>To pay via bank transfer, please deposit to the following account:</p>
                <pre><?php echo htmlspecialchars($bank_details); ?></pre>
                <p>After payment, upload your proof of payment below.</p>

                <form action="buy-credits.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="manual_payment" value="1">
                    <label for="package_id">Select Package:</label>
                    <select id="package_id" name="package_id" required onchange="updateAmount(this)">
                        <option value="">-- Select a Package --</option>
                        <?php $packages_result->data_seek(0); // Reset pointer again ?>
                        <?php while($pkg = $packages_result->fetch_assoc()): ?>
                            <option value="<?php echo $pkg['id']; ?>" data-price="<?php echo $pkg['price']; ?>">
                                <?php echo htmlspecialchars($pkg['name']); ?> ($<?php echo $pkg['price']; ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>

                    <label for="amount">Amount to Pay (USD):</label>
                    <input type="number" step="0.01" id="amount" name="amount" readonly required>

                    <label for="pop">Proof of Payment (Image/PDF):</label>
                    <input type="file" id="pop" name="pop" accept="image/*,.pdf" required>

                    <button type="submit">Submit Proof of Payment</button>
                </form>
            </div>
        </main>
    </div>
    <script>
        function updateAmount(selectElement) {
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            const price = selectedOption.getAttribute('data-price') || '';
            document.getElementById('amount').value = price;
        }
    </script>
</body>
</html>
