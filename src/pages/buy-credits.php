<?php
// src/pages/buy-credits.php
require_once __DIR__ . '/../lib/functions.php';
require_once __DIR__ . '/../lib/auth.php'; // Ensures user is logged in
check_login();

$page_title = "Buy Credits";

// Fetch credit packages from the database
$packages_query = $mysqli->query("SELECT * FROM credit_packages ORDER BY price ASC");
$packages = $packages_query->fetch_all(MYSQLI_ASSOC);

$paystack_pk = get_setting('paystack_public_key');

include __DIR__ . '/../includes/header_app.php';
?>

<div class="container app-content">
    <h1>Buy Credits</h1>
    <p>Choose a credit package to top up your account. Payments are securely processed by Paystack.</p>

    <div class="pricing-grid">
        <?php foreach ($packages as $pkg) : ?>
            <div class="pricing-card <?php echo $pkg['is_popular'] ? 'popular' : ''; ?>">
                 <?php if ($pkg['is_popular']) : ?>
                    <div class="popular-badge">Most Popular</div>
                <?php endif; ?>
                <h3><?php echo htmlspecialchars($pkg['name']); ?></h3>
                <div class="price"><?php echo '$' . number_format($pkg['price'], 2); ?></div>
                <div class="credits"><?php echo number_format($pkg['credits']); ?> Credits</div>
                <p><?php echo htmlspecialchars($pkg['description']); ?></p>

                <?php if ($paystack_pk) : ?>
                <button
                    class="cta-button"
                    onclick="payWithPaystack(<?php echo $pkg['price'] * 100; ?>, '<?php echo $user['email']; ?>', <?php echo $pkg['id']; ?>)">
                    Buy Now
                </button>
                <?php else: ?>
                 <p class="text-muted">Paystack not configured.</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="manual-payment-option">
        <h2>Manual Bank Transfer</h2>
        <p>Alternatively, you can make a direct bank transfer to the account below and upload your proof of payment.</p>
        <div class="bank-details">
            <p><strong>Bank Name:</strong> <?php echo get_setting('bank_name', 'N/A'); ?></p>
            <p><strong>Account Number:</strong> <?php echo get_setting('bank_account_number', 'N/A'); ?></p>
            <p><strong>Account Name:</strong> <?php echo get_setting('bank_account_name', 'N/A'); ?></p>
        </div>
        <a href="/manual-payment" class="btn btn-secondary">Upload Proof of Payment</a>
    </div>

</div>

<?php if ($paystack_pk) : ?>
<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
function payWithPaystack(amount, email, package_id) {
    var handler = PaystackPop.setup({
        key: '<?php echo $paystack_pk; ?>',
        email: email,
        amount: amount,
        currency: "USD", // Or your currency
        ref: ''+Math.floor((Math.random() * 1000000000) + 1), // Generate a random reference.
        metadata: {
            user_id: <?php echo $user['id']; ?>,
            package_id: package_id,
        },
        callback: function(response){
            // On successful payment, Paystack will hit our webhook.
            // We can redirect the user to a success page here.
            window.location.href = '/billing?payment=success&ref=' + response.reference;
        },
        onClose: function(){
            // User closed the popup
        }
    });
    handler.openIframe();
}
</script>
<?php endif; ?>


<?php
include __DIR__ . '/../includes/footer_app.php';
?>
