<?php
// email_helper.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer files
// (If using Composer, use: require 'vendor/autoload.php';)
require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

function send_email_notification($to_email, $to_name, $subject, $message_content, $debug = false) {
    $mail = new PHPMailer(true);

    try {
        if ($debug) {
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = 'html';
        }
        // --- SMTP SERVER CONFIGURATION ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';             // Your SMTP server (e.g. Gmail)
        $mail->SMTPAuth   = true;
        $mail->Username   = 'optitasking@gmail.com';       // Your SMTP email account
        $mail->Password   = 'czborkmnhmfparqu';    // Your SMTP App Password (not your main password)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // --- RECIPIENTS ---
        $mail->setFrom('optitasking@gmail.com', 'OptiTask');
        $mail->addAddress($to_email, $to_name);

        // --- EMAIL CONTENT & STYLING ---
        $mail->isHTML(true);
        $mail->Subject = $subject;

        // Premium HTML layout matching the soft pink theme of OptiTask
        $mail->Body = "
        <div style='font-family: sans-serif; background-color: #FFF5F7; padding: 40px 20px; border-radius: 24px; max-width: 600px; margin: 0 auto;'>
            <div style='background-color: #ffffff; padding: 40px; border-radius: 20px; box-shadow: 0 4px 15px rgba(255, 143, 171, 0.1); border: 1px solid #FFE5EC;'>
                <div style='text-align: center; margin-bottom: 24px;'>
                    <span style='background-color: #FF8FAB; color: white; padding: 12px 24px; border-radius: 12px; font-weight: bold; font-size: 20px; letter-spacing: 1px;'>OptiTask</span>
                </div>
                <h2 style='color: #1e293b; font-size: 22px; font-weight: 700; margin-bottom: 16px;'>Hello $to_name,</h2>
                <p style='color: #475569; font-size: 16px; line-height: 1.6;'>You have a new update regarding your tasks:</p>
                <div style='background-color: #FFF9FA; border-left: 4px solid #FB6F92; padding: 16px; margin: 20px 0; border-radius: 8px;'>
                    <p style='color: #db2777; font-size: 16px; font-weight: 600; margin: 0;'>$message_content</p>
                </div>
                <p style='color: #64748b; font-size: 14px;'>Please log in to your dashboard to review this update.</p>
                <div style='text-align: center; margin-top: 30px;'>
                    <a href='http://localhost/optitask/login.php' style='background-color: #FF8FAB; color: white; text-decoration: none; padding: 14px 28px; border-radius: 12px; font-weight: bold; font-size: 14px; display: inline-block; box-shadow: 0 4px 10px rgba(255, 143, 171, 0.3);'>Go to Dashboard</a>
                </div>
            </div>
            <div style='text-align: center; margin-top: 24px; color: #94a3b8; font-size: 12px;'>
                <p>This is an automated notification. Please do not reply directly to this email.</p>
            </div>
        </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email notification failed. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
