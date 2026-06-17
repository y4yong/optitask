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
$active_users = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'Employee'")->fetch_row()[0];

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
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
  body { font-family: 'Quicksand', sans-serif; background-color: #FFF5F7; }

  .pink-gradient { background: linear-gradient(135deg, #FB6F92 0%, #FFB3C6 100%); }

  .sidebar-active{
    background: rgba(251, 111, 146, 0.08);
    border-left: 4px solid #FB6F92;
    color: #FB6F92;
    font-weight: 700;
    border-radius: 0.75rem;
  }
  .sidebar-active i{ color:#FB6F92; }

  .sidebar-link{ color:#6b7280; }
  .sidebar-link:hover{ background:#fff1f2; color:#FB6F92; border-radius:0.75rem; }
  .sidebar-link:hover i{ color:#FB6F92; }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="flex h-screen overflow-hidden">

<aside class="w-72 bg-white border-r border-gray-100 flex flex-col">

  <!-- LOGO -->
  <div class="p-8 flex items-center gap-3">
    <div class="w-10 h-10 pink-gradient rounded-xl flex items-center justify-center text-white shadow-lg shadow-pink-100">
      <i data-lucide="layers" class="w-6 h-6"></i>
    </div>
    <span class="text-2xl font-extrabold tracking-tight text-gray-800">
      OptiTask<span class="text-[#FB6F92]">.</span>
    </span>
  </div>

  <!-- NAV -->
  <nav class="flex-1 px-4 space-y-1">
    <p class="text-[10px] uppercase tracking-widest text-gray-400 font-bold px-4 mb-3">Admin Panel</p>

    <a href="dashboard.php"
       class="<?= $active==='dashboard' ? 'sidebar-active' : 'sidebar-link' ?> flex items-center gap-3 px-4 py-3 transition-all">
      <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
      Dashboard
    </a>

    <a href="audit.php"
       class="<?= $active==='audit' ? 'sidebar-active' : 'sidebar-link' ?> flex items-center gap-3 px-4 py-3 transition-all">
      <i data-lucide="shield-alert" class="w-5 h-5"></i>
      Audit Trail
    </a>

    <a href="manage_users.php"
       class="<?= $active==='manage_users' ? 'sidebar-active' : 'sidebar-link' ?> flex items-center gap-3 px-4 py-3 transition-all">
      <i data-lucide="users" class="w-5 h-5"></i>
      Manage Users
    </a>
  </nav>

  <!-- USER CARD -->
  <div class="p-4 border-t border-gray-50">
    <div class="bg-gray-50 rounded-2xl p-4 flex items-center gap-3">
      <div class="w-10 h-10 rounded-full bg-pink-100 flex items-center justify-center text-[#FB6F92] font-bold">AD</div>
      <div class="flex-1 min-w-0">
        <p class="text-xs font-bold text-gray-800 truncate"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></p>
        <p class="text-[10px] text-gray-500 truncate"><?= htmlspecialchars($_SESSION['user_id']) ?></p>
      </div>
      <a href="#" onclick="confirmLogout(event)"><i data-lucide="log-out" class="w-4 h-4 text-gray-400 hover:text-[#FB6F92] ml-auto cursor-pointer"></i></a>
    </div>
  </div>

</aside>

    <main class="flex-1 overflow-y-auto bg-[#FFF5F7] p-8">
        
        <header class="flex justify-between items-center mb-10">
            <div>
                <h1 class="text-3xl font-black text-gray-900 italic">ADMINISTRATION DASHBOARD</h1>
                <p class="text-gray-500 text-sm">Real-time data for Jan 2026</p>
            </div>
            <div class="flex gap-4">
                <div class="relative">
                    <input type="text" placeholder="Search tasks..." class="bg-white border border-gray-200 rounded-full pl-10 pr-4 py-2 text-sm focus:ring-2 focus:ring-pink-100 outline-none w-64 transition-all">
                    <i data-lucide="search" class="w-4 h-4 text-gray-400 absolute left-4 top-2.5"></i>
                </div>
                <button class="bg-[#FF8FAB] hover:bg-[#FB6F92] text-white px-6 py-2.5 rounded-full font-bold shadow-lg shadow-pink-100 transition-all text-sm flex items-center gap-2 hover:scale-[1.02] transform duration-300">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i> Create Task
                </button>
            </div>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
            <?php
                $stats = [
                    ['title' => 'Total Tasks', 'value' => $total_tasks, 'trend' => '', 'icon' => 'briefcase'],
                    ['title' => 'Completed', 'value' => $completed_tasks, 'trend' => '', 'icon' => 'check-circle'],
                    ['title' => 'Pending Review', 'value' => $pending_tasks, 'trend' => '', 'icon' => 'clock'],
                    ['title' => 'Active Users', 'value' => $active_users, 'trend' => '', 'icon' => 'users'],
                ];
                foreach ($stats as $s):
            ?>
            <div class="glass-card p-6 rounded-[2rem] shadow-sm relative overflow-hidden group hover:scale-105 transition-transform">
                <div class="absolute -right-4 -top-4 w-24 h-24 bg-pink-50 rounded-full group-hover:bg-pink-100 transition-colors"></div>
                <i data-lucide="<?= $s['icon'] ?>" class="w-6 h-6 text-[#FB6F92] mb-4 relative"></i>
                <p class="text-gray-500 text-xs font-bold uppercase tracking-widest relative"><?= $s['title'] ?></p>
                <div class="flex items-end gap-2 relative">
                    <h2 class="text-3xl font-black text-gray-800"><?= $s['value'] ?></h2>
                    <span class="text-[10px] font-bold text-green-500 mb-1"><?= $s['trend'] ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="glass-card rounded-[2rem] shadow-sm p-8 bg-white border border-gray-100">
                <h3 class="font-bold text-gray-800 mb-6 flex items-center gap-2">
                    <i data-lucide="pie-chart" class="w-5 h-5 text-[#FB6F92]"></i> Task Status Distribution
                </h3>
                <div class="relative h-[300px] w-full flex justify-center">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

            <div class="glass-card rounded-[2rem] shadow-sm p-8 bg-white border border-gray-100">
                <h3 class="font-bold text-gray-800 mb-6 flex items-center gap-2">
                    <i data-lucide="bar-chart-3" class="w-5 h-5 text-[#FB6F92]"></i> Department Productivity
                </h3>
                <div class="relative h-[300px] w-full">
                    <canvas id="deptChart"></canvas>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        lucide.createIcons();
        
        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($status_labels) ?>,
                datasets: [{
                    data: <?= json_encode($status_counts) ?>,
                    backgroundColor: ['#FF8FAB', '#FB6F92', '#FFB3C6', '#FFD1DC', '#FFE5EC'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
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
                    barThickness: 30
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, grid: { borderDash: [5, 5] } },
                    x: { grid: { display: false } }
                },
                plugins: { legend: { display: false } }
            }
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