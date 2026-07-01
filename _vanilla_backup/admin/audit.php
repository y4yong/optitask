<?php
session_start();
require_once '../db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

$active = 'audit';
$month_filter = $_GET['month'] ?? '';

$query = "SELECT l.*, u.username, u.role FROM audit_logs l LEFT JOIN users u ON l.user_id = u.user_id";
if ($month_filter) {
    $stmt = $conn->prepare("SELECT l.*, u.username, u.role FROM audit_logs l LEFT JOIN users u ON l.user_id = u.user_id WHERE DATE_FORMAT(l.timestamp, '%Y-%m') = ? ORDER BY l.timestamp DESC");
    $stmt->bind_param("s", $month_filter);
    $stmt->execute();
    $logs_res = $stmt->get_result();
} else {
    $logs_res = $conn->query($query . " ORDER BY l.timestamp DESC");
}

$logs = [];
$unique_actors = [];
$alert_count = 0;
$login_count = 0;

while($row = $logs_res->fetch_assoc()) {
    $logs[] = $row;
    
    // Calculate actors
    $actor = $row['username'] ?? $row['user_id'] ?? 'System';
    if ($actor !== 'System') {
        $unique_actors[$actor] = true;
    }
    
    // Check for high-alert events (DELETE actions, password resets, status changes)
    $act = strtoupper($row['action']);
    if (strpos($act, 'DELETE') !== false || strpos($act, 'RESET') !== false || strpos($act, 'SUSPEND') !== false) {
        $alert_count++;
    }
    
    // Check login count
    if ($act === 'LOGIN') {
        $login_count++;
    }
}
$total_logs = count($logs);
$total_actors = count($unique_actors);
$recent_logs = array_slice($logs, 0, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>OptiTask | Audit Trail</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
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
    
    .log-row {
        transition: all 0.2s ease;
    }
    .log-row:hover {
        background-color: rgba(255, 241, 242, 0.5) !important;
    }
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

    <a href="dashboard_admin.php" class="sidebar-link flex items-center gap-4 px-8 py-4 transition-all">
      <i data-lucide="layout-dashboard" class="w-5 h-5"></i> Dashboard
    </a>

    <a href="audit.php" class="sidebar-active flex items-center gap-4 px-8 py-4 transition-all">
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
  <header class="flex justify-between items-end mb-12">
    <div>
      <h1 class="text-4xl font-extrabold text-[#1e293b] tracking-tight uppercase">Audit Trail</h1>
      <p class="text-pink-400 mt-1 font-bold italic">Live system events, logins, modifications, and security actions.</p>
    </div>

    <div class="flex gap-4 items-center">
      <form method="GET" action="audit.php" class="flex gap-2 items-center bg-white border border-pink-100 rounded-full p-1.5 shadow-sm">
        <input type="month" name="month" value="<?= htmlspecialchars($month_filter) ?>" class="bg-transparent text-xs font-bold text-gray-700 outline-none px-4 py-2 cursor-pointer">
        <button type="submit" class="bg-gray-800 hover:bg-gray-700 text-white px-5 py-2.5 rounded-full text-xs font-bold transition-all">Filter</button>
        <?php if($month_filter): ?>
          <a href="audit.php" class="text-xs font-bold text-gray-400 hover:text-red-500 px-3 transition-colors">Clear</a>
        <?php endif; ?>
      </form>
      <button onclick="downloadPDF()" class="bg-[#FF8FAB] hover:bg-[#FB6F92] text-white px-6 py-3.5 rounded-full font-bold shadow-lg shadow-pink-100 transition-all text-xs flex items-center gap-2 hover:scale-[1.02] transform duration-300">
        <i data-lucide="download" class="w-4.5 h-4.5"></i> Export Report
      </button>
    </div>
  </header>

  <!-- KPI Metrics Row -->
  <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-10">
      <!-- Total Logs -->
      <div class="glass-card p-6 relative overflow-hidden group">
          <div class="absolute -right-4 -top-4 w-20 h-20 bg-pink-50 rounded-full group-hover:bg-pink-100/70 transition-colors"></div>
          <div class="flex items-center gap-3 mb-3 relative z-10">
              <div class="w-10 h-10 rounded-xl bg-pink-50 flex items-center justify-center text-[#FB6F92]">
                  <i data-lucide="database" class="w-5 h-5"></i>
              </div>
              <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Total Actions</span>
          </div>
          <h2 class="text-3xl font-black text-gray-800 relative z-10"><?= $total_logs ?></h2>
          <p class="text-[10px] text-pink-400 font-bold mt-1.5 uppercase tracking-wide">Recorded events in view</p>
      </div>

      <!-- Active Operators -->
      <div class="glass-card p-6 relative overflow-hidden group">
          <div class="absolute -right-4 -top-4 w-20 h-20 bg-indigo-50 rounded-full group-hover:bg-indigo-100/75 transition-colors"></div>
          <div class="flex items-center gap-3 mb-3 relative z-10">
              <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-500">
                  <i data-lucide="shield-check" class="w-5 h-5"></i>
              </div>
              <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Active Actors</span>
          </div>
          <h2 class="text-3xl font-black text-gray-800 relative z-10"><?= $total_actors ?></h2>
          <p class="text-[10px] text-indigo-400 font-bold mt-1.5 uppercase tracking-wide">Staff performing actions</p>
      </div>

      <!-- High Alert Events -->
      <div class="glass-card p-6 relative overflow-hidden group">
          <div class="absolute -right-4 -top-4 w-20 h-20 bg-rose-50 rounded-full group-hover:bg-rose-100/75 transition-colors"></div>
          <div class="flex items-center gap-3 mb-3 relative z-10">
              <div class="w-10 h-10 rounded-xl bg-rose-50 flex items-center justify-center text-rose-500">
                  <i data-lucide="alert-octagon" class="w-5 h-5"></i>
              </div>
              <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Alert actions</span>
          </div>
          <h2 class="text-3xl font-black text-gray-800 relative z-10"><?= $alert_count ?></h2>
          <p class="text-[10px] text-rose-400 font-bold mt-1.5 uppercase tracking-wide">Deletions & Resets</p>
      </div>

      <!-- Logins / Sessions -->
      <div class="glass-card p-6 relative overflow-hidden group">
          <div class="absolute -right-4 -top-4 w-20 h-20 bg-emerald-50 rounded-full group-hover:bg-emerald-100/75 transition-colors"></div>
          <div class="flex items-center gap-3 mb-3 relative z-10">
              <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-500">
                  <i data-lucide="key-round" class="w-5 h-5"></i>
              </div>
              <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">User Logins</span>
          </div>
          <h2 class="text-3xl font-black text-gray-800 relative z-10"><?= $login_count ?></h2>
          <p class="text-[10px] text-emerald-400 font-bold mt-1.5 uppercase tracking-wide">Successful sessions</p>
      </div>
  </div>

  <!-- Search & Category Filters -->
  <div class="glass-card p-6 mb-8 flex flex-col md:flex-row gap-6 justify-between items-center bg-white/60">
      <div class="flex flex-wrap gap-2 items-center justify-start w-full md:w-auto">
          <button data-category="all" class="filter-tab px-5 py-2.5 rounded-full text-xs font-bold transition-all bg-[#FB6F92] text-white shadow-md shadow-pink-100">
              All Events
          </button>
          <button data-category="auth" class="filter-tab px-5 py-2.5 rounded-full text-xs font-bold transition-all bg-white text-gray-600 border border-pink-50 hover:bg-pink-50">
              Authentication
          </button>
          <button data-category="users" class="filter-tab px-5 py-2.5 rounded-full text-xs font-bold transition-all bg-white text-gray-600 border border-pink-50 hover:bg-pink-50">
              Deletion
          </button>
          <button data-category="tasks" class="filter-tab px-5 py-2.5 rounded-full text-xs font-bold transition-all bg-white text-gray-600 border border-pink-50 hover:bg-pink-50">
              Task Operations
          </button>
          <button data-category="skills" class="filter-tab px-5 py-2.5 rounded-full text-xs font-bold transition-all bg-white text-gray-600 border border-pink-50 hover:bg-pink-50">
              Skill Settings
          </button>
      </div>
      
      <div class="relative w-full md:w-72">
          <input type="text" id="logSearch" placeholder="Search actor, action, or details..." class="w-full bg-white border border-pink-100 rounded-full pl-11 pr-5 py-3 text-xs font-bold text-gray-700 outline-none focus:border-[#FB6F92] focus:ring-2 focus:ring-pink-50 transition-all shadow-sm">
          <div class="absolute left-4 top-3.5 text-pink-300">
              <i data-lucide="search" class="w-4 h-4"></i>
          </div>
      </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Timeline (Left) -->
    <div class="glass-card p-8 lg:col-span-1 h-fit flex flex-col bg-white/70">
      <h3 class="font-extrabold text-[#1e293b] text-xl mb-6 flex items-center gap-2">
        <i data-lucide="activity" class="w-5 h-5 text-[#FB6F92]"></i> Live Timeline
      </h3>
      <div class="space-y-6 relative border-l-2 border-pink-100 ml-3 pl-6">
        <?php if (empty($recent_logs)): ?>
          <p class="text-xs text-gray-400 font-bold italic">No recent activity detected.</p>
        <?php else: ?>
          <?php foreach($recent_logs as $log): ?>
            <?php 
              $isLoginLogout = in_array($log['action'], ['LOGIN', 'LOGOUT']);
              $color = $isLoginLogout ? 'bg-gray-400 ring-gray-100' : 'bg-[#FB6F92] ring-pink-100 shadow-pink-100 shadow-lg';
              $actor = htmlspecialchars($log['username'] ?? $log['user_id'] ?? 'System');
            ?>
            <div class="relative group">
              <div class="absolute -left-[31px] top-1.5 w-3 h-3 rounded-full <?= $color ?> ring-4 transition-all group-hover:scale-125"></div>
              <p class="text-xs font-black text-gray-800 uppercase tracking-wider"><?= htmlspecialchars($log['action']) ?></p>
              <p class="text-[10px] text-gray-500 font-bold mt-1 leading-relaxed"><?= htmlspecialchars($log['details']) ?></p>
              <div class="flex items-center gap-2 mt-2">
                <span class="text-[9px] bg-pink-50 text-[#FB6F92] px-2 py-0.5 rounded-md font-bold uppercase tracking-wider"><?= $actor ?></span>
                <span class="text-[9px] text-gray-400 font-mono flex items-center gap-1">
                    <i data-lucide="clock" class="w-2.5 h-2.5"></i>
                    <?= date('h:i A', strtotime($log['timestamp'])) ?>
                </span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Log Table (Right) -->
    <div id="pdf-content" class="lg:col-span-2 glass-card overflow-hidden">
      <div class="p-8 border-b border-pink-50 flex justify-between items-center bg-white/50">
        <h3 class="font-extrabold text-[#1e293b] text-xl flex items-center gap-2">
          <i data-lucide="list" class="w-5 h-5 text-[#FB6F92]"></i> Master Event Log
          <?php if($month_filter): ?>
             <span class="text-[9px] bg-pink-100 text-[#FB6F92] px-3 py-1 rounded-full uppercase ml-2 tracking-widest font-black"><?= date('F Y', strtotime($month_filter . '-01')) ?></span>
          <?php endif; ?>
        </h3>
      </div>

      <div class="overflow-x-auto max-h-[600px]">
        <table class="w-full text-left">
          <thead class="sticky top-0 bg-[#FFF9FA] border-b border-pink-50 z-10">
            <tr class="text-[10px] uppercase font-black text-pink-300 tracking-[0.1em]">
              <th class="px-8 py-5">Timestamp</th>
              <th class="px-8 py-5">Actor / System</th>
              <th class="px-8 py-5">Event Action</th>
              <th class="px-8 py-5">Details</th>
            </tr>
          </thead>
          <tbody class="text-sm font-semibold divide-y divide-pink-50 bg-white/50">
            <?php if (empty($logs)): ?>
              <tr>
                <td colspan="4" class="px-8 py-16 text-center text-gray-400 text-xs font-bold uppercase tracking-widest">No matching logs found</td>
              </tr>
            <?php else: ?>
              <?php foreach($logs as $log): ?>
              <?php 
                $actor = htmlspecialchars($log['username'] ?? $log['user_id'] ?? 'System');
                $role = htmlspecialchars($log['role'] ?? 'System');
                $action = htmlspecialchars($log['action']);
                $details = htmlspecialchars($log['details']);
                $timestamp = $log['timestamp'];

                // Define role colors
                $role_badge = 'bg-gray-100 text-gray-600 border border-gray-200';
                if ($role === 'Admin') $role_badge = 'bg-pink-50 text-[#FB6F92] border border-pink-100';
                elseif ($role === 'Manager') $role_badge = 'bg-indigo-50 text-indigo-600 border border-indigo-100';
                elseif ($role === 'Employee') $role_badge = 'bg-emerald-50 text-emerald-600 border border-emerald-100';

                // Define action specific colors and icons
                $action_badge = 'bg-gray-50 text-gray-600 border border-gray-200';
                $action_icon = 'activity';
                
                $act_upper = strtoupper($action);
                if (strpos($act_upper, 'DELETE') !== false) {
                    $action_badge = 'bg-rose-50 text-rose-600 border border-rose-100';
                    $action_icon = 'trash-2';
                } elseif (strpos($act_upper, 'CREATE') !== false) {
                    $action_badge = 'bg-emerald-50 text-emerald-600 border border-emerald-100';
                    $action_icon = 'plus-circle';
                } elseif (strpos($act_upper, 'LOGIN') !== false) {
                    $action_badge = 'bg-violet-50 text-violet-600 border-violet-100';
                    $action_icon = 'key-round';
                } elseif (strpos($act_upper, 'LOGOUT') !== false) {
                    $action_badge = 'bg-slate-100 text-slate-600 border-slate-200';
                    $action_icon = 'log-out';
                } elseif ($act_upper === 'REGISTER') {
                    $action_badge = 'bg-indigo-50 text-indigo-600 border-indigo-100';
                    $action_icon = 'user-plus';
                } elseif (strpos($act_upper, 'TASK') !== false) {
                    $action_badge = 'bg-pink-50 text-[#FB6F92] border border-pink-100';
                    $action_icon = 'briefcase';
                } elseif (strpos($act_upper, 'SKILL') !== false) {
                    $action_badge = 'bg-sky-50 text-sky-600 border-sky-100';
                    $action_icon = 'cpu';
                } elseif (strpos($act_upper, 'PASSWORD') !== false || strpos($act_upper, 'RESET') !== false) {
                    $action_badge = 'bg-amber-50 text-amber-600 border-amber-100';
                    $action_icon = 'lock';
                }
              ?>
              <tr class="log-row border-b border-pink-50/50" data-action="<?= $action ?>" data-actor="<?= $actor ?>" data-details="<?= $details ?>">
                <td class="px-8 py-5 text-xs font-bold text-gray-400 font-mono">
                  <?= date('M d, Y', strtotime($timestamp)) ?><br>
                  <span class="text-[10px] text-gray-400 font-normal"><?= date('h:i:s A', strtotime($timestamp)) ?></span>
                </td>
                <td class="px-8 py-5">
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-pink-50 text-[#FB6F92] flex items-center justify-center font-black text-xs">
                      <?= strtoupper(substr($actor, 0, 2)) ?>
                    </div>
                    <div>
                      <p class="font-extrabold text-gray-800 text-sm"><?= $actor ?></p>
                      <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider <?= $role_badge ?>"><?= $role ?></span>
                    </div>
                  </div>
                </td>
                <td class="px-8 py-5">
                  <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[9px] font-black border uppercase tracking-wider <?= $action_badge ?> shadow-sm">
                    <i data-lucide="<?= $action_icon ?>" class="w-3.5 h-3.5"></i>
                    <?= $action ?>
                  </span>
                </td>
                <td class="px-8 py-5">
                  <p class="text-xs text-gray-600 font-bold leading-relaxed"><?= $details ?></p>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<script>
  lucide.createIcons();
  
  // Real-time client side search & category filtering
  const logSearch = document.getElementById('logSearch');
  const filterTabs = document.querySelectorAll('.filter-tab');
  const tableRows = document.querySelectorAll('.log-row');

  function filterLogs() {
      const searchValue = logSearch.value.toLowerCase().trim();
      let activeCategory = 'all';
      filterTabs.forEach(tab => {
          if (tab.classList.contains('bg-[#FB6F92]')) {
              activeCategory = tab.dataset.category;
          }
      });

      tableRows.forEach(row => {
          const action = (row.dataset.action || '').toUpperCase();
          const actor = (row.dataset.actor || '').toLowerCase();
          const details = (row.dataset.details || '').toLowerCase();
          const textContent = `${action} ${actor} ${details}`;

          // Check category
          let categoryMatch = false;
          if (activeCategory === 'all') {
              categoryMatch = true;
          } else if (activeCategory === 'auth') {
              categoryMatch = ['LOGIN', 'LOGOUT', 'REGISTER'].includes(action);
          } else if (activeCategory === 'users') {
              categoryMatch = ['CREATE_USER', 'DELETE_USER', 'UPDATE_USER_STATUS', 'RESET_PASSWORD'].includes(action);
          } else if (activeCategory === 'tasks') {
              categoryMatch = ['CREATE_TASK', 'ASSIGN_TASK', 'SUBMIT_TASK', 'VERIFY_TASK'].includes(action);
          } else if (activeCategory === 'skills') {
              categoryMatch = ['UPDATE_SKILL', 'DELETE_SKILL'].includes(action);
          }

          // Check search text
          const searchMatch = textContent.includes(searchValue);

          if (categoryMatch && searchMatch) {
              row.style.display = '';
          } else {
              row.style.display = 'none';
          }
      });
  }

  logSearch.addEventListener('input', filterLogs);
  filterTabs.forEach(tab => {
      tab.addEventListener('click', () => {
          filterTabs.forEach(t => {
              t.classList.remove('bg-[#FB6F92]', 'text-white', 'shadow-md', 'shadow-pink-100');
              t.classList.add('bg-white', 'text-gray-600', 'border-pink-50', 'hover:bg-pink-50');
          });
          tab.classList.add('bg-[#FB6F92]', 'text-white', 'shadow-md', 'shadow-pink-100');
          tab.classList.remove('bg-white', 'text-gray-600', 'border-pink-50', 'hover:bg-pink-50');
          filterLogs();
      });
  });

  function downloadPDF() {
    const element = document.getElementById('pdf-content');
    const opt = {
      margin:       0.5,
      filename:     'Audit_Report_<?= $month_filter ?: "All" ?>.pdf',
      image:        { type: 'jpeg', quality: 0.98 },
      html2canvas:  { scale: 2 },
      jsPDF:        { unit: 'in', format: 'letter', orientation: 'landscape' }
    };
    
    // Temporary styling for PDF
    element.classList.remove('overflow-hidden');
    const tableDiv = element.querySelector('.overflow-x-auto');
    tableDiv.classList.remove('max-h-[600px]');
    
    html2pdf().set(opt).from(element).save().then(() => {
      // Restore styling
      element.classList.add('overflow-hidden');
      tableDiv.classList.add('max-h-[600px]');
    });
  }

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
