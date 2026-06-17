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
while($row = $logs_res->fetch_assoc()) {
    $logs[] = $row;
}
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
  <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
  body { font-family: 'Quicksand', sans-serif; background-color: #FFF5F7; }
  .pink-gradient { background: linear-gradient(135deg, #FB6F92 0%, #FFB3C6 100%); }
  .sidebar-active{ background: rgba(251, 111, 146, 0.08); border-left: 4px solid #FB6F92; color: #FB6F92; font-weight: 700; border-radius: 0.75rem; }
  .sidebar-active i{ color:#FB6F92; }
  .sidebar-link{ color:#6b7280; }
  .sidebar-link:hover{ background:#fff1f2; color:#FB6F92; border-radius:0.75rem; }
  .sidebar-link:hover i{ color:#FB6F92; }
</style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="flex h-screen overflow-hidden">

<aside class="w-72 bg-white border-r border-gray-100 flex flex-col">
  <div class="p-8 flex items-center gap-3">
    <div class="w-10 h-10 pink-gradient rounded-xl flex items-center justify-center text-white shadow-lg shadow-pink-100">
      <i data-lucide="layers" class="w-6 h-6"></i>
    </div>
    <span class="text-2xl font-extrabold tracking-tight text-gray-800">OptiTask<span class="text-[#FB6F92]">.</span></span>
  </div>

  <nav class="flex-1 px-4 space-y-1">
    <p class="text-[10px] uppercase tracking-widest text-gray-400 font-bold px-4 mb-3">Admin Panel</p>
    <a href="dashboard_admin.php" class="<?= $active==='dashboard' ? 'sidebar-active' : 'sidebar-link' ?> flex items-center gap-3 px-4 py-3 transition-all">
      <i data-lucide="layout-dashboard" class="w-5 h-5"></i> Dashboard
    </a>
    <a href="audit.php" class="<?= $active==='audit' ? 'sidebar-active' : 'sidebar-link' ?> flex items-center gap-3 px-4 py-3 transition-all">
      <i data-lucide="shield-alert" class="w-5 h-5"></i> Audit Trail
    </a>
    <a href="manage_users.php" class="<?= $active==='manage_users' ? 'sidebar-active' : 'sidebar-link' ?> flex items-center gap-3 px-4 py-3 transition-all">
      <i data-lucide="users" class="w-5 h-5"></i> Manage Users
    </a>
  </nav>

  <div class="p-4 border-t border-gray-50">
    <div class="bg-gray-50 rounded-2xl p-4 flex items-center gap-3">
      <div class="w-10 h-10 rounded-full bg-pink-100 flex items-center justify-center text-[#FB6F92] font-bold">AD</div>
      <div>
        <p class="text-xs font-bold text-gray-800"><?= htmlspecialchars($_SESSION['user_id']) ?></p>
        <p class="text-[10px] text-gray-500">Administrator</p>
      </div>
      <a href="#" onclick="confirmLogout(event)" class="ml-auto text-gray-400 hover:text-[#FB6F92] transition-colors"><i data-lucide="log-out" class="w-4 h-4"></i></a>
    </div>
  </div>
</aside>

  <main class="flex-1 overflow-y-auto bg-[#FFF5F7] p-8">
    <header class="flex justify-between items-center mb-10">
      <div>
        <h1 class="text-3xl font-black text-gray-900 italic uppercase">Audit Trail Monitor</h1>
        <p class="text-gray-500 text-sm">Live system events, access logs, and sensitive changes.</p>
      </div>

      <div class="flex gap-4 items-center">
        <form method="GET" action="audit.php" class="flex gap-2 items-center">
          <input type="month" name="month" value="<?= htmlspecialchars($month_filter) ?>" class="bg-white border border-gray-200 rounded-full px-4 py-2 text-sm focus:ring-2 focus:ring-pink-200 outline-none transition-all">
          <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded-full text-sm font-bold shadow-lg shadow-gray-200 transition-all hover:bg-gray-700">Filter</button>
          <?php if($month_filter): ?>
            <a href="audit.php" class="text-gray-400 hover:text-red-500 text-sm font-bold ml-2">Clear</a>
          <?php endif; ?>
        </form>
        <button onclick="downloadPDF()" class="bg-[#FF8FAB] hover:bg-[#FB6F92] text-white px-6 py-2.5 rounded-full font-bold shadow-lg shadow-pink-100 transition-all text-sm flex items-center gap-2 hover:scale-[1.02] transform duration-300">
          <i data-lucide="download" class="w-4 h-4"></i> Export PDF
        </button>
      </div>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Timeline -->
      <div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 p-6 lg:col-span-1 h-fit">
        <h3 class="font-bold text-gray-800 mb-6 flex items-center gap-2">
          <i data-lucide="activity" class="w-5 h-5 text-[#FB6F92]"></i> Live Timeline
        </h3>
        <div class="space-y-6 relative border-l-2 border-pink-50 ml-2 pl-6">
          <?php if (empty($recent_logs)): ?>
            <p class="text-xs text-gray-400 font-bold italic">No recent activity detected.</p>
          <?php else: ?>
            <?php foreach($recent_logs as $log): ?>
              <?php 
                $isLoginLogout = in_array($log['action'], ['LOGIN', 'LOGOUT']);
                $color = $isLoginLogout ? 'bg-gray-300 ring-gray-100' : 'bg-[#FF8FAB] ring-pink-100 shadow-pink-100 shadow-lg';
              ?>
              <div class="relative">
                <div class="absolute -left-[31px] top-1 w-3 h-3 rounded-full <?= $color ?> ring-4"></div>
                <p class="text-xs font-bold <?= $isLoginLogout ? 'text-gray-600' : 'text-gray-800' ?> italic"><?= htmlspecialchars($log['action']) ?></p>
                <p class="text-[10px] text-gray-500 font-mono mt-1"><?= htmlspecialchars($log['details']) ?></p>
                <p class="text-[9px] text-gray-400 mt-1 uppercase"><?= date('h:i A · M d', strtotime($log['timestamp'])) ?></p>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Log Table -->
      <div id="pdf-content" class="lg:col-span-2 bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-50 flex justify-between items-center bg-gray-50/50">
          <h3 class="font-bold text-gray-800 flex items-center gap-2">
            <i data-lucide="list" class="w-5 h-5 text-[#FB6F92]"></i> Event Log
            <?php if($month_filter): ?>
               <span class="text-[10px] bg-pink-100 text-[#FB6F92] px-2 py-1 rounded-full uppercase ml-2"><?= date('F Y', strtotime($month_filter . '-01')) ?></span>
            <?php endif; ?>
          </h3>
        </div>

        <div class="overflow-x-auto max-h-[600px]">
          <table class="w-full text-left">
            <thead class="sticky top-0 bg-white">
              <tr class="text-[10px] uppercase tracking-wider text-gray-400 border-b border-gray-50">
                <th class="px-6 py-4">Timestamp</th>
                <th class="px-6 py-4">Actor</th>
                <th class="px-6 py-4">Action</th>
                <th class="px-6 py-4">Details</th>
              </tr>
            </thead>
            <tbody class="text-sm">
              <?php if (empty($logs)): ?>
                <tr>
                  <td colspan="4" class="px-6 py-10 text-center text-gray-400 text-xs font-bold uppercase tracking-widest">No logs found</td>
                </tr>
              <?php else: ?>
                <?php foreach($logs as $log): ?>
                <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                  <td class="px-6 py-4 text-xs font-bold text-gray-500"><?= date('M d, Y · h:i A', strtotime($log['timestamp'])) ?></td>
                  <td class="px-6 py-4">
                    <p class="font-bold text-gray-800"><?= htmlspecialchars($log['user_id']) ?></p>
                    <p class="text-[10px] text-gray-400"><?= htmlspecialchars($log['role'] ?? 'System') ?></p>
                  </td>
                  <td class="px-6 py-4">
                    <?php 
                      $actionColor = 'bg-gray-100 text-gray-600 border-gray-200';
                      if (strpos($log['action'], 'DELETE') !== false) $actionColor = 'bg-red-100 text-red-600 border-red-200';
                      if (strpos($log['action'], 'CREATE') !== false) $actionColor = 'bg-green-100 text-green-600 border-green-200';
                      if (in_array($log['action'], ['ASSIGN_TASK', 'VERIFY_TASK', 'SUBMIT_TASK'])) $actionColor = 'bg-pink-100 text-[#FB6F92] border-pink-200';
                    ?>
                    <span class="px-3 py-1 rounded-full text-[10px] font-bold border <?= $actionColor ?>"><?= htmlspecialchars($log['action']) ?></span>
                  </td>
                  <td class="px-6 py-4">
                    <p class="text-xs text-gray-600"><?= htmlspecialchars($log['details']) ?></p>
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
      element.classList.remove('lg:col-span-2', 'overflow-hidden');
      const tableDiv = element.querySelector('.overflow-x-auto');
      tableDiv.classList.remove('max-h-[600px]');
      
      html2pdf().set(opt).from(element).save().then(() => {
        // Restore styling
        element.classList.add('lg:col-span-2', 'overflow-hidden');
        tableDiv.classList.add('max-h-[600px]');
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
