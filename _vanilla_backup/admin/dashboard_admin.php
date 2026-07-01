<?php
session_start();
require_once '../db_config.php';

// Check role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

$active = 'dashboard';

// 1. KPI Queries
$total_tasks = $conn->query("SELECT COUNT(*) FROM tasks")->fetch_row()[0];
$completed_tasks = $conn->query("SELECT COUNT(*) FROM tasks WHERE task_status IN ('Done', 'Verified')")->fetch_row()[0];
$pending_tasks = $conn->query("SELECT COUNT(*) FROM tasks WHERE task_status = 'Review'")->fetch_row()[0];
$total_users = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];

// Extra Admin Dashboard details
$count_emp = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'Employee'")->fetch_row()[0];
$count_mgr = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'Manager'")->fetch_row()[0];
$count_admin = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'Admin'")->fetch_row()[0];
$active_accts = $conn->query("SELECT COUNT(*) FROM users WHERE account_status = 'Active'")->fetch_row()[0];
$suspended_accts = $conn->query("SELECT COUNT(*) FROM users WHERE account_status = 'Suspended'")->fetch_row()[0];

// Recent events (audit logs)
$logs_res = $conn->query("SELECT l.*, u.username, u.role FROM audit_logs l LEFT JOIN users u ON l.user_id = u.user_id ORDER BY l.timestamp DESC LIMIT 5");
$recent_logs = [];
if ($logs_res) {
    while($row = $logs_res->fetch_assoc()) {
        $recent_logs[] = $row;
    }
}

// Recent tasks
$tasks_res = $conn->query("SELECT t.*, u.username FROM tasks t LEFT JOIN users u ON t.employee_id = u.user_id ORDER BY t.start_date DESC LIMIT 5");
$recent_tasks = [];
if ($tasks_res) {
    while($row = $tasks_res->fetch_assoc()) {
        $recent_tasks[] = $row;
    }
}

// 2. Chart Data: Task Status Distribution
$status_res = $conn->query("SELECT task_status, COUNT(*) as count FROM tasks GROUP BY task_status");
$status_labels = [];
$status_counts = [];
while($row = $status_res->fetch_assoc()){
    $status_labels[] = $row['task_status'];
    $status_counts[] = $row['count'];
}

// 3. Chart Data: Department Productivity
$dept_res = $conn->query("
    SELECT d.dept_name, COUNT(t.task_id) as completed 
    FROM departments d
    LEFT JOIN users u ON d.dept_id = u.dept_id
    LEFT JOIN tasks t ON u.user_id = t.employee_id AND t.task_status IN ('Done', 'Verified')
    GROUP BY d.dept_id
");
$dept_labels = [];
$dept_counts = [];
while($row = $dept_res->fetch_assoc()){
    $dept_labels[] = $row['dept_name'];
    $dept_counts[] = $row['completed'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OptiTask | Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Quicksand:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Quicksand', sans-serif; background-color: #FFF5F7; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Outfit', sans-serif; }
        
        .pink-gradient { background: linear-gradient(135deg, #FB6F92 0%, #FFB3C6 100%); }
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
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #FFF5F7; }
        ::-webkit-scrollbar-thumb { background: #FFD1DC; border-radius: 10px; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="flex h-screen overflow-hidden">

<aside class="w-72 bg-white border-r border-pink-100 flex flex-col">
    <!-- LOGO -->
    <div class="p-8 pb-10 flex items-center gap-3">
        <div class="w-12 h-12 pink-gradient rounded-2xl flex items-center justify-center text-white shadow-lg shadow-pink-100">
            <i data-lucide="layers" class="w-6 h-6"></i>
        </div>
        <span class="text-2xl font-extrabold tracking-tight text-gray-800">
            OptiTask<span class="text-[#FB6F92]">.</span>
        </span>
    </div>

    <!-- NAV -->
    <nav class="flex-1 space-y-2 pr-4">
        <p class="text-[11px] uppercase tracking-[0.2em] text-pink-300 font-bold px-8 mb-4">Admin Panel</p>

        <a href="dashboard_admin.php" class="sidebar-active flex items-center gap-4 px-8 py-4 transition-all">
            <i data-lucide="layout-dashboard" class="w-5 h-5"></i> Dashboard
        </a>

        <a href="audit.php" class="sidebar-link flex items-center gap-4 px-8 py-4 transition-all">
            <i data-lucide="shield-alert" class="w-5 h-5"></i> Audit Trail
        </a>

        <a href="manage_users.php" class="sidebar-link flex items-center gap-4 px-8 py-4 transition-all">
            <i data-lucide="users" class="w-5 h-5"></i> Manage Users
        </a>
    </nav>

    <!-- USER CARD -->
    <div class="p-6">
        <div class="bg-[#FFF9FA] rounded-[1.5rem] p-4 flex items-center gap-3 border border-pink-100">
            <div class="w-10 h-10 rounded-full bg-white border-2 border-pink-200 text-[#FB6F92] flex items-center justify-center font-bold text-sm">AD</div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-extrabold text-[#1e293b] truncate"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></p>
                <p class="text-[11px] text-pink-400 font-bold uppercase tracking-widest">ID: <?= htmlspecialchars($_SESSION['user_id']) ?></p>
            </div>
            <a href="#" onclick="confirmLogout(event)">
                <i data-lucide="log-out" class="w-5 h-5 text-pink-200 hover:text-red-500 cursor-pointer transition-colors"></i>
            </a>
        </div>
    </div>
</aside>

<main class="flex-1 overflow-y-auto bg-[#FFF5F7] p-12">
    <!-- Header -->
    <header class="flex justify-between items-end mb-12">
        <div>
            <h1 class="text-4xl font-extrabold text-[#1e293b] tracking-tight uppercase">Admin Dashboard</h1>
            <p class="text-pink-400 mt-1 font-bold italic">Real-time system health and administration metrics.</p>
        </div>
        <div class="bg-white px-6 py-3 rounded-2xl shadow-sm border border-pink-50 flex items-center gap-3">
            <div class="w-2.5 h-2.5 rounded-full bg-green-500 animate-pulse"></div>
            <span class="font-bold text-[#1e293b] text-xs uppercase tracking-wider">System: Active</span>
        </div>
    </header>

    <!-- KPI Grid -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-12">
        <!-- Card 1: Total Users -->
        <div class="glass-card p-8 relative overflow-hidden group">
            <div class="absolute -right-4 -top-4 w-24 h-24 bg-pink-50 rounded-full group-hover:bg-pink-100/70 transition-colors"></div>
            <i data-lucide="users" class="w-6 h-6 text-[#FB6F92] mb-4 relative z-10"></i>
            <p class="text-gray-400 text-[10px] font-bold uppercase tracking-widest relative z-10">Total Accounts</p>
            <h2 class="text-4xl font-black text-gray-800 mt-2 relative z-10"><?= $total_users ?></h2>
            <div class="flex gap-4 mt-3 text-[10px] font-bold text-gray-400 relative z-10">
                <span class="text-green-500"><?= $active_accts ?> Active</span>
                <span class="text-red-400"><?= $suspended_accts ?> Suspended</span>
            </div>
        </div>

        <!-- Card 2: Total Tasks -->
        <div class="glass-card p-8 relative overflow-hidden group">
            <div class="absolute -right-4 -top-4 w-24 h-24 bg-pink-50 rounded-full group-hover:bg-pink-100/70 transition-colors"></div>
            <i data-lucide="briefcase" class="w-6 h-6 text-[#FB6F92] mb-4 relative z-10"></i>
            <p class="text-gray-400 text-[10px] font-bold uppercase tracking-widest relative z-10">Total Tasks</p>
            <h2 class="text-4xl font-black text-gray-800 mt-2 relative z-10"><?= $total_tasks ?></h2>
            <p class="text-gray-400 text-[10px] font-semibold mt-3 relative z-10"><?= $pending_tasks ?> Pending Review</p>
        </div>

        <!-- Card 3: Completion Rate -->
        <div class="glass-card p-8 relative overflow-hidden group">
            <div class="absolute -right-4 -top-4 w-24 h-24 bg-pink-50 rounded-full group-hover:bg-pink-100/70 transition-colors"></div>
            <i data-lucide="check-circle" class="w-6 h-6 text-[#FB6F92] mb-4 relative z-10"></i>
            <p class="text-gray-400 text-[10px] font-bold uppercase tracking-widest relative z-10">Task Completion</p>
            <?php 
                $rate = ($total_tasks > 0) ? ($completed_tasks / $total_tasks) * 100 : 0;
            ?>
            <h2 class="text-4xl font-black text-gray-800 mt-2 relative z-10"><?= number_format($rate, 1) ?>%</h2>
            <div class="mt-4 w-full bg-pink-100 rounded-full h-1.5 overflow-hidden relative z-10">
                <div class="pink-gradient h-1.5 rounded-full" style="width: <?= $rate ?>%"></div>
            </div>
        </div>

        <!-- Card 4: Workforce Mix -->
        <div class="glass-card p-8 relative overflow-hidden group">
            <div class="absolute -right-4 -top-4 w-24 h-24 bg-pink-50 rounded-full group-hover:bg-pink-100/70 transition-colors"></div>
            <i data-lucide="shield" class="w-6 h-6 text-[#FB6F92] mb-4 relative z-10"></i>
            <p class="text-gray-400 text-[10px] font-bold uppercase tracking-widest relative z-10">Workforce Roles</p>
            <h2 class="text-4xl font-black text-gray-800 mt-2 relative z-10"><?= $count_emp + $count_mgr ?></h2>
            <div class="flex gap-4 mt-3 text-[10px] font-bold text-gray-400 relative z-10">
                <span><?= $count_mgr ?> Managers</span>
                <span><?= $count_emp ?> Employees</span>
            </div>
        </div>
    </div>

    <!-- Row 1: Graphical Performance Analytics & Combined Tabbed Monitor/Matrix -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
        <!-- Left Column: Graphical Performance Analytics -->
        <div class="lg:col-span-2">
            <div class="glass-card p-8 h-full flex flex-col justify-between">
                <h3 class="font-extrabold text-[#1e293b] text-xl mb-6 flex items-center gap-3">
                    <span class="w-2.5 h-6 pink-gradient rounded-full"></span>
                    Graphical Performance Analytics
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 flex-1 items-center">
                    <!-- Status Distribution Chart -->
                    <div class="flex flex-col items-center">
                        <h4 class="text-xs font-black uppercase text-pink-400 tracking-wider mb-2">Task Status Distribution</h4>
                        <div class="relative h-[220px] w-full flex justify-center">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                    <!-- Department Productivity Chart -->
                    <div class="flex flex-col items-center">
                        <h4 class="text-xs font-black uppercase text-pink-400 tracking-wider mb-2">Department Productivity</h4>
                        <div class="relative h-[220px] w-full">
                            <canvas id="deptChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Combined Live Activity Monitor & Staffing Matrix -->
        <div class="lg:col-span-1">
            <div class="glass-card p-8 flex flex-col h-full justify-between">
                <!-- Tab Headers -->
                <div class="flex gap-2 p-1.5 bg-pink-50/50 rounded-2xl mb-6">
                    <button id="tab-activity-btn" class="flex-1 py-2 text-xs font-black rounded-xl transition-all pink-gradient text-white shadow-sm">
                        Activity Monitor
                    </button>
                    <button id="tab-matrix-btn" class="flex-1 py-2 text-xs font-black text-gray-500 rounded-xl hover:text-pink-500 hover:bg-white/50 transition-all">
                        Staff Matrix
                    </button>
                </div>

                <!-- Tab 1 Content: Live Activity Monitor -->
                <div id="tab-activity-content" class="flex-1 flex flex-col justify-between">
                    <div class="space-y-5 relative border-l-2 border-pink-100 ml-2 pl-6 overflow-y-auto max-h-[220px] pr-2">
                        <?php if (empty($recent_logs)): ?>
                            <p class="text-xs text-gray-400 font-bold italic">No activity detected.</p>
                        <?php else: ?>
                            <?php foreach($recent_logs as $log): ?>
                                <?php 
                                    $isAuth = in_array($log['action'], ['LOGIN', 'LOGOUT']);
                                    $dot_color = $isAuth ? 'bg-gray-400 ring-gray-100' : 'bg-[#FB6F92] ring-pink-100 shadow-pink-100 shadow-lg';
                                ?>
                                <div class="relative">
                                    <div class="absolute -left-[31px] top-1.5 w-3 h-3 rounded-full <?= $dot_color ?> ring-4"></div>
                                    <p class="text-xs font-black text-gray-800 uppercase tracking-wider"><?= htmlspecialchars($log['action']) ?></p>
                                    <p class="text-[10px] text-gray-500 font-medium mt-0.5 leading-relaxed truncate" title="<?= htmlspecialchars($log['details']) ?>"><?= htmlspecialchars($log['details']) ?></p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-[8px] bg-pink-50 text-pink-500 px-1.5 py-0.5 rounded font-bold uppercase tracking-wider"><?= htmlspecialchars($log['username'] ?? 'System') ?></span>
                                        <span class="text-[8px] text-gray-400 font-mono"><?= date('h:i A', strtotime($log['timestamp'])) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="mt-4 pt-4 border-t border-pink-50 text-center">
                        <a href="audit.php" class="inline-flex items-center gap-2 text-xs font-extrabold text-[#FB6F92] hover:text-[#FF8FAB] transition-colors uppercase tracking-widest">
                            View Full Event Log <i data-lucide="chevron-right" class="w-4 h-4"></i>
                        </a>
                    </div>
                </div>

                <!-- Tab 2 Content: Staffing Matrix -->
                <div id="tab-matrix-content" class="hidden flex-1 flex flex-col justify-center space-y-5">
                    <!-- Managers Progress -->
                    <div>
                        <div class="flex justify-between text-xs font-bold text-gray-700 mb-1.5">
                            <span>Managers</span>
                            <span><?= $count_mgr ?></span>
                        </div>
                        <div class="w-full bg-pink-50 rounded-full h-2 overflow-hidden">
                            <div class="pink-gradient h-2 rounded-full" style="width: <?= ($total_users > 0) ? ($count_mgr / $total_users) * 100 : 0 ?>%"></div>
                        </div>
                    </div>
                    <!-- Employees Progress -->
                    <div>
                        <div class="flex justify-between text-xs font-bold text-gray-700 mb-1.5">
                            <span>Employees</span>
                            <span><?= $count_emp ?></span>
                        </div>
                        <div class="w-full bg-pink-50 rounded-full h-2 overflow-hidden">
                            <div class="pink-gradient h-2 rounded-full" style="width: <?= ($total_users > 0) ? ($count_emp / $total_users) * 100 : 0 ?>%"></div>
                        </div>
                    </div>
                    <!-- Admins Progress -->
                    <div>
                        <div class="flex justify-between text-xs font-bold text-gray-700 mb-1.5">
                            <span>Admins</span>
                            <span><?= $count_admin ?></span>
                        </div>
                        <div class="w-full bg-pink-50 rounded-full h-2 overflow-hidden">
                            <div class="pink-gradient h-2 rounded-full" style="width: <?= ($total_users > 0) ? ($count_admin / $total_users) * 100 : 0 ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 2: Recent Task Assignments (Full Width) -->
    <div class="glass-card overflow-hidden w-full">
        <div class="p-8 border-b border-pink-50 bg-white/50 flex justify-between items-center">
            <h3 class="font-extrabold text-[#1e293b] text-xl flex items-center gap-3">
                <span class="w-2.5 h-6 pink-gradient rounded-full"></span>
                Recent Task Assignments
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-[10px] uppercase font-black text-pink-300 tracking-[0.1em] border-b border-pink-50 bg-[#FFF9FA]">
                        <th class="px-8 py-4">Task Info</th>
                        <th class="px-8 py-4">Assignee</th>
                        <th class="px-8 py-4">Deadline</th>
                        <th class="px-8 py-4 text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="text-sm font-semibold divide-y divide-pink-50">
                    <?php if (count($recent_tasks) > 0): ?>
                        <?php foreach ($recent_tasks as $t): ?>
                        <tr class="hover:bg-[#FFF9FA] transition-colors">
                            <td class="px-8 py-5">
                                <p class="text-gray-800 font-extrabold text-sm"><?= htmlspecialchars($t['task_title']) ?></p>
                                <span class="text-[9px] font-mono text-gray-400 font-bold uppercase tracking-widest mt-0.5 inline-block">#<?= htmlspecialchars($t['task_id']) ?></span>
                            </td>
                            <td class="px-8 py-5">
                                <p class="text-gray-700 font-bold text-xs"><?= htmlspecialchars($t['username'] ?? 'Unassigned') ?></p>
                            </td>
                            <td class="px-8 py-5">
                                <span class="text-gray-500 font-bold text-xs"><?= date('d M Y', strtotime($t['due_date'])) ?></span>
                            </td>
                            <td class="px-8 py-5 text-right">
                                <?php 
                                    $st_color = 'bg-gray-100 text-gray-600';
                                    if ($t['task_status'] === 'To-Do') $st_color = 'bg-pink-50 text-pink-500';
                                    if ($t['task_status'] === 'In Progress') $st_color = 'bg-blue-50 text-blue-500';
                                    if ($t['task_status'] === 'Done') $st_color = 'bg-green-50 text-green-500';
                                    if ($t['task_status'] === 'Verified') $st_color = 'bg-[#EFF6FF] text-[#3B82F6]';
                                ?>
                                <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest <?= $st_color ?>">
                                    <?= htmlspecialchars($t['task_status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-8 py-10 text-center text-gray-400 text-xs italic">No tasks created yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    lucide.createIcons();
    
    // Status Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    const statusLabels = <?= json_encode($status_labels) ?>;
    const statusColors = statusLabels.map(label => {
        if (label === 'Done') return '#10B981'; // Green
        if (label === 'Verified') return '#86EFAC'; // Light green
        if (label === 'In Progress') return '#3B82F6'; // Blue
        if (label === 'To-Do') return '#FB6F92'; // Pink
        return '#CBD5E1'; // Default
    });
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: <?= json_encode($status_counts) ?>,
                backgroundColor: statusColors,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { family: 'Quicksand', weight: 'bold' } } } },
            cutout: '70%'
        }
    });

    // Dept Chart
    const deptCtx = document.getElementById('deptChart').getContext('2d');
    new Chart(deptCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($dept_labels) ?>,
            datasets: [{
                label: 'Completed Tasks',
                data: <?= json_encode($dept_counts) ?>,
                backgroundColor: '#FB6F92',
                borderRadius: 8,
                barThickness: 20
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, grid: { borderDash: [5, 5] }, ticks: { font: { family: 'Quicksand', weight: 'bold' } } },
                x: { grid: { display: false }, ticks: { font: { family: 'Quicksand', weight: 'bold' } } }
            },
            plugins: { legend: { display: false } }
        }
    });

    // Sidebar Tab Switching
    const tabActivityBtn = document.getElementById('tab-activity-btn');
    const tabMatrixBtn = document.getElementById('tab-matrix-btn');
    const tabActivityContent = document.getElementById('tab-activity-content');
    const tabMatrixContent = document.getElementById('tab-matrix-content');

    tabActivityBtn.addEventListener('click', () => {
        tabActivityBtn.className = "flex-1 py-2 text-xs font-black rounded-xl transition-all pink-gradient text-white shadow-sm";
        tabMatrixBtn.className = "flex-1 py-2 text-xs font-black text-gray-500 rounded-xl hover:text-pink-500 hover:bg-white/50 transition-all";
        tabActivityContent.classList.remove('hidden');
        tabActivityContent.classList.add('flex');
        tabMatrixContent.classList.add('hidden');
        tabMatrixContent.classList.remove('flex');
    });

    tabMatrixBtn.addEventListener('click', () => {
        tabMatrixBtn.className = "flex-1 py-2 text-xs font-black rounded-xl transition-all pink-gradient text-white shadow-sm";
        tabActivityBtn.className = "flex-1 py-2 text-xs font-black text-gray-500 rounded-xl hover:text-pink-500 hover:bg-white/50 transition-all";
        tabMatrixContent.classList.remove('hidden');
        tabMatrixContent.classList.add('flex');
        tabActivityContent.classList.add('hidden');
        tabActivityContent.classList.remove('flex');
    });

    function confirmLogout(e) {
        if(e) e.preventDefault();
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
                title: 'font-black text-[#1e293b] font-outfit',
                confirmButton: 'rounded-xl px-6 py-3 font-bold',
                cancelButton: 'rounded-xl px-6 py-3 font-bold'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '../logout.php';
            }
        });
    }
</script>
</body>
</html>