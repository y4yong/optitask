<?php
/**
 * OptiTask System - Employee Notifications
 * Theme: Ultra-Pink Edition (Strict Sidebar Sync)
 * Features: Unread Alert System & Red Dot Logic
 */
session_start();
require_once '../db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Employee') {
    header("Location: ../login.php");
    exit();
}

$active = 'notification';
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// 1. Check for Unread Notifications for Sidebar Red Dot (LISTEN)
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
    <title>OptiTask | Notifications</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Quicksand', sans-serif; background-color: #FFF5F7; }
        .pink-gradient { background: linear-gradient(135deg, #FB6F92 0%, #FFB3C6 100%); }
        
        /* SIDEBAR - MATCHED EXACTLY TO YOUR REFERENCE */
        .sidebar-active {
            background: #FFE4EA; 
            border-left: 6px solid #FB6F92;
            color: #FB6F92;
            font-weight: 800;
            border-radius: 0 1.5rem 1.5rem 0;
        }
        .sidebar-link { color: #64748b; font-weight: 600; font-size: 0.95rem; }
        .sidebar-link:hover { color: #FB6F92; background: #FFF0F3; border-radius: 0 1.5rem 1.5rem 0; }

        .unread-dot {
            width: 8px;
            height: 8px;
            background-color: #EF4444;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 8px rgba(239, 68, 68, 0.5);
        }

        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-thumb { background: #FFD1DC; border-radius: 10px; }
    </style>
</head>
<body class="flex h-screen overflow-hidden">

<aside class="w-72 bg-white border-r border-pink-100 flex flex-col">
    <div class="p-8 pb-10 flex items-center gap-3">
        <div class="w-12 h-12 pink-gradient rounded-2xl flex items-center justify-center text-white shadow-lg shadow-pink-100">
            <i data-lucide="zap" class="w-6 h-6"></i>
        </div>
        <span class="text-2xl font-bold tracking-tight text-[#1e293b]">
            OptiTask<span class="text-[#FB6F92]">.</span>
        </span>
    </div>

    <nav class="flex-1 space-y-2 pr-4">
        <p class="text-[11px] uppercase tracking-[0.2em] text-pink-300 font-bold px-8 mb-4">Employee Console</p>
        
        <a href="dashboard_employee.php" class="sidebar-link flex items-center gap-4 px-8 py-4 transition-all">
            <i data-lucide="layout-grid" class="w-5 h-5"></i> Dashboard
        </a>
        
        <a href="tasks.php" class="sidebar-link flex items-center gap-4 px-8 py-4 transition-all">
            <i data-lucide="clipboard-list" class="w-5 h-5"></i> My Tasks
        </a>
        
        <div class="pt-6">
            <p class="text-[11px] uppercase tracking-[0.2em] text-pink-300 font-bold px-8 mb-4">Account</p>
            <a href="performance.php" class="sidebar-link flex items-center gap-4 px-8 py-4 transition-all">
                <i data-lucide="bar-chart-3" class="w-5 h-5"></i> Performance
            </a>
            <a href="notification.php" class="sidebar-active flex items-center justify-between px-8 py-4 transition-all">
                <div class="flex items-center gap-4">
                    <i data-lucide="bell" class="w-5 h-5"></i> Notifications
                </div>
                <?php if($unread_count > 0): ?>
                    <span class="unread-dot animate-pulse"></span>
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
            <a href="#" onclick="confirmLogout(event)">
                <i data-lucide="log-out" class="w-5 h-5 text-pink-200 hover:text-red-500 cursor-pointer"></i>
            </a>
        </div>
    </div>
</aside>

<main class="flex-1 overflow-y-auto p-12">
    <header class="flex justify-between items-end mb-12">
        <div>
            <h1 class="text-4xl font-extrabold text-[#1e293b] tracking-tight uppercase">Notifications</h1>
            <p class="text-pink-400 mt-1 font-bold italic">Stay updated with task assignments and manager feedback.</p>
        </div>
        <form method="POST">
            <button name="mark_all_read" class="bg-white border-2 border-pink-50 px-6 py-3 rounded-2xl text-xs font-black text-[#1e293b] hover:border-[#FB6F92] hover:text-[#FB6F92] hover:bg-pink-50/50 transition-all uppercase tracking-widest shadow-sm">
                Mark all read
            </button>
        </form>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-[3rem] shadow-xl shadow-pink-100/50 border border-pink-100 overflow-hidden">
                <div class="p-8 border-b border-pink-50 bg-white flex justify-between items-center">
                    <h3 class="text-xl font-extrabold text-[#1e293b] flex items-center gap-3 italic">
                        <span class="w-2 h-6 pink-gradient rounded-full"></span>
                        Recent Inbox
                    </h3>
                    <span class="text-[10px] font-black text-pink-300 uppercase tracking-[0.2em]"><?= $unread_count ?> New Alerts</span>
                </div>

                <div class="divide-y divide-pink-50">
                    <?php if($notifications->num_rows > 0): ?>
                        <?php while($n = $notifications->fetch_assoc()): ?>
                        <?php
                            $icon = "bell";
                            if ($n['notification_type'] === 'Approval') {
                                $icon = "check-circle";
                            } elseif ($n['notification_type'] === 'Rejection') {
                                $icon = "x-circle";
                            } elseif ($n['notification_type'] === 'Assignment') {
                                $icon = "clipboard";
                            } elseif ($n['notification_type'] === 'Submission') {
                                $icon = "clipboard-check";
                            }
                        ?>
                        <div class="p-8 hover:bg-[#FFF9FA] transition-all flex gap-6 items-start <?= $n['status'] === 'unread' ? 'bg-pink-50/20' : '' ?>">
                            <div class="w-12 h-12 rounded-2xl <?= $n['status'] === 'unread' ? 'pink-gradient text-white shadow-pink-100' : 'bg-gray-100 text-gray-400' ?> flex items-center justify-center shadow-md">
                                <i data-lucide="<?= $icon ?>" class="w-6 h-6"></i>
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between items-start mb-1">
                                    <p class="text-base font-extrabold text-[#1e293b]"><?= htmlspecialchars($n['notification_type']) ?></p>
                                    <span class="text-[10px] text-pink-300 font-bold uppercase"><?= date('H:i A', strtotime($n['timestamp'])) ?></span>
                                </div>
                                <p class="text-sm text-gray-500 font-medium leading-relaxed"><?= htmlspecialchars($n['message']) ?></p>
                                <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-3"><?= date('d M Y', strtotime($n['timestamp'])) ?></p>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-20 text-center opacity-40">
                             <i data-lucide="inbox" class="w-12 h-12 text-pink-300 mx-auto mb-4"></i>
                             <p class="text-sm font-bold text-gray-400">Your inbox is empty.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="space-y-8">
            <div class="pink-gradient p-8 rounded-[2.5rem] text-white shadow-xl shadow-pink-100/50 relative overflow-hidden">
                <i data-lucide="bell" class="absolute -right-4 -bottom-4 w-32 h-32 opacity-10 rotate-12"></i>
                <h4 class="text-xs font-black uppercase tracking-[0.2em] mb-4 opacity-80">Summary</h4>
                <div class="flex items-center justify-between mb-4">
                    <span class="text-5xl font-black italic tracking-tight"><?= $unread_count ?></span>
                    <i data-lucide="bell-ring" class="w-10 h-10 opacity-50"></i>
                </div>
                <p class="text-xs font-bold leading-relaxed">Alerts pending your review. Timely updates ensure smooth technical operations.</p>
            </div>
        </div>
    </div>
</main>

<script>
    lucide.createIcons();
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