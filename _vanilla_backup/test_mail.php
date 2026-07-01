<?php
// test_mail.php
// OptiTask - Email Diagnostic and Setup Assistant
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Check if we need to load existing credentials
$host = 'smtp.gmail.com';
$username = '';
$password = '';
$port = 587;
$encryption = 'tls';

if (file_exists('email_helper.php')) {
    $helper_content = file_get_contents('email_helper.php');
    if (preg_match("/Host\s*=\s*'([^']+)'/", $helper_content, $matches)) $host = $matches[1];
    if (preg_match("/Username\s*=\s*'([^']+)'/", $helper_content, $matches)) $username = $matches[1];
    if (preg_match("/Password\s*=\s*'([^']+)'/", $helper_content, $matches)) $password = $matches[1];
    if (preg_match("/Port\s*=\s*(\d+)/", $helper_content, $matches)) $port = intval($matches[1]);
    if (preg_match("/SMTPSecure\s*=\s*PHPMailer::(ENCRYPTION_[A-Z]+)/", $helper_content, $matches)) {
        $encryption = ($matches[1] === 'ENCRYPTION_SMTPS') ? 'ssl' : 'tls';
    }
}

// ---------------- AJAX HANDLER: TEST CONNECTION ----------------
if (isset($_GET['action']) && $_GET['action'] === 'test') {
    header('Content-Type: application/json');
    
    $test_host = $_POST['host'] ?? '';
    $test_user = $_POST['username'] ?? '';
    $test_pass = $_POST['password'] ?? '';
    $test_port = intval($_POST['port'] ?? 587);
    $test_enc  = $_POST['encryption'] ?? 'tls';
    $receiver  = $_POST['receiver'] ?? '';

    if (empty($test_user) || empty($test_pass) || empty($receiver)) {
        echo json_encode(['success' => false, 'error' => 'Please fill in SMTP Username, Password, and Receiver Email.']);
        exit();
    }

    // Load PHPMailer files manually
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';

    $mail = new PHPMailer(true);
    
    // Capture debug output
    $debugOutput = "";
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = function($str, $level) use (&$debugOutput) {
        $debugOutput .= htmlspecialchars($str) . "\n";
    };

    try {
        $mail->isSMTP();
        $mail->Host       = $test_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $test_user;
        $mail->Password   = $test_pass;
        $mail->SMTPSecure = ($test_enc === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $test_port;

        $mail->setFrom($test_user, 'OptiTask Diagnostics');
        $mail->addAddress($receiver, 'Test Recipient');

        $mail->isHTML(true);
        $mail->Subject = 'OptiTask - Diagnostic Test Mail ';
        $mail->Body    = '<h3>Lucky One! Connection Successful!</h3><p>This email confirms your OptiTask SMTP setup is working beautifully.</p>';

        $mail->send();
        
        echo json_encode([
            'success' => true,
            'log' => $debugOutput,
            'message' => 'The connection succeeded and test email was sent!'
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'log' => $debugOutput,
            'error' => $mail->ErrorInfo ?: $e->getMessage()
        ]);
    }
    exit();
}

// ---------------- AJAX HANDLER: SAVE CREDENTIALS ----------------
if (isset($_GET['action']) && $_GET['action'] === 'save') {
    header('Content-Type: application/json');

    $save_host = $_POST['host'] ?? 'smtp.gmail.com';
    $save_user = $_POST['username'] ?? '';
    $save_pass = $_POST['password'] ?? '';
    $save_port = intval($_POST['port'] ?? 587);
    $save_enc  = $_POST['encryption'] ?? 'tls';

    if (empty($save_user) || empty($save_pass)) {
        echo json_encode(['success' => false, 'message' => 'Cannot save empty credentials.']);
        exit();
    }

    $encryption_class = ($save_enc === 'ssl') ? 'ENCRYPTION_SMTPS' : 'ENCRYPTION_STARTTLS';

    // Generate the complete content of email_helper.php
    $helper_code = "<?php
// email_helper.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer files
require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

function send_email_notification(\$to_email, \$to_name, \$subject, \$message_content, \$debug = false) {
    \$mail = new PHPMailer(true);

    try {
        if (\$debug) {
            \$mail->SMTPDebug = 2;
            \$mail->Debugoutput = 'html';
        }
        // --- SMTP SERVER CONFIGURATION ---
        \$mail->isSMTP();
        \$mail->Host       = '{$save_host}';
        \$mail->SMTPAuth   = true;
        \$mail->Username   = '{$save_user}';
        \$mail->Password   = '{$save_pass}';
        \$mail->SMTPSecure = PHPMailer::{$encryption_class};
        \$mail->Port       = {$save_port};

        // --- RECIPIENTS ---
        \$mail->setFrom('{$save_user}', 'OptiTask');
        \$mail->addAddress(\$to_email, \$to_name);

        // --- EMAIL CONTENT & STYLING ---
        \$mail->isHTML(true);
        \$mail->Subject = \$subject;

        // Premium HTML layout matching the soft pink theme of OptiTask
        \$mail->Body = \"
        <div style='font-family: sans-serif; background-color: #FFF5F7; padding: 40px 20px; border-radius: 24px; max-width: 600px; margin: 0 auto;'>
            <div style='background-color: #ffffff; padding: 40px; border-radius: 20px; box-shadow: 0 4px 15px rgba(255, 143, 171, 0.1); border: 1px solid #FFE5EC;'>
                <div style='text-align: center; margin-bottom: 24px;'>
                    <span style='background-color: #FF8FAB; color: white; padding: 12px 24px; border-radius: 12px; font-weight: bold; font-size: 20px; letter-spacing: 1px;'>OptiTask</span>
                </div>
                <h2 style='color: #1e293b; font-size: 22px; font-weight: 700; margin-bottom: 16px;'>Hello \\\$to_name,</h2>
                <p style='color: #475569; font-size: 16px; line-height: 1.6;'>You have a new update regarding your tasks:</p>
                <div style='background-color: #FFF9FA; border-left: 4px solid #FB6F92; padding: 16px; margin: 20px 0; border-radius: 8px;'>
                    <p style='color: #db2777; font-size: 16px; font-weight: 600; margin: 0;'>\\\$message_content</p>
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
        \";

        \$mail->send();
        return true;
    } catch (Exception \$e) {
        error_log(\"Email notification failed. Mailer Error: {\$mail->ErrorInfo}\");
        return false;
    }
}
";

    if (file_put_contents('email_helper.php', $helper_code)) {
        echo json_encode(['success' => true, 'message' => 'Successfully saved credentials directly to email_helper.php!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to write to email_helper.php. Check permissions.']);
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OptiTask - Mail Diagnostics</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;600;700;800&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <style>
        body { font-family: 'Quicksand', sans-serif; }
        .pink-bg { background-color: #FFF5F7; }
        .soft-pink-btn { 
            background: #FF8FAB; 
            box-shadow: 0 4px 15px rgba(255, 143, 171, 0.3);
            transition: all 0.3s ease;
        }
        .soft-pink-btn:hover:not(:disabled) { 
            background: #FB6F92; 
            transform: scale(1.02);
        }
        .input-box {
            border: 2px solid #FFE5EC;
            transition: all 0.3s ease;
        }
        .input-box:focus-within {
            border-color: #FF8FAB;
            background-color: #FFF;
        }
        .console-font { font-family: 'Fira Code', monospace; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: #FFD1DC; border-radius: 10px; }
    </style>
</head>
<body class="pink-bg flex items-center justify-center min-h-screen p-6">

    <div class="bg-white p-8 md:p-10 rounded-[40px] shadow-2xl w-full max-w-2xl">
        
        <!-- Header -->
        <div class="flex items-center gap-4 mb-8">
            <div class="w-12 h-12 bg-[#FFB3C6] rounded-2xl flex items-center justify-center shadow-md text-white">
                <ion-icon name="mail-open-outline" class="text-2xl"></ion-icon>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-800 tracking-tight">Mail Setup Wizard</h1>
                <p class="text-pink-400 text-xs font-bold uppercase tracking-wider">OptiTask Diagnostics & Configuration</p>
            </div>
        </div>

        <form id="smtpForm" class="space-y-6">
            
            <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 flex gap-3 text-sm text-amber-800">
                <div class="text-xl shrink-0"><ion-icon name="information-circle-outline"></ion-icon></div>
                <div>
                    <span class="font-bold">Gmail User Notice:</span> Make sure you are using a 16-character <strong>App Password</strong> generated in Google Account settings. Google rejects regular account passwords for security reasons. 
                    <a href="https://myaccount.google.com/apppasswords" target="_blank" class="text-[#FB6F92] hover:underline font-bold inline-block ml-1">Get App Password <ion-icon name="open-outline" class="align-middle"></ion-icon></a>
                </div>
            </div>

            <!-- Credentials Section -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">Gmail SMTP Username</label>
                    <input type="email" name="username" required value="<?= htmlspecialchars($username) ?>" placeholder="nuraleeyaazman677@gmail.com" 
                        class="w-full px-4 py-3 rounded-2xl bg-[#FBFBFC] input-box text-sm text-gray-600 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">App Password</label>
                    <div class="relative flex items-center bg-[#FBFBFC] rounded-2xl input-box">
                        <input type="password" id="password" name="password" required value="<?= htmlspecialchars($password) ?>" placeholder="Enter 16-character code" 
                            class="w-full px-4 py-3 rounded-2xl bg-transparent text-sm text-gray-600 outline-none">
                        <button type="button" onclick="togglePassword('password', 'eye-icon-1')" class="absolute right-4 text-pink-300 flex items-center">
                            <ion-icon id="eye-icon-1" name="eye-off-outline" class="text-xl"></ion-icon>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Advanced Config -->
            <div class="border-t border-pink-50 pt-4">
                <button type="button" onclick="toggleAdvanced()" class="text-xs font-bold text-[#FB6F92] flex items-center gap-1">
                    <ion-icon id="advanced-icon" name="chevron-forward-outline"></ion-icon> Advanced Server Settings
                </button>
                
                <div id="advanced-section" class="hidden grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-2 ml-1">SMTP Host</label>
                        <input type="text" name="host" value="<?= htmlspecialchars($host) ?>" 
                            class="w-full px-4 py-3 rounded-2xl bg-[#FBFBFC] input-box text-xs text-gray-600 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-2 ml-1">SMTP Encryption</label>
                        <select name="encryption" onchange="autoPort()" class="w-full px-4 py-3 rounded-2xl bg-[#FBFBFC] input-box text-xs text-gray-600 outline-none">
                            <option value="tls" <?= $encryption === 'tls' ? 'selected' : '' ?>>STARTTLS (Recommended)</option>
                            <option value="ssl" <?= $encryption === 'ssl' ? 'selected' : '' ?>>SSL / Implicit TLS</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-2 ml-1">SMTP Port</label>
                        <input type="number" id="port" name="port" value="<?= $port ?>" 
                            class="w-full px-4 py-3 rounded-2xl bg-[#FBFBFC] input-box text-xs text-gray-600 outline-none">
                    </div>
                </div>
            </div>

            <!-- Testing Section -->
            <div class="border-t border-pink-50 pt-4">
                <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">Receiver Address (Send Test Email To)</label>
                <div class="flex gap-2">
                    <input type="email" name="receiver" required value="ezzateadleen@gmail.com" placeholder="ezzateadleen@gmail.com" 
                        class="flex-1 px-4 py-3 rounded-2xl bg-[#FBFBFC] input-box text-sm text-gray-600 outline-none">
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 pt-2">
                <button type="button" id="testBtn" onclick="runDiagnostics()" class="flex-1 soft-pink-btn text-white py-4 rounded-2xl font-bold text-sm flex items-center justify-center gap-2">
                    <ion-icon name="play-outline" class="text-lg"></ion-icon> Run Diagnostic Test
                </button>
                <button type="button" id="saveBtn" disabled onclick="saveCredentials()" class="flex-1 bg-gray-100 text-gray-400 py-4 rounded-2xl font-bold text-sm flex items-center justify-center gap-2 cursor-not-allowed">
                    <ion-icon name="save-outline" class="text-lg"></ion-icon> Save to system
                </button>
            </div>
        </form>

        <!-- Live Diagnostic Console Logs -->
        <div class="mt-8 space-y-2">
            <div class="flex justify-between items-center px-1">
                <label class="block text-xs font-black text-gray-400 uppercase tracking-widest">Diagnostic logs</label>
                <span id="testBadge" class="hidden px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase"></span>
            </div>
            <div id="consoleBox" class="bg-slate-900 text-slate-300 rounded-2xl p-4 console-font text-xs overflow-y-auto max-h-64 border border-slate-800 whitespace-pre-wrap">Console idle... Click 'Run Diagnostic Test' to begin authentication handshake.</div>
        </div>

    </div>

    <script>
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const eyeIcon = document.getElementById(iconId);
            
            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                eyeIcon.setAttribute("name", "eye-outline");
            } else {
                passwordInput.type = "password";
                eyeIcon.setAttribute("name", "eye-off-outline");
            }
        }

        function toggleAdvanced() {
            const section = document.getElementById('advanced-section');
            const icon = document.getElementById('advanced-icon');
            if (section.classList.contains('hidden')) {
                section.classList.remove('hidden');
                icon.setAttribute("name", "chevron-down-outline");
            } else {
                section.classList.add('hidden');
                icon.setAttribute("name", "chevron-forward-outline");
            }
        }

        function autoPort() {
            const enc = document.getElementsByName('encryption')[0].value;
            document.getElementById('port').value = (enc === 'ssl') ? 465 : 587;
        }

        function runDiagnostics() {
            const form = document.getElementById('smtpForm');
            const formData = new FormData(form);
            const testBtn = document.getElementById('testBtn');
            const saveBtn = document.getElementById('saveBtn');
            const consoleBox = document.getElementById('consoleBox');
            const testBadge = document.getElementById('testBadge');

            testBtn.disabled = true;
            testBtn.innerHTML = '<ion-icon name="sync-outline" class="animate-spin text-lg"></ion-icon> Testing connection...';
            consoleBox.innerHTML = "Initializing connection to " + formData.get('host') + "...\nEstablishing secure connection socket...";
            
            testBadge.className = "px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-100 text-amber-700";
            testBadge.innerText = "Running";
            testBadge.classList.remove('hidden');

            fetch('test_mail.php?action=test', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                testBtn.disabled = false;
                testBtn.innerHTML = '<ion-icon name="play-outline" class="text-lg"></ion-icon> Run Diagnostic Test';
                
                consoleBox.innerHTML = data.log || "No logs returned from server.";
                
                if (data.success) {
                    testBadge.className = "px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-green-100 text-green-700";
                    testBadge.innerText = "Success";
                    
                    // Enable save button
                    saveBtn.disabled = false;
                    saveBtn.className = "flex-1 soft-pink-btn text-white py-4 rounded-2xl font-bold text-sm flex items-center justify-center gap-2";
                    saveBtn.classList.remove('cursor-not-allowed');
                } else {
                    testBadge.className = "px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-red-100 text-red-700";
                    testBadge.innerText = "Failed";
                    
                    // Show friendly prompt at the top of logs
                    consoleBox.innerHTML = "=== ERROR REPORT ===\n" + (data.error || "Connection timed out.") + "\n\n=== SMTP HANDSHAKE LOGS ===\n" + consoleBox.innerHTML;
                    
                    // Disable save button
                    saveBtn.disabled = true;
                    saveBtn.className = "flex-1 bg-gray-100 text-gray-400 py-4 rounded-2xl font-bold text-sm flex items-center justify-center gap-2 cursor-not-allowed";
                    saveBtn.classList.add('cursor-not-allowed');
                }
                consoleBox.scrollTop = consoleBox.scrollHeight;
            })
            .catch(err => {
                testBtn.disabled = false;
                testBtn.innerHTML = '<ion-icon name="play-outline" class="text-lg"></ion-icon> Run Diagnostic Test';
                testBadge.className = "px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-red-100 text-red-700";
                testBadge.innerText = "Network Error";
                consoleBox.innerHTML = "Ajax network transmission error:\n" + err;
            });
        }

        function saveCredentials() {
            const form = document.getElementById('smtpForm');
            const formData = new FormData(form);
            const saveBtn = document.getElementById('saveBtn');

            saveBtn.disabled = true;
            saveBtn.innerHTML = '<ion-icon name="sync-outline" class="animate-spin text-lg"></ion-icon> Saving...';

            fetch('test_mail.php?action=save', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                saveBtn.innerHTML = '<ion-icon name="checkmark-circle-outline" class="text-lg"></ion-icon> Saved!';
                alert(data.message);
                setTimeout(() => {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<ion-icon name="save-outline" class="text-lg"></ion-icon> Save to system';
                }, 2000);
            })
            .catch(err => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<ion-icon name="save-outline" class="text-lg"></ion-icon> Save to system';
                alert("Error saving file: " + err);
            });
        }
    </script>
</body>
</html>
