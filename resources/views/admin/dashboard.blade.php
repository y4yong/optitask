@extends('layouts.dashboard')

@section('title', 'OptiTask | Admin Dashboard')

@section('content')
    <!-- Header -->
    <header class="flex justify-between items-end mb-12">
        <div>
            <h1 class="text-4xl font-extrabold text-[#1e293b] tracking-tight uppercase">Admin Dashboard</h1>
            <p class="text-pink-400 mt-1 font-bold">Real-time system health and administration metrics.</p>
        </div>
        <div class="bg-white px-6 py-3 rounded-2xl shadow-sm border border-pink-50 flex items-center gap-3">
            <div class="w-2.5 h-2.5 rounded-full bg-green-500 animate-pulse"></div>
            <span class="font-bold text-[#1e293b] text-xs uppercase tracking-wider">System: Active</span>
        </div>
    </header>

    <!-- KPI Grid -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-12">
        <!-- Card 1: Total Users -->
        <div class="glass-card p-8 relative overflow-hidden group">
            <div class="absolute -right-4 -top-4 w-24 h-24 bg-pink-50 rounded-full group-hover:bg-pink-100/70 transition-colors"></div>
            <i data-lucide="users" class="w-6 h-6 text-[#FB6F92] mb-4 relative z-10"></i>
            <p class="text-gray-400 text-[10px] font-bold uppercase tracking-widest relative z-10">Total Accounts</p>
            <h2 class="text-4xl font-black text-gray-800 mt-2 relative z-10">{{ $totalUsers }}</h2>
            <div class="flex gap-4 mt-3 text-[10px] font-bold text-gray-400 relative z-10">
                <span class="text-green-500">{{ $activeAccts }} Active</span>
                <span class="text-red-400">{{ $suspendedAccts }} Suspended</span>
            </div>
        </div>

        <!-- Card 2: Total Tasks -->
        <div class="glass-card p-8 relative overflow-hidden group">
            <div class="absolute -right-4 -top-4 w-24 h-24 bg-pink-50 rounded-full group-hover:bg-pink-100/70 transition-colors"></div>
            <i data-lucide="briefcase" class="w-6 h-6 text-[#FB6F92] mb-4 relative z-10"></i>
            <p class="text-gray-400 text-[10px] font-bold uppercase tracking-widest relative z-10">Total Tasks</p>
            <h2 class="text-4xl font-black text-gray-800 mt-2 relative z-10">{{ $totalTasks }}</h2>
            <p class="text-gray-400 text-[10px] font-semibold mt-3 relative z-10">{{ $pendingTasks }} Pending Review</p>
        </div>

        <!-- Card 3: Completion Rate -->
        <div class="glass-card p-8 relative overflow-hidden group">
            <div class="absolute -right-4 -top-4 w-24 h-24 bg-pink-50 rounded-full group-hover:bg-pink-100/70 transition-colors"></div>
            <i data-lucide="check-circle" class="w-6 h-6 text-[#FB6F92] mb-4 relative z-10"></i>
            <p class="text-gray-400 text-[10px] font-bold uppercase tracking-widest relative z-10">Task Completion</p>
            @php 
                $rate = ($totalTasks > 0) ? ($completedTasks / $totalTasks) * 100 : 0;
            @endphp
            <h2 class="text-4xl font-black text-gray-800 mt-2 relative z-10">{{ number_format($rate, 1) }}%</h2>
            <div class="mt-4 w-full bg-pink-100 rounded-full h-1.5 overflow-hidden relative z-10">
                <div class="pink-gradient h-1.5 rounded-full" style="width: {{ $rate }}%"></div>
            </div>
        </div>

        <!-- Card 4: Workforce Mix -->
        <div class="glass-card p-8 relative overflow-hidden group">
            <div class="absolute -right-4 -top-4 w-24 h-24 bg-pink-50 rounded-full group-hover:bg-pink-100/70 transition-colors"></div>
            <i data-lucide="shield" class="w-6 h-6 text-[#FB6F92] mb-4 relative z-10"></i>
            <p class="text-gray-400 text-[10px] font-bold uppercase tracking-widest relative z-10">Workforce Roles</p>
            <h2 class="text-4xl font-black text-gray-800 mt-2 relative z-10">{{ $countEmp + $countMgr }}</h2>
            <div class="flex gap-4 mt-3 text-[10px] font-bold text-gray-400 relative z-10">
                <span>{{ $countMgr }} Managers</span>
                <span>{{ $countEmp }} Employees</span>
            </div>
        </div>
    </div>

    <!-- Row 1: Graphical Performance Analytics & Combined Tabbed Monitor/Matrix -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
        <!-- Left Column: Graphical Performance Analytics -->
        <div class="lg:col-span-2">
            <div class="glass-card p-8 h-full flex flex-col justify-between">
                <h3 class="font-extrabold text-[#1e293b] text-xl mb-6 flex items-center gap-3">
                    <span class="w-2.5 h-6 pink-gradient rounded-full"></span>
                    Graphical Performance Analytics
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 flex-1 items-center">
                    <!-- Status Distribution Chart -->
                    <div class="flex flex-col items-center">
                        <h4 class="text-xs font-black uppercase text-pink-400 tracking-wider mb-2">Task Status Distribution</h4>
                        <div class="relative h-[220px] w-full flex justify-center">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                    <!-- Department Productivity Chart -->
                    <div class="flex flex-col items-center">
                        <h4 class="text-xs font-black uppercase text-pink-400 tracking-wider mb-2">Department Productivity</h4>
                        <div class="relative h-[220px] w-full">
                            <canvas id="deptChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Combined Live Activity Monitor & Staffing Matrix -->
        <div class="lg:col-span-1">
            <div class="glass-card p-8 flex flex-col h-full justify-between">
                <!-- Tab Headers -->
                <div class="flex gap-2 p-1.5 bg-pink-50/50 rounded-2xl mb-6">
                    <button id="tab-activity-btn" class="flex-1 py-2 text-xs font-black rounded-xl transition-all pink-gradient text-white shadow-sm">
                        Activity Monitor
                    </button>
                    <button id="tab-matrix-btn" class="flex-1 py-2 text-xs font-black text-gray-500 rounded-xl hover:text-pink-500 hover:bg-white/50 transition-all">
                        Staff Matrix
                    </button>
                </div>

                <!-- Tab 1 Content: Live Activity Monitor -->
                <div id="tab-activity-content" class="flex-1 flex flex-col justify-between">
                    <div class="space-y-5 relative border-l-2 border-pink-100 ml-2 pl-6 overflow-y-auto max-h-[220px] pr-2">
                        @if($recentLogs->isEmpty())
                            <p class="text-xs text-gray-400 font-bold italic">No activity detected.</p>
                        @else
                            @foreach($recentLogs as $log)
                                @php 
                                    $isAuth = in_array($log->action, ['LOGIN', 'LOGOUT']);
                                    $dot_color = $isAuth ? 'bg-gray-400 ring-gray-100' : 'bg-[#FB6F92] ring-pink-100 shadow-pink-100 shadow-lg';
                                @endphp
                                <div class="relative">
                                    <div class="absolute -left-[31px] top-1.5 w-3 h-3 rounded-full {{ $dot_color }} ring-4"></div>
                                    <p class="text-xs font-black text-gray-800 uppercase tracking-wider">{{ $log->action }}</p>
                                    <p class="text-[10px] text-gray-500 font-medium mt-0.5 leading-relaxed truncate" title="{{ $log->details }}">{{ $log->details }}</p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-[8px] bg-pink-50 text-pink-500 px-1.5 py-0.5 rounded font-bold uppercase tracking-wider">{{ $log->username ?? 'System' }}</span>
                                        <span class="text-[8px] text-gray-400 font-mono">{{ date('h:i A', strtotime($log->timestamp)) }}</span>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                    <div class="mt-4 pt-4 border-t border-pink-50 text-center">
                        <a href="{{ route('admin.audit') }}" class="inline-flex items-center gap-2 text-xs font-extrabold text-[#FB6F92] hover:text-[#FF8FAB] transition-colors uppercase tracking-widest">
                            View Full Event Log <i data-lucide="chevron-right" class="w-4 h-4"></i>
                        </a>
                    </div>
                </div>

                <!-- Tab 2 Content: Staffing Matrix -->
                <div id="tab-matrix-content" class="hidden flex-1 flex flex-col justify-center space-y-5">
                    <!-- Managers Progress -->
                    <div>
                        <div class="flex justify-between text-xs font-bold text-gray-700 mb-1.5">
                            <span>Managers</span>
                            <span>{{ $countMgr }}</span>
                        </div>
                        <div class="w-full bg-pink-50 rounded-full h-2 overflow-hidden">
                            <div class="pink-gradient h-2 rounded-full" style="width: {{ ($totalUsers > 0) ? ($countMgr / $totalUsers) * 100 : 0 }}%"></div>
                        </div>
                    </div>
                    <!-- Employees Progress -->
                    <div>
                        <div class="flex justify-between text-xs font-bold text-gray-700 mb-1.5">
                            <span>Employees</span>
                            <span>{{ $countEmp }}</span>
                        </div>
                        <div class="w-full bg-pink-50 rounded-full h-2 overflow-hidden">
                            <div class="pink-gradient h-2 rounded-full" style="width: {{ ($totalUsers > 0) ? ($countEmp / $totalUsers) * 100 : 0 }}%"></div>
                        </div>
                    </div>
                    <!-- Admins Progress -->
                    <div>
                        <div class="flex justify-between text-xs font-bold text-gray-700 mb-1.5">
                            <span>Admins</span>
                            <span>{{ $countAdmin }}</span>
                        </div>
                        <div class="w-full bg-pink-50 rounded-full h-2 overflow-hidden">
                            <div class="pink-gradient h-2 rounded-full" style="width: {{ ($totalUsers > 0) ? ($countAdmin / $totalUsers) * 100 : 0 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 2: Recent Task Assignments (Full Width) -->
    <div class="glass-card overflow-hidden w-full">
        <div class="p-8 border-b border-pink-50 bg-white/50 flex justify-between items-center">
            <h3 class="font-extrabold text-[#1e293b] text-xl flex items-center gap-3">
                <span class="w-2.5 h-6 pink-gradient rounded-full"></span>
                Recent Task Assignments
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-[10px] uppercase font-black text-pink-300 tracking-[0.1em] border-b border-pink-50 bg-[#FFF9FA]">
                        <th class="px-8 py-4">Task Info</th>
                        <th class="px-8 py-4">Assignee</th>
                        <th class="px-8 py-4">Deadline</th>
                        <th class="px-8 py-4 text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="text-sm font-semibold divide-y divide-pink-50">
                    @if($recentTasks->count() > 0)
                        @foreach ($recentTasks as $t)
                        <tr class="hover:bg-[#FFF9FA] transition-colors">
                            <td class="px-8 py-5">
                                <p class="text-gray-800 font-extrabold text-sm">{{ $t->task_title }}</p>
                                <span class="text-[9px] font-mono text-gray-400 font-bold uppercase tracking-widest mt-0.5 inline-block">{{ $t->task_id }}</span>
                            </td>
                            <td class="px-8 py-5">
                                <p class="text-gray-700 font-bold text-xs">{{ $t->employee->username ?? 'Unassigned' }}</p>
                            </td>
                            <td class="px-8 py-5">
                                <span class="text-gray-500 font-bold text-xs">{{ $t->due_date ? date('d M Y', strtotime($t->due_date)) : '-' }}</span>
                            </td>
                            <td class="px-8 py-5 text-right">
                                @php 
                                    $st_color = 'bg-gray-100 text-gray-600';
                                    if ($t->task_status === 'To-Do') $st_color = 'bg-pink-50 text-pink-500';
                                    if ($t->task_status === 'In Progress') $st_color = 'bg-blue-50 text-blue-500';
                                    if ($t->task_status === 'Done') $st_color = 'bg-green-50 text-green-500';
                                    if ($t->task_status === 'Verified') $st_color = 'bg-[#EFF6FF] text-[#3B82F6]';
                                @endphp
                                <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest {{ $st_color }}">
                                    {{ $t->task_status }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="4" class="px-8 py-10 text-center text-gray-400 text-xs italic">No tasks created yet.</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Status Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    const statusLabels = {!! json_encode($statusLabels) !!};
    const statusColors = statusLabels.map(label => {
        if (label === 'Done') return '#10B981'; // Green
        if (label === 'Verified') return '#86EFAC'; // Light green
        if (label === 'In Progress') return '#3B82F6'; // Blue
        if (label === 'To-Do') return '#FB6F92'; // Pink
        return '#CBD5E1'; // Default
    });
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: {!! json_encode($statusCounts) !!},
                backgroundColor: statusColors,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { family: 'Quicksand', weight: 'bold' } } } },
            cutout: '70%'
        }
    });

    // Dept Chart
    const deptCtx = document.getElementById('deptChart').getContext('2d');
    new Chart(deptCtx, {
        type: 'bar',
        data: {
            labels: {!! json_encode($deptLabels) !!},
            datasets: [{
                label: 'Completed Tasks',
                data: {!! json_encode($deptCounts) !!},
                backgroundColor: '#FB6F92',
                borderRadius: 8,
                barThickness: 20
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, grid: { borderDash: [5, 5] }, ticks: { font: { family: 'Quicksand', weight: 'bold' } } },
                x: { grid: { display: false }, ticks: { font: { family: 'Quicksand', weight: 'bold' } } }
            },
            plugins: { legend: { display: false } }
        }
    });

    // Tab Switching
    const tabActivityBtn = document.getElementById('tab-activity-btn');
    const tabMatrixBtn = document.getElementById('tab-matrix-btn');
    const tabActivityContent = document.getElementById('tab-activity-content');
    const tabMatrixContent = document.getElementById('tab-matrix-content');

    tabActivityBtn.addEventListener('click', () => {
        tabActivityBtn.className = "flex-1 py-2 text-xs font-black rounded-xl transition-all pink-gradient text-white shadow-sm";
        tabMatrixBtn.className = "flex-1 py-2 text-xs font-black text-gray-500 rounded-xl hover:text-pink-500 hover:bg-white/50 transition-all";
        tabActivityContent.classList.remove('hidden');
        tabActivityContent.classList.add('flex');
        tabMatrixContent.classList.add('hidden');
        tabMatrixContent.classList.remove('flex');
    });

    tabMatrixBtn.addEventListener('click', () => {
        tabMatrixBtn.className = "flex-1 py-2 text-xs font-black rounded-xl transition-all pink-gradient text-white shadow-sm";
        tabActivityBtn.className = "flex-1 py-2 text-xs font-black text-gray-500 rounded-xl hover:text-pink-500 hover:bg-white/50 transition-all";
        tabMatrixContent.classList.remove('hidden');
        tabMatrixContent.classList.add('flex');
        tabActivityContent.classList.add('hidden');
        tabActivityContent.classList.remove('flex');
    });
</script>
@endsection
