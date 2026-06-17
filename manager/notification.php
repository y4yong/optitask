<?php
/**
 * OptiTask System - Manager Notifications
 * Theme: Ultra-Pink Edition (Consistent with Dashboard & Assign Tasks)
 */
session_start();
require_once '../db_config.php';

// Auto-clean trailing question marks from database
$conn->query("UPDATE notifications SET message = TRIM(TRAILING '?' FROM TRIM(message)) WHERE message LIKE '%?'");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Manager') {
    header("Location: ../login.php");
    exit();
}

$active = 'notification';
$user_id = $_SESSION['user_id'];

// 1. Check for Unread Notifications for Sidebar Red Dot
$unread_query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND status = 'unread'";
$stmt_unread = $conn->prepare($unread_query);
$stmt_unread->bind_param("s", $user_id);
$stmt_unread->execute();
$unread_count = $stmt_unread->get_result()->fetch_assoc()['total'];
$stmt_unread->close();

// 2. Mark all as read logic
if (isset($_POST['mark_all_read'])) {
    $mark_query = "UPDATE notifications SET status = 'read' WHERE user_id = ? AND status = 'unread'";
    $stmt_mark = $conn->prepare($mark_query);
    $stmt_mark->bind_param("s", $user_id);
    $stmt_mark->execute();
    $stmt_mark->close();
    header("Location: notification.php");
    exit();
}

// 3. Fetch Notifications List
$notif_query = "SELECT * FROM notifications WHERE user_id = ? ORDER BY timestamp DESC LIMIT 15";
$stmt_list = $conn->prepare($notif_query);
$stmt_list->bind_param("s", $user_id);
$stmt_list->execute();
$notifications = $stmt_list->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OptiTask | Manager Notifications</title>
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
            <i data-lucide="briefcase" class="w-6 h-6"></i>
        </div>
        <span class="text-2xl font-bold tracking-tight text-[#1e293b]">
            OptiTask<span class="text-[#FB6F92]">.</span>
        </span>
    </div>

    <nav class="flex-1 space-y-2 pr-4">
        <p class="text-[11px] uppercase tracking-[0.2em] text-pink-300 font-bold px-8 mb-4">Manager</p>
        
        <a href="dashboard_manager.php" class="sidebar-link flex items-center gap-4 px-8 py-4 transition-all">
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
            <a href="notification.php" class="sidebar-active flex items-center justify-between px-8 py-4 transition-all">
                <div class="flex items-center gap-4">
                    <i data-lucide="bell" class="w-5 h-5"></i> Notifications
                </div>
                <?php if($unread_count > 0): ?>
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
    <header class="flex justify-between items-end mb-10">
        <div>
            <h1 class="text-4xl font-extrabold text-[#1e293b] tracking-tight uppercase">Notifications</h1>
            <p class="text-pink-400 mt-1 font-bold italic">Approvals, submissions, and team capacity alerts.</p>
        </div>

        <div class="flex gap-4">
            <form method="POST">
                <button name="mark_all_read" class="bg-white border border-pink-100 px-6 py-3 rounded-2xl text-xs font-black text-[#1e293b] hover:border-[#FB6F92] hover:text-[#FB6F92] hover:bg-pink-50/50 transition-all uppercase tracking-widest flex items-center gap-2">
                    <i data-lucide="check" class="w-4 h-4"></i> Mark all read
                </button>
            </form>
            <button class="pink-gradient text-white px-7 py-3 rounded-2xl font-black shadow-lg shadow-pink-100 transition-all text-xs uppercase tracking-widest flex items-center gap-2 hover:scale-[1.02] transform duration-300">
                <i data-lucide="filter" class="w-4 h-4"></i> Filter
            </button>
        </div>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
        <div class="lg:col-span-2 space-y-6">
            <div class="glass-card overflow-hidden">
                <div class="p-8 border-b border-pink-50 flex justify-between items-center bg-white">
                    <h3 class="text-xl font-extrabold text-[#1e293b] flex items-center gap-3">
                        <span class="w-2 h-8 pink-gradient rounded-full"></span>
                        Recent Alerts
                    </h3>
                    <span class="text-[11px] font-black text-pink-300 uppercase tracking-widest"><?= $unread_count ?> Unread Items</span>
                </div>

                <div class="divide-y divide-pink-50">
                    <?php if($notifications->num_rows > 0): ?>
                        <?php while($n = $notifications->fetch_assoc()): ?>
                        <?php
                            $bg_color = "bg-gray-50 text-gray-500 border-gray-100";
                            $icon = "bell";
                            if ($n['notification_type'] === 'Submission') {
                                $bg_color = "bg-yellow-50 text-yellow-600 border-yellow-100";
                                $icon = "clipboard-check";
                            } elseif ($n['notification_type'] === 'Assignment') {
                                $bg_color = "bg-blue-50 text-blue-600 border-blue-100";
                                $icon = "clipboard";
                            } elseif ($n['notification_type'] === 'Approval') {
                                $bg_color = "bg-green-50 text-green-600 border-green-100";
                                $icon = "check-circle";
                            } elseif ($n['notification_type'] === 'Rejection') {
                                $bg_color = "bg-red-50 text-red-600 border-red-100";
                                $icon = "x-circle";
                            }
                        ?>
                        <div class="p-8 hover:bg-[#FFF9FA] transition-all flex gap-6 items-start <?= $n['status'] === 'unread' ? 'bg-pink-50/10' : '' ?>">
                            <div class="w-14 h-14 rounded-2xl <?= $bg_color ?> flex items-center justify-center border shadow-sm">
                                <i data-lucide="<?= $icon ?>" class="w-7 h-7"></i>
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between items-start mb-1">
                                    <p class="text-base font-extrabold text-[#1e293b]"><?= htmlspecialchars($n['notification_type']) ?></p>
                                    <span class="text-[10px] text-pink-300 font-bold uppercase"><?= date('H:i A', strtotime($n['timestamp'])) ?></span>
                                </div>
                                <p class="text-sm text-gray-500 mt-1 font-medium leading-relaxed"><?= htmlspecialchars(rtrim($n['message'], '? ')) ?></p>
                                <?php if ($n['notification_type'] === 'Submission'): ?>
                                    <div class="mt-4 flex gap-3">
                                        <a href="verify_tasks.php" class="px-5 py-2 rounded-xl text-[11px] font-black pink-gradient text-white shadow-md shadow-pink-100">REVIEW NOW</a>
                                    </div>
                                <?php endif; ?>
                                <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-3"><?= date('d M Y', strtotime($n['timestamp'])) ?></p>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-20 text-center opacity-40">
                             <i data-lucide="inbox" class="w-12 h-12 text-pink-300 mx-auto mb-4"></i>
                             <p class="text-sm font-bold text-gray-400">Your notifications inbox is empty.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="space-y-8">
            <div class="glass-card p-10 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-24 h-24 bg-pink-50 rounded-bl-full opacity-50"></div>
                <h3 class="text-xl font-extrabold text-[#1e293b] mb-8 relative">Quick Management</h3>
                
                <div class="space-y-4 relative">
                    <a href="assign_tasks.php" class="w-full p-5 bg-[#FF8FAB] hover:bg-[#FB6F92] text-white rounded-2xl text-[11px] font-black uppercase tracking-widest flex items-center gap-3 shadow-lg hover:scale-[1.02] transition-all duration-300">
                        <i data-lucide="plus-circle" class="w-5 h-5 text-pink-500"></i> Assign New Work
                    </a>
                    
                    <a href="verify_tasks.php" class="w-full p-5 bg-white border-2 border-pink-50 text-[#1e293b] rounded-2xl text-[11px] font-black uppercase tracking-widest flex items-center gap-3 hover:border-[#FB6F92] hover:text-[#FB6F92] hover:bg-pink-50/50 transition-all">
                        <i data-lucide="check-circle-2" class="w-5 h-5 text-[#FB6F92]"></i> Review Submissions
                    </a>

                </div>
            </div>
        </div>
    </div>
</main>

<script>
    lucide.createIcons();

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