<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';
require_once 'email_config.php';

function generate_verification_code() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function send_verification_email($to_email, $to_name, $code) {
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
        $mail->addAddress($to_email, $to_name);

        $mail->isHTML(true);
        $mail->Subject = 'ShelfLife: Verify your email';

        $safe_name = htmlspecialchars($to_name);
        $year = date('Y');

        $mail->Body = "
            <div style='font-family: sans-serif; max-width: 480px; margin: 0 auto; border: 1px solid #ebe7df; border-radius: 12px; overflow: hidden;'>
                <div style='background: #2d5a3d; padding: 24px; text-align: center;'>
                    <h1 style='color: white; margin: 0; font-size: 20px; font-weight: 700;'>Welcome to ShelfLife</h1>
                </div>
                <div style='padding: 32px 24px;'>
                    <p style='color: #2a302c; font-size: 14px; margin: 0 0 12px;'>Hi <strong>{$safe_name}</strong>,</p>
                    <p style='color: #6b7268; font-size: 14px; margin: 0 0 20px;'>Use this 6-digit code to verify your email address:</p>
                    <div style='background: #f2f6f1; border: 2px dashed #2d5a3d; border-radius: 10px; padding: 22px; text-align: center; margin: 0 0 24px;'>
                        <span style='font-size: 32px; font-weight: 700; letter-spacing: 8px; color: #1a3823; font-family: monospace;'>{$code}</span>
                    </div>
                    <p style='color: #6b7268; font-size: 13px; margin: 0 0 8px;'>This code expires in <strong>15 minutes</strong>.</p>
                    <p style='color: #6b7268; font-size: 13px; margin: 0;'>If you didn't create this account, you can safely ignore this email.</p>
                </div>
                <div style='background: #fbfaf7; padding: 16px; text-align: center; font-size: 11px; color: #8a9088;'>
                    &copy; {$year} ShelfLife
                </div>
            </div>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Verification email failed for {$to_email}: " . $mail->ErrorInfo);
        return false;
    }
}

function send_password_reset_email($to_email, $to_name, $code) {
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
        $mail->addAddress($to_email, $to_name);

        $mail->isHTML(true);
        $mail->Subject = 'ShelfLife: Password reset code';

        $safe_name = htmlspecialchars($to_name);
        $year = date('Y');

        $mail->Body = "
            <div style='font-family: sans-serif; max-width: 480px; margin: 0 auto; border: 1px solid #ebe7df; border-radius: 12px; overflow: hidden;'>
                <div style='background: #2d5a3d; padding: 24px; text-align: center;'>
                    <h1 style='color: white; margin: 0; font-size: 20px; font-weight: 700;'>Password Reset</h1>
                </div>
                <div style='padding: 32px 24px;'>
                    <p style='color: #2a302c; font-size: 14px; margin: 0 0 12px;'>Hi <strong>{$safe_name}</strong>,</p>
                    <p style='color: #6b7268; font-size: 14px; margin: 0 0 20px;'>We received a request to reset your ShelfLife password. Use this code to continue:</p>
                    <div style='background: #f2f6f1; border: 2px dashed #2d5a3d; border-radius: 10px; padding: 22px; text-align: center; margin: 0 0 24px;'>
                        <span style='font-size: 32px; font-weight: 700; letter-spacing: 8px; color: #1a3823; font-family: monospace;'>{$code}</span>
                    </div>
                    <p style='color: #6b7268; font-size: 13px; margin: 0 0 8px;'>This code expires in <strong>15 minutes</strong>.</p>
                    <p style='color: #6b7268; font-size: 13px; margin: 0;'>If you didn't request this, you can safely ignore this email. Your password will not be changed.</p>
                </div>
                <div style='background: #fbfaf7; padding: 16px; text-align: center; font-size: 11px; color: #8a9088;'>
                    &copy; {$year} ShelfLife
                </div>
            </div>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Reset email failed for {$to_email}: " . $mail->ErrorInfo);
        return false;
    }
}

?>