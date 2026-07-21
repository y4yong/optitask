@extends('layouts.dashboard')

@section('title', 'OptiTask | Manager Dashboard')

@section('styles')
<style>
    /* Status-specific badges */
    .badge-todo { background-color: #FFF5F7; color: #FB6F92; border: 1px solid #FFE5EC; }
    .badge-inprogress { background-color: #EFF6FF; color: #3B82F6; border: 1px solid #DBEAFE; }
    .badge-review { background-color: #FEF3C7; color: #D97706; border: 1px solid #FEF3C7; }
    .badge-done { background-color: #ECFDF5; color: #10B981; border: 1px solid #A7F3D0; }
    .badge-verified { background-color: #F5F3FF; color: #7C3AED; border: 1px solid #DDD6FE; }
    .badge-rejected { background-color: #FEF2F2; color: #EF4444; border: 1px solid #FEE2E2; }
</style>
@endsection

@section('content')
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
                            <th class="px-6 py-4 text-right">Score</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm font-semibold divide-y divide-gray-50">
                        @if (count($rankedEmployees) > 0)
                            @foreach($rankedEmployees as $idx => $emp)
                            <tr class="hover:bg-[#FFF9FA] transition-all">
                                <td class="px-6 py-4">
                                    <span class="w-8 h-8 rounded-full bg-pink-50 text-[#FB6F92] flex items-center justify-center font-black text-xs border border-pink-100">
                                        {{ $idx + 1 }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-[#1e293b] font-extrabold text-sm">{{ $emp['username'] }}</p>
                                    <span class="text-[9px] text-gray-400 uppercase">{{ $emp['dept_name'] }}</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <span class="inline-flex items-center px-3 py-1 rounded-lg bg-green-50 text-green-600 font-black text-xs">
                                        {{ number_format($emp['score'], 1) }}%
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="3" class="px-6 py-10 text-center text-gray-400 font-bold">No employees found.</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Assigned Tasks Modal -->
    <div id="assignedModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-gray-950/50 backdrop-blur-sm">
      <div class="bg-white w-full max-w-5xl max-h-[85vh] rounded-[2rem] overflow-hidden flex flex-col border border-pink-50 shadow-2xl">
        <!-- Header -->
        <div class="p-6 bg-gradient-to-r from-pink-50 to-white border-b border-pink-100 flex justify-between items-center">
          <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-[#FB6F92] to-[#ff9ab2] flex items-center justify-center text-white shadow-lg shadow-pink-200">
              <i data-lucide="clipboard-list" class="w-5 h-5"></i>
            </div>
            <div>
              <h3 class="text-lg font-black text-[#1e293b]">All Assigned Tasks</h3>
              <p class="text-xs text-[#FB6F92] font-semibold">Full task history & attachments</p>
            </div>
          </div>
          <button onclick="closeModal('assignedModal')" class="w-10 h-10 bg-white rounded-full border border-pink-100 flex items-center justify-center text-gray-400 hover:text-red-500 transition-all">
            <i data-lucide="x" class="w-5 h-5"></i>
          </button>
        </div>
        
        <!-- Filters -->
        <div class="p-4 bg-[#fffafb] border-b border-pink-50 flex gap-3 flex-wrap items-center">
          <div class="flex items-center gap-2 bg-white border border-pink-100 rounded-xl px-3 py-2 text-xs font-bold text-gray-700">
            <i data-lucide="calendar" class="w-4 h-4 text-[#FB6F92]"></i>
            <input type="date" id="assignedStartFilter" onchange="filterAssigned()" class="border-none outline-none bg-transparent">
          </div>
          <span class="text-pink-300 text-sm">&rarr;</span>
          <div class="flex items-center gap-2 bg-white border border-pink-100 rounded-xl px-3 py-2 text-xs font-bold text-gray-700">
            <i data-lucide="calendar" class="w-4 h-4 text-[#FB6F92]"></i>
            <input type="date" id="assignedEndFilter" onchange="filterAssigned()" class="border-none outline-none bg-transparent">
          </div>
          <select id="assignedStatusFilter" onchange="filterAssigned()" class="bg-white border border-pink-100 rounded-xl px-3 py-2 text-xs font-bold text-gray-700 outline-none">
            <option value="">All Statuses</option>
            <option value="To-Do">To-Do</option>
            <option value="In Progress">In Progress</option>
            <option value="Review">Submitted / Review</option>
            <option value="Verified">Verified</option>
            <option value="Rejected">Rejected</option>
          </select>
          <div class="flex items-center gap-2 bg-white border border-pink-100 rounded-xl px-3 py-2 text-xs font-bold text-gray-700 flex-1 min-w-[200px]">
            <i data-lucide="search" class="w-4 h-4 text-[#FB6F92]"></i>
            <input type="text" id="assignedSearchFilter" onkeyup="filterAssigned()" placeholder="Search ID, title or employee..." class="border-none outline-none bg-transparent w-full">
          </div>
          <button onclick="clearAssignedFilters()" class="px-4 py-2 bg-pink-50 text-[#FB6F92] rounded-xl text-xs font-bold hover:bg-[#FB6F92] hover:text-white transition-all">Clear</button>
        </div>
        
        <!-- Table -->
        <div class="overflow-y-auto flex-1">
          <table class="w-full text-left text-sm font-semibold">
            <thead class="sticky top-0 bg-[#FFF9FA] border-b border-pink-50 z-10 text-[10px] uppercase font-black text-pink-300 tracking-[0.08em]">
              <tr>
                <th class="p-4 pl-6">Assigned</th>
                <th class="p-4">Due</th>
                <th class="p-4">ID</th>
                <th class="p-4">Title</th>
                <th class="p-4">Employee</th>
                <th class="p-4">Priority</th>
                <th class="p-4">Attached File</th>
                <th class="p-4">Submission</th>
                <th class="p-4 pr-6 text-right">Status</th>
              </tr>
            </thead>
            <tbody id="assignedTableBody" class="divide-y divide-pink-50 bg-white">
              @if ($assignedTasks->count() > 0)
                @foreach ($assignedTasks as $t)
                  <tr class="assigned-tr-row hover:bg-[#FFF9FA] transition-colors"
                      data-start="{{ $t->start_date }}"
                      data-due="{{ $t->due_date }}"
                      data-status="{{ $t->task_status }}"
                      data-search="{{ strtolower($t->task_id . ' ' . $t->task_title . ' ' . ($t->employee->username ?? '')) }}">
                    <td class="p-4 pl-6 text-xs text-gray-400 font-mono">{{ $t->start_date ? date('d-m-Y', strtotime($t->start_date)) : '-' }}</td>
                    <td class="p-4 text-xs text-gray-500 font-mono">{{ $t->due_date ? date('d-m-Y', strtotime($t->due_date)) : '-' }}</td>
                    <td class="p-4 text-xs text-[#FB6F92] font-mono">{{ $t->task_id }}</td>
                    <td class="p-4 max-w-[200px] truncate" title="{{ $t->task_title }}">{{ $t->task_title }}</td>
                    <td class="p-4 text-xs text-gray-700 font-bold">{{ $t->employee->username ?? 'Unassigned' }}</td>
                    <td class="p-4">
                      <span class="text-[9px] font-black uppercase {{ $t->priority === 'High' ? 'text-red-500' : 'text-gray-400' }}">{{ $t->priority }}</span>
                    </td>
                    <td class="p-4">
                      @if ($t->task_file)
                        <a href="{{ asset('storage/' . $t->task_file) }}" target="_blank" class="text-xs text-blue-500 hover:underline flex items-center gap-1">
                          <i data-lucide="file" class="w-3.5 h-3.5"></i> View File
                        </a>
                      @else
                        <span class="text-xs text-gray-400">None</span>
                      @endif
                    </td>
                    <td class="p-4">
                      @if ($t->submission_file)
                        <a href="{{ asset('storage/' . $t->submission_file) }}" target="_blank" class="text-xs text-green-600 hover:underline flex items-center gap-1">
                          <i data-lucide="file-check" class="w-3.5 h-3.5"></i> Download
                        </a>
                      @else
                        <span class="text-xs text-gray-400">Not Submitted</span>
                      @endif
                    </td>
                    <td class="p-4 pr-6 text-right">
                      @php
                        $badge = 'badge-todo';
                        if ($t->task_status === 'In Progress') $badge = 'badge-inprogress';
                        if ($t->task_status === 'Review') $badge = 'badge-review';
                        if ($t->task_status === 'Done') $badge = 'badge-done';
                        if ($t->task_status === 'Verified') $badge = 'badge-verified';
                      @endphp
                      <span class="px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-wider {{ $badge }}">
                        {{ $t->task_status }}
                      </span>
                    </td>
                  </tr>
                @endforeach
              @else
                <tr>
                  <td colspan="9" class="p-8 text-center text-gray-400 italic">No tasks found.</td>
                </tr>
              @endif
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Header -->
    <header class="flex justify-between items-end mb-12">
        <div>
            <h1 class="text-4xl font-extrabold text-[#1e293b] tracking-tight uppercase">Manager Console</h1>
            <p class="text-pink-400 mt-1 font-bold">Department tasks monitoring and candidate allocation.</p>
        </div>
        <div class="bg-white px-6 py-3 rounded-2xl shadow-sm border border-pink-50 flex items-center gap-3">
            <div class="w-2.5 h-2.5 rounded-full bg-pink-500 animate-pulse"></div>
            <span class="font-bold text-[#1e293b] text-xs uppercase tracking-wider">Manager Mode</span>
        </div>
    </header>

    <!-- Top KPI Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
        <div onclick="openModal('assignedModal')" class="glass-card p-8 cursor-pointer relative overflow-hidden group">
            <div class="absolute -right-4 -top-4 w-24 h-24 bg-pink-50 rounded-full group-hover:bg-pink-100/70 transition-colors"></div>
            <i data-lucide="clipboard-list" class="w-7 h-7 text-[#FB6F92] mb-4 relative z-10"></i>
            <p class="text-gray-400 text-[10px] font-bold uppercase tracking-widest relative z-10">Assigned Tasks</p>
            <h2 class="text-4xl font-black text-gray-800 mt-2 relative z-10">{{ $totalTasks }}</h2>
            <p class="text-[10px] text-pink-400 font-bold mt-3 relative z-10 flex items-center gap-1">Click to view task details <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></p>
        </div>

        <div class="glass-card p-8 relative overflow-hidden group">
            <div class="absolute -right-4 -top-4 w-24 h-24 bg-pink-50 rounded-full group-hover:bg-pink-100/70 transition-colors"></div>
            <i data-lucide="users" class="w-7 h-7 text-[#FB6F92] mb-4 relative z-10"></i>
            <p class="text-gray-400 text-[10px] font-bold uppercase tracking-widest relative z-10">Active Staff</p>
            <h2 class="text-4xl font-black text-gray-800 mt-2 relative z-10">{{ $totalEmployees }}</h2>
            <p class="text-[10px] text-gray-400 font-semibold mt-3 relative z-10">Currently assigned tasks</p>
        </div>

        <div class="glass-card p-8 relative overflow-hidden group">
            <div class="absolute -right-4 -top-4 w-24 h-24 bg-pink-50 rounded-full group-hover:bg-pink-100/70 transition-colors"></div>
            <i data-lucide="check-circle-2" class="w-7 h-7 text-[#FB6F92] mb-4 relative z-10"></i>
            <p class="text-gray-400 text-[10px] font-bold uppercase tracking-widest relative z-10">Verified Completions</p>
            <h2 class="text-4xl font-black text-gray-800 mt-2 relative z-10">{{ $totalVerified }}</h2>
            <p class="text-[10px] text-gray-400 font-semibold mt-3 relative z-10">Completed & Verified</p>
        </div>
    </div>

    <!-- Row 1: Workforce Performance and Department Leaderboard -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12">
        <!-- Workforce performance monitoring list -->
        <div class="lg:col-span-2 glass-card overflow-hidden">
            <div class="p-8 border-b border-pink-50 bg-white/50 flex justify-between items-center">
                <h3 class="font-extrabold text-[#1e293b] text-xl flex items-center gap-3">
                    <span class="w-2.5 h-6 pink-gradient rounded-full"></span>
                    Workforce Monitor
                </h3>
            </div>
            <div class="p-6 space-y-6">
                @if ($workforceData->isNotEmpty())
                    @foreach ($workforceData as $w)
                        <div class="bg-[#FFF9FA] rounded-[1.5rem] p-5 border border-pink-50 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-white border-2 border-pink-200 text-[#FB6F92] flex items-center justify-center font-bold text-sm">
                                    {{ strtoupper(substr($w['username'], 0, 2)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-extrabold text-gray-800">{{ $w['username'] }}</p>
                                    <span class="text-[9px] font-mono text-gray-400 font-bold uppercase tracking-wider">{{ $w['user_id'] }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-8">
                                <div class="text-center">
                                    <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">Efficiency</p>
                                    <span class="text-sm font-black text-green-500">{{ number_format($w['performance_percentage'], 1) }}%</span>
                                </div>
                                <div class="text-center">
                                    <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">Tasks</p>
                                    <span class="text-sm font-black text-gray-700">{{ $w['completed'] }}/{{ $w['total_tasks'] }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <p class="text-center py-10 text-xs text-gray-400 italic">No workforce logs found for this manager.</p>
                @endif
            </div>
        </div>

        <!-- Leaderboard -->
        <div class="lg:col-span-1 glass-card p-8 flex flex-col justify-between">
            <div>
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-extrabold text-[#1e293b] text-xl flex items-center gap-3">
                        <span class="w-2.5 h-6 pink-gradient rounded-full"></span>
                        Leaderboard
                    </h3>
                    <button onclick="document.getElementById('rankingModal').classList.remove('hidden')" class="text-xs font-black text-[#FB6F92] hover:underline">View All</button>
                </div>
                
                <form method="GET" action="{{ route('manager.dashboard') }}" class="mb-6">
                    <select name="dept_filter" onchange="this.form.submit()" class="w-full bg-[#FFF9FA] border-2 border-pink-50 rounded-2xl px-4 py-3 text-xs font-bold text-gray-700 outline-none">
                        <option value="all">All Departments</option>
                        @foreach ($departments as $d)
                            <option value="{{ $d->dept_id }}" {{ $selectedDept == $d->dept_id ? 'selected' : '' }}>{{ $d->dept_name }}</option>
                        @endforeach
                    </select>
                </form>

                <div class="space-y-4">
                    @if (count($top3) > 0)
                        @foreach ($top3 as $idx => $emp)
                            <div class="flex items-center justify-between p-4 bg-white border border-pink-50 rounded-2xl shadow-sm">
                                <div class="flex items-center gap-3">
                                    <span class="w-7 h-7 rounded-full bg-pink-50 text-[#FB6F92] flex items-center justify-center font-black text-xs border border-pink-100 shadow-sm">
                                        {{ $idx + 1 }}
                                    </span>
                                    <div>
                                        <p class="text-xs font-extrabold text-gray-800">{{ $emp['username'] }}</p>
                                        <span class="text-[9px] text-gray-400 font-mono">{{ $emp['user_id'] }}</span>
                                    </div>
                                </div>
                                <span class="bg-green-50 text-green-600 px-2 py-0.5 rounded text-[9px] font-black">{{ number_format($emp['score'], 1) }}%</span>
                            </div>
                        @endforeach
                    @else
                        <p class="text-center py-10 text-xs text-gray-400 italic">No ranking data.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    // Modal utility methods
    function openModal(id) {
        document.getElementById(id).classList.remove('hidden');
        document.getElementById(id).classList.add('flex');
    }
    
    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
        document.getElementById(id).classList.remove('flex');
    }

    // Modal Table Filtering
    function filterAssigned() {
        const start = document.getElementById('assignedStartFilter').value;
        const end = document.getElementById('assignedEndFilter').value;
        const status = document.getElementById('assignedStatusFilter').value;
        const search = document.getElementById('assignedSearchFilter').value.toLowerCase();
        const rows = document.querySelectorAll('.assigned-tr-row');
        
        rows.forEach(row => {
            const rowStart = row.dataset.start;
            const rowDue = row.dataset.due;
            const rowStatus = row.dataset.status;
            const rowSearch = row.dataset.search;

            let matchesStart = true;
            let matchesEnd = true;
            let matchesStatus = true;
            let matchesSearch = true;

            if (start && rowStart) matchesStart = new Date(rowStart) >= new Date(start);
            if (end && rowDue) matchesEnd = new Date(rowDue) <= new Date(end);
            if (status) matchesStatus = rowStatus === status;
            if (search) matchesSearch = rowSearch.includes(search);

            if (matchesStart && matchesEnd && matchesStatus && matchesSearch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    function clearAssignedFilters() {
        document.getElementById('assignedStartFilter').value = '';
        document.getElementById('assignedEndFilter').value = '';
        document.getElementById('assignedStatusFilter').value = '';
        document.getElementById('assignedSearchFilter').value = '';
        filterAssigned();
    }
</script>
@endsection
