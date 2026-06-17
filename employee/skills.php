<?php
/**
 * OptiTask System - Manage My Skills (Employee)
 * Theme: Ultra-Pink Edition
 */
session_start();
require_once '../db_config.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Employee') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$active = 'skills';

// Check for Unread Notifications for Sidebar Red Dot
$unread_query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND status = 'unread'";
$stmt_unread = $conn->prepare($unread_query);
$stmt_unread->bind_param("s", $user_id);
$stmt_unread->execute();
$unread_count = $stmt_unread->get_result()->fetch_assoc()['total'];
$stmt_unread->close();

$message = '';
$status_type = 'success';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'save_skill') {
        $skill_id = (int)($_POST['skill_id'] ?? 0);
        $level = (int)($_POST['proficiency_level'] ?? 0);
        
        if ($skill_id <= 0 || $level < 1 || $level > 5) {
            $message = "Error: Invalid skill selection or proficiency level.";
            $status_type = "error";
        } else {
            $stmt = $conn->prepare("INSERT INTO employee_skills (user_id, skill_id, proficiency_level) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE proficiency_level = ?");
            $stmt->bind_param("siii", $user_id, $skill_id, $level, $level);
            if ($stmt->execute()) {
                log_audit($conn, $user_id, 'UPDATE_SKILL', "Saved skill ID $skill_id with level $level");
                $message = "Skill saved successfully.";
                $status_type = "success";
            } else {
                $message = "Error: Failed to save skill.";
                $status_type = "error";
            }
            $stmt->close();
        }
    } elseif ($action === 'delete_skill') {
        $skill_id = (int)($_POST['skill_id'] ?? 0);
        if ($skill_id <= 0) {
            $message = "Error: Invalid skill ID.";
            $status_type = "error";
        } else {
            $stmt = $conn->prepare("DELETE FROM employee_skills WHERE user_id = ? AND skill_id = ?");
            $stmt->bind_param("si", $user_id, $skill_id);
            if ($stmt->execute()) {
                log_audit($conn, $user_id, 'DELETE_SKILL', "Deleted skill ID $skill_id");
                $message = "Skill deleted successfully.";
                $status_type = "success";
            } else {
                $message = "Error: Failed to delete skill.";
                $status_type = "error";
            }
            $stmt->close();
        }
    } elseif ($action === 'save_department') {
        $dept_id = (int)($_POST['dept_id'] ?? 0);
        if ($dept_id <= 0) {
            $message = "Error: Invalid department selection.";
            $status_type = "error";
        } else {
            // Check if department is already set in the database
            $check_res = $conn->query("SELECT dept_id FROM users WHERE user_id = '$user_id'")->fetch_assoc();
            if ($check_res && !empty($check_res['dept_id'])) {
                $message = "Error: You have already selected a department. Only Admin can update it.";
                $status_type = "error";
            } else {
                $stmt = $conn->prepare("UPDATE users SET dept_id = ? WHERE user_id = ?");
                $stmt->bind_param("is", $dept_id, $user_id);
                if ($stmt->execute()) {
                    log_audit($conn, $user_id, 'UPDATE_DEPARTMENT', "Updated department to ID $dept_id");
                    $message = "Department updated successfully.";
                    $status_type = "success";
                } else {
                    $message = "Error: Failed to update department.";
                    $status_type = "error";
                }
                $stmt->close();
            }
        }
    }
}

// Fetch employee skills
$stmt = $conn->prepare("
    SELECT s.skill_id, s.skill_name, es.proficiency_level 
    FROM employee_skills es
    JOIN skills s ON es.skill_id = s.skill_id
    WHERE es.user_id = ?
    ORDER BY s.skill_name ASC
");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$my_skills = $stmt->get_result();
$stmt->close();

// Fetch all available skills
$all_skills_res = $conn->query("SELECT skill_id, skill_name FROM skills ORDER BY skill_name ASC");
$all_skills = [];
while ($row = $all_skills_res->fetch_assoc()) {
    $all_skills[] = $row;
}

// Fetch user's current department and all departments
$user_info = $conn->query("SELECT username, email, dept_id FROM users WHERE user_id = '$user_id'")->fetch_assoc();
$my_username = $user_info['username'] ?? '';
$my_email = $user_info['email'] ?? '';
$my_dept_id = $user_info['dept_id'] ?? '';

$depts_res = $conn->query("SELECT dept_id, dept_name FROM departments ORDER BY dept_id ASC");
$all_depts = [];
if ($depts_res) {
    while ($row = $depts_res->fetch_assoc()) {
        $all_depts[] = $row;
    }
}

$prof_labels = [
    1 => 'Beginner (Level 1)',
    2 => 'Novice (Level 2)',
    3 => 'Intermediate (Level 3)',
    4 => 'Advanced (Level 4)',
    5 => 'Expert (Level 5)'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OptiTask | Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Quicksand:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Quicksand', sans-serif; background-color: #FFF5F7; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Outfit', sans-serif; }
        .pink-gradient { background: linear-gradient(135deg, #FB6F92 0%, #FFB3C6 100%); }
        
        /* SIDEBAR UI MATCHED TO ADMIN */
        .sidebar-active {
            background: linear-gradient(90deg, rgba(251, 111, 146, 0.08) 0%, rgba(255, 179, 198, 0.02) 100%);
            border-left: 5px solid #FB6F92;
            color: #FB6F92;
            font-weight: 800;
            border-radius: 0 1rem 1rem 0;
        }
        .sidebar-active i { color: #FB6F92; }
        .sidebar-link {
            color: #64748b;
            font-weight: 600;
            border-left: 5px solid transparent;
            font-size: 0.95rem;
        }
        .sidebar-link:hover {
            background: #fff1f2;
            color: #FB6F92;
            border-radius: 0 1rem 1rem 0;
        }

        /* Glassmorphism Card styling */
        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 228, 234, 0.6);
            border-radius: 2rem;
            box-shadow: 0 20px 40px rgba(251, 111, 146, 0.03);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .glass-card:hover {
            transform: translateY(-4px);
            border-color: rgba(251, 111, 146, 0.3);
            box-shadow: 0 30px 60px rgba(251, 111, 146, 0.07);
        }
        
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #FFF5F7; }
        ::-webkit-scrollbar-thumb { background: #FFD1DC; border-radius: 10px; }
    </style>
</head>
<body class="flex h-screen overflow-hidden">

<aside class="w-72 bg-white border-r border-pink-100 flex flex-col">
    <div class="p-8 pb-10 flex items-center gap-3">
        <div class="w-12 h-12 pink-gradient rounded-2xl flex items-center justify-center text-white shadow-lg shadow-pink-100">
            <i data-lucide="zap" class="w-6 h-6"></i>
        </div>
        <span class="text-2xl font-bold tracking-tight text-[#1e293b]">
            OptiTask<span class="text-[#FB6F92]">.</span>
        </span>
    </div>

    <nav class="flex-1 space-y-2 pr-4">
        <p class="text-[11px] uppercase tracking-[0.2em] text-pink-300 font-bold px-8 mb-4">Employee Console</p>
        
        <a href="dashboard_employee.php" class="sidebar-link flex items-center gap-4 px-8 py-4 transition-all">
            <i data-lucide="layout-grid" class="w-5 h-5"></i> Dashboard
        </a>
        
        <a href="tasks.php" class="sidebar-link flex items-center gap-4 px-8 py-4 transition-all">
            <i data-lucide="clipboard-list" class="w-5 h-5"></i> My Tasks
        </a>

        <a href="update_tasks.php" class="sidebar-link flex items-center gap-4 px-8 py-4 transition-all">
            <i data-lucide="check-circle" class="w-5 h-5"></i> Submissions
        </a>

        <div class="pt-6">
            <p class="text-[11px] uppercase tracking-[0.2em] text-pink-300 font-bold px-8 mb-4">Account</p>
            <a href="skills.php" class="sidebar-active flex items-center gap-4 px-8 py-4 transition-all">
                <i data-lucide="user" class="w-5 h-5"></i> Profile
            </a>
            <a href="performance.php" class="sidebar-link flex items-center gap-4 px-8 py-4 transition-all">
                <i data-lucide="bar-chart-3" class="w-5 h-5"></i> Performance
            </a>
            <a href="notification.php" class="sidebar-link flex items-center justify-between px-8 py-4 transition-all">
                <div class="flex items-center gap-4">
                    <i data-lucide="bell" class="w-5 h-5"></i> Notifications
                </div>
                <?php if(isset($unread_count) && $unread_count > 0): ?>
                    <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse shadow-[0_0_8px_rgba(239,68,68,0.5)]"></span>
                <?php endif; ?>
            </a>
        </div>
    </nav>

    <div class="p-6">
        <div class="bg-[#FFF9FA] rounded-[1.5rem] p-4 flex items-center gap-3 border border-pink-100">
            <div class="w-10 h-10 rounded-full bg-white border-2 border-pink-200 text-[#FB6F92] flex items-center justify-center font-bold text-sm">EM</div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-extrabold text-[#1e293b] truncate"><?= htmlspecialchars($username) ?></p>
                <p class="text-[11px] text-pink-400 font-bold uppercase tracking-wider"><?= htmlspecialchars($user_id) ?></p>
            </div>
            <button id="logout-trigger">
                <i data-lucide="log-out" class="w-5 h-5 text-pink-200 hover:text-red-500 cursor-pointer"></i>
            </button>
        </div>
    </div>
</aside>

<main class="flex-1 overflow-y-auto p-12">
    <header class="flex justify-between items-end mb-12">
        <div>
            <h1 class="text-4xl font-extrabold text-[#1e293b] tracking-tight uppercase">Profile</h1>
            <p class="text-pink-400 mt-1 font-bold italic">View your profile details and manage your department and skill portfolio.</p>
        </div>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left: Add/Update Skill & Registered Skills -->
        <div class="lg:col-span-2 space-y-8 flex flex-col">
            <!-- Add/Update Skill Card -->
            <div class="glass-card p-6">
                <form method="POST" class="flex flex-col md:flex-row items-end gap-4">
                    <input type="hidden" name="action" value="save_skill">
                    
                    <div class="flex-1 w-full">
                        <label class="text-[10px] font-black text-pink-400 uppercase tracking-widest ml-1">Select Skill</label>
                        <select name="skill_id" required class="mt-1.5 w-full bg-[#FFF9FA] rounded-xl px-4 py-2.5 text-xs font-bold text-[#1e293b] outline-none border border-pink-100 focus:border-[#FB6F92] cursor-pointer">
                            <option value="">-- Choose Skill --</option>
                            <?php foreach ($all_skills as $s): ?>
                                <option value="<?= $s['skill_id'] ?>"><?= htmlspecialchars($s['skill_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex-1 w-full">
                        <label class="text-[10px] font-black text-pink-400 uppercase tracking-widest ml-1">Expertise Level</label>
                        <select name="proficiency_level" required class="mt-1.5 w-full bg-[#FFF9FA] rounded-xl px-4 py-2.5 text-xs font-bold text-[#1e293b] outline-none border border-pink-100 focus:border-[#FB6F92] cursor-pointer">
                            <option value="">-- Choose Level --</option>
                            <option value="1">Level 1 - Beginner</option>
                            <option value="2">Level 2 - Novice</option>
                            <option value="3">Level 3 - Intermediate</option>
                            <option value="4">Level 4 - Advanced</option>
                            <option value="5">Level 5 - Expert</option>
                        </select>
                    </div>

                    <button type="submit" class="w-full md:w-auto pink-gradient text-white px-6 py-2.5 rounded-xl font-extrabold shadow-lg shadow-pink-100 flex items-center justify-center gap-1.5 hover:scale-[1.01] transition-all uppercase tracking-wider text-xs h-[38px] shrink-0">
                        <i data-lucide="plus" class="w-4 h-4"></i> Add Skill
                    </button>
                </form>
            </div>

            <!-- Registered Skills Card -->
            <div class="glass-card overflow-hidden flex flex-col">
                <div class="p-6 border-b border-pink-50 bg-white flex justify-between items-center">
                    <h3 class="font-extrabold text-[#1e293b] text-base tracking-tight italic flex items-center gap-2">
                        <span class="w-1.5 h-5 pink-gradient rounded-full"></span>
                        Registered Skills
                    </h3>
                </div>
                
                <div class="p-6 space-y-4 max-h-[300px] overflow-y-auto pr-1">
                    <?php if ($my_skills->num_rows > 0): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <?php 
                            $my_skills->data_seek(0);
                            while($row = $my_skills->fetch_assoc()): 
                            ?>
                                <div class="p-4 bg-[#FFF9FA] rounded-2xl border border-pink-100/50 flex flex-col justify-between hover:scale-[1.01] transition-transform">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <h4 class="text-xs font-black text-[#1e293b]"><?= htmlspecialchars($row['skill_name']) ?></h4>
                                            <p class="text-[9px] text-pink-400 font-bold uppercase mt-0.5"><?= $prof_labels[$row['proficiency_level']] ?></p>
                                        </div>
                                        <form method="POST" class="inline-block" onsubmit="return confirm('Remove this skill from your profile?')">
                                            <input type="hidden" name="action" value="delete_skill">
                                            <input type="hidden" name="skill_id" value="<?= $row['skill_id'] ?>">
                                            <button type="submit" class="p-1.5 rounded-lg bg-white text-red-400 hover:text-red-600 hover:bg-red-50 transition-all border border-pink-100/30">
                                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                            </button>
                                        </form>
                                    </div>
                                    <div class="flex items-center gap-1 mt-1">
                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                            <i data-lucide="star" class="w-3 h-3 <?= $i <= $row['proficiency_level'] ? 'fill-[#FB6F92] text-[#FB6F92]' : 'text-gray-300' ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-10 flex flex-col items-center justify-center">
                            <div class="w-12 h-12 bg-pink-50 rounded-xl flex items-center justify-center mb-3">
                                <i data-lucide="award" class="w-6 h-6 text-pink-200"></i>
                            </div>
                            <p class="text-xs font-bold text-gray-400 italic">No skills registered yet. Use the panel above to add skills!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: Department & Insight -->
        <div class="space-y-8 lg:col-span-1">
            <!-- Profile Info Card -->
            <div class="glass-card p-6 flex flex-col gap-5">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 rounded-full bg-pink-100/50 border-2 border-pink-200 text-[#FB6F92] flex items-center justify-center font-bold text-2xl shrink-0">
                        <?= strtoupper(substr($my_username ?? 'EM', 0, 2)) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-black text-pink-400 uppercase tracking-widest">Employee Profile</p>
                        <h3 class="text-base font-black text-[#1e293b] truncate mt-0.5"><?= htmlspecialchars($my_username) ?></h3>
                        <p class="text-xs font-bold text-gray-500 truncate"><?= htmlspecialchars($my_email) ?></p>
                        <p class="text-[10px] text-gray-400 font-extrabold uppercase mt-1 tracking-wider">ID: <?= htmlspecialchars($user_id) ?></p>
                    </div>
                </div>
                
                <hr class="border-pink-50/80">

                <form method="POST" class="flex flex-col gap-3" onsubmit="confirmDeptChange(event)">
                    <input type="hidden" name="action" value="save_department">
                    <div>
                        <label class="text-[10px] font-black text-pink-400 uppercase tracking-wider ml-1">My Department</label>
                        <div class="flex gap-2 mt-1.5">
                            <?php if (!empty($my_dept_id)): ?>
                                <?php 
                                $my_dept_name = 'None';
                                foreach ($all_depts as $d) {
                                    if ($d['dept_id'] == $my_dept_id) {
                                        $my_dept_name = $d['dept_name'];
                                        break;
                                    }
                                }
                                ?>
                                <div class="flex-1 bg-pink-50/20 border-2 border-pink-50/50 rounded-xl px-4 py-2.5 text-xs font-bold text-gray-500 flex items-center gap-2 select-none">
                                    <i data-lucide="lock" class="w-3.5 h-3.5 text-pink-400"></i>
                                    <?= htmlspecialchars($my_dept_name) ?>
                                </div>
                            <?php else: ?>
                                <select name="dept_id" required class="flex-1 bg-[#FFF9FA] rounded-xl px-4 py-2 text-xs font-bold text-[#1e293b] outline-none border border-pink-100 focus:border-[#FB6F92] cursor-pointer">
                                    <option value="">-- Choose Department --</option>
                                    <?php foreach ($all_depts as $d): ?>
                                        <option value="<?= $d['dept_id'] ?>"><?= htmlspecialchars($d['dept_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="pink-gradient text-white px-4 py-2 rounded-xl font-extrabold shadow-md shadow-pink-100 flex items-center justify-center text-[10px] uppercase tracking-wider hover:scale-[1.02] transition-transform">
                                    Save
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="pink-gradient p-6 rounded-[2rem] text-white shadow-xl shadow-pink-200/50 relative overflow-hidden">
                <i data-lucide="info" class="absolute -right-4 -bottom-4 w-24 h-24 opacity-10 rotate-12"></i>
                <h4 class="text-[10px] font-black uppercase tracking-[0.2em] mb-2 opacity-80">Skill Matching</h4>
                <p class="text-[11px] font-bold leading-relaxed">Updating your skills and levels allows the AI matching engine to suggest your profile for relevant work orders assigned by managers.</p>
            </div>
        </div>
    </div>
</main>

<script>
    lucide.createIcons();

    // Logout Confirmation Logic
    document.getElementById('logout-trigger').addEventListener('click', function() {
        Swal.fire({
            title: 'End session?',
            text: "Ensure all your progress is saved!",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#FF8FAB',
            cancelButtonColor: '#1e293b',
            confirmButtonText: 'Yes, Sign Out',
            cancelButtonText: 'Stay Here',
            background: '#FFF9FA',
            customClass: {
                popup: 'rounded-[2.5rem] border-2 border-pink-100',
                title: 'font-black text-[#1e293b]',
                confirmButton: 'rounded-xl px-6 py-3',
                cancelButton: 'rounded-xl px-6 py-3'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '../logout.php';
            }
        });
    });

    // Department Selection Confirmation
    function confirmDeptChange(e) {
        e.preventDefault();
        const form = e.target;
        Swal.fire({
            title: 'Confirm Department?',
            text: "Choosing a department is a one-time setup. Please verify your selection!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#FF8FAB',
            cancelButtonColor: '#1e293b',
            confirmButtonText: 'Yes, Confirm',
            cancelButtonText: 'Cancel',
            background: '#FFF9FA',
            customClass: {
                popup: 'rounded-[2.5rem] border-2 border-pink-100',
                title: 'font-black text-[#1e293b]',
                confirmButton: 'rounded-xl px-6 py-3',
                cancelButton: 'rounded-xl px-6 py-3'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    }

    <?php if($message): ?>
    Swal.fire({
        icon: '<?= $status_type ?>',
        title: '<?= $status_type === "success" ? "Success!" : "Failed" ?>',
        text: '<?= addslashes($message) ?>',
        confirmButtonColor: '#FF8FAB',
        background: '#FFF9FA',
        customClass: { popup: 'rounded-[2.5rem] border-2 border-pink-100' }
    });
    <?php endif; ?>
</script>
</body>
</html>
