<?php
/**
 * OptiTask System - Employee Task Inventory (Pro Version)
 * Theme: Ultra-Pink Edition (Strict Consistency)
 * Logic: Start Task -> Submit Work (Done) -> Manager Verifies (Verified)
 */
session_start();
require_once '../db_config.php'; 
require_once '../email_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Employee') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$active = 'tasks'; 

// Check for Unread Notifications for Sidebar Red Dot
$unread_query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND status = 'unread'";
$stmt_unread = $conn->prepare($unread_query);
$stmt_unread->bind_param("s", $user_id);
$stmt_unread->execute();
$unread_count = $stmt_unread->get_result()->fetch_assoc()['total'];
$stmt_unread->close();

// --- ACTION HANDLER ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tid = $_POST['task_id'];
    
    // 1. Manual Start
    if(isset($_POST['start_task'])) {
        $stmt = $conn->prepare("UPDATE tasks SET task_status = 'In Progress' WHERE task_id = ? AND employee_id = ?");
        $stmt->bind_param("ss", $tid, $user_id);
        if ($stmt->execute()) {
            log_audit($conn, $user_id, 'START_TASK', "Started task ID $tid");
        }
    } 
    // 2. Submission (Moves to Done)
    elseif(isset($_POST['submit_work'])) {
        if(isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            $upload_dir = "../uploads/submissions/";
            if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $path = $upload_dir . time() . "_" . $_FILES['attachment']['name'];
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $path)) {
                $stmt = $conn->prepare("UPDATE tasks SET task_status = 'Done', submission_file = ? WHERE task_id = ? AND employee_id = ?");
                $stmt->bind_param("sss", $path, $tid, $user_id);
                if ($stmt->execute()) {
                    log_audit($conn, $user_id, 'SUBMIT_TASK', "Submitted task ID $tid with attachment: " . basename($path));
                    
                    // 1. Fetch Task Title and assigning Manager details
                    $task_title = "Unknown Task";
                    $manager = null;
                    $stmt_task = $conn->prepare("
                        SELECT t.task_title, t.manager_id, u.email as mgr_email, u.username as mgr_name 
                        FROM tasks t 
                        LEFT JOIN users u ON t.manager_id = u.user_id 
                        WHERE t.task_id = ?
                    ");
                    if ($stmt_task) {
                        $stmt_task->bind_param("s", $tid);
                        $stmt_task->execute();
                        $task_res = $stmt_task->get_result()->fetch_assoc();
                        if ($task_res) {
                            $task_title = $task_res['task_title'];
                            if (!empty($task_res['manager_id'])) {
                                $manager = [
                                    'user_id' => $task_res['manager_id'],
                                    'email' => $task_res['mgr_email'],
                                    'username' => $task_res['mgr_name']
                                ];
                            }
                        }
                        $stmt_task->close();
                    }

                    // 2. Fetch Employee Username and Department
                    $emp_name = $_SESSION['username'] ?? 'Employee';
                    $dept_id = null;
                    $stmt_dept = $conn->prepare("SELECT username, dept_id FROM users WHERE user_id = ?");
                    if ($stmt_dept) {
                        $stmt_dept->bind_param("s", $user_id);
                        $stmt_dept->execute();
                        $dept_res = $stmt_dept->get_result()->fetch_assoc();
                        if ($dept_res) {
                            $emp_name = $dept_res['username'];
                            $dept_id = $dept_res['dept_id'];
                        }
                        $stmt_dept->close();
                    }

                    // 3. Populate Managers List
                    $managers = [];
                    if ($manager !== null) {
                        $managers[] = $manager;
                    } else {
                        // Fallback 1: Find Managers in same department
                        if ($dept_id !== null) {
                            $stmt_mgrs = $conn->prepare("SELECT user_id, email, username FROM users WHERE role = 'Manager' AND dept_id = ?");
                            if ($stmt_mgrs) {
                                $stmt_mgrs->bind_param("i", $dept_id);
                                $stmt_mgrs->execute();
                                $mgrs_res = $stmt_mgrs->get_result();
                                while ($row = $mgrs_res->fetch_assoc()) {
                                    $managers[] = $row;
                                }
                                $stmt_mgrs->close();
                            }
                        }

                        // Fallback 2: Default to all Managers if no department managers are set
                        if (empty($managers)) {
                            $stmt_all_mgrs = $conn->prepare("SELECT user_id, email, username FROM users WHERE role = 'Manager'");
                            if ($stmt_all_mgrs) {
                                $stmt_all_mgrs->execute();
                                $all_mgrs_res = $stmt_all_mgrs->get_result();
                                while ($row = $all_mgrs_res->fetch_assoc()) {
                                    $managers[] = $row;
                                }
                                $stmt_all_mgrs->close();
                            }
                        }
                    }

                    // 4. Notify all retrieved managers
                    $notif_type = 'Submission';
                    $notif_msg = "Employee $emp_name ($user_id) has submitted task '$task_title' (#$tid) for verification.";
                    foreach ($managers as $mgr) {
                        if (empty($mgr['user_id'])) {
                            continue;
                        }
                        
                        // Check if the user_id exists in the users table to avoid foreign key failure
                        $check_user = $conn->prepare("SELECT 1 FROM users WHERE user_id = ?");
                        if ($check_user) {
                            $check_user->bind_param("s", $mgr['user_id']);
                            $check_user->execute();
                            $user_exists = $check_user->get_result()->num_rows > 0;
                            $check_user->close();
                            
                            if ($user_exists) {
                                $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, notification_type, message, status) VALUES (?, ?, ?, 'unread')");
                                if ($stmt_notif) {
                                    $stmt_notif->bind_param("sss", $mgr['user_id'], $notif_type, $notif_msg);
                                    $stmt_notif->execute();
                                    $stmt_notif->close();
                                }

                                if (!empty($mgr['email'])) {
                                    $email_content = "<strong>Task:</strong> " . htmlspecialchars($task_title) . " (#$tid)<br>" .
                                                     "<strong>Submitted By:</strong> " . htmlspecialchars($emp_name) . " ($user_id)<br>" .
                                                     "<strong>Attached File:</strong> " . htmlspecialchars(basename($path)) . "<br><br>" .
                                                     "<strong>Details:</strong> The employee has completed the task and submitted it with an attachment for your verification.";
                                    
                                    send_email_notification(
                                        $mgr['email'],
                                        $mgr['username'],
                                        "Task Submission: $task_title",
                                        $email_content
                                    );
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    header("Location: tasks.php");
    exit();
}

// --- DATA FETCH ---
$query = "SELECT * FROM tasks WHERE employee_id = ? ORDER BY due_date ASC";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$tasks_array = [];
$counts = ['todo' => 0, 'progress' => 0, 'done' => 0, 'verified' => 0];

while ($row = $result->fetch_assoc()) {
    $raw = $row['task_status'];
    $status_key = 'todo';
    if ($raw === 'In Progress') { $status_key = 'inprogress'; $counts['progress']++; }
    elseif ($raw === 'Done') { $status_key = 'done'; $counts['done']++; }
    elseif ($raw === 'Verified') { $status_key = 'verified'; $counts['verified']++; }
    else { $counts['todo']++; }

    $tasks_array[] = [
        'id'       => $row['task_id'],
        'title'    => $row['task_title'],
        'due'      => $row['due_date'],
        'due_display' => date('d M Y', strtotime($row['due_date'])),
        'status'   => $status_key, 
        'raw_status' => $raw,
        'priority' => strtoupper($row['priority']),
        'desc'     => $row['description'],
        'notes'    => $row['manager_notes'] ?? ''
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OptiTask | My Tasks</title>
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

        /* STATUS UI */
        .state-todo { background-color: #FFE4EA; color: #FB6F92; border: 1px solid #FFD1DC; }
        .state-progress { background-color: #FEF2F2; color: #EF4444; border: 1px solid #FEE2E2; }
        .state-done { background-color: #ECFDF5; color: #10B981; border: 1px solid #D1FAE5; }
        .state-verified { background-color: #EFF6FF; color: #3B82F6; border: 1px solid #DBEAFE; }

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

        .task-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(255, 228, 234, 0.6);
            cursor: pointer;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
        }
        .task-card:hover {
            transform: translateY(-5px);
            border-color: rgba(251, 111, 146, 0.3);
            box-shadow: 0 15px 30px rgba(251, 111, 146, 0.08);
        }

        .modal-backdrop { display: none; backdrop-filter: blur(10px); background: rgba(30, 41, 59, 0.4); }
        .modal-backdrop.show { display: flex; }
        
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-thumb { background: #FFD1DC; border-radius: 10px; }

        /* SweetAlert Styling Overrides */
        .swal2-popup {
            font-family: 'Quicksand', sans-serif !important;
        }
        .swal2-title {
            font-family: 'Outfit', sans-serif !important;
        }
    </style>
</head>
<body class="flex h-screen overflow-hidden">

<aside class="w-72 bg-white border-r border-pink-100 flex flex-col">
    <div class="p-8 pb-10 flex items-center gap-3">
        <div class="w-12 h-12 pink-gradient rounded-2xl flex items-center justify-center text-white shadow-lg shadow-pink-100">
            <i data-lucide="zap" class="w-6 h-6"></i>
        </div>
        <span class="text-2xl font-bold tracking-tight text-[#1e293b]">OptiTask<span class="text-[#FB6F92]">.</span></span>
    </div>

    <nav class="flex-1 space-y-2 pr-4">
        <p class="text-[11px] uppercase tracking-[0.2em] text-pink-300 font-bold px-8 mb-4">Employee Console</p>
        <a href="dashboard_employee.php" class="sidebar-link flex items-center gap-4 px-8 py-4 transition-all"><i data-lucide="layout-grid" class="w-5 h-5"></i> Dashboard</a>
        <a href="tasks.php" class="sidebar-active flex items-center gap-4 px-8 py-4 transition-all"><i data-lucide="clipboard-list" class="w-5 h-5"></i> My Tasks</a>
        <a href="update_tasks.php" class="sidebar-link flex items-center gap-4 px-8 py-4 transition-all"><i data-lucide="check-circle" class="w-5 h-5"></i> Submissions</a>
        <div class="pt-6">
            <p class="text-[11px] uppercase tracking-[0.2em] text-pink-300 font-bold px-8 mb-4">Account</p>
            <a href="skills.php" class="sidebar-link flex items-center gap-4 px-8 py-4 transition-all"><i data-lucide="user" class="w-5 h-5"></i> Profile</a>
            <a href="performance.php" class="sidebar-link flex items-center gap-4 px-8 py-4 transition-all"><i data-lucide="bar-chart-3" class="w-5 h-5"></i> Performance</a>
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
            <a href="#" onclick="confirmLogout(event)"><i data-lucide="log-out" class="w-5 h-5 text-pink-200 hover:text-red-500 cursor-pointer"></i></a>
        </div>
    </div>
</aside>

<main class="flex-1 overflow-y-auto p-12">
    <header class="flex justify-between items-end mb-12">
        <div>
            <h1 class="text-4xl font-extrabold text-[#1e293b] tracking-tight uppercase">Task Inventory</h1>
            <p class="text-pink-400 mt-1 font-bold italic">Search and filter your verified workload.</p>
        </div>
        <div class="relative">
            <input id="searchInput" type="text" placeholder="Search tasks..." class="bg-white border-2 border-pink-50 rounded-2xl pl-12 pr-6 py-3 text-sm font-bold w-80 outline-none focus:border-pink-300 shadow-sm">
            <i data-lucide="search" class="w-5 h-5 text-pink-200 absolute left-4 top-1/2 -translate-y-1/2"></i>
        </div>
    </header>

    <div class="bg-white p-6 rounded-[2.5rem] border border-pink-50 shadow-sm mb-10 flex flex-wrap items-center gap-4">
        <button class="chip px-5 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest bg-[#FB6F92] text-white shadow-lg" data-filter-status="all">All</button>
        <button class="chip px-5 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest bg-pink-50 text-pink-400 border border-pink-100" data-filter-status="todo">To-Do (<?= $counts['todo'] ?>)</button>
        <button class="chip px-5 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest bg-pink-50 text-pink-400 border border-pink-100" data-filter-status="inprogress">Active (<?= $counts['progress'] ?>)</button>
        <button class="chip px-5 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest bg-pink-50 text-pink-400 border border-pink-100" data-filter-status="done">Done (<?= $counts['done'] ?>)</button>
        <button class="chip px-5 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest bg-pink-50 text-pink-400 border border-pink-100" data-filter-status="verified">Verified (<?= $counts['verified'] ?>)</button>
    </div>

    <div id="taskGrid" class="grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-3 gap-8 pb-10"></div>
</main>

<div id="modalBackdrop" class="modal-backdrop fixed inset-0 z-50 items-center justify-center p-6">
    <div class="w-full max-w-2xl bg-white rounded-[3rem] shadow-2xl overflow-hidden border border-pink-50">
        <div class="p-10 border-b border-pink-50 flex justify-between items-center bg-white">
            <div>
                <p id="mId" class="text-[11px] font-black text-pink-300 uppercase tracking-widest mb-1">#ID</p>
                <h3 id="mTitle" class="text-3xl font-extrabold text-[#1e293b]">Task Detail</h3>
            </div>
            <button id="closeModal" class="w-12 h-12 rounded-2xl bg-pink-50 text-[#FB6F92] flex items-center justify-center hover:rotate-90 transition-all"><i data-lucide="x"></i></button>
        </div>

        <div class="p-10 space-y-8 bg-white">
            <div class="flex gap-3">
                <span id="mStatus" class="px-5 py-2 rounded-xl text-[10px] font-black uppercase border">STATUS</span>
                <span id="mDue" class="px-5 py-2 rounded-xl text-[10px] font-black bg-gray-50 text-gray-500 uppercase">DATE</span>
            </div>
            <div class="bg-[#FFF9FA] rounded-[2rem] p-8 border border-pink-50 shadow-inner">
                <p class="text-[11px] font-black text-pink-300 uppercase mb-3 tracking-widest">Requirement</p>
                <p id="mDesc" class="text-sm text-gray-600 font-semibold leading-relaxed"></p>
            </div>

            <div id="modal-action-area" class="border-2 border-dashed border-pink-100 rounded-[2.5rem] p-8 text-center bg-white">
                </div>
        </div>
    </div>
</div>

<script>
    const tasks = <?= json_encode($tasks_array); ?>;
    const grid = document.getElementById("taskGrid");
    const searchInput = document.getElementById("searchInput");
    const chips = document.querySelectorAll("[data-filter-status]");

    let currentStatus = "all";

    function getStatusClass(status) {
        if(status === "todo") return 'state-todo';
        if(status === "inprogress") return 'state-progress';
        if(status === "done") return 'state-done';
        return 'state-verified'; 
    }

    function render() {
        grid.innerHTML = "";
        let filtered = tasks.filter(t => {
            const matchesSearch = t.title.toLowerCase().includes(searchInput.value.toLowerCase()) || t.id.toLowerCase().includes(searchInput.value.toLowerCase());
            const matchesStatus = currentStatus === "all" || t.status === currentStatus;
            return matchesSearch && matchesStatus;
        });

        filtered.forEach(t => {
            const card = document.createElement("div");
            card.className = "task-card rounded-[2.5rem] p-8 flex flex-col";
            card.onclick = () => openModal(t);
            card.innerHTML = `
                <div class="flex justify-between items-start mb-6">
                    <span class="px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest ${getStatusClass(t.status)}">${t.raw_status}</span>
                    <span class="text-[10px] font-black text-pink-200 uppercase tracking-widest">#${t.id}</span>
                </div>
                <h3 class="text-xl font-extrabold text-[#1e293b] mb-4 leading-tight">${t.title}</h3>
                <div class="mt-auto pt-6 border-t border-pink-50 flex items-center justify-between text-gray-400">
                    <div class="flex items-center gap-2"><i data-lucide="calendar" class="w-4 h-4"></i><span class="text-[10px] font-bold uppercase">${t.due_display}</span></div>
                    <i data-lucide="chevron-right" class="w-5 h-5 text-pink-100"></i>
                </div>
            `;
            grid.appendChild(card);
        });
        lucide.createIcons();
    }

    function openModal(t) {
        document.getElementById("mId").textContent = "#" + t.id;
        document.getElementById("mTitle").textContent = t.title;
        document.getElementById("mDesc").textContent = t.desc;
        document.getElementById("mDue").textContent = t.due_display;
        
        const mStatus = document.getElementById("mStatus");
        mStatus.textContent = t.raw_status;
        mStatus.className = `px-5 py-2 rounded-xl text-[10px] font-black uppercase border ${getStatusClass(t.status)}`;

        const actionArea = document.getElementById("modal-action-area");
        if(t.status === 'todo') {
            actionArea.innerHTML = `<form action="tasks.php" method="POST"><input type="hidden" name="task_id" value="${t.id}"><button type="submit" name="start_task" class="w-full bg-[#FF8FAB] hover:bg-[#FB6F92] text-white py-4 rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl hover:scale-[1.02] transform duration-300">Start Task Now</button></form>`;
        } else if(t.status === 'inprogress') {
            actionArea.innerHTML = `<form action="tasks.php" method="POST" enctype="multipart/form-data" class="space-y-4"><input type="hidden" name="task_id" value="${t.id}"><input type="file" name="attachment" required class="block w-full text-xs text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-pink-50 file:text-[#FB6F92] file:font-black"><button type="submit" name="submit_work" class="w-full pink-gradient text-white py-4 rounded-2xl font-black text-xs uppercase tracking-widest shadow-lg hover:scale-[1.02] transform duration-300">Submit Work</button></form>`;
        } else if(t.status === 'done') {
            actionArea.innerHTML = `<div class="py-4 text-green-500 font-black uppercase text-xs">Waiting for Manager Approval</div>`;
        } else {
            actionArea.innerHTML = `<p class="text-blue-500 font-black uppercase text-xs flex items-center justify-center gap-2"><i data-lucide="shield-check"></i> Task Verified</p>`;
        }
        document.getElementById("modalBackdrop").classList.add("show");
        lucide.createIcons();
    }

    document.getElementById("closeModal").onclick = () => document.getElementById("modalBackdrop").classList.remove("show");
    
    chips.forEach(chip => {
        chip.onclick = () => {
            chips.forEach(c => c.className = "chip px-5 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest bg-pink-50 text-pink-400 border border-pink-100");
            chip.className = "chip px-5 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest bg-[#FB6F92] text-white shadow-lg";
            currentStatus = chip.dataset.filterStatus;
            render();
        };
    });

    searchInput.oninput = render;
    render();
</script>
<script>
function confirmLogout(e) {
    if(e) e.preventDefault();
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Close Session?',
            text: "Are you sure you want to exit OptiTask?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#FF8FAB',
            cancelButtonColor: '#1e293b',
            confirmButtonText: 'Yes, Logout',
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
    } else {
        if(confirm("Are you sure you want to exit OptiTask?")) {
            window.location.href = '../logout.php';
        }
    }
}
</script>
</body>
</html>