<?php
/**
 * OptiTask System - Employee Dashboard
 * Theme: Ultra-Pink Edition (Consistent with System Design)
 */
session_start();
require_once '../db_config.php'; 

// 1. Session Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Employee') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$active = 'dashboard';

// Check for Unread Notifications for Sidebar Red Dot
$unread_query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND status = 'unread'";
$stmt_unread = $conn->prepare($unread_query);
$stmt_unread->bind_param("s", $user_id);
$stmt_unread->execute();
$unread_count = $stmt_unread->get_result()->fetch_assoc()['total'];
$stmt_unread->close();


// 2. Fetch Personal Performance (Dynamic Calculation)
$perf_query = "SELECT task_status FROM tasks WHERE employee_id = ?";
$stmt_perf = $conn->prepare($perf_query);
$stmt_perf->bind_param("s", $user_id);
$stmt_perf->execute();
$perf_result = $stmt_perf->get_result();

$total_t = 0;
$done_t = 0;
while($r = $perf_result->fetch_assoc()) {
    $total_t++;
    if ($r['task_status'] === 'Done' || $r['task_status'] === 'Verified') {
        $done_t++;
    }
}
$performance = ($total_t > 0) ? ($done_t / $total_t) * 100 : 0;

// 3. Fetch Assigned Tasks (Live Data)
$task_query = "SELECT * FROM tasks WHERE employee_id = ? ORDER BY due_date ASC";
$stmt_task = $conn->prepare($task_query);
$stmt_task->bind_param("s", $user_id);
$stmt_task->execute();
$tasks = $stmt_task->get_result();


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OptiTask | My Dashboard</title>
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
        
        .status-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23FB6F92'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1rem;
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
        
        <a href="dashboard_employee.php" class="sidebar-active flex items-center gap-4 px-8 py-4 transition-all">
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
            <a href="skills.php" class="sidebar-link flex items-center gap-4 px-8 py-4 transition-all">
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
            <h1 class="text-4xl font-extrabold text-[#1e293b] tracking-tight uppercase">Dashboard</h1>
            <p class="text-pink-400 mt-1 font-bold italic">Welcome back, <?= explode(' ', trim($username))[0] ?>.</p>
        </div>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 glass-card overflow-hidden">
            <div class="p-8 border-b border-pink-50 bg-white flex justify-between items-center">
                <h3 class="font-extrabold text-[#1e293b] text-xl tracking-tight italic flex items-center gap-3">
                    <span class="w-2 h-6 pink-gradient rounded-full"></span>
                    Active Assignments
                </h3>
                <span class="text-[10px] font-black text-pink-300 uppercase tracking-widest"><?= $tasks->num_rows ?> Tasks Found</span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-[11px] uppercase font-black text-pink-300 tracking-[0.1em] border-b border-pink-50">
                            <th class="px-8 py-5">Task ID</th>
                            <th class="px-8 py-5">Project Details</th>
                            <th class="px-8 py-5">Deadline</th>
                            <th class="px-8 py-5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-pink-50">
                        <?php if ($tasks->num_rows > 0): ?>
                            <?php while($row = $tasks->fetch_assoc()): ?>
                            <tr class="hover:bg-[#FFF9FA] transition-all">
                                <td class="px-8 py-6 font-mono text-[11px] text-gray-400 font-bold">#<?= htmlspecialchars($row['task_id']) ?></td>
                                <td class="px-8 py-6">
                                    <p class="text-sm font-extrabold text-[#1e293b]"><?= htmlspecialchars($row['task_title']) ?></p>
                                    <span class="inline-block mt-1 px-2 py-0.5 rounded-md text-[9px] font-black uppercase <?= $row['priority'] == 'High' ? 'bg-red-50 text-red-500' : 'bg-pink-50 text-pink-400' ?>">
                                        <?= htmlspecialchars($row['priority']) ?> Priority
                                    </span>
                                </td>
                                <td class="px-8 py-6">
                                    <div class="flex items-center gap-2 text-xs font-bold text-gray-500">
                                        <i data-lucide="calendar-days" class="w-3.5 h-3.5"></i>
                                        <?= date('M d, Y', strtotime($row['due_date'])) ?>
                                    </div>
                                </td>
                                <td class="px-8 py-6 text-right">
                                    <a href="tasks.php" class="inline-flex items-center gap-1.5 bg-pink-50 hover:bg-[#FB6F92] text-[#FB6F92] hover:text-white px-4 py-2.5 rounded-xl text-xs font-black transition-all shadow-sm">
                                        <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                                        Task Details
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="px-8 py-20 text-center">
                                    <div class="w-16 h-16 bg-pink-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                                        <i data-lucide="coffee" class="w-8 h-8 text-pink-200"></i>
                                    </div>
                                    <p class="text-sm font-bold text-gray-400 italic">No tasks currently assigned. Take a break!</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-8">
            <div class="glass-card p-8 flex flex-col items-center text-center">
                <h4 class="text-[11px] font-black text-pink-300 uppercase tracking-widest mb-8">Efficiency Rating</h4>
                
                <div class="relative w-40 h-40 flex items-center justify-center mb-6">
                    <svg class="w-40 h-40 transform -rotate-90 absolute" viewBox="0 0 36 36">
                        <path class="text-pink-50" stroke-width="3" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        <path class="text-[#FB6F92]" stroke-dasharray="<?= number_format($performance) ?>, 100" stroke-linecap="round" stroke-width="3" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" style="transition: stroke-dasharray 1s ease-out;" />
                    </svg>
                    <div class="flex flex-col items-center justify-center z-10">
                        <span class="text-4xl font-black text-[#1e293b] tracking-tighter"><?= number_format($performance) ?>%</span>
                    </div>
                </div>
                <p class="text-xs font-bold text-gray-500">Your current performance score based on verified assignments.</p>
            </div>

            <div class="pink-gradient p-8 rounded-[2.5rem] text-white shadow-xl shadow-pink-200/50 relative overflow-hidden">
                <i data-lucide="sparkles" class="absolute -right-4 -bottom-4 w-32 h-32 opacity-10 rotate-12"></i>
                <h4 class="text-xs font-black uppercase tracking-[0.2em] mb-4 opacity-80">Efficiency Insight</h4>
                <div class="flex items-center justify-between mb-4">
                    <span class="text-3xl font-extrabold italic tracking-tight">AI Audit</span>
                    <i data-lucide="cpu" class="w-10 h-10 opacity-50"></i>
                </div>
                <p class="text-xs font-bold leading-relaxed">OptiTask is currently monitoring your task completion speed. Faster updates boost your performance score!</p>
            </div>
            
            <div class="bg-white p-8 rounded-[2.5rem] shadow-xl shadow-pink-100/30 border border-pink-50">
                <h4 class="font-extrabold text-[#1e293b] mb-6 flex items-center gap-3">
                    <i data-lucide="bell" class="w-5 h-5 text-[#FB6F92]"></i> 
                    Latest Alerts
                </h4>
                <div class="space-y-4">
                    <div class="p-4 bg-[#FFF9FA] rounded-2xl border-l-4 border-[#FB6F92]">
                        <p class="text-[10px] font-black text-[#FB6F92] uppercase mb-1">Status: Operational</p>
                        <p class="text-xs font-bold text-gray-600">-</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    lucide.createIcons();

    // Logout Confirmation Logic (Matches Manager Dashboard)
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
</script>
</body>
</html>