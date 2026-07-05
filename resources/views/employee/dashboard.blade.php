@extends('layouts.dashboard')

@section('title', 'OptiTask | Employee Dashboard')

@section('content')
    <header class="flex justify-between items-end mb-12">
        <div>
            <h1 class="text-4xl font-extrabold text-[#1e293b] tracking-tight uppercase">Dashboard</h1>
            <p class="text-pink-400 mt-1 font-bold">Welcome back, {{ explode(' ', trim(Auth::user()->username))[0] }}.</p>
        </div>
    </header>

    @if(session('success'))
        <div class="mb-6 p-4 rounded-2xl text-center text-sm font-semibold bg-green-50 text-green-500">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-8">
            
            <div class="glass-card overflow-hidden bg-white">
                <div class="p-8 border-b border-pink-50 bg-white/55 flex justify-between items-center">
                    <h3 class="font-extrabold text-[#1e293b] text-xl tracking-tight flex items-center gap-3">
                        <span class="w-2 h-6 bg-[#FB6F92] rounded-full"></span>
                        Active Assignments
                    </h3>
                    <span class="text-[10px] font-black text-pink-400 uppercase tracking-widest bg-pink-50 px-3 py-1 rounded-full border border-pink-100">{{ $activeTasks->count() }} Tasks</span>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-[11px] uppercase font-black text-pink-300 tracking-[0.1em] border-b border-pink-50">
                                <th class="px-8 py-5">Task ID</th>
                                <th class="px-8 py-5">Project Details</th>
                                <th class="px-8 py-5">Deadline</th>
                                <th class="px-8 py-5 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-pink-50">
                            @if ($activeTasks->count() > 0)
                                @foreach($activeTasks as $row)
                                <tr class="hover:bg-[#FFF9FA] transition-all">
                                    <td class="px-8 py-6 font-mono text-[11px] text-slate-700 font-bold">{{ $row->task_id }}</td>
                                    <td class="px-8 py-6">
                                        <p class="text-sm font-extrabold text-[#1e293b]">{{ $row->task_title }}</p>
                                        <span class="inline-block mt-1 px-2.5 py-0.5 rounded text-[8px] font-black uppercase {{ $row->task_status === 'In Progress' ? 'bg-amber-50 text-amber-600 border border-amber-100' : ($row->task_status === 'Done' ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' : 'bg-pink-50 text-[#FB6F92] border border-pink-100') }}">
                                            {{ $row->task_status === 'Done' ? 'In Review' : $row->task_status }}
                                        </span>
                                        @if(in_array($row->task_status, ['To-Do', 'In Progress']) && strtotime($row->due_date) < strtotime(date('Y-m-d')))
                                            <span class="inline-block mt-1 ml-1 px-2.5 py-0.5 rounded text-[8px] font-black uppercase bg-red-50 text-red-600 border border-red-100 animate-pulse">
                                                Overdue
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-8 py-6">
                                        <div class="flex items-center gap-2 text-xs font-bold text-gray-500">
                                            <i data-lucide="calendar" class="w-3.5 h-3.5 text-[#FB6F92]"></i>
                                            {{ date('M d, Y', strtotime($row->due_date)) }}
                                        </div>
                                    </td>
                                    <td class="px-8 py-6 text-right">
                                        <a href="{{ route('employee.tasks') }}" class="inline-flex items-center gap-1.5 bg-pink-50 hover:bg-[#FB6F92] text-[#FB6F92] hover:text-white px-4 py-2.5 rounded-xl text-xs font-black transition-all shadow-sm">
                                            <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                                            Task Details
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="4" class="px-8 py-16 text-center">
                                        <div class="w-12 h-12 bg-pink-50 rounded-2xl flex items-center justify-center mx-auto mb-3">
                                            <i data-lucide="inbox" class="w-6 h-6 text-pink-200"></i>
                                        </div>
                                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">No active tasks assigned.</p>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="glass-card overflow-hidden bg-white">
                <div class="p-8 border-b border-pink-50 bg-white/55 flex justify-between items-center">
                    <h3 class="font-extrabold text-[#1e293b] text-xl tracking-tight flex items-center gap-3">
                        <span class="w-2 h-6 bg-blue-500 rounded-full"></span>
                        Verified Assignments
                    </h3>
                    <span class="text-[10px] font-black text-blue-500 uppercase tracking-widest bg-blue-50 px-3 py-1 rounded-full border border-blue-100">{{ $verifiedTasks->count() }} Tasks</span>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-[11px] uppercase font-black text-pink-300 tracking-[0.1em] border-b border-pink-50">
                                <th class="px-8 py-5">Task ID</th>
                                <th class="px-8 py-5">Project Details</th>
                                <th class="px-8 py-5">Completed Date</th>
                                <th class="px-8 py-5 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-pink-50">
                            @if ($verifiedTasks->count() > 0)
                                @foreach($verifiedTasks as $row)
                                <tr class="hover:bg-[#FFF9FA] transition-all">
                                    <td class="px-8 py-6 font-mono text-[11px] text-slate-700 font-bold">{{ $row->task_id }}</td>
                                    <td class="px-8 py-6">
                                        <p class="text-sm font-extrabold text-[#1e293b]">{{ $row->task_title }}</p>
                                    </td>
                                    <td class="px-8 py-6">
                                        <div class="flex items-center gap-2 text-xs font-bold text-gray-500">
                                            <i data-lucide="calendar-check" class="w-3.5 h-3.5 text-blue-500"></i>
                                            {{ date('M d, Y', strtotime($row->updated_at)) }}
                                        </div>
                                    </td>
                                    <td class="px-8 py-6 text-right">
                                        <span class="inline-flex items-center gap-1 bg-blue-50 text-blue-600 px-3 py-1.5 rounded-lg text-[9px] font-black border border-blue-100 uppercase tracking-wider">
                                            <i data-lucide="shield-check" class="w-3 h-3"></i> Verified
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="4" class="px-8 py-16 text-center">
                                        <div class="w-12 h-12 bg-pink-50 rounded-2xl flex items-center justify-center mx-auto mb-3">
                                            <i data-lucide="award" class="w-6 h-6 text-pink-200"></i>
                                        </div>
                                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">No verified tasks yet.</p>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <div class="space-y-8">
            <div class="glass-card p-8 flex flex-col items-center text-center bg-white">
                <h4 class="text-[11px] font-black text-pink-300 uppercase tracking-widest mb-8">Efficiency Rating</h4>
                
                <div class="relative w-40 h-40 flex items-center justify-center mb-6">
                    <svg class="w-40 h-40 transform -rotate-90 absolute" viewBox="0 0 36 36">
                        <path class="text-pink-50" stroke-width="3" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        <path class="text-[#FB6F92]" stroke-dasharray="{{ number_format($performance) }}, 100" stroke-linecap="round" stroke-width="3" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" style="transition: stroke-dasharray 1s ease-out;" />
                    </svg>
                    <div class="flex flex-col items-center justify-center z-10">
                        <span class="text-4xl font-black text-[#1e293b] tracking-tighter">{{ number_format($performance) }}%</span>
                    </div>
                </div>
                <p class="text-xs font-bold text-gray-500">Your current performance score based on verified assignments.</p>
            </div>

            <div class="pink-gradient p-8 rounded-[2.5rem] text-white shadow-xl shadow-pink-200/50 relative overflow-hidden">
                <i data-lucide="sparkles" class="absolute -right-4 -bottom-4 w-32 h-32 opacity-10 rotate-12"></i>
                <h4 class="text-xs font-black uppercase tracking-[0.2em] mb-4 opacity-80">Efficiency Insight</h4>
                <div class="flex items-center justify-between mb-4">
                    <span class="text-3xl font-extrabold italic tracking-tight font-outfit">AI Coach</span>
                    <i data-lucide="cpu" class="w-10 h-10 opacity-50"></i>
                </div>
                <p class="text-xs font-bold leading-relaxed mb-4">Need help sorting out assignments? Head to the Performance tab to let the AI audit your pipeline or deconstruct tasks.</p>
                <a href="{{ route('employee.performance') }}" class="inline-block bg-white text-[#FB6F92] px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider hover:scale-105 transition-transform shadow-md">Get Audit</a>
            </div>
        </div>
    </div>
@endsection
