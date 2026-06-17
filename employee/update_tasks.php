<?php
/**
 * OptiTask System - Task Submission (Employee)
 * Features: Select active task, provide evidence link, and update status.
 */
session_start();
require_once '../db_config.php';
require_once '../email_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Employee') {
    header("Location: ../login.php");
    exit();
}

$active = 'update_tasks';
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$message = "";

// Check for Unread Notifications for Sidebar Red Dot
$unread_query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND status = 'unread'";
$stmt_unread = $conn->prepare($unread_query);
$stmt_unread->bind_param("s", $user_id);
$stmt_unread->execute();
$unread_count = $stmt_unread->get_result()->fetch_assoc()['total'];
$stmt_unread->close();

$status_type = "success";

// Handle Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_work'])) {
    $task_id = $_POST['task_id'];
    $evidence = htmlspecialchars($_POST['evidence_link']);
    $new_status = "Done"; // Automatically move to Done when submitted

    // We assume your tasks table has an 'evidence_link' column. 
    // If not, run: ALTER TABLE tasks ADD COLUMN evidence_link VARCHAR(255);
    $query = "UPDATE tasks SET task_status = ?, evidence_link = ? WHERE task_id = ? AND employee_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssss", $new_status, $evidence, $task_id, $user_id);

    if ($stmt->execute()) {
        log_audit($conn, $user_id, 'SUBMIT_TASK', "Submitted task $task_id with evidence: $evidence");
        $message = "Work submitted for verification!";

        // 1. Fetch Task Title and assigning Manager details
        $task_title = "Unknown Task";
        $manager = null;
        $stmt_task = $conn->prepare("
            SELECT t.task_title, t.manager_id, u.email as mgr_email, u.username as mgr_name 
            FROM tasks t 
            LEFT JOIN users u ON t.manager_id = u.user_id 
            WHERE t.task_id = ?
        ");
        if ($stmt_task) {
            $stmt_task->bind_param("s", $task_id);
            $stmt_task->execute();
            $task_res = $stmt_task->get_result()->fetch_assoc();
            if ($task_res) {
                $task_title = $task_res['task_title'];
                if (!empty($task_res['manager_id'])) {
                    $manager = [
                        'user_id' => $task_res['manager_id'],
                        'email' => $task_res['mgr_email'],
                        'username' => $task_res['mgr_name']
                    ];
                }
            }
            $stmt_task->close();
        }

        // 2. Fetch Employee Username and Department
        $emp_name = $_SESSION['username'] ?? 'Employee';
        $dept_id = null;
        $stmt_dept = $conn->prepare("SELECT username, dept_id FROM users WHERE user_id = ?");
        if ($stmt_dept) {
            $stmt_dept->bind_param("s", $user_id);
            $stmt_dept->execute();
            $dept_res = $stmt_dept->get_result()->fetch_assoc();
            if ($dept_res) {
                $emp_name = $dept_res['username'];
                $dept_id = $dept_res['dept_id'];
            }
            $stmt_dept->close();
        }

        // 3. Populate Managers List
        $managers = [];
        if ($manager !== null) {
            $managers[] = $manager;
        } else {
            // Fallback 1: Find Managers in same department
            if ($dept_id !== null) {
                $stmt_mgrs = $conn->prepare("SELECT user_id, email, username FROM users WHERE role = 'Manager' AND dept_id = ?");
                if ($stmt_mgrs) {
                    $stmt_mgrs->bind_param("i", $dept_id);
                    $stmt_mgrs->execute();
                    $mgrs_res = $stmt_mgrs->get_result();
                    while ($row = $mgrs_res->fetch_assoc()) {
                        $managers[] = $row;
                    }
                    $stmt_mgrs->close();
                }
            }

            // Fallback 2: Default to all Managers if no department managers are set
            if (empty($managers)) {
                $stmt_all_mgrs = $conn->prepare("SELECT user_id, email, username FROM users WHERE role = 'Manager'");
                if ($stmt_all_mgrs) {
                    $stmt_all_mgrs->execute();
                    $all_mgrs_res = $stmt_all_mgrs->get_result();
                    while ($row = $all_mgrs_res->fetch_assoc()) {
                        $managers[] = $row;
                    }
                    $stmt_all_mgrs->close();
                }
            }
        }

        // 5. Notify all retrieved managers
        $notif_type = 'Submission';
        $notif_msg = "Employee $emp_name ($user_id) has submitted task '$task_title' (#$task_id) for verification.";
        foreach ($managers as $mgr) {
            if (empty($mgr['user_id'])) {
                continue;
            }
            
            // Check if the user_id exists in the users table to avoid foreign key failure
            $check_user = $conn->prepare("SELECT 1 FROM users WHERE user_id = ?");
            if ($check_user) {
                $check_user->bind_param("s", $mgr['user_id']);
                $check_user->execute();
                $user_exists = $check_user->get_result()->num_rows > 0;
                $check_user->close();
                
                if ($user_exists) {
                    $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, notification_type, message, status) VALUES (?, ?, ?, 'unread')");
                    if ($stmt_notif) {
                        $stmt_notif->bind_param("sss", $mgr['user_id'], $notif_type, $notif_msg);
                        $stmt_notif->execute();
                        $stmt_notif->close();
                    }

                    if (!empty($mgr['email'])) {
                        $email_content = "<strong>Task:</strong> " . htmlspecialchars($task_title) . " (#$task_id)<br>" .
                                         "<strong>Submitted By:</strong> " . htmlspecialchars($emp_name) . " ($user_id)<br>" .
                                         "<strong>Evidence Link:</strong> <a href='" . htmlspecialchars($evidence) . "'>" . htmlspecialchars($evidence) . "</a><br><br>" .
                                         "<strong>Details:</strong> The employee has completed the task and submitted it for your verification.";
                        
                        send_email_notification(
                            $mgr['email'],
                            $mgr['username'],
                            "Task Submission: $task_title",
                            $email_content
                        );
                    }
                }
            }
        }
    } else {
        $message = "Error: " . $stmt->error;
        $status_type = "error";
    }
}

// Fetch only To-Do or In Progress tasks
$tasks = $conn->query("SELECT task_id, task_title FROM tasks WHERE employee_id = '$user_id' AND task_status != 'Done'");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>OptiTask | Submit Work</title>
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
  </style>
</head>
<body class="flex h-screen overflow-hidden">

<aside class="w-72 bg-white border-r border-pink-100 flex flex-col">
  <div class="p-8 flex items-center gap-3">
    <div class="w-12 h-12 pink-gradient rounded-2xl flex items-center justify-center text-white shadow-lg shadow-pink-100"><i data-lucide="zap" class="w-6 h-6"></i></div>
    <span class="text-2xl font-bold tracking-tight text-[#1e293b]">OptiTask<span class="text-[#FB6F92]">.</span></span>
  </div>
  <nav class="flex-1 space-y-2 pr-4">
    <p class="text-[11px] uppercase tracking-[0.2em] text-pink-300 font-bold px-8 mb-4">Employee Console</p>
    <a href="dashboard_employee.php" class="sidebar-link flex items-center gap-4 px-8 py-4 transition-all"><i data-lucide="layout-grid" class="w-5 h-5"></i> Dashboard</a>
    <a href="tasks.php" class="sidebar-link flex items-center gap-4 px-8 py-4 transition-all"><i data-lucide="clipboard-list" class="w-5 h-5"></i> My Tasks</a>
    <a href="update_tasks.php" class="sidebar-active flex items-center gap-4 px-8 py-4 transition-all"><i data-lucide="check-circle" class="w-5 h-5"></i> Submissions</a>
    
    <div class="pt-6">
        <p class="text-[11px] uppercase tracking-[0.2em] text-pink-300 font-bold px-8 mb-4">Account</p>
        <a href="skills.php" class="sidebar-link flex items-center gap-4 px-8 py-4 transition-all"><i data-lucide="user" class="w-5 h-5"></i> Profile</a>
        <a href="performance.php" class="sidebar-link flex items-center gap-4 px-8 py-4 transition-all"><i data-lucide="bar-chart-3" class="w-5 h-5"></i> Performance</a>
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
          <a href="#" onclick="confirmLogout(event)"><i data-lucide="log-out" class="w-5 h-5 text-pink-200 hover:text-red-500 cursor-pointer"></i></a>
      </div>
  </div>
</aside>

<main class="flex-1 overflow-y-auto p-12">
  <header class="mb-10">
    <h1 class="text-4xl font-extrabold text-[#1e293b] tracking-tight uppercase">Submit Work</h1>
    <p class="text-pink-400 mt-1 font-bold italic">Ready to finish? Provide your evidence link below.</p>
  </header>

  <div class="max-w-3xl">
    <form action="update_tasks.php" method="POST" class="glass-card p-10 space-y-8">
      
      <div>
        <label class="text-[11px] font-black text-pink-300 uppercase tracking-widest ml-1">Select Task</label>
        <select name="task_id" required class="mt-2 w-full bg-[#FFF9FA] rounded-2xl px-6 py-4 text-sm font-bold text-[#1e293b] outline-none border-2 border-pink-50 focus:border-[#FB6F92] transition-all">
          <option value="" disabled selected>Which task are you finishing?</option>
          <?php while($row = $tasks->fetch_assoc()): ?>
            <option value="<?= $row['task_id'] ?>"><?= $row['task_title'] ?> (<?= $row['task_id'] ?>)</option>
          <?php endwhile; ?>
        </select>
      </div>

      <div>
        <label class="text-[11px] font-black text-pink-300 uppercase tracking-widest ml-1">Submission Link (Evidence)</label>
        <div class="relative mt-2">
            <input type="url" name="evidence_link" required class="w-full bg-[#FFF9FA] rounded-2xl px-6 py-4 pl-14 text-sm font-bold text-[#1e293b] outline-none border-2 border-pink-50 focus:border-[#FB6F92] transition-all" placeholder="https://github.com/your-repo">
            <i data-lucide="link" class="absolute left-6 top-1/2 -translate-y-1/2 w-5 h-5 text-pink-300"></i>
        </div>
        <p class="text-[10px] text-gray-400 mt-3 ml-1 font-bold italic">Managers will use this link to verify your work before approval.</p>
      </div>

      <button type="submit" name="submit_work" class="w-full pink-gradient text-white py-5 rounded-2xl font-extrabold shadow-lg shadow-pink-100 flex items-center justify-center gap-3 hover:scale-[1.02] active:scale-[0.98] transition-all uppercase tracking-widest transform duration-300">
        <i data-lucide="rocket" class="w-5 h-5"></i> Submit for Approval
      </button>
    </form>
  </div>
</main>

<script>
  lucide.createIcons();
  <?php if($message): ?>
  Swal.fire({
      title: '<?= $status_type === "success" ? "Submitted!" : "Error" ?>',
      text: '<?= $message ?>',
      icon: '<?= $status_type ?>',
      confirmButtonColor: '#FF8FAB',
      customClass: { popup: 'rounded-[2rem]' }
  });
  <?php endif; ?>

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