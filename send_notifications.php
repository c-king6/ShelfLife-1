<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Step 1: Loading PHPMailer files
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

// FIXED: the Gmail app password used to be typed directly into this
// file. It now lives in email_config.php instead, which you keep out
// of anything you share (a repo, a zip for your panel, etc).
require 'email_config.php';

// Step 2: Database Connection
include "db_connect.php";

// 1. Find items expiring in 3 days or less
$query = "SELECT u.email, u.username, u.id AS user_id, i.id AS item_id, i.item_name, i.expiry_date 
          FROM inventory i 
          JOIN users u ON i.user_id = u.id 
          WHERE i.is_bought = 1 
          AND u.email_notify = 1
          AND i.expiry_date <= DATE_ADD(CURDATE(), INTERVAL u.notify_days DAY)
          AND i.expiry_date >= CURDATE()
          AND (i.notified_at IS NULL OR i.notified_at < CURDATE())";

$result = mysqli_query($conn, $query);

$notifications = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $notifications[$row['email']]['items'][]      = $row['item_name'] . " (Expiring: " . $row['expiry_date'] . ")";
        $notifications[$row['email']]['item_ids'][]   = (int)$row['item_id'];
        $notifications[$row['email']]['items_raw'][]  = ['name' => $row['item_name']];
        $notifications[$row['email']]['username']     = $row['username'];
        $notifications[$row['email']]['user_id']      = (int)$row['user_id'];
    }
}

// 2. Send the emails using PHPMailer
if (!empty($notifications)) {
    foreach ($notifications as $email => $data) {
        $mail = new PHPMailer(true);

        try {
            // --- SMTP SERVER SETTINGS ---
            // FIXED: these now come from email_config.php instead of
            // being typed directly into this file.
            $mail->isSMTP();
            $mail->Host       = SHELFLIFE_SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SHELFLIFE_SMTP_USERNAME;
            $mail->Password   = SHELFLIFE_SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SHELFLIFE_SMTP_PORT;

            // --- RECIPIENTS ---
            $mail->setFrom(SHELFLIFE_SMTP_USERNAME, SHELFLIFE_FROM_NAME);
            $mail->addAddress($email, $data['username']);

            // --- CONTENT ---
            $mail->isHTML(true);
            $mail->Subject = 'ShelfLife Alert: Items Expiring Soon!';
            
            $itemList = "<ul><li>" . implode("</li><li>", $data['items']) . "</li></ul>";
            $mail->Body = "
                <div style='font-family: sans-serif; border: 1px solid #e0e0e0; padding: 20px; border-radius: 10px;'>
                    <h2 style='color: #6B8E23;'>Hi " . htmlspecialchars($data['username']) . "!</h2>
                    <p>This is an automated reminder from <b>ShelfLife</b>. The following items in your pantry are nearly expired:</p>
                    $itemList
                    <p>Log in now to see recipe suggestions and reduce waste!</p>
                    <br>
                    <small style='color: #888;'>Happy Cooking!</small>
                </div>";

                            $mail->send();
                    echo "✅ Real Email sent to: $email <br>";

                    // Mark every item in this batch as notified so we don't repeat
                    // the same email tomorrow or in the next hourly run.
                    foreach ($data['item_ids'] as $item_id) {
                        mysqli_query($conn, "UPDATE inventory SET notified_at = NOW() WHERE id = " . (int)$item_id);
                    }

                    // Log the notification for the admin system logs page.
                    // We log one entry per item so admins can trace exactly what was sent.
                    $log_email = mysqli_real_escape_string($conn, $email);
                    $log_user  = (int)$data['user_id'];
                    foreach ($data['items_raw'] as $raw) {
                        $log_item = mysqli_real_escape_string($conn, $raw['name']);
                        mysqli_query($conn, "INSERT INTO notification_logs (user_id, email, item_name, status)
                                            VALUES ($log_user, '$log_email', '$log_item', 'sent')");
                    }

            } catch (Exception $e) {
            echo "❌ Message could not be sent to $email. Mailer Error: {$mail->ErrorInfo} <br>";

            $log_email = mysqli_real_escape_string($conn, $email);
            $log_user  = (int)$data['user_id'];
            foreach ($data['items_raw'] as $raw) {
                $log_item = mysqli_real_escape_string($conn, $raw['name']);
                mysqli_query($conn, "INSERT INTO notification_logs (user_id, email, item_name, status)
                                     VALUES ($log_user, '$log_email', '$log_item', 'failed')");
            }
        }
    }
} else {
    echo "No items are expiring soon for any user. No emails sent.";
}
?>