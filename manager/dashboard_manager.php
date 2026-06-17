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

// Check for Unread Notifications for Sidebar Red Dot
$unread_query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND status = 'unread'";
$stmt_unread = $conn->prepare($unread_query);
$stmt_unread->bind_param("s", $user_id);
$stmt_unread->execute();
$unread_count = $stmt_unread->get_result()->fetch_assoc()['total'];
$stmt_unread->close();


// Auto-assign existing tasks with no manager to logged-in manager for testing/seeding
$stmt_auto = $conn->prepare("UPDATE tasks SET manager_id = ? WHERE manager_id IS NULL OR manager_id = ''");
if ($stmt_auto) {
    $stmt_auto->bind_param("s", $user_id);
    $stmt_auto->execute();
    $stmt_auto->close();
}

// Fetch departments for filter
$depts_res = $conn->query("SELECT dept_id, dept_name FROM departments ORDER BY dept_name ASC");

// 1. Fetch Stats
$task_res = $conn->prepare("SELECT COUNT(*) as total FROM tasks WHERE manager_id = ?");
$task_res->bind_param("s", $user_id);
$task_res->execute();
$total_tasks = $task_res->get_result()->fetch_assoc()['total'];

$emp_res = $conn->prepare("SELECT COUNT(DISTINCT employee_id) as total FROM tasks WHERE manager_id = ?");
$emp_res->bind_param("s", $user_id);
$emp_res->execute();
$total_employees = $emp_res->get_result()->fetch_assoc()['total'];

$verified_res = $conn->prepare("SELECT COUNT(*) as total FROM tasks WHERE task_status = 'Verified' AND manager_id = ?");
$verified_res->bind_param("s", $user_id);
$verified_res->execute();
$total_verified = $verified_res->get_result()->fetch_assoc()['total'];

// Fetch tasks for modals — JOIN users to get employee name
$assigned_stmt = $conn->prepare("
    SELECT t.task_id, t.task_title, t.description, t.start_date, t.due_date,
           t.task_status, t.priority, t.submission_file, t.task_file,
           u.username as employee_name
    FROM tasks t
    LEFT JOIN users u ON t.employee_id = u.user_id
    WHERE t.manager_id = ?
    ORDER BY t.due_date DESC
");
$assigned_stmt->bind_param("s", $user_id);
$assigned_stmt->execute();
$assigned_tasks = $assigned_stmt->get_result();
$assigned_stmt->close();

$verified_stmt = $conn->prepare("
    SELECT t.task_id, t.task_title, t.description, t.start_date, t.due_date,
           t.task_status, t.priority, t.submission_file, t.task_file,
           u.username as employee_name
    FROM tasks t
    LEFT JOIN users u ON t.employee_id = u.user_id
    WHERE t.manager_id = ? AND t.task_status = 'Verified'
    ORDER BY t.due_date DESC
");
$verified_stmt->bind_param("s", $user_id);
$verified_stmt->execute();
$verified_tasks = $verified_stmt->get_result();
$verified_stmt->close();

// 2. Fetch Workforce Monitoring Data
$workforce_query = "SELECT u.user_id, u.username, 
                    (SELECT COUNT(*) FROM tasks t WHERE t.employee_id = u.user_id AND (t.task_status = 'Done' OR t.task_status = 'Verified') AND t.manager_id = ?) as completed,
                    (SELECT COUNT(*) FROM tasks t WHERE t.employee_id = u.user_id AND t.manager_id = ?) as total_tasks,
                    p.performance_percentage
                    FROM users u
                    LEFT JOIN performance p ON u.user_id = p.user_id
                    WHERE u.role = 'Employee' AND u.user_id IN (SELECT DISTINCT employee_id FROM tasks WHERE manager_id = ?)
                    LIMIT 5";
$stmt_workforce = $conn->prepare($workforce_query);
$stmt_workforce->bind_param("sss", $user_id, $user_id, $user_id);
$stmt_workforce->execute();
$workforce_result = $stmt_workforce->get_result();

// 3. Leaderboard Calculation
$selected_dept = $_GET['dept_filter'] ?? 'all';

$leaderboard_query = "SELECT u.user_id, u.username, d.dept_name, u.dept_id,
              (SELECT COUNT(*) FROM tasks t WHERE t.employee_id = u.user_id AND (t.task_status = 'Done' OR t.task_status = 'Verified') AND t.manager_id = ?) as completed,
              (SELECT COUNT(*) FROM tasks t WHERE t.employee_id = u.user_id AND t.manager_id = ?) as total_tasks
              FROM users u 
              LEFT JOIN departments d ON u.dept_id = d.dept_id 
              WHERE u.role = 'Employee' AND u.user_id IN (SELECT DISTINCT employee_id FROM tasks WHERE manager_id = ?)";
              
if ($selected_dept !== 'all') {
    $leaderboard_query .= " AND u.dept_id = " . (int)$selected_dept;
}

$stmt_leader = $conn->prepare($leaderboard_query);
$stmt_leader->bind_param("sss", $user_id, $user_id, $user_id);
$stmt_leader->execute();
$leaderboard_res = $stmt_leader->get_result();

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

<!-- Assigned Tasks Modal -->
<div id="assignedModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="display:none;background:rgba(15,10,30,0.55);backdrop-filter:blur(6px);">
  <div style="width:100%;max-width:1050px;max-height:88vh;display:flex;flex-direction:column;border-radius:2rem;overflow:hidden;box-shadow:0 32px 80px rgba(251,111,146,0.18),0 2px 24px rgba(0,0,0,0.10);border:1.5px solid rgba(251,111,146,0.18);background:#fff;">
    <!-- Header -->
    <div style="padding:1.5rem 2rem;background:linear-gradient(120deg,#fff0f5 0%,#fff9fb 100%);border-bottom:1.5px solid #fde8ef;display:flex;align-items:center;justify-content:space-between;">
      <div style="display:flex;align-items:center;gap:0.75rem;">
        <div style="width:44px;height:44px;border-radius:14px;background:linear-gradient(135deg,#FB6F92,#ff9ab2);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 14px rgba(251,111,146,0.3);">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        </div>
        <div>
          <h3 style="margin:0;font-size:1.2rem;font-weight:800;color:#1e293b;letter-spacing:-0.02em;">All Assigned Tasks</h3>
          <p style="margin:0;font-size:0.75rem;color:#FB6F92;font-weight:600;">Full task history & files</p>
        </div>
      </div>
      <button onclick="closeModal('assignedModal')" style="width:38px;height:38px;border-radius:50%;background:#fff;border:1.5px solid #fde8ef;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#94a3b8;transition:all 0.2s;" onmouseover="this.style.background='#fff0f5';this.style.color='#FB6F92';" onmouseout="this.style.background='#fff';this.style.color='#94a3b8';">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <!-- Filters -->
    <div style="padding:1rem 2rem;background:#fffafb;border-bottom:1px solid #fde8ef;display:flex;gap:0.75rem;flex-wrap:wrap;align-items:center;">
      <div style="display:flex;align-items:center;gap:0.5rem;background:#fff;border:1.5px solid #fde8ef;border-radius:12px;padding:0.45rem 0.85rem;">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#FB6F92" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <input type="date" id="assignedStartFilter" onchange="filterAssigned()" style="border:none;outline:none;font-size:0.8rem;color:#1e293b;background:transparent;">
      </div>
      <span style="color:#fbb6ca;font-size:0.8rem;">&#8594;</span>
      <div style="display:flex;align-items:center;gap:0.5rem;background:#fff;border:1.5px solid #fde8ef;border-radius:12px;padding:0.45rem 0.85rem;">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#FB6F92" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <input type="date" id="assignedEndFilter" onchange="filterAssigned()" style="border:none;outline:none;font-size:0.8rem;color:#1e293b;background:transparent;">
      </div>
      <select id="assignedStatusFilter" onchange="filterAssigned()" style="background:#fff;border:1.5px solid #fde8ef;border-radius:12px;padding:0.45rem 0.85rem;font-size:0.8rem;color:#1e293b;outline:none;cursor:pointer;">
        <option value="">All Statuses</option>
        <option value="To-Do">To-Do</option>
        <option value="In Progress">In Progress</option>
        <option value="Done">Submitted</option>
        <option value="Verified">Verified</option>
        <option value="Rejected">Rejected</option>
      </select>
      <div style="display:flex;align-items:center;gap:0.5rem;background:#fff;border:1.5px solid #fde8ef;border-radius:12px;padding:0.45rem 0.85rem;flex:1;min-width:160px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#FB6F92" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" id="assignedSearchFilter" onkeyup="filterAssigned()" placeholder="Search ID, title or employee..." style="border:none;outline:none;font-size:0.8rem;color:#1e293b;background:transparent;width:100%;">
      </div>
      <button onclick="clearAssignedFilters()" style="padding:0.45rem 1rem;background:#fde8ef;border:none;border-radius:12px;font-size:0.75rem;font-weight:700;color:#FB6F92;cursor:pointer;transition:all 0.2s;" onmouseover="this.style.background='#FB6F92';this.style.color='#fff';" onmouseout="this.style.background='#fde8ef';this.style.color='#FB6F92';">Clear</button>
    </div>
    <!-- Table -->
    <div style="overflow-y:auto;flex:1;">
      <table style="width:100%;border-collapse:collapse;font-size:0.84rem;">
        <thead style="position:sticky;top:0;z-index:10;">
          <tr style="background:linear-gradient(90deg,#fff0f5,#fff9fb);">
            <th style="padding:0.9rem 1rem;text-align:left;font-size:0.65rem;font-weight:800;color:#FB6F92;letter-spacing:0.08em;text-transform:uppercase;border-bottom:1.5px solid #fde8ef;white-space:nowrap;">Assigned Date</th>
            <th style="padding:0.9rem 1rem;text-align:left;font-size:0.65rem;font-weight:800;color:#FB6F92;letter-spacing:0.08em;text-transform:uppercase;border-bottom:1.5px solid #fde8ef;white-space:nowrap;">Due Date</th>
            <th style="padding:0.9rem 1rem;text-align:left;font-size:0.65rem;font-weight:800;color:#FB6F92;letter-spacing:0.08em;text-transform:uppercase;border-bottom:1.5px solid #fde8ef;white-space:nowrap;">Task ID</th>
            <th style="padding:0.9rem 1rem;text-align:left;font-size:0.65rem;font-weight:800;color:#FB6F92;letter-spacing:0.08em;text-transform:uppercase;border-bottom:1.5px solid #fde8ef;">Task Name</th>
            <th style="padding:0.9rem 1rem;text-align:left;font-size:0.65rem;font-weight:800;color:#FB6F92;letter-spacing:0.08em;text-transform:uppercase;border-bottom:1.5px solid #fde8ef;white-space:nowrap;">Assigned To</th>
            <th style="padding:0.9rem 1rem;text-align:left;font-size:0.65rem;font-weight:800;color:#FB6F92;letter-spacing:0.08em;text-transform:uppercase;border-bottom:1.5px solid #fde8ef;">Priority</th>
            <th style="padding:0.9rem 1rem;text-align:left;font-size:0.65rem;font-weight:800;color:#FB6F92;letter-spacing:0.08em;text-transform:uppercase;border-bottom:1.5px solid #fde8ef;">Status</th>
          </tr>
        </thead>
        <tbody id="assignedTasksBody">
          <?php 
          $assigned_tasks->data_seek(0);
          while($t = $assigned_tasks->fetch_assoc()):
            $priorityColor = ['High'=>'#ef4444','Medium'=>'#f59e0b','Low'=>'#22c55e'];
            $statusColor = ['Verified'=>'#22c55e','Done'=>'#3b82f6','In Progress'=>'#f59e0b','To-Do'=>'#94a3b8','Rejected'=>'#ef4444'];
            $pColor = $priorityColor[$t['priority']] ?? '#94a3b8';
            $sColor = $statusColor[$t['task_status']] ?? '#94a3b8';
            $statusLabel = $t['task_status'] === 'Done' ? 'Submitted' : $t['task_status'];
            $isOverdue = !empty($t['due_date']) && strtotime($t['due_date']) < time() && !in_array($t['task_status'], ['Verified', 'Done']);
          ?>
          <tr class="modal-task-row" style="border-bottom:1px solid #fde8ef;transition:background 0.15s;" onmouseover="this.style.background='#fff8fa';" onmouseout="this.style.background='';">
            <td data-date="<?php echo $t['start_date'] ?? ''; ?>" style="padding:0.85rem 1rem;white-space:nowrap;">
              <span style="font-size:0.78rem;color:#64748b;font-weight:500;"><?php echo $t['start_date'] ? date('d M Y', strtotime($t['start_date'])) : '—'; ?></span>
            </td>
            <td style="padding:0.85rem 1rem;white-space:nowrap;">
              <span style="font-size:0.78rem;color:<?php echo $isOverdue ? '#ef4444' : '#64748b'; ?>;font-weight:<?php echo $isOverdue ? '700' : '500'; ?>;"><?php echo $t['due_date'] ? date('d M Y', strtotime($t['due_date'])) : '—'; ?></span>
              <?php if ($isOverdue): ?><br><span style="font-size:0.6rem;color:#ef4444;font-weight:800;letter-spacing:0.05em;">OVERDUE</span><?php endif; ?>
            </td>
            <td style="padding:0.85rem 1rem;"><span style="font-family:monospace;font-size:0.75rem;background:#fde8ef;color:#FB6F92;padding:2px 7px;border-radius:6px;font-weight:700;"><?php echo htmlspecialchars($t['task_id']); ?></span></td>
            <td style="padding:0.85rem 1rem;font-weight:600;color:#1e293b;max-width:180px;"><?php echo htmlspecialchars($t['task_title']); ?></td>
            <td style="padding:0.85rem 1rem;white-space:nowrap;">
              <div style="display:flex;align-items:center;gap:6px;">
                <div style="width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#FB6F92,#ffb3c6);display:flex;align-items:center;justify-content:center;font-size:0.6rem;font-weight:800;color:#fff;flex-shrink:0;"><?php echo strtoupper(substr($t['employee_name'] ?? '?', 0, 2)); ?></div>
                <span style="font-size:0.78rem;font-weight:600;color:#1e293b;"><?php echo htmlspecialchars($t['employee_name'] ?? 'Unknown'); ?></span>
              </div>
            </td>
            <td style="padding:0.85rem 1rem;"><span style="display:inline-flex;padding:3px 9px;border-radius:20px;font-size:0.7rem;font-weight:700;background:<?php echo $pColor; ?>1a;color:<?php echo $pColor; ?>;"><?php echo htmlspecialchars($t['priority']); ?></span></td>
            <td style="padding:0.85rem 1rem;"><span style="display:inline-flex;padding:3px 9px;border-radius:20px;font-size:0.7rem;font-weight:700;background:<?php echo $sColor; ?>1a;color:<?php echo $sColor; ?>;"><?php echo $statusLabel; ?></span></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>


<!-- Verified Tasks Modal -->
<div id="verifiedModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="display:none;background:rgba(15,10,30,0.55);backdrop-filter:blur(6px);">
  <div style="width:100%;max-width:1050px;max-height:88vh;display:flex;flex-direction:column;border-radius:2rem;overflow:hidden;box-shadow:0 32px 80px rgba(34,197,94,0.12),0 2px 24px rgba(0,0,0,0.10);border:1.5px solid rgba(34,197,94,0.15);background:#fff;">
    <!-- Header -->
    <div style="padding:1.5rem 2rem;background:linear-gradient(120deg,#f0fdf4 0%,#f9fffe 100%);border-bottom:1.5px solid #dcfce7;display:flex;align-items:center;justify-content:space-between;">
      <div style="display:flex;align-items:center;gap:0.75rem;">
        <div style="width:44px;height:44px;border-radius:14px;background:linear-gradient(135deg,#22c55e,#4ade80);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 14px rgba(34,197,94,0.3);">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div>
          <h3 style="margin:0;font-size:1.2rem;font-weight:800;color:#1e293b;letter-spacing:-0.02em;">Verified Tasks</h3>
          <p style="margin:0;font-size:0.75rem;color:#22c55e;font-weight:600;">Successfully completed & verified</p>
        </div>
      </div>
      <button onclick="closeModal('verifiedModal')" style="width:38px;height:38px;border-radius:50%;background:#fff;border:1.5px solid #dcfce7;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#94a3b8;transition:all 0.2s;" onmouseover="this.style.background='#f0fdf4';this.style.color='#22c55e';" onmouseout="this.style.background='#fff';this.style.color='#94a3b8';">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <!-- Filters -->
    <div style="padding:1rem 2rem;background:#f9fffe;border-bottom:1px solid #dcfce7;display:flex;gap:0.75rem;flex-wrap:wrap;align-items:center;">
      <div style="display:flex;align-items:center;gap:0.5rem;background:#fff;border:1.5px solid #dcfce7;border-radius:12px;padding:0.45rem 0.85rem;">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#22c55e" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <input type="date" id="verifiedStartFilter" onchange="filterVerified()" style="border:none;outline:none;font-size:0.8rem;color:#1e293b;background:transparent;">
      </div>
      <span style="color:#86efac;font-size:0.8rem;">&#8594;</span>
      <div style="display:flex;align-items:center;gap:0.5rem;background:#fff;border:1.5px solid #dcfce7;border-radius:12px;padding:0.45rem 0.85rem;">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#22c55e" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <input type="date" id="verifiedEndFilter" onchange="filterVerified()" style="border:none;outline:none;font-size:0.8rem;color:#1e293b;background:transparent;">
      </div>
      <div style="display:flex;align-items:center;gap:0.5rem;background:#fff;border:1.5px solid #dcfce7;border-radius:12px;padding:0.45rem 0.85rem;flex:1;min-width:160px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#22c55e" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" id="verifiedSearchFilter" onkeyup="filterVerified()" placeholder="Search ID, title or employee..." style="border:none;outline:none;font-size:0.8rem;color:#1e293b;background:transparent;width:100%;">
      </div>
      <button onclick="clearVerifiedFilters()" style="padding:0.45rem 1rem;background:#dcfce7;border:none;border-radius:12px;font-size:0.75rem;font-weight:700;color:#22c55e;cursor:pointer;transition:all 0.2s;" onmouseover="this.style.background='#22c55e';this.style.color='#fff';" onmouseout="this.style.background='#dcfce7';this.style.color='#22c55e';">Clear</button>
    </div>
    <!-- Table -->
    <div style="overflow-y:auto;flex:1;">
      <table style="width:100%;border-collapse:collapse;font-size:0.84rem;">
        <thead style="position:sticky;top:0;z-index:10;">
          <tr style="background:linear-gradient(90deg,#f0fdf4,#f9fffe);">
            <th style="padding:0.9rem 1rem;text-align:left;font-size:0.65rem;font-weight:800;color:#22c55e;letter-spacing:0.08em;text-transform:uppercase;border-bottom:1.5px solid #dcfce7;white-space:nowrap;">Assigned Date</th>
            <th style="padding:0.9rem 1rem;text-align:left;font-size:0.65rem;font-weight:800;color:#22c55e;letter-spacing:0.08em;text-transform:uppercase;border-bottom:1.5px solid #dcfce7;white-space:nowrap;">Due Date</th>
            <th style="padding:0.9rem 1rem;text-align:left;font-size:0.65rem;font-weight:800;color:#22c55e;letter-spacing:0.08em;text-transform:uppercase;border-bottom:1.5px solid #dcfce7;white-space:nowrap;">Task ID</th>
            <th style="padding:0.9rem 1rem;text-align:left;font-size:0.65rem;font-weight:800;color:#22c55e;letter-spacing:0.08em;text-transform:uppercase;border-bottom:1.5px solid #dcfce7;">Task Name</th>
            <th style="padding:0.9rem 1rem;text-align:left;font-size:0.65rem;font-weight:800;color:#22c55e;letter-spacing:0.08em;text-transform:uppercase;border-bottom:1.5px solid #dcfce7;white-space:nowrap;">Completed By</th>
            <th style="padding:0.9rem 1rem;text-align:left;font-size:0.65rem;font-weight:800;color:#22c55e;letter-spacing:0.08em;text-transform:uppercase;border-bottom:1.5px solid #dcfce7;">Priority</th>
            <th style="padding:0.9rem 1rem;text-align:left;font-size:0.65rem;font-weight:800;color:#22c55e;letter-spacing:0.08em;text-transform:uppercase;border-bottom:1.5px solid #dcfce7;white-space:nowrap;">Submitted File</th>
          </tr>
        </thead>
        <tbody id="verifiedTasksBody">
          <?php 
          $verified_tasks->data_seek(0);
          while($t = $verified_tasks->fetch_assoc()):
            $priorityColor = ['High'=>'#ef4444','Medium'=>'#f59e0b','Low'=>'#22c55e'];
            $pColor = $priorityColor[$t['priority']] ?? '#94a3b8';
          ?>
          <tr class="modal-verified-row" style="border-bottom:1px solid #dcfce7;transition:background 0.15s;" onmouseover="this.style.background='#f0fdf4';" onmouseout="this.style.background='';">
            <td data-date="<?php echo $t['start_date'] ?? ''; ?>" style="padding:0.85rem 1rem;color:#64748b;font-size:0.78rem;white-space:nowrap;"><?php echo $t['start_date'] ? date('d M Y', strtotime($t['start_date'])) : '—'; ?></td>
            <td style="padding:0.85rem 1rem;color:#64748b;font-size:0.78rem;white-space:nowrap;"><?php echo $t['due_date'] ? date('d M Y', strtotime($t['due_date'])) : '—'; ?></td>
            <td style="padding:0.85rem 1rem;"><span style="font-family:monospace;font-size:0.75rem;background:#dcfce7;color:#16a34a;padding:2px 7px;border-radius:6px;font-weight:700;"><?php echo htmlspecialchars($t['task_id']); ?></span></td>
            <td style="padding:0.85rem 1rem;font-weight:600;color:#1e293b;max-width:180px;"><?php echo htmlspecialchars($t['task_title']); ?></td>
            <td style="padding:0.85rem 1rem;white-space:nowrap;">
              <div style="display:flex;align-items:center;gap:6px;">
                <div style="width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#22c55e,#4ade80);display:flex;align-items:center;justify-content:center;font-size:0.6rem;font-weight:800;color:#fff;flex-shrink:0;"><?php echo strtoupper(substr($t['employee_name'] ?? '?', 0, 2)); ?></div>
                <span style="font-size:0.78rem;font-weight:600;color:#1e293b;"><?php echo htmlspecialchars($t['employee_name'] ?? 'Unknown'); ?></span>
              </div>
            </td>
            <td style="padding:0.85rem 1rem;"><span style="display:inline-flex;padding:3px 9px;border-radius:20px;font-size:0.7rem;font-weight:700;background:<?php echo $pColor; ?>1a;color:<?php echo $pColor; ?>;"><?php echo htmlspecialchars($t['priority']); ?></span></td>
            <td style="padding:0.85rem 1rem;">
              <?php if (!empty($t['submission_file'])): ?>
                <a href="../uploads/<?php echo htmlspecialchars($t['submission_file']); ?>" target="_blank" download style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;background:linear-gradient(135deg,#22c55e,#4ade80);color:#fff;border-radius:8px;font-size:0.72rem;font-weight:700;text-decoration:none;box-shadow:0 2px 8px rgba(34,197,94,0.2);transition:all 0.2s;" onmouseover="this.style.transform='translateY(-1px)';" onmouseout="this.style.transform='';">
                  <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/></svg> Download
                </a>
              <?php else: ?>
                <span style="color:#cbd5e1;font-size:0.78rem;">None</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endwhile; ?>
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
        <div class="glass-card p-8 cursor-pointer" onclick="openAssignedModal();">
            <div class="w-12 h-12 bg-blue-50 text-blue-500 rounded-2xl flex items-center justify-center mb-4"><i data-lucide="layers"></i></div>
            <p class="text-pink-300 text-[11px] font-black uppercase tracking-widest">Total Assigned</p>
            <h2 class="text-3xl font-black text-[#1e293b] mt-1"><?php echo $total_tasks ?></h2>
        </div>

        <div class="glass-card p-8">
            <div class="w-12 h-12 bg-pink-50 text-[#FB6F92] rounded-2xl flex items-center justify-center mb-4"><i data-lucide="users"></i></div>
            <p class="text-pink-300 text-[11px] font-black uppercase tracking-widest">Workforce</p>
            <h2 class="text-3xl font-black text-[#1e293b] mt-1"><?= $total_employees ?></h2>
        </div>

        <div class="glass-card p-8 cursor-pointer" onclick="openVerifiedModal();">
            <div class="w-12 h-12 bg-green-50 text-green-500 rounded-2xl flex items-center justify-center mb-4"><i data-lucide="check-square"></i></div>
            <p class="text-pink-300 text-[11px] font-black uppercase tracking-widest">Verified Tasks</p>
            <h2 class="text-3xl font-black text-[#1e293b] mt-1"><?php echo $total_verified ?></h2>
        </div>

        <div class="glass-card p-8">
            <div class="w-12 h-12 bg-orange-50 text-orange-500 rounded-2xl flex items-center justify-center mb-4"><i data-lucide="trending-up"></i></div>
            <p class="text-pink-300 text-[11px] font-black uppercase tracking-widest">AI Status</p>
            <h2 class="text-3xl font-black text-[#1e293b] mt-1">Active</h2>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Team Rankings Section -->
        <div class="lg:col-span-1 glass-card overflow-hidden flex flex-col">
            <div class="p-6 border-b border-pink-50 bg-white flex flex-col gap-4">
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
        <div class="lg:col-span-2 glass-card overflow-hidden">
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
    // Modal open/close
    function openModal(id) {
        const el = document.getElementById(id);
        el.style.display = 'flex';
        el.classList.remove('hidden');
        requestAnimationFrame(() => { el.style.opacity = '1'; });
    }
    function closeModal(id) {
        const el = document.getElementById(id);
        el.style.display = 'none';
        el.classList.add('hidden');
    }
    function openAssignedModal() { openModal('assignedModal'); }
    function openVerifiedModal() { openModal('verifiedModal'); }
    // Close on backdrop click
    ['assignedModal','verifiedModal'].forEach(id => {
        document.getElementById(id).addEventListener('click', function(e) {
            if (e.target === this) closeModal(id);
        });
    });
    // Filter for Assigned Tasks
    function filterAssigned() {
        const start = document.getElementById('assignedStartFilter').value; // YYYY-MM-DD
        const end   = document.getElementById('assignedEndFilter').value;   // YYYY-MM-DD
        const status = document.getElementById('assignedStatusFilter').value.toLowerCase();
        const search = document.getElementById('assignedSearchFilter').value.toLowerCase();
        document.querySelectorAll('#assignedTasksBody tr').forEach(row => {
            const rowDate  = row.children[0].dataset.date || ''; // YYYY-MM-DD from data-date (Assigned Date)
            const id       = row.children[2].textContent.trim().toLowerCase(); // col 2 = Task ID
            const title    = row.children[3].textContent.trim().toLowerCase(); // col 3 = Task Name
            const employee = row.children[4].textContent.trim().toLowerCase(); // col 4 = Assigned To
            const rowStatus = row.children[6].textContent.trim().toLowerCase(); // col 6 = Status
            const dateOk   = (!start || rowDate >= start) && (!end || rowDate <= end);
            const statusOk = !status || rowStatus.includes(status);
            const searchOk = !search || id.includes(search) || title.includes(search) || employee.includes(search);
            row.style.display = (dateOk && statusOk && searchOk) ? '' : 'none';
        });
    }
    function clearAssignedFilters() {
        ['assignedStartFilter','assignedEndFilter','assignedSearchFilter'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('assignedStatusFilter').value = '';
        filterAssigned();
    }
    // Filter for Verified Tasks
    function filterVerified() {
        const start = document.getElementById('verifiedStartFilter').value; // YYYY-MM-DD
        const end   = document.getElementById('verifiedEndFilter').value;   // YYYY-MM-DD
        const search = document.getElementById('verifiedSearchFilter').value.toLowerCase();
        document.querySelectorAll('#verifiedTasksBody tr').forEach(row => {
            const rowDate  = row.children[0].dataset.date || ''; // YYYY-MM-DD from data-date (Assigned Date)
            const id       = row.children[2].textContent.trim().toLowerCase(); // col 2 = Task ID
            const title    = row.children[3].textContent.trim().toLowerCase(); // col 3 = Task Name
            const employee = row.children[4].textContent.trim().toLowerCase(); // col 4 = Completed By
            const dateOk   = (!start || rowDate >= start) && (!end || rowDate <= end);
            const searchOk = !search || id.includes(search) || title.includes(search) || employee.includes(search);
            row.style.display = (dateOk && searchOk) ? '' : 'none';
        });
    }
    function clearVerifiedFilters() {
        ['verifiedStartFilter','verifiedEndFilter','verifiedSearchFilter'].forEach(id => document.getElementById(id).value = '');
        filterVerified();
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