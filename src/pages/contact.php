<?php
// src/pages/contact.php
require_once __DIR__ . '/../lib/functions.php';

$page_title = "Contact Us";
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name)) $errors[] = "Name is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "A valid email is required.";
    if (empty($subject)) $errors[] = "Subject is required.";
    if (empty($message)) $errors[] = "Message is required.";

    if (empty($errors)) {
        // Create a support ticket from a "guest"
        $stmt = $mysqli->prepare(
            "INSERT INTO support_tickets (user_id, team_id, guest_name, guest_email, subject, status, created_at)
             VALUES (NULL, NULL, ?, ?, ?, 'open', NOW())"
        );
        $stmt->bind_param("sss", $name, $email, $subject);
        if ($stmt->execute()) {
            $ticket_id = $stmt->insert_id;

            // Add the first reply
            $reply_stmt = $mysqli->prepare(
                "INSERT INTO support_ticket_replies (ticket_id, user_id, message, created_at)
                 VALUES (?, NULL, ?, NOW())"
            );
            $reply_stmt->bind_param("is", $ticket_id, $message);
            $reply_stmt->execute();

            $success = true;

            // TODO: Send an email notification to the admin

        } else {
            $errors[] = "Sorry, there was an error submitting your message. Please try again later.";
        }
    }
}

include __DIR__ . '/../includes/header_public.php';
?>

<div class="container page-content">
    <h1>Contact Us</h1>
    <p>Have a question or need help? Fill out the form below, and we'll get back to you as soon as possible.</p>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <strong>Thank you!</strong> Your message has been received. Our team will get back to you shortly. Your ticket has been created.
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

        <form action="/contact" method="POST" class="contact-form">
            <div class="form-group">
                <label for="name">Your Name</label>
                <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="email">Your Email</label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="subject">Subject</label>
                <input type="text" id="subject" name="subject" required value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="message">Message</label>
                <textarea id="message" name="message" rows="6" required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
            </div>
            <button type="submit" class="cta-button">Send Message</button>
        </form>
    <?php endif; ?>
</div>

<?php
include __DIR__ . '/../includes/footer_public.php';
?>
