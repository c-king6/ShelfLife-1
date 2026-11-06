<?php
/**
 * send_weekly_digest.php
 *
 * Sunday-evening digest email — one per user, sent by a scheduler.
 *
 * What's inside each digest:
 *   - Items expiring in the next 7 days
 *   - This month's savings vs waste
 *   - The coming week's meal plan
 *
 * Trigger via browser:
 *   http://localhost/ShelfLife/send_weekly_digest.php?key=YOUR_KEY
 *
 * Schedule via Windows Task Scheduler or cron (see bottom of this file).
 *
 * NOTES:
 *   - Does NOT require an active session.
 *   - Requires a secret key in the URL to prevent random triggering.
 *   - Uses the same PHPMailer + SMTP as the expiry notifier.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ---------- Guard ----------
// Change this secret to anything long and random.
// Keep it OUT of any shared zip or repo.
define('DIGEST_KEY', 'shelflife-digest-k7x9m2p4q8');
$is_cli = (php_sapi_name() === 'cli');
if (!$is_cli && ($_GET['key'] ?? '') !== DIGEST_KEY) {
    http_response_code(403);
    exit('Forbidden');
}

// ---------- Config + DB ----------
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'email_config.php';
include 'db_connect.php';

// Set timezone to match the app
date_default_timezone_set('Asia/Manila');

// ---------- Send window ----------
// Today is fine — the scheduler decides the day/time.
// Compute "coming week" as Monday through Sunday starting next Monday.
$today        = date('Y-m-d');
$month_start  = date('Y-m-01');
$week_start   = date('Y-m-d', strtotime('next monday'));
$week_end     = date('Y-m-d', strtotime($week_start . ' +6 days'));

$digest_label = 'Weekly Digest · ' . date('M j, Y');

// ---------- Fetch eligible users ----------
$users_q = mysqli_query($conn, "
    SELECT id, username, email, notify_days
    FROM users
    WHERE email_notify = 1
      AND is_verified = 1
      AND role != 'admin'
");

$sent = 0;
$skipped = 0;

while ($user = mysqli_fetch_assoc($users_q)) {
    $uid       = (int)$user['id'];
    $uname     = $user['username'];
    $email     = $user['email'];
    $ndays     = max(1, min(30, (int)$user['notify_days']));

    // --------------------------------------------------
    // 1. Items expiring in the next N days (their setting)
    // --------------------------------------------------
    $exp_q = mysqli_query($conn, "
        SELECT item_name, expiry_date, location
        FROM inventory
        WHERE user_id = '$uid'
          AND is_bought = 1
          AND expiry_date IS NOT NULL
          AND expiry_date >= CURDATE()
          AND DATEDIFF(expiry_date, CURDATE()) <= $ndays
        ORDER BY expiry_date ASC
        LIMIT 10
    ");
    $expiring = [];
    while ($r = mysqli_fetch_assoc($exp_q)) {
        $expiring[] = $r;
    }

    // --------------------------------------------------
    // 2. This month's savings / waste
    // --------------------------------------------------
    $money_q = mysqli_query($conn, "
        SELECT
            COALESCE(SUM(CASE WHEN status='consumed' AND price IS NOT NULL THEN price ELSE 0 END), 0) AS saved,
            COALESCE(SUM(CASE WHEN status='wasted'   AND price IS NOT NULL THEN price ELSE 0 END), 0) AS wasted,
            COUNT(CASE WHEN status='consumed' THEN 1 END) AS consumed_count,
            COUNT(CASE WHEN status='wasted'   THEN 1 END) AS wasted_count
        FROM item_history
        WHERE user_id = '$uid'
          AND logged_at >= '$month_start'
    ");
    $money = mysqli_fetch_assoc($money_q);
    $saved  = (float)$money['saved'];
    $wasted = (float)$money['wasted'];
    $total  = $saved + $wasted;
    $eff    = $total > 0 ? round(($saved / $total) * 100) : 0;

    // --------------------------------------------------
    // 3. Meal plan for the coming week
    // --------------------------------------------------
    $plan_q = mysqli_query($conn, "
        SELECT mp.plan_date, mp.meal_slot, r.recipe_name
        FROM meal_plan mp
        JOIN recipes r ON mp.recipe_id = r.id
        WHERE mp.user_id = '$uid'
          AND mp.plan_date BETWEEN '$week_start' AND '$week_end'
        ORDER BY mp.plan_date ASC, FIELD(mp.meal_slot, 'lunch', 'dinner')
    ");
    $plan = [];
    while ($r = mysqli_fetch_assoc($plan_q)) {
        $plan[] = $r;
    }

    // --------------------------------------------------
    // Skip if there's genuinely nothing worth sending
    // --------------------------------------------------
    if (empty($expiring) && $saved == 0 && $wasted == 0 && empty($plan)) {
        $skipped++;
        continue;
    }

    // --------------------------------------------------
    // Build the email
    // --------------------------------------------------
    $safe_name = htmlspecialchars($uname);
    $subject_bits = [];
    if (!empty($expiring)) $subject_bits[] = count($expiring) . ' expiring';
    if ($saved > 0)        $subject_bits[] = '₱' . number_format($saved, 0) . ' saved';
    $subject = 'ShelfLife Weekly - ' . implode(' - ', $subject_bits ?: ['your pantry summary']);

    // --- Expiring section ---
    $expiring_html = '';
    if (!empty($expiring)) {
        $expiring_html .= "<h3 style='margin: 24px 0 12px; color: #1a3823; font-size: 15px;'>⏰ Expiring soon</h3>";
        $expiring_html .= "<table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='border-collapse: collapse;'>";
        foreach ($expiring as $e) {
            $days = (int)((strtotime($e['expiry_date']) - strtotime($today)) / 86400);
            $days_label = $days === 0 ? 'Today' : ($days === 1 ? 'Tomorrow' : $days . ' days');
            $loc = !empty($e['location']) ? " · " . htmlspecialchars($e['location']) : '';
            $expiring_html .= "
                <tr>
                    <td style='padding: 10px 12px; border-bottom: 1px solid #ebe7df;'>
                        <div style='font-weight: 600; color: #1a1f1c; font-size: 14px;'>" . htmlspecialchars($e['item_name']) . "</div>
                        <div style='font-size: 12px; color: #8a3730; font-weight: 700; margin-top: 2px;'>" . $days_label . $loc . "</div>
                    </td>
                </tr>";
        }
        $expiring_html .= "</table>";
    }

    // --- Money section ---
    $money_html = "
        <h3 style='margin: 24px 0 12px; color: #1a3823; font-size: 15px;'>💰 This month</h3>
        <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='border-collapse: collapse;'>
            <tr>
                <td style='padding: 8px 0;'><strong style='color: #2d5a3d;'>₱" . number_format($saved, 2) . "</strong> saved</td>
                <td style='padding: 8px 0; text-align: right; color: #6b7268; font-size: 13px;'>{$money['consumed_count']} item(s) eaten in time</td>
            </tr>
            <tr>
                <td style='padding: 8px 0;'><strong style='color: #b54a3f;'>₱" . number_format($wasted, 2) . "</strong> wasted</td>
                <td style='padding: 8px 0; text-align: right; color: #6b7268; font-size: 13px;'>{$money['wasted_count']} item(s) expired</td>
            </tr>
            <tr>
                <td colspan='2' style='padding: 12px 0 0; border-top: 1px solid #ebe7df;'>
                    <strong style='color: #1a1f1c; font-size: 14px;'>Efficiency: {$eff}%</strong>
                    <span style='color: #6b7268; font-size: 13px;'>of grocery value used</span>
                </td>
            </tr>
        </table>";

    // --- Meal plan section ---
    $plan_html = '';
    if (!empty($plan)) {
        // Group by date
        $by_date = [];
        foreach ($plan as $p) {
            $by_date[$p['plan_date']][] = $p;
        }
        $plan_html .= "<h3 style='margin: 24px 0 12px; color: #1a3823; font-size: 15px;'>🍽️ Your meal plan</h3>";
        $plan_html .= "<table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='border-collapse: collapse;'>";
        foreach ($by_date as $date => $items) {
            $day_label = date('D, M j', strtotime($date));
            $meals = [];
            foreach ($items as $m) {
                $meals[] = ucfirst($m['meal_slot']) . ': ' . htmlspecialchars($m['recipe_name']);
            }
            $plan_html .= "
                <tr>
                    <td style='padding: 8px 0; border-bottom: 1px solid #ebe7df;'>
                        <div style='font-weight: 700; color: #1a1f1c; font-size: 13px;'>{$day_label}</div>
                        <div style='font-size: 12.5px; color: #6b7268; margin-top: 2px;'>" . implode('<br>', $meals) . "</div>
                    </td>
                </tr>";
        }
        $plan_html .= "</table>";
    }

    // --- Full body ---
    $year = date('Y');
    $body_html = "
        <div style='font-family: sans-serif; max-width: 560px; margin: 0 auto; border: 1px solid #ebe7df; border-radius: 12px; overflow: hidden;'>
            <div style='background: #2d5a3d; padding: 24px; text-align: center;'>
                <h1 style='color: white; margin: 0; font-size: 20px; font-weight: 700;'>ShelfLife Weekly</h1>
                <div style='color: rgba(255,255,255,0.75); font-size: 12px; margin-top: 4px;'>" . date('l, F j, Y') . "</div>
            </div>
            <div style='padding: 28px 24px;'>
                <p style='color: #2a302c; font-size: 15px; margin: 0 0 4px;'>Hi <strong>{$safe_name}</strong>,</p>
                <p style='color: #6b7268; font-size: 14px; margin: 0 0 8px;'>Here's your pantry snapshot for the week ahead.</p>
                {$expiring_html}
                {$money_html}
                {$plan_html}
                <div style='margin-top: 28px; padding-top: 20px; border-top: 1px solid #ebe7df; text-align: center;'>
                    <a href='http://localhost/ShelfLife/dashboard.php' style='display: inline-block; background: #2d5a3d; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px;'>Open ShelfLife</a>
                </div>
            </div>
            <div style='background: #fbfaf7; padding: 16px; text-align: center; font-size: 11px; color: #8a9088;'>
                You're receiving this because email alerts are on.<br>
                Turn them off at Profile → Notifications.<br>
                <span style='color: #a8a399;'>If this landed in spam, please mark it as &quot;Not spam&quot; so future digests reach your inbox.</span><br>
                &copy; {$year} ShelfLife
            </div>
        </div>";

    // --------------------------------------------------
    // Send
    // --------------------------------------------------
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SHELFLIFE_SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SHELFLIFE_SMTP_USERNAME;
        $mail->Password   = SHELFLIFE_SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SHELFLIFE_SMTP_PORT;

        $mail->setFrom(SHELFLIFE_SMTP_USERNAME, SHELFLIFE_FROM_NAME);
        $mail->addAddress($email, $uname);

        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body_html;
        $mail->AltBody = "Hi $uname,\n\n"
            . "Here's your pantry snapshot for the week ahead.\n\n"
            . (!empty($expiring) ? "Expiring soon: " . count($expiring) . " items\n" : '')
            . "Saved this month: ₱" . number_format($saved, 2) . "\n"
            . "Wasted this month: ₱" . number_format($wasted, 2) . "\n"
            . "Efficiency: {$eff}%\n\n"
            . "Open ShelfLife: http://localhost/ShelfLife/dashboard.php";

        $mail->send();
        $sent++;
        echo "✅ Sent digest to: $email<br>";

        // Log to the shared notification_logs table
        $log_email = mysqli_real_escape_string($conn, $email);
        $log_item  = mysqli_real_escape_string($conn, $digest_label);
        mysqli_query($conn, "INSERT INTO notification_logs (user_id, email, item_name, status)
                             VALUES ($uid, '$log_email', '$log_item', 'sent')");

    } catch (Exception $e) {
        error_log("Weekly digest failed for $email: " . $mail->ErrorInfo);
        echo "❌ Failed for: $email — {$mail->ErrorInfo}<br>";

        $log_email = mysqli_real_escape_string($conn, $email);
        $log_item  = mysqli_real_escape_string($conn, $digest_label);
        mysqli_query($conn, "INSERT INTO notification_logs (user_id, email, item_name, status)
                             VALUES ($uid, '$log_email', '$log_item', 'failed')");
    }
}

echo "<hr>";
echo "<strong>Done.</strong> Sent: $sent. Skipped (nothing to report): $skipped.";