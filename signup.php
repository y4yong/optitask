<?php
require_once 'db_config.php';

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect and sanitize inputs
    $security_word = $_POST['security_word'] ?? '';
    $user_id = strtoupper(htmlspecialchars($_POST['worker_id'] ?? ''));
    $full_name = htmlspecialchars($_POST['full_name'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Determine the required Security Key based on the Worker ID prefix
    $prefix = substr($user_id, 0, 2);
    $required_key = "";
    $role = "";

    if ($prefix === 'AD') {
        $role = "Admin";
        $required_key = "ADMIN_BOSS"; 
    } elseif ($prefix === 'MG') {
        $role = "Manager";
        $required_key = "MGR_OPTI";   
    } else {
        $role = "Employee";
        $required_key = "STAFF_UKM"; 
    }

    // 1. SECURITY VALIDATION: Match Key to Role
    if ($security_word !== $required_key) {
        $message = "Access Denied: Invalid Security Word for the " . ucfirst($role) . " role.";
        $message_type = "error";
    } 
    // 2. DATA VALIDATION: Password Match
    elseif ($password !== $confirm_password) {
        $message = "Error: Password confirmation does not match.";
        $message_type = "error";
    } 
    else {
        // 3. DATABASE CHECK: Email uniqueness
        $check_email = "SELECT email FROM users WHERE email = ?";
        $stmt = $conn->prepare($check_email);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $message = "Error: This email is already registered.";
            $message_type = "error";
        } else {
            // 4. PROCESS: Hash Password and Save
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $insert_query = "INSERT INTO users (user_id, username, email, password, role, account_status) VALUES (?, ?, ?, ?, ?, 'Active')";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("sssss", $user_id, $full_name, $email, $hashed_password, $role);

            if ($stmt->execute()) {
                log_audit($conn, $user_id, 'REGISTER', "Registered new account as $role");
                $message = "Success: " . ucfirst($role) . " account created. You may now login.";
                $message_type = "success";
            } else {
                $message = "Critical Error: Database synchronization failed.";
                $message_type = "error";
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OptiTask - Registration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;600;700&display=swap" rel="stylesheet">
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
        .soft-pink-btn:hover { 
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
    </style>
</head>
<body class="pink-bg flex items-center justify-center min-h-screen p-6">

    <div class="bg-white p-10 rounded-[40px] shadow-2xl w-full max-w-md">
        <div class="flex flex-col items-center mb-8">
            <div class="w-16 h-16 bg-[#FFB3C6] rounded-2xl flex items-center justify-center shadow-md mb-4">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="4" y="4" width="7" height="7" rx="2" fill="white"/>
                    <rect x="4" y="13" width="7" height="7" rx="2" fill="white"/>
                    <rect x="13" y="4" width="7" height="7" rx="2" fill="white"/>
                    <rect x="13" y="13" width="7" height="7" rx="2" fill="#FF8FAB"/>
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 tracking-tight text-center">OptiTask</h1>
            <p class="text-gray-400 text-sm font-medium uppercase tracking-widest text-center">Sign Up</p>
        </div>

        <div class="flex bg-[#FDE2E4] p-1.5 rounded-2xl mb-8">
            <a href="login.php" class="w-1/2 text-center py-2.5 text-sm font-bold text-[#FF8FAB]">Login</a>
            <button class="w-1/2 text-center py-2.5 text-sm font-bold bg-white text-[#FB6F92] shadow-sm rounded-xl">Sign Up</button>
        </div>

        <?php if($message): ?>
            <div class="mb-6 p-4 rounded-2xl text-center text-sm font-semibold <?php echo $message_type == 'success' ? 'bg-green-50 text-green-500' : 'bg-red-50 text-red-400'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="signup.php" method="POST" class="space-y-5">
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">Worker ID</label>
                <input type="text" name="worker_id" required placeholder="e.g. EM001, MG042, AD001" 
                    class="w-full px-5 py-3 rounded-2xl bg-[#FBFBFC] input-box text-sm text-gray-600 outline-none">
                <div class="mt-2 flex justify-between px-1">
                    <span class="text-[10px] text-pink-400 font-bold tracking-tight">ID STARTS WITH EM, MG, OR AD</span>
                </div>
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">Security Key</label>
                <input type="password" name="security_word" required placeholder="*********" 
                    class="w-full px-5 py-3 rounded-2xl bg-[#FBFBFC] input-box text-sm text-gray-600 outline-none">
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">Full Name</label>
                <input type="text" name="full_name" required placeholder="Full Name" 
                    class="w-full px-5 py-3 rounded-2xl bg-[#FBFBFC] input-box text-sm text-gray-600 outline-none">
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">Email Address</label>
                <input type="email" name="email" required placeholder="name@company.com" 
                    class="w-full px-5 py-3 rounded-2xl bg-[#FBFBFC] input-box text-sm text-gray-600 outline-none">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">Password</label>
                    <div class="relative flex items-center bg-[#FBFBFC] rounded-2xl input-box">
                        <input type="password" id="password" name="password" required placeholder="••••••••" 
                            class="w-full px-5 py-3 rounded-2xl bg-transparent text-sm text-gray-600 outline-none">
                        <button type="button" onclick="togglePassword('password', 'eye-icon-1')" class="absolute right-4 text-pink-300 flex items-center">
                            <ion-icon id="eye-icon-1" name="eye-off-outline" class="text-xl"></ion-icon>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">Confirm</label>
                    <div class="relative flex items-center bg-[#FBFBFC] rounded-2xl input-box">
                        <input type="password" id="confirm_password" name="confirm_password" required placeholder="••••••••" 
                            class="w-full px-5 py-3 rounded-2xl bg-transparent text-sm text-gray-600 outline-none">
                        <button type="button" onclick="togglePassword('confirm_password', 'eye-icon-2')" class="absolute right-4 text-pink-300 flex items-center">
                            <ion-icon id="eye-icon-2" name="eye-off-outline" class="text-xl"></ion-icon>
                        </button>
                    </div>
                </div>
            </div>

            <button type="submit" class="w-full soft-pink-btn text-white py-4 rounded-2xl font-bold text-md mt-4">
                Register Account
            </button>
        </form>
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
    </script>
</body>
</html>