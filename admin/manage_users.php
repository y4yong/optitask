<?php
session_start();
require_once '../db_config.php';

// Check role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $target_user = $_POST['target_user'] ?? '';

    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("s", $target_user);
        if ($stmt->execute()) {
            log_audit($conn, $_SESSION['user_id'], 'DELETE_USER', "Deleted user $target_user");
            $message = "User deleted successfully.";
        }
    } elseif ($action === 'edit') {
        $account_status = $_POST['account_status'];
        $suspension_reason = null;
        if ($account_status === 'Suspended') {
            $suspension_reason = $_POST['suspension_reason'];
            if ($suspension_reason === 'Other' && !empty($_POST['custom_reason'])) {
                $suspension_reason = $_POST['custom_reason'];
            }
        }
        $stmt = $conn->prepare("UPDATE users SET account_status = ?, suspension_reason = ? WHERE user_id = ?");
        $stmt->bind_param("sss", $account_status, $suspension_reason, $target_user);
        if ($stmt->execute()) {
            log_audit($conn, $_SESSION['user_id'], 'UPDATE_USER_STATUS', "Changed status of $target_user to $account_status");
            $message = "User status updated successfully.";
        }
    } elseif ($action === 'reset_password') {
        $hash = password_hash('123', PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->bind_param("ss", $hash, $target_user);
        if ($stmt->execute()) {
            log_audit($conn, $_SESSION['user_id'], 'RESET_PASSWORD', "Reset password for user $target_user");
            $message = "Password reset to '123' successfully.";
        }
    } elseif ($action === 'create') {
        $new_id = strtoupper(htmlspecialchars($_POST['new_user_id']));
        $new_username = htmlspecialchars($_POST['new_username']);
        $new_email = filter_var($_POST['new_email'], FILTER_SANITIZE_EMAIL);
        $new_role = $_POST['new_role'];
        $hash = password_hash('123', PASSWORD_BCRYPT);
        
        $check = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? OR email = ?");
        $check->bind_param("ss", $new_id, $new_email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $message = "Error: User ID or Email already exists.";
        } else {
            $stmt = $conn->prepare("INSERT INTO users (user_id, username, email, password, role, account_status) VALUES (?, ?, ?, ?, ?, 'Active')");
            $stmt->bind_param("sssss", $new_id, $new_username, $new_email, $hash, $new_role);
            if ($stmt->execute()) {
                log_audit($conn, $_SESSION['user_id'], 'CREATE_USER', "Created user $new_id with role $new_role");
                $message = "User $new_id created successfully.";
            }
        }
    }
}

$active = 'manage_users';

// KPIs
$total_users = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
$count_emp = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'Employee'")->fetch_row()[0];
$count_mgr = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'Manager'")->fetch_row()[0];
$count_admin = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'Admin'")->fetch_row()[0];

// Users list
$users_res = $conn->query("SELECT u.user_id, u.username, u.email, u.role, u.account_status, u.suspension_reason, u.dept_id, d.dept_name FROM users u LEFT JOIN departments d ON u.dept_id = d.dept_id ORDER BY u.role, u.user_id");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>OptiTask | User Accounts</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

  <!-- Main -->
  <main class="flex-1 overflow-y-auto bg-[#FFF5F7] p-8">
    <header class="flex justify-between items-center mb-10">
      <div>
        <h1 class="text-3xl font-black text-gray-900 italic uppercase">User Accounts</h1>
        <p class="text-gray-500 text-sm">Create, edit roles, and manage access permissions. (UI only)</p>
      </div>

      <div class="flex gap-4">
        <div class="relative">
          <input type="text" id="searchInput" placeholder="Search users..." class="bg-white border border-gray-200 rounded-full pl-10 pr-4 py-2 text-sm focus:ring-2 focus:ring-pink-100 outline-none w-72 transition-all">
          <i data-lucide="search" class="w-4 h-4 text-gray-400 absolute left-4 top-2.5"></i>
        </div>
        <button onclick="openCreateModal()" class="bg-[#FF8FAB] hover:bg-[#FB6F92] text-white px-6 py-2.5 rounded-full font-bold shadow-lg shadow-pink-100 transition-all text-sm flex items-center gap-2 hover:scale-[1.02] transform duration-300">
          <i data-lucide="user-plus" class="w-4 h-4"></i> Create User
        </button>
      </div>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-10">
      <div class="glass-card p-6 rounded-[2rem] shadow-sm relative overflow-hidden group hover:scale-105 transition-transform">
        <p class="text-gray-500 text-xs font-bold uppercase tracking-widest relative z-10">Total Users</p>
        <h2 class="text-3xl font-black text-gray-800 mt-2 relative z-10"><?= $total_users ?></h2>
        <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-pink-50 rounded-full group-hover:bg-pink-100 transition-colors"></div>
      </div>
      <div class="glass-card p-6 rounded-[2rem] shadow-sm relative overflow-hidden group hover:scale-105 transition-transform">
        <p class="text-gray-500 text-xs font-bold uppercase tracking-widest relative z-10">Employees</p>
        <h2 class="text-3xl font-black text-gray-800 mt-2 relative z-10"><?= $count_emp ?></h2>
        <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-pink-50 rounded-full group-hover:bg-pink-100 transition-colors"></div>
      </div>
      <div class="glass-card p-6 rounded-[2rem] shadow-sm relative overflow-hidden group hover:scale-105 transition-transform">
        <p class="text-gray-500 text-xs font-bold uppercase tracking-widest relative z-10">Managers</p>
        <h2 class="text-3xl font-black text-gray-800 mt-2 relative z-10"><?= $count_mgr ?></h2>
        <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-pink-50 rounded-full group-hover:bg-pink-100 transition-colors"></div>
      </div>
      <div class="glass-card p-6 rounded-[2rem] shadow-sm relative overflow-hidden group hover:scale-105 transition-transform">
        <p class="text-gray-500 text-xs font-bold uppercase tracking-widest relative z-10">Admins</p>
        <h2 class="text-3xl font-black text-gray-800 mt-2 relative z-10"><?= $count_admin ?></h2>
        <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-pink-50 rounded-full group-hover:bg-pink-100 transition-colors"></div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- User table -->
      <div class="lg:col-span-2 glass-card rounded-[2rem] shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-50 flex justify-between items-center bg-white/50">
          <h3 class="font-bold text-gray-800 flex items-center gap-2">
            <i data-lucide="users" class="w-5 h-5 text-[#FB6F92]"></i> User Directory
          </h3>
          <div class="flex gap-2">
            <select id="roleFilter" class="bg-white border border-gray-200 rounded-full px-4 py-2 text-sm font-bold text-gray-600 outline-none focus:ring-2 focus:ring-pink-200">
              <option value="">All Roles</option>
              <option value="Employee">Employee</option>
              <option value="Manager">Manager</option>
              <option value="Admin">Admin</option>
            </select>
            <select class="bg-white border border-gray-200 rounded-full px-4 py-2 text-sm font-bold text-gray-600 outline-none focus:ring-2 focus:ring-pink-200">
              <option>All Status</option>
              <option selected>Active</option>
              <option>Suspended</option>
            </select>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead>
              <tr class="text-[10px] uppercase tracking-wider text-gray-400 border-b border-gray-50">
                <th class="px-6 py-4">User</th>
                <th class="px-6 py-4">Role</th>
                <th class="px-6 py-4">Last Login</th>
                <th class="px-6 py-4">Status</th>
                <th class="px-6 py-4 text-right">Action</th>
              </tr>
            </thead>
            <tbody class="text-sm">
              <?php while($user = $users_res->fetch_assoc()): ?>
              <tr class="user-row border-b border-gray-50 hover:bg-gray-50/50 transition-colors" data-role="<?= htmlspecialchars($user['role']) ?>" data-search="<?= strtolower(htmlspecialchars($user['user_id'] . ' ' . $user['username'])) ?>">
                <td class="px-6 py-4">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-pink-100 text-[#FB6F92] flex items-center justify-center font-black text-xs uppercase">
                        <?= substr($user['username'], 0, 2) ?>
                    </div>
                    <div>
                      <p class="text-xs font-black text-gray-800 hover:text-[#FB6F92] cursor-pointer transition-colors" onclick="showUserDetails('<?= htmlspecialchars($user['user_id']) ?>', '<?= addslashes(htmlspecialchars($user['username'])) ?>', '<?= addslashes(htmlspecialchars($user['email'])) ?>', '<?= htmlspecialchars($user['role']) ?>', '<?= htmlspecialchars($user['account_status']) ?>', '<?= addslashes(htmlspecialchars($user['suspension_reason'])) ?>', '<?= htmlspecialchars($user['dept_id']) ?>', '<?= addslashes(htmlspecialchars($user['dept_name'])) ?>')">
                          <?= htmlspecialchars($user['user_id']) ?> · <?= htmlspecialchars($user['username']) ?>
                      </p>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <?php 
                    $role_color = 'bg-gray-100 text-gray-600 border-gray-200';
                    if ($user['role'] === 'Admin') $role_color = 'bg-gray-900 text-white border-gray-900';
                    if ($user['role'] === 'Manager') $role_color = 'bg-pink-100 text-[#FB6F92] border-pink-200';
                  ?>
                  <span class="px-3 py-1 rounded-full text-[10px] font-bold <?= $role_color ?> border">
                      <?= strtoupper(htmlspecialchars($user['role'])) ?>
                  </span>
                </td>
                <td class="px-6 py-4 text-xs font-bold text-gray-500">-</td>
                <td class="px-6 py-4">
                  <?php if ($user['account_status'] === 'Suspended'): ?>
                    <span class="px-3 py-1 rounded-full text-[10px] font-bold bg-red-100 text-red-600 border border-red-200">SUSPENDED</span>
                  <?php else: ?>
                    <span class="px-3 py-1 rounded-full text-[10px] font-bold bg-green-100 text-green-600 border border-green-200">ACTIVE</span>
                  <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-right">
                  <div class="flex items-center justify-end gap-2">
                      <button onclick="openEditor('<?= $user['user_id'] ?>', '<?= addslashes($user['username']) ?>', '<?= $user['role'] ?>', '<?= $user['account_status'] ?>', '<?= addslashes($user['suspension_reason']) ?>')" class="text-[#FB6F92] hover:bg-pink-50 p-2 rounded-lg transition-colors" title="Edit">
                        <i data-lucide="edit-2" class="w-4 h-4"></i>
                      </button>
                      <button onclick="confirmDelete('<?= $user['user_id'] ?>')" class="text-red-500 hover:bg-red-50 p-2 rounded-lg transition-colors" title="Delete">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                      </button>
                  </div>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Side panel: role editor mock -->
      <div class="glass-card rounded-[2rem] shadow-sm p-6 relative">
        <h3 class="font-bold text-gray-800 mb-6 flex items-center gap-2">
          <i data-lucide="shield" class="w-5 h-5 text-[#FB6F92]"></i> Quick Role Editor
        </h3>

        <div id="editor-placeholder" class="text-center py-10 text-gray-400 text-xs font-bold">
            Select a user to manage.
        </div>

        <div id="editor-form-container" class="space-y-5 hidden">
          <form method="POST" id="edit-form" class="space-y-5">
              <input type="hidden" name="action" value="edit">
              <input type="hidden" name="target_user" id="edit_target_user">
              <div>
                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Selected User</label>
                <input id="edit_display_user" class="mt-2 w-full bg-gray-50 border border-gray-200 rounded-2xl px-4 py-3 text-sm font-bold text-gray-600" readonly />
              </div>

              <div>
                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Role (Read-only)</label>
                <input type="text" id="edit_role" class="mt-2 w-full bg-gray-50 border border-gray-200 rounded-2xl px-4 py-3 text-sm font-bold text-gray-500" readonly />
              </div>

              <div>
                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Account Status</label>
                <select name="account_status" id="edit_status" onchange="toggleSuspensionFields()" class="mt-2 w-full bg-white border border-gray-200 rounded-2xl px-4 py-3 text-sm font-bold text-gray-700 outline-none focus:ring-2 focus:ring-pink-200">
                  <option value="Active">Active</option>
                  <option value="Suspended">Suspended</option>
                </select>
              </div>

              <div id="suspension_reason_container" class="hidden">
                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Suspension Reason</label>
                <select name="suspension_reason" id="suspension_reason" onchange="toggleCustomReason()" class="mt-2 w-full bg-white border border-gray-200 rounded-2xl px-4 py-3 text-sm font-bold text-gray-700 outline-none focus:ring-2 focus:ring-pink-200">
                    <option value="">Select a reason...</option>
                    <option value="Security Violation">Security Violation</option>
                    <option value="Inactivity">Inactivity</option>
                    <option value="Pending Investigation">Pending Investigation</option>
                    <option value="Other">Other</option>
                </select>
              </div>

              <div id="custom_reason_container" class="hidden">
                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Custom Reason</label>
                <input type="text" name="custom_reason" id="custom_reason" class="mt-2 w-full bg-white border border-gray-200 rounded-2xl px-4 py-3 text-sm font-bold text-gray-700 outline-none focus:ring-2 focus:ring-pink-200" placeholder="Type reason here...">
              </div>

              <button type="submit" class="w-full bg-[#FF8FAB] hover:bg-[#FB6F92] text-white px-6 py-3 rounded-2xl font-black shadow-lg shadow-pink-100 transition-all text-[10px] uppercase tracking-widest flex items-center justify-center gap-2 mt-4 hover:scale-[1.02] transform duration-300">
                <i data-lucide="save" class="w-4 h-4"></i> Save Changes
              </button>
          </form>

          <form method="POST" id="reset-form">
              <input type="hidden" name="action" value="reset_password">
              <input type="hidden" name="target_user" id="reset_target_user">
              <button type="button" onclick="confirmReset()" class="w-full py-3 border-2 border-gray-100 rounded-2xl text-[10px] font-bold text-gray-400 hover:border-[#FB6F92] hover:text-[#FB6F92] hover:bg-pink-50/50 transition-all uppercase tracking-widest mt-2">
                Reset Password
              </button>
          </form>
        </div>
      </div>
    </div>
  </main>

  <form method="POST" id="delete-form" class="hidden">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="target_user" id="delete_target_user">
  </form>

  <script>
    lucide.createIcons();

    // Filtering Logic
    const searchInput = document.getElementById('searchInput');
    const roleFilter = document.getElementById('roleFilter');
    const userRows = document.querySelectorAll('.user-row');

    function filterTable() {
        const searchTerm = searchInput.value.toLowerCase();
        const roleTerm = roleFilter.value;

        userRows.forEach(row => {
            const matchesSearch = row.dataset.search.includes(searchTerm);
            const matchesRole = roleTerm === '' || row.dataset.role === roleTerm;
            
            if (matchesSearch && matchesRole) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    if (searchInput) searchInput.addEventListener('keyup', filterTable);
    if (roleFilter) roleFilter.addEventListener('change', filterTable);

    function openCreateModal() {
        Swal.fire({
            title: 'Create New User',
            html: `
                <form id="create-user-form" method="POST" class="text-left space-y-4 mt-4">
                    <input type="hidden" name="action" value="create">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">User ID</label>
                        <input type="text" name="new_user_id" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-bold text-gray-700 outline-none" placeholder="e.g. EM005" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Username</label>
                        <input type="text" name="new_username" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-bold text-gray-700 outline-none" placeholder="Full Name" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Email</label>
                        <input type="email" name="new_email" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-bold text-gray-700 outline-none" placeholder="name@company.com" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Role</label>
                        <select name="new_role" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-bold text-gray-700 outline-none">
                            <option value="Employee">Employee</option>
                            <option value="Manager">Manager</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                    <p class="text-[10px] text-pink-400 font-bold mt-2 italic">* Default password will be set to '123'</p>
                </form>
            `,
            showCancelButton: true,
            confirmButtonColor: '#FF8FAB',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Create Account',
            background: '#FFF9FA',
            customClass: {
                popup: 'rounded-[2.5rem] border-2 border-pink-100',
                title: 'font-black text-[#1e293b]',
                confirmButton: 'rounded-xl px-6 py-3',
                cancelButton: 'rounded-xl px-6 py-3'
            },
            preConfirm: () => {
                const form = document.getElementById('create-user-form');
                if (form.checkValidity()) {
                    form.submit();
                } else {
                    Swal.showValidationMessage('Please fill out all required fields correctly.');
                    return false;
                }
            }
        });
    }

    <?php if ($message): ?>
    Swal.fire({
        icon: '<?= strpos($message, "Error") !== false ? "error" : "success" ?>',
        title: '<?= strpos($message, "Error") !== false ? "Failed" : "Success" ?>',
        text: '<?= addslashes($message) ?>',
        confirmButtonColor: '#FF8FAB',
        background: '#FFF9FA',
        customClass: { popup: 'rounded-[2.5rem] border-2 border-pink-100' }
    });
    <?php endif; ?>

    function toggleSuspensionFields() {
        const status = document.getElementById('edit_status').value;
        const reasonContainer = document.getElementById('suspension_reason_container');
        if (status === 'Suspended') {
            reasonContainer.classList.remove('hidden');
        } else {
            reasonContainer.classList.add('hidden');
        }
        toggleCustomReason();
    }

    function toggleCustomReason() {
        const status = document.getElementById('edit_status').value;
        const reason = document.getElementById('suspension_reason').value;
        const customContainer = document.getElementById('custom_reason_container');
        
        if (status === 'Suspended' && reason === 'Other') {
            customContainer.classList.remove('hidden');
        } else {
            customContainer.classList.add('hidden');
        }
    }

    function openEditor(userId, username, role, status, reason) {
        document.getElementById('editor-placeholder').classList.add('hidden');
        document.getElementById('editor-form-container').classList.remove('hidden');

        document.getElementById('edit_target_user').value = userId;
        document.getElementById('reset_target_user').value = userId;
        document.getElementById('edit_display_user').value = userId + ' · ' + username;
        document.getElementById('edit_role').value = role;
        
        const statusSelect = document.getElementById('edit_status');
        if (status) {
            statusSelect.value = status;
        } else {
            statusSelect.value = 'Active';
        }

        const reasonSelect = document.getElementById('suspension_reason');
        const customInput = document.getElementById('custom_reason');
        reasonSelect.value = '';
        customInput.value = '';

        if (status === 'Suspended' && reason) {
            const predefined = Array.from(reasonSelect.options).map(opt => opt.value);
            if (predefined.includes(reason)) {
                reasonSelect.value = reason;
            } else {
                reasonSelect.value = 'Other';
                customInput.value = reason;
            }
        }

        toggleSuspensionFields();
    }

    function showUserDetails(id, name, email, role, status, reason, deptId, deptName) {
        let statusHtml = status === 'Suspended' 
            ? `<span class="bg-red-100 text-red-600 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-widest">SUSPENDED</span>`
            : `<span class="bg-green-100 text-green-600 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-widest">ACTIVE</span>`;
            
        let reasonHtml = (status === 'Suspended' && reason) 
            ? `<div class="mt-6 p-5 bg-red-50 rounded-2xl border border-red-100 text-left">
                 <p class="text-[10px] font-black text-red-400 uppercase tracking-widest mb-1 flex items-center gap-2"><i data-lucide="alert-circle" class="w-3 h-3"></i> Suspension Reason</p>
                 <p class="text-sm font-bold text-red-700 mt-2">${reason}</p>
               </div>`
            : '';

        Swal.fire({
            html: `
                <div class="text-center pt-4">
                    <div class="w-24 h-24 bg-pink-100 text-[#FB6F92] rounded-full flex items-center justify-center font-black text-3xl mx-auto mb-5 uppercase shadow-inner border-4 border-white">
                        ${name.substring(0, 2)}
                    </div>
                    <h2 class="text-2xl font-black text-gray-800 tracking-tight">${name}</h2>
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-8">${id}</p>
                    
                    <div class="grid grid-cols-2 gap-4 text-left mb-6">
                        <div class="bg-gray-50 p-5 rounded-3xl border border-gray-100">
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Email Address</p>
                            <p class="text-sm font-bold text-gray-700 break-all">${email || 'Not provided'}</p>
                        </div>
                        <div class="bg-gray-50 p-5 rounded-3xl border border-gray-100">
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Role</p>
                            <p class="text-sm font-bold text-gray-700 uppercase">${role}</p>
                        </div>
                        <div class="bg-gray-50 p-5 rounded-3xl border border-gray-100 col-span-2">
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Department</p>
                            <p class="text-sm font-bold text-gray-700 uppercase">${deptName ? deptName : 'None'}</p>
                        </div>
                    </div>
                    
                    <div class="flex flex-col items-center justify-center gap-3">
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Account Status</p>
                        ${statusHtml}
                    </div>
                    ${reasonHtml}
                </div>
            `,
            showConfirmButton: true,
            confirmButtonColor: '#FF8FAB',
            confirmButtonText: 'Close Profile',
            background: '#FFF9FA',
            didOpen: () => {
                lucide.createIcons();
            },
            customClass: {
                popup: 'rounded-[2.5rem] border-2 border-pink-100',
                confirmButton: 'rounded-2xl px-10 py-4 font-bold uppercase tracking-widest text-xs'
            }
        });
    }

    function confirmDelete(userId) {
        Swal.fire({
            title: 'Delete User?',
            text: `Are you sure you want to delete ${userId}? This action cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Yes, Delete',
            background: '#FFF9FA',
            customClass: {
                popup: 'rounded-[2.5rem] border-2 border-red-100',
                title: 'font-black text-[#1e293b]',
                confirmButton: 'rounded-xl px-6 py-3',
                cancelButton: 'rounded-xl px-6 py-3'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('delete_target_user').value = userId;
                document.getElementById('delete-form').submit();
            }
        });
    }

    function confirmReset() {
        Swal.fire({
            title: 'Reset Password?',
            text: "This will reset the user's password to '123'. Proceed?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#f97316',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Yes, Reset',
            background: '#FFF9FA',
            customClass: {
                popup: 'rounded-[2.5rem] border-2 border-orange-100',
                title: 'font-black text-[#1e293b]',
                confirmButton: 'rounded-xl px-6 py-3',
                cancelButton: 'rounded-xl px-6 py-3'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('reset-form').submit();
            }
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
