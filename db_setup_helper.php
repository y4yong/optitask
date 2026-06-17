<?php
/**
 * OptiTask System - Database Setup & Migration Helper
 * Theme: Ultra-Pink Installer Edition
 */

// 1. Parse credentials from db_config.php to maintain a single source of truth
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "optitask";

if (file_exists('db_config.php')) {
    $config_content = file_get_contents('db_config.php');
    if (preg_match('/\$servername\s*=\s*["\'](.*?)["\'];/', $config_content, $matches)) {
        $servername = $matches[1];
    }
    if (preg_match('/\$username\s*=\s*["\'](.*?)["\'];/', $config_content, $matches)) {
        $username = $matches[1];
    }
    if (preg_match('/\$password\s*=\s*["\'](.*?)["\'];/', $config_content, $matches)) {
        $password = $matches[1];
    }
    if (preg_match('/\$dbname\s*=\s*["\'](.*?)["\'];/', $config_content, $matches)) {
        $dbname = $matches[1];
    }
}

// 2. Initial MySQL Connection Test (without selecting a DB)
$mysql_connected = false;
$mysql_error = "";
$conn_temp = @new mysqli($servername, $username, $password);
if ($conn_temp->connect_error) {
    $mysql_error = $conn_temp->connect_error;
} else {
    $mysql_connected = true;
}

// Check if database exists
$db_exists = false;
if ($mysql_connected) {
    $result = $conn_temp->query("SHOW DATABASES LIKE '$dbname'");
    if ($result && $result->num_rows > 0) {
        $db_exists = true;
    }
}

// Check tables status
$tables_status = [];
$required_tables = [
    'departments',
    'users',
    'tasks',
    'notifications',
    'audit_logs',
    'skills',
    'employee_skills'
];

if ($mysql_connected && $db_exists) {
    $conn_temp->select_db($dbname);
    foreach ($required_tables as $table) {
        $check_table = $conn_temp->query("SHOW TABLES LIKE '$table'");
        $tables_status[$table] = ($check_table && $check_table->num_rows > 0);
    }
} else {
    foreach ($required_tables as $table) {
        $tables_status[$table] = false;
    }
}

// Handle Database Action
$action_output = [];
$action_success = null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['initialize'])) {
    if (!$mysql_connected) {
        $action_success = false;
        $action_output[] = "Error: Cannot connect to MySQL server. Please verify credentials.";
    } else {
        $action_success = true;
        
        // A. Create Database
        if (!$db_exists) {
            if ($conn_temp->query("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
                $action_output[] = "Database `$dbname` created successfully.";
                $db_exists = true;
                $conn_temp->select_db($dbname);
            } else {
                $action_success = false;
                $action_output[] = "Error creating database: " . $conn_temp->error;
            }
        } else {
            $action_output[] = "Database `$dbname` already exists.";
            $conn_temp->select_db($dbname);
        }

        if ($action_success) {
            // B. Create Tables
            $queries = [
                'departments' => "CREATE TABLE IF NOT EXISTS departments (
                    dept_id INT AUTO_INCREMENT PRIMARY KEY,
                    dept_name VARCHAR(100) NOT NULL UNIQUE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                'users' => "CREATE TABLE IF NOT EXISTS users (
                    user_id VARCHAR(50) PRIMARY KEY,
                    username VARCHAR(100) NOT NULL,
                    email VARCHAR(100) NOT NULL UNIQUE,
                    password VARCHAR(255) NOT NULL,
                    role VARCHAR(50) NOT NULL,
                    account_status VARCHAR(20) DEFAULT 'Active',
                    suspension_reason VARCHAR(255) DEFAULT NULL,
                    dept_id INT DEFAULT NULL,
                    FOREIGN KEY (dept_id) REFERENCES departments(dept_id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                'tasks' => "CREATE TABLE IF NOT EXISTS tasks (
                    task_id VARCHAR(50) PRIMARY KEY,
                    task_title VARCHAR(255) NOT NULL,
                    description TEXT,
                    start_date DATE,
                    due_date DATE,
                    task_status VARCHAR(50) DEFAULT 'To-Do',
                    priority VARCHAR(20) DEFAULT 'Medium',
                    employee_id VARCHAR(50) NOT NULL,
                    manager_notes TEXT,
                    task_type VARCHAR(50) DEFAULT 'Personal',
                    task_file VARCHAR(255) DEFAULT NULL,
                    submission_file VARCHAR(255) DEFAULT NULL,
                    evidence_link VARCHAR(255) DEFAULT NULL,
                    FOREIGN KEY (employee_id) REFERENCES users(user_id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                'notifications' => "CREATE TABLE IF NOT EXISTS notifications (
                    notification_id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id VARCHAR(50) NOT NULL,
                    notification_type VARCHAR(50),
                    message TEXT,
                    status VARCHAR(20) DEFAULT 'unread',
                    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                'audit_logs' => "CREATE TABLE IF NOT EXISTS audit_logs (
                    log_id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id VARCHAR(50) DEFAULT 'SYSTEM',
                    action VARCHAR(50),
                    details TEXT,
                    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                'skills' => "CREATE TABLE IF NOT EXISTS skills (
                    skill_id INT AUTO_INCREMENT PRIMARY KEY,
                    skill_name VARCHAR(100) NOT NULL UNIQUE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                'employee_skills' => "CREATE TABLE IF NOT EXISTS employee_skills (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id VARCHAR(50) NOT NULL,
                    skill_id INT NOT NULL,
                    proficiency_level INT NOT NULL CHECK(proficiency_level BETWEEN 1 AND 5),
                    UNIQUE KEY emp_skill_unique (user_id, skill_id),
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                    FOREIGN KEY (skill_id) REFERENCES skills(skill_id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            ];

            foreach ($queries as $table_name => $sql) {
                if ($conn_temp->query($sql)) {
                    $action_output[] = "Table `$table_name` initialized successfully.";
                    $tables_status[$table_name] = true;
                } else {
                    $action_success = false;
                    $action_output[] = "Error creating table `$table_name`: " . $conn_temp->error;
                }
            }
        }

        if ($action_success) {
            // C. Seed Initial Data
            
            // 1. Departments
            $conn_temp->query("INSERT IGNORE INTO departments (dept_id, dept_name) VALUES 
                (1, 'IT Department'),
                (2, 'Human Resources'),
                (3, 'Marketing'),
                (4, 'Finance')");
            $action_output[] = "Seeded default departments.";

            // 2. Users (Admin, Manager, Employee)
            $admin_pw = password_hash('admin123', PASSWORD_DEFAULT);
            $manager_pw = password_hash('manager123', PASSWORD_DEFAULT);
            $employee_pw = password_hash('employee123', PASSWORD_DEFAULT);

            $stmt_user = $conn_temp->prepare("INSERT IGNORE INTO users (user_id, username, email, password, role, account_status, dept_id) VALUES (?, ?, ?, ?, ?, 'Active', 1)");
            
            // Seed Admin
            $role_admin = 'Admin'; $uid_admin = 'AD001'; $u_admin = 'System Admin'; $e_admin = 'admin@optitask.com';
            $stmt_user->bind_param("sssss", $uid_admin, $u_admin, $e_admin, $admin_pw, $role_admin);
            $stmt_user->execute();

            // Seed Manager
            $role_mgr = 'Manager'; $uid_mgr = 'MG001'; $u_mgr = 'Manager Jane'; $e_mgr = 'manager@optitask.com';
            $stmt_user->bind_param("sssss", $uid_mgr, $u_mgr, $e_mgr, $manager_pw, $role_mgr);
            $stmt_user->execute();

            // Seed Employee
            $role_emp = 'Employee'; $uid_emp = 'EM001'; $u_emp = 'Employee John'; $e_emp = 'employee@optitask.com';
            $stmt_user->bind_param("sssss", $uid_emp, $u_emp, $e_emp, $employee_pw, $role_emp);
            $stmt_user->execute();
            
            $stmt_user->close();
            $action_output[] = "Seeded standard test accounts (AD001, MG001, EM001).";

            // 3. Skills Seeding
            $dummy_skills = [
                'PHP Development', 'React Frontend', 'UI/UX Design', 
                'Marketing Strategy', 'Data Analysis', 'Project Management',
                'Customer Support', 'SEO Optimization', 'Database Administration'
            ];
            foreach ($dummy_skills as $skill) {
                $stmt = $conn_temp->prepare("INSERT IGNORE INTO skills (skill_name) VALUES (?)");
                $stmt->bind_param("s", $skill);
                $stmt->execute();
                $stmt->close();
            }
            $action_output[] = "Seeded default skills list.";

            // 4. Employee Skills Link
            $conn_temp->query("INSERT IGNORE INTO employee_skills (user_id, skill_id, proficiency_level) VALUES ('EM001', 1, 4), ('EM001', 9, 3)");
            $action_output[] = "Linked test skills to employee EM001.";
            
            $action_output[] = "All setup steps completed successfully!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OptiTask | Database Setup Helper</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Quicksand', sans-serif; background-color: #FFF5F7; }
        .pink-gradient { background: linear-gradient(135deg, #FB6F92 0%, #FFB3C6 100%); }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-6">

    <div class="bg-white p-8 md:p-12 rounded-[40px] shadow-2xl w-full max-w-2xl border border-pink-50 my-10">
        <!-- Header -->
        <div class="flex flex-col items-center mb-8">
            <div class="w-16 h-16 pink-gradient rounded-2xl flex items-center justify-center shadow-lg shadow-pink-200 mb-4 text-white">
                <i data-lucide="database" class="w-8 h-8"></i>
            </div>
            <h1 class="text-3xl font-extrabold text-gray-800 tracking-tight text-center">OptiTask Database Installer</h1>
            <p class="text-gray-400 text-xs font-bold uppercase tracking-widest text-center mt-1">Transitioning to Laragon</p>
        </div>

        <!-- Connection Settings Summary -->
        <div class="bg-pink-50/50 border border-pink-100 rounded-3xl p-6 mb-8">
            <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wider mb-4 flex items-center gap-2">
                <i data-lucide="settings" class="w-4 h-4 text-[#FB6F92]"></i> Connection Configuration (from db_config.php)
            </h3>
            <div class="grid grid-cols-2 gap-4 text-xs font-bold text-gray-600">
                <div class="bg-white p-4 rounded-2xl border border-pink-50">
                    <span class="text-[10px] text-pink-400 block mb-1">HOST</span>
                    <span class="text-sm"><?= htmlspecialchars($servername) ?></span>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-pink-50">
                    <span class="text-[10px] text-pink-400 block mb-1">DATABASE NAME</span>
                    <span class="text-sm text-pink-600"><?= htmlspecialchars($dbname) ?></span>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-pink-50">
                    <span class="text-[10px] text-pink-400 block mb-1">USERNAME</span>
                    <span class="text-sm"><?= htmlspecialchars($username) ?></span>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-pink-50">
                    <span class="text-[10px] text-pink-400 block mb-1">PASSWORD</span>
                    <span class="text-sm italic text-gray-400"><?= empty($password) ? 'None (Empty Password)' : '********' ?></span>
                </div>
            </div>
        </div>

        <!-- Status Checklist -->
        <div class="space-y-4 mb-8">
            <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wider mb-2 flex items-center gap-2">
                <i data-lucide="check-square" class="w-4 h-4 text-[#FB6F92]"></i> System Checks
            </h3>
            
            <!-- MySQL Connection Status -->
            <div class="flex items-center justify-between p-4 bg-[#FBFBFC] rounded-2xl border border-gray-100">
                <div class="flex items-center gap-3">
                    <i data-lucide="server" class="w-5 h-5 text-gray-400"></i>
                    <div>
                        <span class="text-sm font-bold text-gray-700">MySQL Server Connection</span>
                        <?php if(!$mysql_connected && !empty($mysql_error)): ?>
                            <span class="block text-[10px] text-red-500 font-medium"><?= htmlspecialchars($mysql_error) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($mysql_connected): ?>
                    <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-[10px] font-black uppercase">CONNECTED</span>
                <?php else: ?>
                    <span class="bg-red-100 text-red-600 px-3 py-1 rounded-full text-[10px] font-black uppercase">FAILED</span>
                <?php endif; ?>
            </div>

            <!-- Database existence Status -->
            <div class="flex items-center justify-between p-4 bg-[#FBFBFC] rounded-2xl border border-gray-100">
                <div class="flex items-center gap-3">
                    <i data-lucide="database" class="w-5 h-5 text-gray-400"></i>
                    <span class="text-sm font-bold text-gray-700">Database `<?= htmlspecialchars($dbname) ?>`</span>
                </div>
                <?php if ($db_exists): ?>
                    <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-[10px] font-black uppercase">FOUND</span>
                <?php else: ?>
                    <span class="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-[10px] font-black uppercase">MISSING</span>
                <?php endif; ?>
            </div>

            <!-- Tables check -->
            <div class="p-5 bg-[#FBFBFC] rounded-3xl border border-gray-100">
                <span class="text-sm font-bold text-gray-700 block mb-3">Database Tables Setup</span>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                    <?php foreach ($tables_status as $tbl => $exists): ?>
                        <div class="flex items-center gap-2 p-2 rounded-xl bg-white border border-gray-50 text-xs font-semibold">
                            <?php if ($exists): ?>
                                <i data-lucide="check" class="w-4 h-4 text-green-500 shrink-0"></i>
                                <span class="text-gray-700 truncate"><?= $tbl ?></span>
                            <?php else: ?>
                                <i data-lucide="x" class="w-4 h-4 text-red-400 shrink-0"></i>
                                <span class="text-gray-400 truncate"><?= $tbl ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Action / Console Output -->
        <?php if (!empty($action_output)): ?>
            <div class="mb-8 p-6 bg-gray-900 rounded-3xl text-left border border-gray-800">
                <p class="text-[10px] font-black text-pink-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                    <i data-lucide="terminal" class="w-3.5 h-3.5"></i> Execution Logs
                </p>
                <div class="font-mono text-xs text-green-400 space-y-1 max-h-48 overflow-y-auto pr-2">
                    <?php foreach ($action_output as $line): ?>
                        <p class="leading-relaxed">> <?= htmlspecialchars($line) ?></p>
                    <?php endforeach; ?>
                </div>

                <?php if ($action_success): ?>
                    <div class="mt-6 p-4 bg-green-950/50 border border-green-800 rounded-2xl">
                        <span class="text-xs font-black text-green-400 uppercase block tracking-wider mb-2">Default Login Accounts:</span>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs font-mono text-white">
                            <div class="bg-black/20 p-2 rounded-lg">
                                <span class="text-green-500 font-bold">Admin:</span><br>
                                ID: AD001<br>
                                Pass: admin123
                            </div>
                            <div class="bg-black/20 p-2 rounded-lg">
                                <span class="text-green-500 font-bold">Manager:</span><br>
                                ID: MG001<br>
                                Pass: manager123
                            </div>
                            <div class="bg-black/20 p-2 rounded-lg">
                                <span class="text-green-500 font-bold">Employee:</span><br>
                                ID: EM001<br>
                                Pass: employee123
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Form Action -->
        <form action="" method="POST">
            <button type="submit" name="initialize" class="w-full pink-gradient text-white py-5 rounded-3xl font-extrabold shadow-lg shadow-pink-100 flex items-center justify-center gap-3 hover:scale-[1.01] transition-all uppercase tracking-[0.2em] text-sm">
                <i data-lucide="zap" class="w-5 h-5"></i>
                <?= ($db_exists) ? 'Re-Initialize / Repair Tables' : 'Initialize & Seed Database' ?>
            </button>
        </form>

        <!-- Navigation back -->
        <div class="mt-6 text-center">
            <a href="login.php" class="text-xs font-bold text-pink-400 hover:text-[#FB6F92] transition-colors flex items-center justify-center gap-1">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Return to login screen
            </a>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
