<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Step 1: Loading PHPMailer files
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

// Step 2: Database Connection
include "db_connect.php";

// 1. Find items expiring in 3 days or less
$query = "SELECT u.email, u.username, i.item_name, i.expiry_date 
          FROM inventory i 
          JOIN users u ON i.user_id = u.id 
          WHERE i.is_bought = 1 
          AND i.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
          AND i.expiry_date >= CURDATE()";

$result = mysqli_query($conn, $query);

$notifications = [];
while ($row = mysqli_fetch_assoc($result)) {
    // Group items by email so we only send ONE email per user
    $notifications[$row['email']]['items'][] = $row['item_name'] . " (Expiring: " . $row['expiry_date'] . ")";
    $notifications[$row['email']]['username'] = $row['username'];
}

// 2. Send the emails using PHPMailer
foreach ($notifications as $email => $data) {
    $mail = new PHPMailer(true);

    try {
        // --- SMTP SERVER SETTINGS ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'king.villafranca06@gmail.com'; // Fixed: Added @ symbol
        $mail->Password   = 'mxbmokliktidmjef';           // Your App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // --- RECIPIENTS ---
        $mail->setFrom('king.villafranca06@gmail.com', 'ShelfLife Pantry');
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

    } catch (Exception $e) {
        echo "❌ Message could not be sent to $email. Mailer Error: {$mail->ErrorInfo} <br>";
    }
}

if (empty($notifications)) {
    echo "No items are expiring within 3 days. No emails sent.";
}
?>