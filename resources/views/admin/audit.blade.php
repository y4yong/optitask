@extends('layouts.dashboard')

@section('title', 'OptiTask | Audit Trail')

@section('styles')
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <style>
    .log-row {
        transition: all 0.2s ease;
    }
    .log-row:hover {
        background-color: rgba(255, 241, 242, 0.5) !important;
    }
  </style>
@endsection

@section('content')
  <header class="flex justify-between items-end mb-12">
    <div>
      <h1 class="text-4xl font-extrabold text-[#1e293b] tracking-tight uppercase">Audit Trail</h1>
      <p class="text-pink-400 mt-1 font-bold">Live system events, logins, modifications, and security actions.</p>
    </div>

    <div class="flex gap-4 items-center">
      <form method="GET" action="{{ route('admin.audit') }}" class="flex gap-2 items-center bg-white border border-pink-100 rounded-full p-1.5 shadow-sm">
        <input type="date" name="date" value="{{ $dateFilter === 'all' ? '' : $dateFilter }}" class="bg-transparent text-xs font-bold text-gray-700 outline-none px-4 py-2 cursor-pointer">
        <button type="submit" class="bg-gray-800 hover:bg-gray-700 text-white px-5 py-2.5 rounded-full text-xs font-bold transition-all">Filter</button>
        @if($dateFilter && $dateFilter !== 'all')
          <a href="{{ route('admin.audit', ['date' => 'all']) }}" class="text-xs font-bold text-gray-400 hover:text-red-500 px-3 transition-colors">Clear</a>
        @endif
      </form>
      <button onclick="downloadPDF()" class="bg-[#FF8FAB] hover:bg-[#FB6F92] text-white px-6 py-3.5 rounded-full font-bold shadow-lg shadow-pink-100 transition-all text-xs flex items-center gap-2 hover:scale-[1.02] transform duration-300">
        <i data-lucide="download" class="w-4.5 h-4.5"></i> Export Report
      </button>
    </div>
  </header>

  <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-10">
      <div class="glass-card p-6 relative overflow-hidden group">
          <div class="absolute -right-4 -top-4 w-20 h-20 bg-pink-50 rounded-full group-hover:bg-pink-100/70 transition-colors"></div>
          <div class="flex items-center gap-3 mb-3 relative z-10">
              <div class="w-10 h-10 rounded-xl bg-pink-50 flex items-center justify-center text-[#FB6F92]">
                  <i data-lucide="database" class="w-5 h-5"></i>
              </div>
              <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Total Actions</span>
          </div>
          <h2 class="text-3xl font-black text-gray-800 relative z-10">{{ $totalLogs }}</h2>
          <p class="text-[10px] text-pink-400 font-bold mt-1.5 uppercase tracking-wide">Recorded events in view</p>
      </div>

      <div class="glass-card p-6 relative overflow-hidden group">
          <div class="absolute -right-4 -top-4 w-20 h-20 bg-indigo-50 rounded-full group-hover:bg-indigo-100/75 transition-colors"></div>
          <div class="flex items-center gap-3 mb-3 relative z-10">
              <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-500">
                  <i data-lucide="shield-check" class="w-5 h-5"></i>
              </div>
              <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Active Actors</span>
          </div>
          <h2 class="text-3xl font-black text-gray-800 relative z-10">{{ $totalActors }}</h2>
          <p class="text-[10px] text-indigo-400 font-bold mt-1.5 uppercase tracking-wide">Staff performing actions</p>
      </div>

      <div class="glass-card p-6 relative overflow-hidden group">
          <div class="absolute -right-4 -top-4 w-20 h-20 bg-rose-50 rounded-full group-hover:bg-rose-100/75 transition-colors"></div>
          <div class="flex items-center gap-3 mb-3 relative z-10">
              <div class="w-10 h-10 rounded-xl bg-rose-50 flex items-center justify-center text-rose-500">
                  <i data-lucide="alert-octagon" class="w-5 h-5"></i>
              </div>
              <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Alert actions</span>
          </div>
          <h2 class="text-3xl font-black text-gray-800 relative z-10">{{ $alertCount }}</h2>
          <p class="text-[10px] text-rose-400 font-bold mt-1.5 uppercase tracking-wide">Deletions & Resets</p>
      </div>

      <div class="glass-card p-6 relative overflow-hidden group">
          <div class="absolute -right-4 -top-4 w-20 h-20 bg-emerald-50 rounded-full group-hover:bg-emerald-100/75 transition-colors"></div>
          <div class="flex items-center gap-3 mb-3 relative z-10">
              <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-500">
                  <i data-lucide="key-round" class="w-5 h-5"></i>
              </div>
              <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">User Logins</span>
          </div>
          <h2 class="text-3xl font-black text-gray-800 relative z-10">{{ $loginCount }}</h2>
          <p class="text-[10px] text-emerald-400 font-bold mt-1.5 uppercase tracking-wide">Successful sessions</p>
      </div>
  </div>

  <div class="glass-card p-6 mb-8 flex flex-col md:flex-row gap-6 justify-between items-center bg-white/60">
      <div class="flex flex-wrap gap-2 items-center justify-start w-full md:w-auto">
          <button data-category="all" class="filter-tab px-5 py-2.5 rounded-full text-xs font-bold transition-all bg-[#FB6F92] text-white shadow-md shadow-pink-100">
              All Events
          </button>
          <button data-category="auth" class="filter-tab px-5 py-2.5 rounded-full text-xs font-bold transition-all bg-white text-gray-600 border border-pink-50 hover:bg-pink-50">
              Authentication
          </button>
          <button data-category="users" class="filter-tab px-5 py-2.5 rounded-full text-xs font-bold transition-all bg-white text-gray-600 border border-pink-50 hover:bg-pink-50">
              User Actions
          </button>
          <button data-category="tasks" class="filter-tab px-5 py-2.5 rounded-full text-xs font-bold transition-all bg-white text-gray-600 border border-pink-50 hover:bg-pink-50">
              Task Operations
          </button>
          <button data-category="skills" class="filter-tab px-5 py-2.5 rounded-full text-xs font-bold transition-all bg-white text-gray-600 border border-pink-50 hover:bg-pink-50">
              Skill Settings
          </button>
      </div>
      
      <div class="relative w-full md:w-72">
          <input type="text" id="logSearch" placeholder="Search actor, action, or details..." class="w-full bg-white border border-pink-100 rounded-full pl-11 pr-5 py-3 text-xs font-bold text-gray-700 outline-none focus:border-[#FB6F92] focus:ring-2 focus:ring-pink-50 transition-all shadow-sm">
          <div class="absolute left-4 top-3.5 text-pink-300">
              <i data-lucide="search" class="w-4 h-4"></i>
          </div>
      </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="glass-card p-8 lg:col-span-1 h-fit flex flex-col bg-white/70">
      <h3 class="font-extrabold text-[#1e293b] text-xl mb-6 flex items-center gap-2">
        <i data-lucide="activity" class="w-5 h-5 text-[#FB6F92]"></i> Live Timeline
      </h3>
      <div class="space-y-6 relative border-l-2 border-pink-100 ml-3 pl-6">
        @if ($recentLogs->isEmpty())
          <p class="text-xs text-gray-400 font-bold">No recent activity detected.</p>
        @else
          @foreach($recentLogs as $log)
            @php 
              $isLoginLogout = in_array($log->action, ['LOGIN', 'LOGOUT']);
              $color = $isLoginLogout ? 'bg-gray-400 ring-gray-100' : 'bg-[#FB6F92] ring-pink-100 shadow-pink-100 shadow-lg';
              $actor = $log->username ?? $log->user_id ?? 'System';
            @endphp
            <div class="relative group">
              <div class="absolute -left-[31px] top-1.5 w-3 h-3 rounded-full {{ $color }} ring-4 transition-all group-hover:scale-125"></div>
              <p class="text-xs font-black text-gray-800 uppercase tracking-wider">{{ $log->action }}</p>
              <p class="text-[10px] text-gray-500 font-bold mt-1 leading-relaxed">{{ $log->details }}</p>
              <div class="flex items-center gap-2 mt-2">
                <span class="text-[9px] bg-pink-50 text-[#FB6F92] px-2 py-0.5 rounded-md font-bold uppercase tracking-wider">{{ $actor }}</span>
                <span class="text-[9px] text-gray-400 font-mono flex items-center gap-1">
                    <i data-lucide="clock" class="w-2.5 h-2.5"></i>
                    {{ date('h:i A', strtotime($log->timestamp)) }}
                </span>
              </div>
            </div>
          @endforeach
        @endif
      </div>
    </div>

    <div id="pdf-content" class="lg:col-span-2 glass-card overflow-hidden">
      <div class="p-8 border-b border-pink-50 flex justify-between items-center bg-white/50">
        <h3 class="font-extrabold text-[#1e293b] text-xl flex items-center gap-2">
          <i data-lucide="list" class="w-5 h-5 text-[#FB6F92]"></i> Master Event Log
          @if($dateFilter && $dateFilter !== 'all')
             <span class="text-[9px] bg-pink-100 text-[#FB6F92] px-3 py-1 rounded-full uppercase ml-2 tracking-widest font-black">{{ date('M d, Y', strtotime($dateFilter)) }}</span>
          @endif
        </h3>
      </div>

      <div class="overflow-x-auto max-h-[600px]">
        <table class="w-full text-left">
          <thead class="sticky top-0 bg-[#FFF9FA] border-b border-pink-50 z-10">
            <tr class="text-[10px] uppercase font-black text-pink-300 tracking-[0.1em]">
              <th class="px-8 py-5">Timestamp</th>
              <th class="px-8 py-5">Actor / System</th>
              <th class="px-8 py-5">Event Action</th>
              <th class="px-8 py-5">Details</th>
            </tr>
          </thead>
          <tbody class="text-sm font-semibold divide-y divide-pink-50 bg-white/50">
            @if ($logs->isEmpty())
              <tr>
                <td colspan="4" class="px-8 py-16 text-center text-gray-400 text-xs font-bold uppercase tracking-widest">No matching logs found</td>
              </tr>
            @else
              @foreach($logs as $log)
              @php 
                $actor = $log->username ?? $log->user_id ?? 'System';
                $role = $log->role ?? 'System';
                $action = $log->action;
                $details = $log->details;
                $timestamp = $log->timestamp;

                $role_badge = 'bg-gray-100 text-gray-600 border border-gray-200';
                if ($role === 'Admin') $role_badge = 'bg-pink-50 text-[#FB6F92] border border-pink-100';
                elseif ($role === 'Manager') $role_badge = 'bg-indigo-50 text-indigo-600 border border-indigo-100';
                elseif ($role === 'Employee') $role_badge = 'bg-emerald-50 text-emerald-600 border border-emerald-100';

                $action_badge = 'bg-gray-50 text-gray-600 border border-gray-200';
                $action_icon = 'activity';
                
                $act_upper = strtoupper($action);
                if (str_contains($act_upper, 'DELETE')) {
                    $action_badge = 'bg-rose-50 text-rose-600 border border-rose-100';
                    $action_icon = 'trash-2';
                } elseif (str_contains($act_upper, 'CREATE')) {
                    $action_badge = 'bg-emerald-50 text-emerald-600 border border-emerald-100';
                    $action_icon = 'plus-circle';
                } elseif (str_contains($act_upper, 'LOGIN')) {
                    $action_badge = 'bg-violet-50 text-violet-600 border-violet-100';
                    $action_icon = 'key-round';
                } elseif (str_contains($act_upper, 'LOGOUT')) {
                    $action_badge = 'bg-slate-100 text-slate-600 border-slate-200';
                    $action_icon = 'log-out';
                } elseif ($act_upper === 'REGISTER') {
                    $action_badge = 'bg-indigo-50 text-indigo-600 border-indigo-100';
                    $action_icon = 'user-plus';
                } elseif (str_contains($act_upper, 'TASK')) {
                    $action_badge = 'bg-pink-50 text-[#FB6F92] border border-pink-100';
                    $action_icon = 'briefcase';
                } elseif (str_contains($act_upper, 'SKILL')) {
                    $action_badge = 'bg-sky-50 text-sky-600 border-sky-100';
                    $action_icon = 'cpu';
                } elseif (str_contains($act_upper, 'PASSWORD') || str_contains($act_upper, 'RESET')) {
                    $action_badge = 'bg-amber-50 text-amber-600 border-amber-100';
                    $action_icon = 'lock';
                }
              @endphp
              <tr class="log-row border-b border-pink-50/50" data-action="{{ $action }}" data-actor="{{ $actor }}" data-details="{{ $details }}">
                <td class="px-8 py-5 text-xs font-bold text-gray-400 font-mono">
                  {{ date('M d, Y', strtotime($timestamp)) }}<br>
                  <span class="text-[10px] text-gray-400 font-normal">{{ date('h:i:s A', strtotime($timestamp)) }}</span>
                </td>
                <td class="px-8 py-5">
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-pink-50 text-[#FB6F92] flex items-center justify-center font-black text-xs">
                      {{ strtoupper(substr($actor, 0, 2)) }}
                    </div>
                    <div>
                      <p class="font-extrabold text-gray-800 text-sm">{{ $actor }}</p>
                      <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider {{ $role_badge }}">{{ $role }}</span>
                    </div>
                  </div>
                </td>
                <td class="px-8 py-5">
                  <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[9px] font-black border uppercase tracking-wider {{ $action_badge }} shadow-sm">
                    <i data-lucide="{{ $action_icon }}" class="w-3.5 h-3.5"></i>
                    {{ $action }}
                  </span>
                </td>
                <td class="px-8 py-5">
                  <p class="text-xs text-gray-600 font-bold leading-relaxed">{{ $details }}</p>
                </td>
              </tr>
              @endforeach
            @endif
          </tbody>
        </table>
      </div>

      <div class="p-6 bg-white border-t border-pink-50">
        {{ $logs->links() }}
      </div>
    </div>
  </div>
@endsection

@section('scripts')
<script>
  const logSearch = document.getElementById('logSearch');
  const filterTabs = document.querySelectorAll('.filter-tab');
  const tableRows = document.querySelectorAll('.log-row');

  function filterLogs() {
      const searchValue = logSearch.value.toLowerCase().trim();
      let activeCategory = 'all';
      filterTabs.forEach(tab => {
          if (tab.classList.contains('bg-[#FB6F92]')) {
              activeCategory = tab.dataset.category;
          }
      });

      tableRows.forEach(row => {
          const action = (row.dataset.action || '').toUpperCase();
          const actor = (row.dataset.actor || '').toLowerCase();
          const details = (row.dataset.details || '').toLowerCase();
          const textContent = `${action} ${actor} ${details}`;

          let categoryMatch = false;
          if (activeCategory === 'all') {
              categoryMatch = true;
          } else if (activeCategory === 'auth') {
              categoryMatch = ['LOGIN', 'LOGOUT', 'REGISTER'].includes(action);
          } else if (activeCategory === 'users') {
              categoryMatch = ['CREATE_USER', 'DELETE_USER', 'UPDATE_USER_PROFILE', 'RESET_PASSWORD', 'SUSPEND_USER', 'ACTIVATE_USER'].includes(action);
          } else if (activeCategory === 'tasks') {
              categoryMatch = ['CREATE_TASK', 'ASSIGN_TASK', 'SUBMIT_TASK', 'VERIFY_TASK'].includes(action);
          } else if (activeCategory === 'skills') {
              categoryMatch = ['UPDATE_SKILL', 'DELETE_SKILL'].includes(action);
          }

          const searchMatch = textContent.includes(searchValue);

          if (categoryMatch && searchMatch) {
              row.style.display = '';
          } else {
              row.style.display = 'none';
          }
      });
  }

  logSearch.addEventListener('input', filterLogs);
  filterTabs.forEach(tab => {
      tab.addEventListener('click', () => {
          filterTabs.forEach(t => {
              t.classList.remove('bg-[#FB6F92]', 'text-white', 'shadow-md', 'shadow-pink-100');
              t.classList.add('bg-white', 'text-gray-600', 'border-pink-50', 'hover:bg-pink-50');
          });
          tab.classList.add('bg-[#FB6F92]', 'text-white', 'shadow-md', 'shadow-pink-100');
          tab.classList.remove('bg-white', 'text-gray-600', 'border-pink-50', 'hover:bg-pink-50');
          filterLogs();
      });
  });

  function downloadPDF() {
    const selectedDate = document.querySelector('input[name="date"]').value;
    const formattedDate = selectedDate ? selectedDate.replace(/-/g, '') : new Date().toISOString().slice(0,10).replace(/-/g, '');
    const filename = `log_${formattedDate}.pdf`;

    const printArea = document.createElement('div');
    printArea.style.padding = '35px';
    printArea.style.backgroundColor = '#ffffff';
    printArea.style.color = '#333333';
    printArea.style.fontSize = '10px';

    printArea.innerHTML = `
      <div style="border-bottom: 2px solid #1e293b; padding-bottom: 15px; margin-bottom: 25px; font-family: 'Stack Sans Headline', sans-serif;">
        <h1 style="font-size: 20px; font-weight: 800; text-transform: uppercase; color: #1e293b; margin: 0;">OptiTask Audit Trail Report</h1>
        <p style="font-size: 10px; color: #64748b; margin: 4px 0 0 0;">Generated on: ${new Date().toLocaleString()} | Filtered Date: ${selectedDate || 'All Records'}</p>
      </div>
    `;

    let tableHTML = `
      <table style="width: 100%; border-collapse: collapse; text-align: left; font-family: 'Stack Sans Headline', sans-serif;">
        <thead>
          <tr style="border-bottom: 2px solid #cbd5e1; background-color: #f8fafc; font-weight: bold; color: #475569;">
            <th style="padding: 10px 8px; border: 1px solid #e2e8f0; font-size: 9px; text-transform: uppercase;">Timestamp</th>
            <th style="padding: 10px 8px; border: 1px solid #e2e8f0; font-size: 9px; text-transform: uppercase;">Actor (Role)</th>
            <th style="padding: 10px 8px; border: 1px solid #e2e8f0; font-size: 9px; text-transform: uppercase;">Action</th>
            <th style="padding: 10px 8px; border: 1px solid #e2e8f0; font-size: 9px; text-transform: uppercase;">Event Details</th>
          </tr>
        </thead>
        <tbody>
    `;

    const rows = document.querySelectorAll('.log-row');
    let visibleRowCount = 0;

    rows.forEach(row => {
      if (row.style.display !== 'none') {
        visibleRowCount++;
        const timestamp = row.cells[0].innerText.replace(/\n/g, ' ');
        const actor = row.cells[1].querySelector('p').innerText;
        const role = row.cells[1].querySelector('span').innerText;
        const action = row.cells[2].innerText.trim();
        const details = row.cells[3].innerText;

        tableHTML += `
          <tr style="border-bottom: 1px solid #e2e8f0; color: #334155;">
            <td style="padding: 8px; border: 1px solid #e2e8f0; font-family: monospace; font-size: 9px;">${timestamp}</td>
            <td style="padding: 8px; border: 1px solid #e2e8f0;">
              <strong>${actor}</strong><br>
              <span style="font-size: 8px; color: #64748b;">${role}</span>
            </td>
            <td style="padding: 8px; border: 1px solid #e2e8f0; font-weight: bold; font-size: 9px;">${action}</td>
            <td style="padding: 8px; border: 1px solid #e2e8f0; line-height: 1.4;">${details}</td>
          </tr>
        `;
      }
    });

    if (visibleRowCount === 0) {
      tableHTML += `
        <tr>
          <td colspan="4" style="padding: 30px; text-align: center; color: #94a3b8; font-style: italic;">No matching logs found.</td>
        </tr>
      `;
    }

    tableHTML += `
        </tbody>
      </table>
    `;

    printArea.innerHTML += tableHTML;

    printArea.innerHTML += `
      <div style="margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 10px; text-align: center; font-size: 8px; color: #94a3b8; font-family: 'Stack Sans Headline', sans-serif;">
        OptiTask System Audit Log Archive | Portrait Report
      </div>
    `;

    const opt = {
      margin:       0.4,
      filename:     filename,
      image:        { type: 'jpeg', quality: 0.98 },
      html2canvas:  { scale: 2 },
      jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
    };

    html2pdf().set(opt).from(printArea).save();
  }
</script>
@endsection
