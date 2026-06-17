<?php
session_start();
require_once '../db_config.php';
require_once '../email_helper.php';

// 1. Session Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Manager') {
    header("Location: ../login.php");
    exit();
}

$active = 'verify_tasks';
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Manager';
$message = "";

// --- ACTION HANDLER ---
if (isset($_GET['action']) && isset($_GET['tid'])) {
    $tid = $_GET['tid'];
    $action = $_GET['action'];

    // Get Employee ID & Title before updating
    $get_info = $conn->prepare("SELECT employee_id, task_title FROM tasks WHERE task_id = ?");
    $get_info->bind_param("s", $tid);
    $get_info->execute();
    $tdata = $get_info->get_result()->fetch_assoc();
    
    if ($tdata) {
        $emp_id = $tdata['employee_id'];
        $title = $tdata['task_title'];

        if ($action === 'approve') {
            $new_status = 'Verified';
            $notif_msg = "Success! Your task '$title' (#$tid) has been verified.";
            $notif_type = "Approval";
        } else {
            $new_status = 'To-Do';
            $notif_msg = "Task '$title' (#$tid) was rejected. Please review and resubmit.";
            $notif_type = "Rejection";
        }

        // Update Task Table
        $upd = $conn->prepare("UPDATE tasks SET task_status = ? WHERE task_id = ?");
        $upd->bind_param("ss", $new_status, $tid);
        
        if ($upd->execute()) {
            log_audit($conn, $_SESSION['user_id'], 'VERIFY_TASK', "Manager " . ($action === 'approve' ? "approved" : "rejected") . " task $tid for $emp_id");
            // 3. Insert Notification for Employee (ID is handled by AUTO_INCREMENT)
            $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, notification_type, message, status) VALUES (?, ?, ?, 'unread')");
            $stmt_notif->bind_param("sss", $emp_id, $notif_type, $notif_msg);
            $stmt_notif->execute();
            $stmt_notif->close();
            
            // Fetch employee email and name for notification
            $stmt_user = $conn->prepare("SELECT email, username FROM users WHERE user_id = ?");
            if ($stmt_user) {
                $stmt_user->bind_param("s", $emp_id);
                $stmt_user->execute();
                $user_res = $stmt_user->get_result()->fetch_assoc();
                $stmt_user->close();
                
                if ($user_res && !empty($user_res['email'])) {
                    send_email_notification(
                        $user_res['email'], 
                        $user_res['username'], 
                        "Task Update: $notif_type", 
                        $notif_msg
                    );
                }
            }
            
            // Redirect to clean the URL and prevent duplicate submission
            header("Location: verify_tasks.php?msg=success");
            exit();
        }
    }
}
// FETCH PENDING SUBMISSIONS (Status 'Done')
$query = "SELECT t.*, u.username as emp_name FROM tasks t 
          JOIN users u ON t.employee_id = u.user_id 
          WHERE t.task_status = 'Done' ORDER BY t.due_date ASC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OptiTask | Verify Work</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Quicksand', sans-serif; background-color: #FFF5F7; }
        .pink-gradient { background: linear-gradient(135deg, #FB6F92 0%, #FFB3C6 100%); }
        .sidebar-active {
            background: #FFE4EA; 
            border-left: 6px solid #FB6F92;
            color: #FB6F92;
            font-weight: 800;
            border-radius: 0 1.5rem 1.5rem 0;
        }
        .sidebar-link { color: #64748b; font-weight: 600; font-size: 0.95rem; }
        .sidebar-link:hover { color: #FB6F92; background: #FFF0F3; border-radius: 0 1.5rem 1.5rem 0; }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-thumb { background: #FFD1DC; border-radius: 10px; }
    </style>
</head>
<body class="flex h-screen overflow-hidden">

<aside class="w-72 bg-white border-r border-pink-100 flex flex-col">
    <div class="p-8 pb-10 flex items-center gap-3">
        <div class="w-12 h-12 pink-gradient rounded-2xl flex items-center justify-center text-white shadow-lg shadow-pink-100">
            <i data-lucide="briefcase" class="w-6 h-6"></i>
        </div>
        <span class="text-2xl font-bold tracking-tight text-[#1e293b]">OptiTask<span class="text-[#FB6F92]">.</span></span>
    </div>

    <nav class="flex-1 space-y-2 pr-4">
        <p class="text-[11px] uppercase tracking-[0.2em] text-pink-300 font-bold px-8 mb-4">Manager</p>
        <a href="dashboard_manager.php" class="sidebar-link flex items-center gap-4 px-8 py-4 transition-all">
            <i data-lucide="layout-grid" class="w-5 h-5"></i> Dashboard
        </a>
        <a href="verify_tasks.php" class="sidebar-active flex items-center gap-4 px-8 py-4 transition-all">
            <i data-lucide="check-circle" class="w-5 h-5"></i> Verify Tasks
        </a>
        <a href="assign_tasks.php" class="sidebar-link flex items-center gap-4 px-8 py-4 transition-all">
            <i data-lucide="plus-circle" class="w-5 h-5"></i> Assign Tasks
        </a>
        <div class="pt-6">
            <p class="text-[11px] uppercase tracking-[0.2em] text-pink-300 font-bold px-8 mb-4">Alerts</p>
            <a href="notification.php" class="sidebar-link flex items-center gap-4 px-8 py-4 transition-all">
                <i data-lucide="bell" class="w-5 h-5"></i> Notifications
            </a>
        </div>
    </nav>

    <div class="p-6">
        <div class="bg-[#FFF9FA] rounded-[1.5rem] p-4 flex items-center gap-3 border border-pink-100">
            <div class="w-10 h-10 rounded-full bg-white border-2 border-pink-200 text-[#FB6F92] flex items-center justify-center font-bold text-sm">
                <?= strtoupper(substr($_SESSION['username'] ?? 'MG', 0, 2)) ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-extrabold text-[#1e293b] truncate"><?= htmlspecialchars($_SESSION['username'] ?? 'Manager') ?></p>
                <p class="text-[11px] text-pink-400 font-bold uppercase tracking-widest">ID: <?= htmlspecialchars($_SESSION['user_id']) ?></p>
            </div>
            <button id="logout-btn" onclick="confirmLogout(event)" class="shrink-0" title="Logout">
                <i data-lucide="log-out" class="w-5 h-5 text-pink-200 hover:text-red-500 cursor-pointer transition-colors"></i>
            </button>
        </div>
    </div>
</aside>

<main class="flex-1 overflow-y-auto p-12">
    <header class="mb-10">
        <h1 class="text-4xl font-extrabold text-[#1e293b] uppercase tracking-tight">Verify Submissions</h1>
        <p class="text-pink-400 font-bold italic mt-1">Review employee files and approve their final work.</p>
    </header>

    <div class="bg-white rounded-[2.5rem] shadow-xl border border-pink-50 overflow-hidden">
        <table class="w-full text-left">
            <thead>
                <tr class="text-[11px] uppercase font-black text-pink-300 tracking-widest border-b border-pink-50 bg-[#FFF9FA]">
                    <th class="px-8 py-6">Employee</th>
                    <th class="px-8 py-6">Task Details</th>
                    <th class="px-8 py-6 text-right">Action Control</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-pink-50">
                <?php if($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr class="hover:bg-[#FFF9FA] transition-all">
                        <td class="px-8 py-6">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-pink-50 text-[#FB6F92] flex items-center justify-center font-black text-xs"><?= strtoupper(substr($row['emp_name'],0,2)) ?></div>
                                <div>
                                    <p class="text-sm font-extrabold text-[#1e293b]"><?= htmlspecialchars($row['emp_name']) ?></p>
                                    <p class="text-[10px] text-pink-300 font-bold"><?= $row['employee_id'] ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-8 py-6">
                            <p class="text-sm font-extrabold text-[#1e293b]"><?= htmlspecialchars($row['task_title']) ?></p>
                            <?php if(!empty($row['submission_file'])): ?>
                                <a href="<?= $row['submission_file'] ?>" target="_blank" class="text-[10px] text-blue-500 font-bold flex items-center gap-1 mt-1 hover:underline">
                                    <i data-lucide="file-text" class="w-3 h-3"></i> View Submission File
                                </a>
                            <?php endif; ?>
                        </td>
                        <td class="px-8 py-6 text-right">
                            <div class="flex justify-end gap-3">
                                <a href="verify_tasks.php?action=approve&tid=<?= $row['task_id'] ?>" class="bg-green-500 text-white px-5 py-2.5 rounded-xl text-[10px] font-black uppercase shadow-lg shadow-green-100">Approve</a>
                                <a href="verify_tasks.php?action=reject&tid=<?= $row['task_id'] ?>" class="bg-white border-2 border-pink-50 text-pink-400 px-5 py-2.5 rounded-xl text-[10px] font-black uppercase hover:text-red-500 hover:border-red-100">Reject</a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="p-20 text-center text-gray-400 font-bold italic">No pending submissions found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
<script>
    lucide.createIcons();
    <?php if($message): ?>
    Swal.fire({ icon: 'success', title: 'Action Recorded', text: '<?= $message ?>', confirmButtonColor: '#FF8FAB' });
    <?php endif; ?>

    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function() {
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
        });
    }
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