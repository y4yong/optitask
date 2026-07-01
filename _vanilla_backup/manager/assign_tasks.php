<?php
/**
 * OptiTask System - Assign Tasks (Manager)
 * Theme: Ultra-Pink Edition (STRICT STYLE RESTORED)
 */
session_start();
require_once '../db_config.php';
require_once '../email_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Manager') {
    header("Location: ../login.php");
    exit();
}

$active = 'assign_tasks';
$user_id = $_SESSION['user_id'];
$message = "";
$status_type = "success";

// Check for Unread Notifications for Sidebar Red Dot
$unread_query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND status = 'unread'";
$stmt_unread = $conn->prepare($unread_query);
$stmt_unread->bind_param("s", $user_id);
$stmt_unread->execute();
$unread_count = $stmt_unread->get_result()->fetch_assoc()['total'];
$stmt_unread->close();


// Filter Logic: Default to Manager's Dept (1), or 'all', or specific selection
$selected_dept = $_GET['dept_filter'] ?? ($_SESSION['dept_id'] ?? 1);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign_now'])) {
    $raw_date = $_POST['deadline']; 
    $due_date = !empty($raw_date) ? date('Y-m-d', strtotime($raw_date)) : null;
    $mgr_notes = htmlspecialchars($_POST['manager_notes']);
    $task_type = $_POST['task_type'] ?? 'Personal';
    $assignees = $_POST['assignee'] ?? []; // Array of selected users

    // File Upload Handling
    $target_file = null;
    if (isset($_FILES['task_file']) && $_FILES['task_file']['error'] == 0) {
        $upload_dir = "../uploads/tasks/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $file_name = time() . "_" . basename($_FILES["task_file"]["name"]);
        $target_file = $upload_dir . $file_name;
        move_uploaded_file($_FILES["task_file"]["tmp_name"], $target_file);
    }

    if (empty($assignees)) {
        $message = "Error: Please select at least one employee!";
        $status_type = "error";
    } elseif ($task_type === 'Personal' && count($assignees) > 1) {
        $message = "Error: Personal tasks can only have 1 assignee!";
        $status_type = "error";
    } elseif (!$due_date || strtotime($due_date) < strtotime(date('Y-m-d'))) {
        $message = "Error: Invalid deadline or date is in the past!";
        $status_type = "error";
    } else {
        $title = htmlspecialchars($_POST['task_title']);
        $desc = htmlspecialchars($_POST['description']);
        $priority = $_POST['priority'];
        $start_date = date('Y-m-d'); 

        $query = "INSERT INTO tasks (task_id, task_title, description, start_date, due_date, task_status, priority, employee_id, manager_notes, task_type, task_file, manager_id) 
                  VALUES (?, ?, ?, ?, ?, 'To-Do', ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);

        foreach ($assignees as $emp_id) {
            $task_id = "TK-" . strtoupper(substr(uniqid(), -6)); 
            $stmt->bind_param("sssssssssss", $task_id, $title, $desc, $start_date, $due_date, $priority, $emp_id, $mgr_notes, $task_type, $target_file, $_SESSION['user_id']);
            $stmt->execute();
            log_audit($conn, $_SESSION['user_id'], 'ASSIGN_TASK', "Assigned task $task_id to $emp_id");

            // 1. Insert Database Notification for the employee
            $notif_type = 'Assignment';
            $notif_msg = "You have been assigned a new task: '$title'. Due date: " . date('d-m-Y', strtotime($due_date)) . ".";
            $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, notification_type, message, status) VALUES (?, ?, ?, 'unread')");
            if ($stmt_notif) {
                $stmt_notif->bind_param("sss", $emp_id, $notif_type, $notif_msg);
                $stmt_notif->execute();
                $stmt_notif->close();
            }

            // 2. Fetch Employee Email and Username, then Send Email Notification
            $stmt_emp = $conn->prepare("SELECT email, username FROM users WHERE user_id = ?");
            if ($stmt_emp) {
                $stmt_emp->bind_param("s", $emp_id);
                $stmt_emp->execute();
                $emp_res = $stmt_emp->get_result()->fetch_assoc();
                $stmt_emp->close();
                
                if ($emp_res && !empty($emp_res['email'])) {
                    $brief_desc = !empty($desc) ? $desc : 'No instructions provided.';
                    $formatted_due = date('d-m-Y', strtotime($due_date));
                    
                    $email_content = "<strong>Task:</strong> " . htmlspecialchars($title) . "<br>" .
                                     "<strong>Assignee:</strong> " . htmlspecialchars($emp_res['username']) . " ($emp_id)<br>" .
                                     "<strong>Due Date:</strong> $formatted_due<br><br>" .
                                     "<strong>Details:</strong> " . nl2br(htmlspecialchars($brief_desc));
                    
                    send_email_notification(
                        $emp_res['email'],
                        $emp_res['username'],
                        "New Task Assigned: $title",
                        $email_content
                    );
                }
            }
        }
        $message = "Successfully assigned to " . count($assignees) . " employees!";
        $status_type = "success";
        $stmt->close();
    }
}

// Data Fetching
$depts_res = $conn->query("SELECT dept_id, dept_name FROM departments ORDER BY dept_name ASC");
$skills_res = $conn->query("SELECT skill_id, skill_name FROM skills ORDER BY skill_name ASC");

$sql_emp = "SELECT user_id, username FROM users WHERE role = 'Employee'";
if ($selected_dept !== 'all') {
    $sql_emp .= " AND dept_id = " . (int)$selected_dept;
}
$employees = $conn->query($sql_emp . " ORDER BY username ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>OptiTask | Assign Tasks</title>
  
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
  
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

    .choices__inner { background-color: #FFF9FA !important; border-radius: 1rem !important; border: 1px solid #FFD1DC !important; min-height: 52px !important; font-weight: 700 !important; }
    .flatpickr-calendar { font-family: 'Quicksand', sans-serif !important; border-radius: 1.5rem !important; border: 2px solid #FFE4EA !important; }
    .flatpickr-day.selected { background: #FB6F92 !important; border-color: #FB6F92 !important; }
    ::-webkit-scrollbar { width: 8px; }
    ::-webkit-scrollbar-track { background: #FFF5F7; }
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
    <a href="dashboard_manager.php" class="sidebar-link flex items-center gap-4 px-8 py-4 transition-all"><i data-lucide="layout-grid" class="w-5 h-5"></i> Dashboard</a>
    <a href="verify_tasks.php" class="sidebar-link flex items-center gap-4 px-8 py-4 transition-all"><i data-lucide="check-circle" class="w-5 h-5"></i> Verify Tasks</a>
    <a href="assign_tasks.php" class="sidebar-active flex items-center gap-4 px-8 py-4 transition-all"><i data-lucide="plus-circle" class="w-5 h-5"></i> Assign Tasks</a>

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
  <header class="mb-10">
    <h1 class="text-4xl font-extrabold text-[#1e293b] tracking-tight uppercase">Assign Tasks</h1>
    <p class="text-pink-400 mt-1 font-bold italic">Delegate work orders with precision.</p>
  </header>

  <form action="assign_tasks.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-2 space-y-6">
      <div class="glass-card p-8 space-y-6">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          
          <div class="md:col-span-1">
            <label class="text-[11px] font-black text-pink-300 uppercase tracking-widest ml-1">Department Filter</label>
            <select onchange="window.location.href='assign_tasks.php?dept_filter=' + this.value" class="mt-2 w-full bg-[#FFF9FA] rounded-2xl px-6 py-4 text-sm font-bold text-[#1e293b] outline-none border-2 border-pink-50 focus:border-[#FB6F92] transition-all">
                <option value="all" <?= $selected_dept == 'all' ? 'selected' : '' ?>>Show All Departments</option>
                <?php while($d = $depts_res->fetch_assoc()): ?>
                    <option value="<?= $d['dept_id'] ?>" <?= $selected_dept == $d['dept_id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['dept_name']) ?></option>
                <?php endwhile; ?>
            </select>
          </div>

          <div class="md:col-span-1">
            <label class="text-[11px] font-black text-pink-300 uppercase tracking-widest ml-1">Required Skill (AI Suggestion)</label>
            <div class="flex gap-2 mt-2">
                <select id="skill-select" class="flex-1 bg-[#FFF9FA] rounded-2xl px-6 py-4 text-sm font-bold text-[#1e293b] outline-none border-2 border-pink-50 focus:border-[#FB6F92] transition-all">
                    <option value="">-- Select Skill --</option>
                    <?php while($s = $skills_res->fetch_assoc()): ?>
                        <option value="<?= $s['skill_id'] ?>"><?= htmlspecialchars($s['skill_name']) ?></option>
                    <?php endwhile; ?>
                </select>
                <button type="button" id="ai-suggest-btn" class="w-14 shrink-0 pink-gradient text-white rounded-2xl flex items-center justify-center hover:scale-105 transition-transform shadow-md shadow-pink-100" title="Suggest Candidate">
                    <i data-lucide="wand-2" class="w-5 h-5"></i>
                </button>
            </div>
          </div>

          <div class="md:col-span-2">
            <label class="text-[11px] font-black text-pink-300 uppercase tracking-widest ml-1">Work Type</label>
            <select name="task_type" id="task-type-select" class="mt-2 w-full bg-[#FFF9FA] rounded-2xl px-6 py-4 text-sm font-bold text-[#1e293b] outline-none border-2 border-pink-50 focus:border-[#FB6F92] transition-all cursor-pointer">
                <option value="Personal">Personal Task (Single Assignee)</option>
                <option value="Group">Group Project (Multiple Assignees)</option>
            </select>
          </div>

          <div class="md:col-span-2">
            <label class="text-[11px] font-black text-pink-300 uppercase tracking-widest ml-1">Assignee(s)</label>
            <div class="mt-2">
                <select name="assignee[]" id="employee-select" multiple required>
                  <?php while($emp = $employees->fetch_assoc()): ?>
                    <option value="<?= $emp['user_id'] ?>"><?= htmlspecialchars($emp['username']) ?> (<?= $emp['user_id'] ?>)</option>
                  <?php endwhile; ?>
                </select>
            </div>
          </div>

          <div class="md:col-span-2">
            <label class="text-[11px] font-black text-pink-300 uppercase tracking-widest ml-1">Task Headline</label>
            <input type="text" name="task_title" required class="mt-2 w-full bg-[#FFF9FA] rounded-2xl px-6 py-4 text-sm font-bold text-[#1e293b] outline-none border-2 border-pink-50 focus:border-[#FB6F92] transition-all" placeholder="e.g. Develop Marketplace API">
          </div>

          <div>
            <label class="text-[11px] font-black text-pink-300 uppercase tracking-widest ml-1">Deadline</label>
            <div class="relative mt-2">
              <input type="text" id="pretty-date" name="deadline" readonly required class="w-full bg-[#FFF9FA] rounded-2xl px-6 py-4 text-sm font-bold text-[#1e293b] outline-none border-2 border-pink-50 cursor-pointer" placeholder="DD-MM-YYYY">
              <i data-lucide="calendar" class="absolute right-5 top-1/2 -translate-y-1/2 w-5 h-5 text-pink-300"></i>
            </div>
          </div>

          <div class="md:col-span-2">
            <label class="text-[11px] font-black text-pink-300 uppercase tracking-widest ml-1">Files / Attachments</label>
            <input type="file" name="task_file" class="mt-2 w-full bg-[#FFF9FA] rounded-2xl px-6 py-3 text-sm font-bold border-2 border-dashed border-pink-100 text-gray-400">
          </div>

          <div class="md:col-span-2">
            <label class="text-[11px] font-black text-pink-300 uppercase tracking-widest ml-1">Priority Level</label>
            <div class="flex gap-4 mt-2">
              <?php 
                $priorities = ['Low' => 'border-green-100 text-green-500 bg-green-50 peer-checked:border-green-500', 'Medium' => 'border-yellow-100 text-yellow-600 bg-yellow-50 peer-checked:border-yellow-500', 'High' => 'border-red-100 text-red-500 bg-red-50 peer-checked:border-red-500'];
                foreach($priorities as $p => $style):
              ?>
              <label class="flex-1 cursor-pointer">
                <input type="radio" name="priority" value="<?= $p ?>" class="hidden peer" <?= $p === 'Medium' ? 'checked' : '' ?>>
                <div class="py-4 text-center rounded-2xl border-2 border-gray-100 font-extrabold text-sm text-gray-400 transition-all <?= $style ?>">
                  <?= $p ?>
                </div>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="md:col-span-2">
            <label class="text-[11px] font-black text-pink-300 uppercase tracking-widest ml-1">Instructions</label>
            <textarea name="description" rows="4" class="mt-2 w-full bg-[#FFF9FA] rounded-2xl px-6 py-4 text-sm font-bold text-[#1e293b] outline-none border-2 border-pink-50 focus:border-[#FB6F92] transition-all" placeholder="What should the employee do?"></textarea>
          </div>
        </div>

        <button type="submit" name="assign_now" class="w-full pink-gradient text-white py-5 rounded-2xl font-extrabold shadow-lg shadow-pink-100 flex items-center justify-center gap-3 hover:scale-[1.01] transition-all uppercase tracking-[0.2em] text-sm">
          <i data-lucide="zap" class="w-5 h-5"></i> Assign Task
        </button>
      </div>
    </div>

    <div class="space-y-6">
      <div class="glass-card p-8 h-[550px] flex flex-col relative overflow-hidden">
        <div class="flex items-center gap-2 mb-6 relative">
          <div class="w-3 h-3 rounded-full bg-pink-500 shadow-sm shadow-pink-200"></div>
          <h3 class="font-extrabold text-[#1e293b] text-xl italic tracking-tight">Sticky Notes</h3>
        </div>
        <textarea id="sticky-notes" name="manager_notes" class="flex-1 w-full bg-[#FFFDF0] rounded-2xl p-6 text-sm font-bold text-gray-600 outline-none border-none resize-none shadow-inner leading-relaxed" placeholder="Write internal notes here..."></textarea>
      </div>
    </div>
  </form>
</main>

<script>
  lucide.createIcons();
  
  // Dynamic Assignee Limiting based on Task Type
  let empSelect;
  function initChoices(maxItems) {
      if (empSelect) {
          empSelect.destroy();
      }
      empSelect = new Choices('#employee-select', {
        removeItemButton: true,
        searchEnabled: true,
        placeholder: true,
        placeholderValue: maxItems === 1 ? 'Select exactly one employee...' : 'Select multiple employees...',
        itemSelectText: 'Click to select',
        maxItemCount: maxItems
      });
  }

  const taskTypeSelect = document.getElementById('task-type-select');
  initChoices(taskTypeSelect.value === 'Personal' ? 1 : -1);

  taskTypeSelect.addEventListener('change', function() {
      initChoices(this.value === 'Personal' ? 1 : -1);
  });

  // AI Suggestion Logic
  document.getElementById('ai-suggest-btn').addEventListener('click', async function() {
      const skillId = document.getElementById('skill-select').value;
      const deptId = '<?= $selected_dept ?>';
      
      if (!skillId) {
          Swal.fire({ icon: 'warning', title: 'Oops!', text: 'Please select a required skill first.', confirmButtonColor: '#FF8FAB' });
          return;
      }

      const btn = this;
      btn.innerHTML = '<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i>';
      lucide.createIcons();

      try {
          const res = await fetch(`suggest_candidate.php?skill_id=${skillId}&dept_id=${deptId}`);
          const data = await res.json();
          
          if (data.success) {
              empSelect.setChoiceByValue(data.user_id.toString());
              
              Swal.fire({
                  title: 'Perfect Match Found!',
                  html: `<b class="text-xl text-[#1e293b]">${data.username}</b><br><br><span class='text-sm text-gray-500'>${data.reason}</span>`,
                  icon: 'success',
                  confirmButtonColor: '#FF8FAB'
              });
          } else {
              Swal.fire({ icon: 'error', title: 'No Match', text: data.message, confirmButtonColor: '#FF8FAB' });
          }
      } catch (error) {
          Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to contact AI system.', confirmButtonColor: '#FF8FAB' });
      }

      btn.innerHTML = '<i data-lucide="wand-2" class="w-5 h-5"></i>';
      lucide.createIcons();
  });

  flatpickr("#pretty-date", { dateFormat: "d-m-Y", minDate: "today" });

  const stickyNote = document.getElementById('sticky-notes');
  stickyNote.value = localStorage.getItem('optitask_sticky_note') || '';
  stickyNote.addEventListener('input', () => { localStorage.setItem('optitask_sticky_note', stickyNote.value); });

  <?php if($message): ?>
  Swal.fire({
      title: '<?= $status_type === "success" ? "Success!" : "Error" ?>',
      text: '<?= $message ?>',
      icon: '<?= $status_type ?>',
      confirmButtonColor: '#FF8FAB'
  });
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