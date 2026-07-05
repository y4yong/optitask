@extends('layouts.dashboard')

@section('title', 'OptiTask | Performance Insights')

@section('styles')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
@endsection

@section('content')
    <div id="pdf-content">
        <header class="flex justify-between items-center mb-12">
            <div>
                <h1 class="text-4xl font-extrabold text-[#1e293b] tracking-tight uppercase">Performance Report</h1>
                <p class="text-pink-400 mt-1 font-bold">Overview of your task completion and productivity.</p>
            </div>
            
            <div class="flex gap-4">
                <button onclick="downloadPDF()" class="bg-white border-2 border-pink-50 hover:border-pink-200 text-[#1e293b] px-6 py-3 rounded-2xl font-bold shadow-xl shadow-pink-100/30 transition-all text-xs uppercase tracking-widest flex items-center gap-2">
                    <i data-lucide="download" class="w-4 h-4 text-[#FB6F92]"></i> Export PDF
                </button>
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- KPI 1 -->
            <div class="glass-card p-8 bg-white">
                <p class="text-[10px] font-black text-pink-300 uppercase tracking-widest">Completion Rate</p>
                <div class="flex items-end gap-3 mt-3">
                    <h2 class="text-5xl font-black text-[#FB6F92]">{{ number_format($performance, 1) }}%</h2>
                </div>
                <p class="text-xs font-bold text-gray-400 mt-3">Verified / Total Tasks</p>
                <div class="mt-6 h-3 bg-pink-50 rounded-full overflow-hidden">
                    <div class="h-full pink-gradient" style="width: {{ $performance }}%"></div>
                </div>
            </div>

            <!-- KPI 2 -->
            <div class="glass-card p-8 bg-white">
                <p class="text-[10px] font-black text-pink-300 uppercase tracking-widest">Tasks Completed</p>
                <div class="flex items-end gap-3 mt-3">
                    <h2 class="text-5xl font-black text-[#1e293b]">{{ $completedTasks }} <span class="text-2xl text-gray-300">/ {{ $totalTasks }}</span></h2>
                </div>
                <p class="text-xs font-bold text-gray-400 mt-3">Total resolved assignments.</p>
                <div class="mt-6 flex gap-2">
                    <!-- Mini visual placeholder bars -->
                    @for($i=0; $i<7; $i++)
                        <div class="h-8 flex-1 rounded-lg bg-pink-{{ mt_rand(50, 100) }}"></div>
                    @endfor
                </div>
            </div>

            <!-- KPI 3 -->
            <div class="pink-gradient rounded-[2.5rem] shadow-xl shadow-pink-100/50 p-8 text-white relative overflow-hidden">
                <i data-lucide="award" class="absolute -right-4 -bottom-4 w-32 h-32 opacity-10 rotate-12"></i>
                <p class="text-[10px] font-black uppercase tracking-[0.2em] opacity-80">Productivity Badge</p>
                <div class="flex items-center justify-between mt-4">
                    <h2 class="text-5xl font-black italic tracking-tight font-outfit">
                        {{ $performance >= 80 ? 'A+' : ($performance >= 60 ? 'B' : 'C') }}
                    </h2>
                    <i data-lucide="shield-check" class="w-12 h-12 opacity-50"></i>
                </div>
                <p class="text-xs font-bold mt-4 leading-relaxed">
                    {{ $performance >= 80 ? 'You are performing exceptionally well. Keep it up!' : 'Focus on active and upcoming tasks to improve your velocity score.' }}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mt-8">
            <!-- Breakdown table -->
            <div class="lg:col-span-2 glass-card overflow-hidden bg-white">
                <div class="p-8 border-b border-pink-50 bg-white/40 flex justify-between items-center">
                    <h3 class="font-extrabold text-[#1e293b] text-xl tracking-tight italic flex items-center gap-3">
                        <span class="w-2 h-6 pink-gradient rounded-full"></span>
                        Task Breakdown
                    </h3>
                    <span class="text-[10px] font-black text-pink-300 uppercase tracking-widest">{{ count($tasksArray) }} Tasks</span>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm font-semibold">
                        <thead>
                            <tr class="text-[11px] uppercase font-black text-pink-300 tracking-[0.1em] border-b border-pink-50">
                                <th class="px-8 py-5">Task Details</th>
                                <th class="px-8 py-5">Due Date</th>
                                <th class="px-8 py-5">Priority</th>
                                <th class="px-8 py-5 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-pink-50 text-gray-700">
                            @if (count($tasksArray) > 0)
                                @foreach($tasksArray as $task)
                                <tr class="hover:bg-[#FFF9FA] transition-all">
                                    <td class="px-8 py-6">
                                        <p class="text-sm font-extrabold text-[#1e293b]">{{ $task['task_title'] }}</p>
                                        <p class="text-[10px] text-gray-400 font-bold font-mono mt-1">{{ $task['task_id'] }}</p>
                                    </td>
                                    <td class="px-8 py-6">
                                        <div class="flex items-center gap-2 text-xs font-bold text-gray-500">
                                            <i data-lucide="calendar" class="w-3.5 h-3.5 text-[#FB6F92]"></i>
                                            {{ date('M d, Y', strtotime($task['due_date'])) }}
                                        </div>
                                    </td>
                                    <td class="px-8 py-6">
                                        @php
                                            $pcolor = 'bg-green-50 text-green-500';
                                            if ($task['priority'] === 'Medium') $pcolor = 'bg-yellow-50 text-yellow-600';
                                            if ($task['priority'] === 'High') $pcolor = 'bg-red-50 text-red-500';
                                        @endphp
                                        <span class="inline-block px-2 py-0.5 rounded-md text-[9px] font-black uppercase {{ $pcolor }}">
                                            {{ $task['priority'] }}
                                        </span>
                                    </td>
                                    <td class="px-8 py-6 text-right">
                                        @php 
                                        $status_color = 'bg-gray-100 text-gray-600';
                                        if ($task['task_status'] === 'Verified' || $task['task_status'] === 'Done' || $task['task_status'] === 'Review') $status_color = 'bg-pink-100 text-[#FB6F92]';
                                        if ($task['task_status'] === 'In Progress') $status_color = 'bg-blue-50 text-blue-500';
                                        @endphp
                                        <span class="px-3 py-1 rounded-full text-[10px] font-black {{ $status_color }} border-none shadow-sm">
                                            {{ $task['task_status'] }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="4" class="px-8 py-12 text-center text-sm font-bold text-gray-400">
                                        No tasks assigned yet.
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- AI Career Coach & Insights Section -->
            <div class="glass-card p-8 h-fit flex flex-col bg-white">
                <div class="flex justify-between items-center mb-6">
                    <h4 class="font-extrabold text-[#1e293b] flex items-center gap-3">
                        <i data-lucide="sparkles" class="w-5 h-5 text-[#FB6F92] animate-pulse"></i> 
                        AI Career Coach
                    </h4>
                    <span class="text-[9px] font-bold px-2.5 py-1 rounded-full bg-pink-100 text-[#FB6F92] uppercase font-mono tracking-wider shadow-sm">
                        {{ $insights['source'] }}
                    </span>
                </div>

                <!-- Tab Headers -->
                <div class="flex gap-2 p-1.5 bg-pink-50/50 rounded-2xl mb-6">
                    <button id="tab-inapp-btn" class="flex-1 py-2 text-xs font-black rounded-xl transition-all pink-gradient text-white shadow-sm">
                        Coaching
                    </button>
                    <button id="tab-external-btn" class="flex-1 py-2 text-xs font-black text-gray-500 rounded-xl hover:text-pink-500 hover:bg-white/50 transition-all">
                        AI Prompters
                    </button>
                </div>

                <!-- TAB 1: In-App Coaching Insights -->
                <div id="tab-inapp-content" class="space-y-6">
                    <!-- Predictive metrics -->
                    <div class="p-5 bg-white border border-pink-100 rounded-2xl shadow-sm hover:shadow-md transition-all">
                        <div class="flex items-center gap-2 mb-2">
                            <i data-lucide="trending-up" class="w-4 h-4 text-[#FB6F92]"></i>
                            <p class="text-[11px] font-black text-pink-400 uppercase tracking-widest">Velocity Prediction</p>
                        </div>
                        <p class="text-xs font-bold text-gray-600 leading-relaxed">
                            {{ $insights['completion_rate_prediction'] }}
                        </p>
                    </div>

                    <!-- Bottlenecks warning -->
                    <div class="p-5 bg-[#FFF9FA] border border-pink-100/50 rounded-2xl border-l-4 border-pink-400">
                        <div class="flex items-center gap-2 mb-2">
                            <i data-lucide="alert-triangle" class="w-4 h-4 text-pink-400"></i>
                            <p class="text-[11px] font-black text-pink-500 uppercase tracking-widest">Bottleneck Warning</p>
                        </div>
                        <p class="text-xs font-bold text-gray-600 leading-relaxed">
                            {{ $insights['bottleneck_warning'] }}
                        </p>
                    </div>

                    <!-- Actionable tips list -->
                    <div class="space-y-3">
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1 flex items-center gap-1.5">
                            <i data-lucide="lightbulb" class="w-3.5 h-3.5 text-yellow-500"></i> Actionable Coaching Tips
                        </p>
                        @foreach ($insights['actionable_tips'] as $tip)
                            <div class="flex items-start gap-2.5 p-3.5 bg-white border border-pink-55 rounded-xl hover:border-pink-200 transition-all">
                                <span class="w-1.5 h-1.5 rounded-full pink-gradient mt-1.5 shrink-0"></span>
                                <p class="text-xs font-bold text-gray-500 leading-relaxed">{{ $tip }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- TAB 2: Interactive AI Assistant -->
                <div id="tab-external-content" class="hidden space-y-6">
                    <!-- Velocity Audit Prompt -->
                    <div class="p-5 bg-white border border-pink-100 rounded-2xl shadow-sm hover:border-pink-200 transition-all">
                        <h5 class="text-xs font-extrabold text-[#1e293b] mb-1">AI Velocity Audit</h5>
                        <p class="text-[11px] font-bold text-gray-400 leading-relaxed mb-4">
                            Let the AI coach review your active tasks, identify blind spots, and draft a high-ROI hourly performance schedule.
                        </p>
                        <button id="run-audit-btn" class="w-full py-2.5 text-xs font-black text-white pink-gradient rounded-xl shadow-md shadow-pink-100/40 hover:shadow-lg hover:shadow-pink-200/50 hover:translate-y-[-1px] transition-all flex items-center justify-center gap-2">
                            <i data-lucide="sparkles" class="w-4 h-4"></i> Run Velocity Audit
                        </button>
                        <!-- Audit Result -->
                        <div id="audit-result-box" class="hidden mt-4 p-4 bg-[#FFF9FA] border border-pink-100 rounded-xl max-h-72 overflow-y-auto text-xs font-semibold text-gray-600 leading-relaxed transition-all">
                        </div>
                    </div>

                    <!-- Task Deconstructor Prompt -->
                    <div class="p-5 bg-white border border-pink-100 rounded-2xl shadow-sm hover:border-pink-200 transition-all">
                        <h5 class="text-xs font-extrabold text-[#1e293b] mb-1">AI Task Deconstructor</h5>
                        <p class="text-[11px] font-bold text-gray-400 leading-relaxed mb-3">
                            Break down complex assignments into simple 30-minute milestones with a clear Definition of Done.
                        </p>
                        <div class="space-y-3">
                            <select id="deconstruct-task-select" class="w-full text-xs font-bold bg-[#FFF9FA] border border-pink-100 text-gray-700 px-3 py-2.5 rounded-xl focus:outline-none focus:border-pink-300 cursor-pointer">
                                <option value="">-- Select Active Task --</option>
                                @foreach ($tasksArray as $t)
                                    @if ($t['task_status'] !== 'Verified' && $t['task_status'] !== 'Done')
                                        <option value="{{ $t['task_title'] }}">{{ $t['task_title'] }} ({{ $t['priority'] }})</option>
                                    @endif
                                @endforeach
                            </select>
                            <button id="run-deconstruct-btn" class="w-full py-2.5 text-xs font-black text-[#FB6F92] bg-pink-50 hover:bg-pink-100 border border-pink-200/50 rounded-xl transition-all flex items-center justify-center gap-2">
                                <i data-lucide="split" class="w-4 h-4"></i> Deconstruct Task
                            </button>
                            <!-- Deconstruct Result -->
                            <div id="deconstruct-result-box" class="hidden mt-4 p-4 bg-[#FFF9FA] border border-pink-100 rounded-xl max-h-72 overflow-y-auto text-xs font-semibold text-gray-600 leading-relaxed transition-all">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    // Tab Switching Logic
    const tabInAppBtn = document.getElementById('tab-inapp-btn');
    const tabExternalBtn = document.getElementById('tab-external-btn');
    const tabInAppContent = document.getElementById('tab-inapp-content');
    const tabExternalContent = document.getElementById('tab-external-content');

    tabInAppBtn.addEventListener('click', () => {
        tabInAppBtn.className = "flex-1 py-2 text-xs font-black rounded-xl transition-all pink-gradient text-white shadow-sm";
        tabExternalBtn.className = "flex-1 py-2 text-xs font-black text-gray-500 rounded-xl hover:text-pink-500 hover:bg-white/50 transition-all";
        tabInAppContent.classList.remove('hidden');
        tabExternalContent.classList.add('hidden');
    });

    tabExternalBtn.addEventListener('click', () => {
        tabExternalBtn.className = "flex-1 py-2 text-xs font-black rounded-xl transition-all pink-gradient text-white shadow-sm";
        tabInAppBtn.className = "flex-1 py-2 text-xs font-black text-gray-500 rounded-xl hover:text-pink-500 hover:bg-white/50 transition-all";
        tabExternalContent.classList.remove('hidden');
        tabInAppContent.classList.add('hidden');
    });

    // Run Velocity Audit Logic
    document.getElementById('run-audit-btn').addEventListener('click', () => {
        const resultBox = document.getElementById('audit-result-box');
        resultBox.classList.remove('hidden');
        resultBox.innerHTML = `
            <div class="flex items-center justify-center py-6 gap-2 text-[#FB6F92]">
                <div class="w-4 h-4 border-2 border-t-transparent border-[#FB6F92] rounded-full animate-spin"></div>
                <span class="font-bold text-[11px] uppercase tracking-wider">Analyzing velocity...</span>
            </div>
        `;

        fetch('{{ route("employee.ai_coach") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: new URLSearchParams({ action: 'audit' })
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                resultBox.innerHTML = data.html;
            } else {
                resultBox.innerHTML = `<p class="text-red-500 text-xs font-bold">${data.message}</p>`;
            }
        })
        .catch(err => {
            console.error(err);
            resultBox.innerHTML = `<p class="text-red-500 text-xs font-bold">Failed to load performance audit. Please try again.</p>`;
        });
    });

    // Run Task Deconstructor Logic
    document.getElementById('run-deconstruct-btn').addEventListener('click', () => {
        const selectedTask = document.getElementById('deconstruct-task-select').value;
        const resultBox = document.getElementById('deconstruct-result-box');

        if (!selectedTask) {
            Swal.fire({
                icon: 'warning',
                title: 'Select a Task',
                text: 'Please select an active task from the list first!',
                confirmButtonColor: '#FB6F92'
            });
            return;
        }

        resultBox.classList.remove('hidden');
        resultBox.innerHTML = `
            <div class="flex items-center justify-center py-6 gap-2 text-[#FB6F92]">
                <div class="w-4 h-4 border-2 border-t-transparent border-[#FB6F92] rounded-full animate-spin"></div>
                <span class="font-bold text-[11px] uppercase tracking-wider">Deconstructing task...</span>
            </div>
        `;

        fetch('{{ route("employee.ai_coach") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: new URLSearchParams({
                action: 'deconstruct',
                task_title: selectedTask
            })
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                resultBox.innerHTML = data.html;
            } else {
                resultBox.innerHTML = `<p class="text-red-500 text-xs font-bold">${data.message}</p>`;
            }
        })
        .catch(err => {
            console.error(err);
            resultBox.innerHTML = `<p class="text-red-500 text-xs font-bold">Failed to deconstruct task. Please try again.</p>`;
        });
    });

    function downloadPDF() {
        const username = "{{ Auth::user()->username }}";
        const workerId = "{{ Auth::user()->user_id }}";
        const department = "{{ Auth::user()->department->dept_name ?? 'N/A' }}";
        const completionRate = "{{ number_format($performance, 1) }}%";
        const totalTasks = "{{ $totalTasks }}";
        const completedTasks = "{{ $completedTasks }}";
        const performanceBadge = "{{ $performance >= 80 ? 'A+' : ($performance >= 60 ? 'B' : 'C') }}";
        
        const velocityPrediction = {!! json_encode($insights['completion_rate_prediction']) !!};
        const bottleneckWarning = {!! json_encode($insights['bottleneck_warning']) !!};
        const actionableTips = {!! json_encode($insights['actionable_tips']) !!};

        const today = new Date();
        const formattedDate = today.toISOString().slice(0,10).replace(/-/g, '');
        const filename = `performance_${workerId}_${formattedDate}.pdf`;

        const printArea = document.createElement('div');
        printArea.style.padding = '40px';
        printArea.style.backgroundColor = '#ffffff';
        printArea.style.color = '#1e293b';
        printArea.style.fontFamily = "'Stack Sans Headline', 'Helvetica Neue', Arial, sans-serif";

        printArea.innerHTML = `
            <div style="border-bottom: 2px solid #FB6F92; padding-bottom: 20px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-end;">
                <div>
                    <h1 style="font-size: 24px; font-weight: 800; text-transform: uppercase; color: #1e293b; margin: 0; tracking-tight: -0.025em;">OptiTask Performance Report</h1>
                    <p style="font-size: 10px; color: #FB6F92; margin: 5px 0 0 0; font-weight: bold; text-transform: uppercase; tracking-wider: 0.05em;">Employee Analytics Portfolio</p>
                </div>
                <div style="text-align: right;">
                    <p style="font-size: 9px; color: #64748b; margin: 0;">Date Generated: ${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                    <p style="font-size: 9px; color: #64748b; margin: 2px 0 0 0;">Report Format: Portrait Archive</p>
                </div>
            </div>

            <div style="background-color: #FFF9FA; border: 1px solid #FFE5EC; padding: 20px; margin-bottom: 30px; border-radius: 16px;">
                <h3 style="font-size: 12px; font-weight: 800; color: #1e293b; margin: 0 0 12px 0; text-transform: uppercase; tracking-wider: 0.05em;">Employee Identification</h3>
                <table style="width: 100%; font-size: 11px; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 4px 0; color: #64748b; width: 120px;">Employee Name:</td>
                        <td style="padding: 4px 0; font-weight: bold; color: #1e293b;">${username}</td>
                        <td style="padding: 4px 0; color: #64748b; width: 120px;">Completion Rate:</td>
                        <td style="padding: 4px 0; font-weight: bold; color: #FB6F92;">${completionRate}</td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 0; color: #64748b;">Worker ID:</td>
                        <td style="padding: 4px 0; font-weight: bold; color: #1e293b; font-family: monospace;">${workerId}</td>
                        <td style="padding: 4px 0; color: #64748b;">Tasks Completed:</td>
                        <td style="padding: 4px 0; font-weight: bold; color: #1e293b;">${completedTasks} / ${totalTasks}</td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 0; color: #64748b;">Department:</td>
                        <td style="padding: 4px 0; font-weight: bold; color: #1e293b;">${department}</td>
                        <td style="padding: 4px 0; color: #64748b;">Productivity Grade:</td>
                        <td style="padding: 4px 0; font-weight: bold; color: #10B981;">${performanceBadge}</td>
                    </tr>
                </table>
            </div>

            <div style="margin-bottom: 35px;">
                <h3 style="font-size: 12px; font-weight: 800; color: #1e293b; border-left: 3px solid #FB6F92; padding-left: 8px; margin: 0 0 15px 0; text-transform: uppercase; tracking-wider: 0.05em;">AI Performance Evaluation</h3>
                <div style="margin-bottom: 15px; padding: 15px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;">
                    <strong style="font-size: 10px; text-transform: uppercase; color: #64748b; display: block; margin-bottom: 5px;">Velocity Prediction</strong>
                    <p style="font-size: 11px; margin: 0; color: #334155; line-height: 1.5;">${velocityPrediction}</p>
                </div>
                <div style="margin-bottom: 15px; padding: 15px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;">
                    <strong style="font-size: 10px; text-transform: uppercase; color: #64748b; display: block; margin-bottom: 5px;">Bottleneck Warning</strong>
                    <p style="font-size: 11px; margin: 0; color: #334155; line-height: 1.5;">${bottleneckWarning}</p>
                </div>
                <div style="padding: 15px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;">
                    <strong style="font-size: 10px; text-transform: uppercase; color: #64748b; display: block; margin-bottom: 8px;">Actionable Recommendations</strong>
                    <ul style="margin: 0; padding-left: 15px; font-size: 11px; color: #334155; line-height: 1.6;">
                        ${actionableTips.map(tip => `<li style="margin-bottom: 6px;">${tip}</li>`).join('')}
                    </ul>
                </div>
            </div>

            <div style="page-break-before: always; padding-top: 20px;">
                <h3 style="font-size: 12px; font-weight: 800; color: #1e293b; border-left: 3px solid #FB6F92; padding-left: 8px; margin: 0 0 15px 0; text-transform: uppercase; tracking-wider: 0.05em;">Assigned Tasks Inventory</h3>
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 10px;">
                    <thead>
                        <tr style="border-bottom: 2px solid #cbd5e1; background-color: #f8fafc; color: #475569; font-weight: bold;">
                            <th style="padding: 8px; border: 1px solid #e2e8f0;">Task ID</th>
                            <th style="padding: 8px; border: 1px solid #e2e8f0;">Task Title</th>
                            <th style="padding: 8px; border: 1px solid #e2e8f0;">Due Date</th>
                            <th style="padding: 8px; border: 1px solid #e2e8f0;">Priority</th>
                            <th style="padding: 8px; border: 1px solid #e2e8f0; text-align: right;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${Array.from(document.querySelectorAll('tbody tr')).map(row => {
                            if (!row.cells || row.cells.length < 4) return '';
                            const title = row.cells[0].querySelector('p').innerText;
                            const id = row.cells[0].querySelector('p:last-child').innerText;
                            const dueDate = row.cells[1].innerText.trim();
                            const priority = row.cells[2].innerText.trim();
                            const status = row.cells[3].innerText.trim();
                            return `
                                <tr style="border-bottom: 1px solid #e2e8f0; color: #334155;">
                                    <td style="padding: 8px; border: 1px solid #e2e8f0; font-family: monospace;">${id}</td>
                                    <td style="padding: 8px; border: 1px solid #e2e8f0; font-weight: bold;">${title}</td>
                                    <td style="padding: 8px; border: 1px solid #e2e8f0;">${dueDate}</td>
                                    <td style="padding: 8px; border: 1px solid #e2e8f0;">${priority}</td>
                                    <td style="padding: 8px; border: 1px solid #e2e8f0; text-align: right; font-weight: bold;">${status}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 40px; border-top: 1px solid #e2e8f0; padding-top: 15px; text-align: center; font-size: 8px; color: #94a3b8; text-transform: uppercase; tracking-wider: 0.05em;">
                OptiTask Performance Ledger • Confidential Audit Log Document
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
