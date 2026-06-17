<?php
/**
 * OptiTask System - Employee Performance Report
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
$active = 'performance';

// Check for Unread Notifications for Sidebar Red Dot
$unread_query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND status = 'unread'";
$stmt_unread = $conn->prepare($unread_query);
$stmt_unread->bind_param("s", $user_id);
$stmt_unread->execute();
$unread_count = $stmt_unread->get_result()->fetch_assoc()['total'];
$stmt_unread->close();


function calculateCompletionRate($verifiedTasks, $totalTasks) {
    if ($totalTasks == 0) {
        return 0;
    }
    return ($verifiedTasks / $totalTasks) * 100;
}

// 2. Fetch Tasks Data to Calculate Performance
$task_query = "SELECT task_status FROM tasks WHERE employee_id = ?";
$stmt_task = $conn->prepare($task_query);
$stmt_task->bind_param("s", $user_id);
$stmt_task->execute();
$tasks_result = $stmt_task->get_result();

$totalTasks = 0;
$completedTasks = 0;

while ($row = $tasks_result->fetch_assoc()) {
    $totalTasks++;
    if ($row['task_status'] === 'Verified' || $row['task_status'] === 'Done') {
        $completedTasks++;
    }
}

$completionRate = calculateCompletionRate($completedTasks, $totalTasks);

// 3. Fetch Full Task Data for Breakdown
$breakdown_query = "SELECT task_id, task_title, task_status, priority, due_date FROM tasks WHERE employee_id = ? ORDER BY due_date DESC";
$stmt_breakdown = $conn->prepare($breakdown_query);
$stmt_breakdown->bind_param("s", $user_id);
$stmt_breakdown->execute();
$breakdown_tasks = $stmt_breakdown->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OptiTask | Performance Report</title>
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
            <a href="skills.php" class="sidebar-link flex items-center gap-4 px-8 py-4 transition-all">
                <i data-lucide="user" class="w-5 h-5"></i> Profile
            </a>
            <a href="performance.php" class="sidebar-active flex items-center gap-4 px-8 py-4 transition-all">
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

<!-- Main Content -->
<main class="flex-1 overflow-y-auto p-12">
    <header class="flex justify-between items-center mb-12">
        <div>
            <h1 class="text-4xl font-extrabold text-[#1e293b] tracking-tight uppercase">Performance Report</h1>
            <p class="text-pink-400 mt-1 font-bold italic">Overview of your task completion and productivity.</p>
        </div>
        
        <div class="flex gap-4">
            <button class="bg-white border-2 border-pink-50 hover:border-pink-200 text-[#1e293b] px-6 py-3 rounded-2xl font-bold shadow-xl shadow-pink-100/30 transition-all text-xs uppercase tracking-widest flex items-center gap-2">
                <i data-lucide="download" class="w-4 h-4 text-[#FB6F92]"></i> Export PDF
            </button>
        </div>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- KPI 1 -->
        <div class="glass-card p-8">
            <p class="text-[10px] font-black text-pink-300 uppercase tracking-widest">Completion Rate</p>
            <div class="flex items-end gap-3 mt-3">
                <h2 class="text-5xl font-black text-[#FB6F92]"><?= number_format($completionRate, 1) ?>%</h2>
            </div>
            <p class="text-xs font-bold text-gray-400 mt-3">Verified / Total Tasks</p>
            <div class="mt-6 h-3 bg-pink-50 rounded-full overflow-hidden">
                <div class="h-full pink-gradient w-[<?= $completionRate ?>%]"></div>
            </div>
        </div>

        <!-- KPI 2 -->
        <div class="glass-card p-8">
            <p class="text-[10px] font-black text-pink-300 uppercase tracking-widest">Tasks Completed</p>
            <div class="flex items-end gap-3 mt-3">
                <h2 class="text-5xl font-black text-[#1e293b]"><?= $completedTasks ?> <span class="text-2xl text-gray-300">/ <?= $totalTasks ?></span></h2>
            </div>
            <p class="text-xs font-bold text-gray-400 mt-3">Total resolved assignments.</p>
            <div class="mt-6 flex gap-2">
                <!-- Mini visualization -->
                <?php for($i=0; $i<7; $i++): ?>
                    <div class="h-8 flex-1 rounded-lg bg-pink-<?= rand(50, 200) ?>"></div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- KPI 3 -->
        <div class="pink-gradient rounded-[2.5rem] shadow-xl shadow-pink-100/50 p-8 text-white relative overflow-hidden">
            <i data-lucide="award" class="absolute -right-4 -bottom-4 w-32 h-32 opacity-10 rotate-12"></i>
            <p class="text-[10px] font-black uppercase tracking-[0.2em] opacity-80">Productivity Badge</p>
            <div class="flex items-center justify-between mt-4">
                <h2 class="text-5xl font-black italic tracking-tight">
                    <?= $completionRate >= 80 ? 'A+' : ($completionRate >= 60 ? 'B' : 'C') ?>
                </h2>
                <i data-lucide="shield-check" class="w-12 h-12 opacity-50"></i>
            </div>
            <p class="text-xs font-bold mt-4 leading-relaxed">
                <?= $completionRate >= 80 ? 'You are performing exceptionally well. Keep it up!' : 'There is room for improvement. Focus on pending tasks.' ?>
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mt-8">
        <!-- Breakdown table -->
        <div class="lg:col-span-2 glass-card overflow-hidden">
            <div class="p-8 border-b border-pink-50 bg-white flex justify-between items-center">
                <h3 class="font-extrabold text-[#1e293b] text-xl tracking-tight italic flex items-center gap-3">
                    <span class="w-2 h-6 pink-gradient rounded-full"></span>
                    Task Breakdown
                </h3>
                <span class="text-[10px] font-black text-pink-300 uppercase tracking-widest"><?= $breakdown_tasks->num_rows ?> Tasks</span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-[11px] uppercase font-black text-pink-300 tracking-[0.1em] border-b border-pink-50">
                            <th class="px-8 py-5">Task Details</th>
                            <th class="px-8 py-5">Due Date</th>
                            <th class="px-8 py-5">Priority</th>
                            <th class="px-8 py-5 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-pink-50">
                        <?php if ($breakdown_tasks->num_rows > 0): ?>
                            <?php while($task = $breakdown_tasks->fetch_assoc()): ?>
                            <tr class="hover:bg-[#FFF9FA] transition-all">
                                <td class="px-8 py-6">
                                    <p class="text-sm font-extrabold text-[#1e293b]"><?= htmlspecialchars($task['task_title']) ?></p>
                                    <p class="text-[10px] text-gray-400 font-bold font-mono mt-1">#<?= htmlspecialchars($task['task_id']) ?></p>
                                </td>
                                <td class="px-8 py-6">
                                    <div class="flex items-center gap-2 text-xs font-bold text-gray-500">
                                        <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                                        <?= date('M d, Y', strtotime($task['due_date'])) ?>
                                    </div>
                                </td>
                                <td class="px-8 py-6">
                                    <span class="inline-block px-2 py-0.5 rounded-md text-[9px] font-black uppercase <?= $task['priority'] == 'High' ? 'bg-red-50 text-red-500' : ($task['priority'] == 'Medium' ? 'bg-yellow-50 text-yellow-600' : 'bg-green-50 text-green-500') ?>">
                                        <?= htmlspecialchars($task['priority']) ?>
                                    </span>
                                </td>
                                <td class="px-8 py-6 text-right">
                                    <?php 
                                    $status_color = 'bg-gray-100 text-gray-600';
                                    if ($task['task_status'] === 'Verified' || $task['task_status'] === 'Done') $status_color = 'bg-pink-100 text-[#FB6F92]';
                                    if ($task['task_status'] === 'In Progress') $status_color = 'bg-blue-50 text-blue-500';
                                    ?>
                                    <span class="px-3 py-1 rounded-full text-[10px] font-black <?= $status_color ?> border-none shadow-sm">
                                        <?= htmlspecialchars($task['task_status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="px-8 py-12 text-center text-sm font-bold text-gray-400">
                                    No tasks assigned yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Alerts/Notes -->
        <div class="glass-card p-8 h-fit">
            <h4 class="font-extrabold text-[#1e293b] mb-6 flex items-center gap-3">
                <i data-lucide="lightbulb" class="w-5 h-5 text-[#FB6F92]"></i> 
                System Insights
            </h4>
            <div class="space-y-4">
                <div class="p-5 bg-[#FFF9FA] rounded-2xl border-l-4 border-[#FB6F92]">
                    <p class="text-[11px] font-black text-[#FB6F92] uppercase mb-1">Tip</p>
                    <p class="text-xs font-bold text-gray-600">Completing tasks before their deadline significantly boosts your performance rating.</p>
                </div>
                <div class="p-5 bg-white border border-pink-50 rounded-2xl border-l-4 border-gray-200">
                    <p class="text-[11px] font-black text-gray-400 uppercase mb-1">Notice</p>
                    <p class="text-xs font-bold text-gray-500">Make sure to provide adequate updates when marking tasks as Done for quick verification.</p>
                </div>
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
</script>
</body>
</html>
