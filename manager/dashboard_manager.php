<?php
/**
 * OptiTask System - Manager Dashboard
 * Theme: Ultra-Pink Edition
 */
session_start();
require_once '../db_config.php'; 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Manager') {
    header("Location: ../login.php");
    exit();
}

$active = 'dashboard';
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch departments for filter
$depts_res = $conn->query("SELECT dept_id, dept_name FROM departments ORDER BY dept_name ASC");

// 1. Fetch Stats
$task_res = $conn->query("SELECT COUNT(*) as total FROM tasks");
$total_tasks = $task_res->fetch_assoc()['total'];

$emp_res = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'Employee'");
$total_employees = $emp_res->fetch_assoc()['total'];

$done_res = $conn->query("SELECT COUNT(*) as total FROM tasks WHERE task_status = 'Done'");
$total_done = $done_res->fetch_assoc()['total'];

// 2. Fetch Workforce Monitoring Data
$workforce_query = "SELECT u.user_id, u.username, 
                    (SELECT COUNT(*) FROM tasks t WHERE t.employee_id = u.user_id AND t.task_status = 'Done') as completed,
                    (SELECT COUNT(*) FROM tasks t WHERE t.employee_id = u.user_id) as total_tasks,
                    p.performance_percentage
                    FROM users u
                    LEFT JOIN performance p ON u.user_id = p.user_id
                    WHERE u.role = 'Employee'
                    LIMIT 5";
$workforce_result = $conn->query($workforce_query);

// 3. Leaderboard Calculation
$selected_dept = $_GET['dept_filter'] ?? 'all';

$leaderboard_query = "SELECT u.user_id, u.username, d.dept_name, u.dept_id,
              (SELECT COUNT(*) FROM tasks t WHERE t.employee_id = u.user_id AND t.task_status = 'Done' OR t.task_status = 'Verified') as completed,
              (SELECT COUNT(*) FROM tasks t WHERE t.employee_id = u.user_id) as total_tasks
              FROM users u 
              LEFT JOIN departments d ON u.dept_id = d.dept_id 
              WHERE u.role = 'Employee'";
              
if ($selected_dept !== 'all') {
    $leaderboard_query .= " AND u.dept_id = " . (int)$selected_dept;
}

$leaderboard_res = $conn->query($leaderboard_query);

$ranked_employees = [];
if ($leaderboard_res) {
    while ($row = $leaderboard_res->fetch_assoc()) {
        $score = ($row['total_tasks'] > 0) ? ($row['completed'] / $row['total_tasks']) * 100 : 0;
        $row['score'] = $score;
        $ranked_employees[] = $row;
    }
}

// Sort by score DESC, then completed DESC
usort($ranked_employees, function($a, $b) {
    if ($a['score'] == $b['score']) {
        return $b['completed'] <=> $a['completed'];
    }
    return $b['score'] <=> $a['score'];
});

$top3 = array_slice($ranked_employees, 0, 3);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OptiTask | Manager Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Quicksand', sans-serif; background-color: #FFF5F7; }
        .pink-gradient { background: linear-gradient(135deg, #FB6F92 0%, #FFB3C6 100%); }
        
        /* SIDEBAR UI MATCHED TO ASSIGN_TASKS */
        .sidebar-active {
            background: #FFE4EA; 
            border-left: 6px solid #FB6F92;
            color: #FB6F92;
            font-weight: 800;
            border-radius: 0 1.5rem 1.5rem 0;
        }
        .sidebar-link { color: #64748b; font-weight: 600; font-size: 0.95rem; }
        .sidebar-link:hover { color: #FB6F92; background: #FFF0F3; border-radius: 0 1.5rem 1.5rem 0; }

        /* Card Animations */
        .stat-card { transition: all 0.3s ease; border: 1px solid #FFE4EA; }
        .stat-card:hover { transform: translateY(-5px); border-color: #FB6F92; box-shadow: 0 10px 25px rgba(251, 111, 146, 0.1); }
        
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #FFF5F7; }
        ::-webkit-scrollbar-thumb { background: #FFD1DC; border-radius: 10px; }
    </style>
</head>
<body class="flex h-screen overflow-hidden relative">

<!-- Full Ranking Modal -->
<div id="rankingModal" class="fixed inset-0 z-50 hidden bg-gray-900/40 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-2xl rounded-[3rem] shadow-2xl overflow-hidden flex flex-col max-h-[80vh] border border-pink-100">
        <div class="p-8 border-b border-pink-50 bg-[#FFF9FA] flex justify-between items-center">
            <h3 class="text-2xl font-extrabold text-[#1e293b] flex items-center gap-3">
                <i data-lucide="list-ordered" class="w-6 h-6 text-[#FB6F92]"></i>
                Full Team Ranking
            </h3>
            <button onclick="document.getElementById('rankingModal').classList.add('hidden')" class="w-10 h-10 bg-white rounded-full flex items-center justify-center text-gray-400 hover:text-red-500 shadow-sm border border-gray-100 transition-all hover:scale-110">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <div class="overflow-y-auto p-6">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[10px] uppercase font-black text-pink-300 tracking-[0.1em] border-b border-pink-50">
                        <th class="px-6 py-4">Rank</th>
                        <th class="px-6 py-4">Employee</th>
                        <th class="px-6 py-4">Department</th>
                        <th class="px-6 py-4 text-right">Score</th>
                    </tr>
                </thead>
                <tbody class="text-sm font-semibold divide-y divide-gray-50">
                    <?php if (count($ranked_employees) > 0): ?>
                        <?php foreach($ranked_employees as $idx => $emp): ?>
                        <tr class="hover:bg-[#FFF9FA] transition-all">
                            <td class="px-6 py-4">
                                <span class="w-8 h-8 rounded-full bg-pink-50 text-[#FB6F92] flex items-center justify-center font-black text-xs border border-pink-100">
                                    <?= $idx + 1 ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-[#1e293b] font-extrabold text-sm"><?= htmlspecialchars($emp['username']) ?></p>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-gray-500 font-bold text-[11px] uppercase"><?= htmlspecialchars($emp['dept_name'] ?? 'N/A') ?></p>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span class="inline-flex items-center px-3 py-1 rounded-lg bg-green-50 text-green-600 font-black text-xs">
                                    <?= number_format($emp['score'], 1) ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-gray-400 font-bold">No employees found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<aside class="w-72 bg-white border-r border-pink-100 flex flex-col z-10 relative">
    <div class="p-8 pb-10 flex items-center gap-3">
        <div class="w-12 h-12 pink-gradient rounded-2xl flex items-center justify-center text-white shadow-lg shadow-pink-100">
            <i data-lucide="briefcase" class="w-6 h-6"></i>
        </div>
        <span class="text-2xl font-bold tracking-tight text-[#1e293b]">
            OptiTask<span class="text-[#FB6F92]">.</span>
        </span>
    </div>

    <nav class="flex-1 space-y-2 pr-4">
        <p class="text-[11px] uppercase tracking-[0.2em] text-pink-300 font-bold px-8 mb-4">Manager</p>
        
        <a href="dashboard_manager.php" class="sidebar-active flex items-center gap-4 px-8 py-4 transition-all">
            <i data-lucide="layout-grid" class="w-5 h-5"></i> Dashboard
        </a>
        
        <a href="verify_tasks.php" class="sidebar-link flex items-center gap-4 px-8 py-4 transition-all">
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
    <header class="mb-10 flex justify-between items-end">
        <div>
            <h1 class="text-4xl font-extrabold text-[#1e293b] tracking-tight uppercase">Manager Dashboard</h1>
            <p class="text-pink-400 mt-1 font-bold italic">Monitoring team efficiency and performance metrics.</p>
        </div>
        <div class="bg-white px-6 py-3 rounded-2xl shadow-sm border border-pink-50 flex items-center gap-3">
            <i data-lucide="calendar" class="w-5 h-5 text-[#FB6F92]"></i>
            <span class="font-bold text-[#1e293b]"><?= date('l, d M Y') ?></span>
        </div>
    </header>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">
        <div class="bg-white p-8 rounded-[2.5rem] stat-card shadow-sm">
            <div class="w-12 h-12 bg-blue-50 text-blue-500 rounded-2xl flex items-center justify-center mb-4"><i data-lucide="layers"></i></div>
            <p class="text-pink-300 text-[11px] font-black uppercase tracking-widest">Total Assigned</p>
            <h2 class="text-3xl font-black text-[#1e293b] mt-1"><?= $total_tasks ?></h2>
        </div>

        <div class="bg-white p-8 rounded-[2.5rem] stat-card shadow-sm">
            <div class="w-12 h-12 bg-pink-50 text-[#FB6F92] rounded-2xl flex items-center justify-center mb-4"><i data-lucide="users"></i></div>
            <p class="text-pink-300 text-[11px] font-black uppercase tracking-widest">Workforce</p>
            <h2 class="text-3xl font-black text-[#1e293b] mt-1"><?= $total_employees ?></h2>
        </div>

        <div class="bg-white p-8 rounded-[2.5rem] stat-card shadow-sm">
            <div class="w-12 h-12 bg-green-50 text-green-500 rounded-2xl flex items-center justify-center mb-4"><i data-lucide="check-square"></i></div>
            <p class="text-pink-300 text-[11px] font-black uppercase tracking-widest">Verified Tasks</p>
            <h2 class="text-3xl font-black text-[#1e293b] mt-1"><?= $total_done ?></h2>
        </div>

        <div class="bg-white p-8 rounded-[2.5rem] stat-card shadow-sm">
            <div class="w-12 h-12 bg-orange-50 text-orange-500 rounded-2xl flex items-center justify-center mb-4"><i data-lucide="trending-up"></i></div>
            <p class="text-pink-300 text-[11px] font-black uppercase tracking-widest">AI Status</p>
            <h2 class="text-3xl font-black text-[#1e293b] mt-1">Active</h2>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Team Rankings Section -->
        <div class="lg:col-span-1 bg-white rounded-[3rem] shadow-xl shadow-pink-100/50 border border-pink-50 overflow-hidden flex flex-col">
            <div class="p-6 border-b border-pink-50 bg-[#FFF9FA] flex flex-col gap-4">
                <h3 class="text-xl font-extrabold text-[#1e293b] flex items-center gap-2">
                    <i data-lucide="award" class="w-6 h-6 text-[#FB6F92]"></i>
                    Team Leaderboard
                </h3>
                <select onchange="window.location.href='dashboard_manager.php?dept_filter=' + this.value" class="w-full bg-white rounded-xl px-4 py-2.5 text-xs font-bold text-[#1e293b] outline-none border-2 border-pink-50 focus:border-[#FB6F92] transition-all cursor-pointer shadow-sm">
                    <option value="all" <?= $selected_dept == 'all' ? 'selected' : '' ?>>Overall Ranking</option>
                    <?php 
                    $depts_res->data_seek(0);
                    while($d = $depts_res->fetch_assoc()): 
                    ?>
                        <option value="<?= $d['dept_id'] ?>" <?= $selected_dept == $d['dept_id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['dept_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="p-6 flex-1 flex flex-col">
                <?php if(count($top3) > 0): ?>
                <div class="flex flex-col gap-4">
                    <?php foreach($top3 as $index => $emp): 
                        $bg = 'bg-white border-gray-100';
                        $icon_color = 'text-gray-400';
                        
                        if ($index == 0) {
                            $bg = 'pink-gradient text-white border-transparent shadow-md shadow-pink-200/50';
                            $icon_color = 'text-yellow-300';
                        } elseif ($index == 1) {
                            $bg = 'bg-[#F8FAFC] border-gray-200';
                            $icon_color = 'text-[#94a3b8]';
                        } elseif ($index == 2) {
                            $bg = 'bg-[#FFF7ED] border-[#ffedd5]';
                            $icon_color = 'text-[#f97316]';
                        }
                    ?>
                    <div class="p-4 rounded-2xl border-2 <?= $bg ?> flex items-center gap-4 transition-transform hover:-translate-y-1 relative">
                        <div class="w-10 h-10 shrink-0 rounded-full bg-white shadow-sm flex items-center justify-center font-black text-[#1e293b] text-xs border border-pink-50">
                            #<?= $index + 1 ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-sm font-extrabold truncate <?= $index == 0 ? 'text-white' : 'text-[#1e293b]' ?>"><?= htmlspecialchars($emp['username']) ?></h4>
                            <p class="text-[9px] font-black uppercase tracking-widest <?= $index == 0 ? 'text-pink-100' : 'text-gray-400' ?> truncate">
                                <?= htmlspecialchars($emp['dept_name'] ?? 'Unassigned Dept') ?>
                            </p>
                        </div>
                        <div class="text-right">
                            <div class="inline-flex items-center px-3 py-1.5 rounded-lg <?= $index == 0 ? 'bg-white/20' : 'bg-white shadow-sm' ?> font-black text-xs">
                                <?= number_format($emp['score'], 1) ?>%
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="mt-auto pt-6 border-t border-pink-50 mt-6 text-center">
                    <button onclick="document.getElementById('rankingModal').classList.remove('hidden')" class="w-full py-3 bg-[#FFF9FA] text-[#FB6F92] font-black uppercase tracking-[0.1em] text-[10px] rounded-xl border-2 border-pink-100 hover:bg-[#FB6F92] hover:text-white hover:border-[#FB6F92] transition-all shadow-sm flex items-center justify-center gap-2">
                        <i data-lucide="expand" class="w-3.5 h-3.5"></i>
                        Full Ranking
                    </button>
                </div>
                <?php else: ?>
                    <div class="text-center py-10 flex flex-col items-center">
                        <div class="w-16 h-16 bg-pink-50 rounded-full flex items-center justify-center mb-3">
                            <i data-lucide="ghost" class="w-8 h-8 text-pink-200"></i>
                        </div>
                        <p class="text-gray-400 font-bold text-xs">No employees found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Workforce Monitoring Section -->
        <div class="lg:col-span-2 bg-white rounded-[3rem] shadow-xl shadow-pink-100/50 border border-pink-50 overflow-hidden">
            <div class="p-8 border-b border-pink-50 flex justify-between items-center bg-white">
                <h3 class="text-xl font-extrabold text-[#1e293b] flex items-center gap-3">
                    <span class="w-2 h-8 pink-gradient rounded-full"></span>
                    Workforce Monitoring
                </h3>
                <button class="text-pink-400 font-bold text-xs hover:underline uppercase tracking-widest">View All</button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-[#FFF9FA] text-[10px] uppercase font-black text-pink-300 tracking-[0.1em]">
                            <th class="px-8 py-5">Employee Info</th>
                            <th class="px-8 py-5">Task Progress</th>
                            <th class="px-8 py-5">Score</th>
                            <th class="px-8 py-5 text-right">Activity</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm font-semibold">
                        <?php while($row = $workforce_result->fetch_assoc()): ?>
                        <tr class="border-b border-pink-50 hover:bg-[#FFF9FA] transition-all">
                            <td class="px-8 py-5">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-[#FFF5F7] border border-pink-100 flex items-center justify-center font-black text-[#FB6F92] text-xs">
                                        <?= strtoupper(substr($row['username'], 0, 2)) ?>
                                    </div>
                                    <div>
                                        <p class="text-[#1e293b] font-extrabold text-sm"><?= htmlspecialchars($row['username']) ?></p>
                                        <p class="text-pink-400 text-[10px] font-bold uppercase"><?= htmlspecialchars($row['user_id']) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-8 py-5">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-[10px] text-[#1e293b] font-bold"><?= $row['completed'] ?> / <?= $row['total_tasks'] ?> Done</span>
                                    <?php $percent = ($row['total_tasks'] > 0) ? ($row['completed'] / $row['total_tasks']) * 100 : 0; ?>
                                    <span class="text-[10px] text-pink-500 font-black"><?= round($percent) ?>%</span>
                                </div>
                                <div class="w-full h-2 bg-pink-50 rounded-full overflow-hidden">
                                    <div class="h-full pink-gradient rounded-full" style="width: <?= $percent ?>%"></div>
                                </div>
                            </td>
                            <td class="px-8 py-5">
                                <div class="inline-flex items-center px-3 py-1.5 rounded-lg bg-[#FFF5F7] text-[#FB6F92] border border-pink-100 font-black text-[10px]">
                                    <?= number_format($percent) ?>.00
                                </div>
                            </td>
                            <td class="px-8 py-5 text-right">
                                <button class="w-8 h-8 rounded-lg hover:bg-pink-50 text-pink-200 hover:text-[#FB6F92] transition-all">
                                    <i data-lucide="external-link" class="w-4 h-4 mx-auto"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

<script>
    lucide.createIcons();

    // Matching SweetAlert Logout
    document.getElementById('logout-btn').addEventListener('click', function() {
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