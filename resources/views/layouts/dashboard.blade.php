<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'OptiTask')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Stack+Sans+Headline:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Stack Sans Headline', system-ui, -apple-system, sans-serif; background-color: #FFF5F7; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Stack Sans Headline', system-ui, -apple-system, sans-serif; }
        .pink-gradient { background: linear-gradient(135deg, #FB6F92 0%, #FFB3C6 100%); }
        
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
        
        .status-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23FB6F92'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1rem;
        }

        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #FFF5F7; }
        ::-webkit-scrollbar-thumb { background: #FFD1DC; border-radius: 10px; }

        /* Sidebar slide-in for mobile */
        #sidebar {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        #sidebar-overlay {
            transition: opacity 0.3s ease;
        }
    </style>
    @yield('styles')
</head>
<body class="flex h-screen overflow-hidden">

<!-- Mobile Overlay -->
<div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-20 hidden lg:hidden" onclick="closeSidebar()"></div>

<!-- Sidebar -->
<aside id="sidebar" class="fixed lg:static z-30 w-72 h-full bg-white border-r border-pink-100 flex flex-col shrink-0 -translate-x-full lg:translate-x-0 transition-transform duration-300">

    <div class="p-8 pb-6 flex items-center gap-3">
        <div class="w-12 h-12 pink-gradient rounded-2xl flex items-center justify-center text-white shadow-lg shadow-pink-100">
            <i data-lucide="zap" class="w-6 h-6"></i>
        </div>
        <span class="text-2xl font-bold tracking-tight text-[#1e293b]">
            OptiTask<span class="text-[#FB6F92]">.</span>
        </span>
        <!-- Close button — mobile only -->
        <button onclick="closeSidebar()" class="ml-auto lg:hidden w-8 h-8 flex items-center justify-center rounded-xl text-pink-300 hover:text-pink-500 hover:bg-pink-50">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>
    </div>

    @php
        $unreadNotificationCount = \App\Models\Notification::where('user_id', auth()->id())->where('status', 'unread')->count();
    @endphp

    <nav class="flex-1 space-y-1 pr-4 overflow-y-auto">
        @if(auth()->user()->role === 'Admin')
            <p class="text-[11px] uppercase tracking-[0.2em] text-pink-300 font-bold px-8 mb-4">Admin Console</p>
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-4 px-8 py-4 transition-all {{ request()->routeIs('admin.dashboard') ? 'sidebar-active' : 'sidebar-link' }}">
                <i data-lucide="layout-grid" class="w-5 h-5"></i> Dashboard
            </a>
            <a href="{{ route('admin.manage_users') }}" class="flex items-center gap-4 px-8 py-4 transition-all {{ request()->routeIs('admin.manage_users') ? 'sidebar-active' : 'sidebar-link' }}">
                <i data-lucide="users" class="w-5 h-5"></i> Manage Users
            </a>
            <a href="{{ route('admin.audit') }}" class="flex items-center gap-4 px-8 py-4 transition-all {{ request()->routeIs('admin.audit') ? 'sidebar-active' : 'sidebar-link' }}">
                <i data-lucide="history" class="w-5 h-5"></i> Audit Logs
            </a>

        @elseif(auth()->user()->role === 'Manager')
            <p class="text-[11px] uppercase tracking-[0.2em] text-pink-300 font-bold px-8 mb-4">Manager Console</p>
            <a href="{{ route('manager.dashboard') }}" class="flex items-center gap-4 px-8 py-4 transition-all {{ request()->routeIs('manager.dashboard') ? 'sidebar-active' : 'sidebar-link' }}">
                <i data-lucide="layout-grid" class="w-5 h-5"></i> Dashboard
            </a>
            <a href="{{ route('manager.assign_tasks') }}" class="flex items-center gap-4 px-8 py-4 transition-all {{ request()->routeIs('manager.assign_tasks') ? 'sidebar-active' : 'sidebar-link' }}">
                <i data-lucide="clipboard-list" class="w-5 h-5"></i> Assign Tasks
            </a>
            <a href="{{ route('manager.verify_tasks') }}" class="flex items-center gap-4 px-8 py-4 transition-all {{ request()->routeIs('manager.verify_tasks') ? 'sidebar-active' : 'sidebar-link' }}">
                <i data-lucide="check-circle" class="w-5 h-5"></i> Verify Submissions
            </a>
            <a href="{{ route('manager.notification') }}" class="flex items-center justify-between px-8 py-4 transition-all {{ request()->routeIs('manager.notification') ? 'sidebar-active' : 'sidebar-link' }}">
                <span class="flex items-center gap-4">
                    <i data-lucide="bell" class="w-5 h-5"></i> Notifications
                </span>
                @if($unreadNotificationCount > 0)
                    <span class="w-2.5 h-2.5 rounded-full bg-red-500 mr-2 animate-pulse"></span>
                @endif
            </a>

        @elseif(auth()->user()->role === 'Employee' || auth()->user()->role === 'employee')
            <p class="text-[11px] uppercase tracking-[0.2em] text-pink-300 font-bold px-8 mb-4">Employee Console</p>
            <a href="{{ route('employee.dashboard') }}" class="flex items-center gap-4 px-8 py-4 transition-all {{ request()->routeIs('employee.dashboard') ? 'sidebar-active' : 'sidebar-link' }}">
                <i data-lucide="layout-grid" class="w-5 h-5"></i> Dashboard
            </a>
            <a href="{{ route('employee.tasks') }}" class="flex items-center gap-4 px-8 py-4 transition-all {{ request()->routeIs('employee.tasks') ? 'sidebar-active' : 'sidebar-link' }}">
                <i data-lucide="clipboard-list" class="w-5 h-5"></i> My Tasks
            </a>

            <div class="pt-6">
                <p class="text-[11px] uppercase tracking-[0.2em] text-pink-300 font-bold px-8 mb-4">Account</p>
                <a href="{{ route('employee.skills') }}" class="flex items-center gap-4 px-8 py-4 transition-all {{ request()->routeIs('employee.skills') ? 'sidebar-active' : 'sidebar-link' }}">
                    <i data-lucide="user" class="w-5 h-5"></i> Profile
                </a>
                <a href="{{ route('employee.performance') }}" class="flex items-center gap-4 px-8 py-4 transition-all {{ request()->routeIs('employee.performance') ? 'sidebar-active' : 'sidebar-link' }}">
                    <i data-lucide="bar-chart-3" class="w-5 h-5"></i> Performance
                </a>
                <a href="{{ route('employee.notification') }}" class="flex items-center justify-between px-8 py-4 transition-all {{ request()->routeIs('employee.notification') ? 'sidebar-active' : 'sidebar-link' }}">
                    <span class="flex items-center gap-4">
                        <i data-lucide="bell" class="w-5 h-5"></i> Notifications
                    </span>
                    @if($unreadNotificationCount > 0)
                        <span class="w-2.5 h-2.5 rounded-full bg-red-500 mr-2 animate-pulse"></span>
                    @endif
                </a>
            </div>
        @endif
    </nav>

    <div class="p-6">
        <div class="bg-[#FFF9FA] rounded-[1.5rem] p-4 flex items-center gap-3 border border-pink-100">
            <div class="w-10 h-10 rounded-full bg-white border-2 border-pink-200 text-[#FB6F92] flex items-center justify-center font-bold text-sm shrink-0">
                {{ strtoupper(substr(auth()->user()->username, 0, 2)) }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-extrabold text-[#1e293b] truncate">{{ auth()->user()->username }}</p>
                <p class="text-[11px] text-pink-400 font-bold uppercase tracking-wider">{{ auth()->user()->user_id }}</p>
            </div>
            <button id="logout-trigger" class="shrink-0">
                <i data-lucide="log-out" class="w-5 h-5 text-pink-200 hover:text-red-500 cursor-pointer transition-colors"></i>
            </button>
        </div>
    </div>
</aside>

<!-- Main Wrapper (takes remaining width) -->
<div class="flex-1 flex flex-col min-w-0 overflow-hidden">

    <!-- Mobile Top Bar -->
    <header class="lg:hidden flex items-center gap-3 px-4 py-3 bg-white border-b border-pink-100 shrink-0">
        <button onclick="openSidebar()" class="w-10 h-10 flex items-center justify-center rounded-xl bg-pink-50 text-[#FB6F92] hover:bg-pink-100 transition-colors">
            <i data-lucide="menu" class="w-5 h-5"></i>
        </button>
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 pink-gradient rounded-xl flex items-center justify-center text-white shadow shadow-pink-100 shrink-0">
                <i data-lucide="zap" class="w-4 h-4"></i>
            </div>
            <span class="text-lg font-bold tracking-tight text-[#1e293b]">OptiTask<span class="text-[#FB6F92]">.</span></span>
        </div>
    </header>

    <!-- Page Content -->
    <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-12">
        @yield('content')
    </main>
</div>

<script>
    lucide.createIcons();

    function openSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
    }

    function closeSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
    }

    document.getElementById('logout-trigger').addEventListener('click', function() {
        Swal.fire({
            title: 'End session?',
            text: "Ensure all your progress is saved!",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#FF8FAB',
            cancelButtonColor: '#1e293b',
            confirmButtonText: 'Yes, Sign Out',
            cancelButtonText: 'Stay Here',
            background: '#FFF9FA',
            customClass: {
                popup: 'rounded-[2.5rem] border-2 border-pink-100',
                title: 'font-black text-[#1e293b]',
                confirmButton: 'rounded-xl px-6 py-3',
                cancelButton: 'rounded-xl px-6 py-3'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "{{ route('logout') }}";
            }
        });
    });
</script>
@yield('scripts')
</body>
</html>
