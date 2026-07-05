@extends('layouts.dashboard')

@section('title', 'OptiTask | My Notifications')

@section('content')
    <header class="flex justify-between items-end mb-10">
        <div>
            <h1 class="text-4xl font-extrabold text-[#1e293b] tracking-tight uppercase">Notifications</h1>
            <p class="text-pink-400 mt-1 font-bold">Approvals, submissions, and assignment alerts.</p>
        </div>

        <div class="flex gap-4">
            <button onclick="window.location.reload()" class="bg-white border border-pink-100 px-6 py-3 rounded-2xl text-xs font-black text-[#1e293b] hover:border-[#FB6F92] hover:text-[#FB6F92] hover:bg-pink-50/50 transition-all uppercase tracking-widest flex items-center gap-2">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i> Refresh Feed
            </button>
        </div>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
        <div class="lg:col-span-2 space-y-6">
            <div class="glass-card overflow-hidden bg-white">
                <div class="p-8 border-b border-pink-50 flex justify-between items-center bg-white">
                    <h3 class="text-xl font-extrabold text-[#1e293b] flex items-center gap-3">
                        <span class="w-2 h-8 pink-gradient rounded-full"></span>
                        Recent Alerts
                    </h3>
                </div>

                <div class="divide-y divide-pink-50">
                    @if($notifications->count() > 0)
                        @foreach($notifications as $n)
                        @php
                            $bg_color = "bg-gray-50 text-gray-500 border-gray-100";
                            $icon = "bell";
                            if ($n->notification_type === 'Submission') {
                                $bg_color = "bg-yellow-50 text-yellow-600 border-yellow-100";
                                $icon = "clipboard-check";
                            } elseif ($n->notification_type === 'Assignment') {
                                $bg_color = "bg-blue-50 text-blue-600 border-blue-100";
                                $icon = "clipboard";
                            } elseif ($n->notification_type === 'Approval' || $n->notification_type === 'Verification') {
                                $bg_color = "bg-green-50 text-green-600 border-green-100";
                                $icon = "check-circle";
                            } elseif ($n->notification_type === 'Rejection') {
                                $bg_color = "bg-red-50 text-red-600 border-red-100";
                                $icon = "x-circle";
                            }
                        @endphp
                        <div class="p-8 hover:bg-[#FFF9FA] transition-all flex gap-6 items-start {{ $n->status === 'unread' ? 'bg-pink-50/10' : '' }}">
                            <div class="w-14 h-14 rounded-2xl {{ $bg_color }} flex items-center justify-center border shadow-sm shrink-0">
                                <i data-lucide="{{ $icon }}" class="w-7 h-7"></i>
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between items-start mb-1">
                                    <p class="text-base font-extrabold text-[#1e293b]">{{ $n->notification_type }}</p>
                                    <span class="text-[10px] text-pink-300 font-bold uppercase">{{ date('H:i A', strtotime($n->timestamp)) }}</span>
                                </div>
                                <p class="text-sm text-gray-500 mt-1 font-medium leading-relaxed">{{ rtrim($n->message, '? ') }}</p>
                                @if ($n->notification_type === 'Assignment')
                                    <div class="mt-4 flex gap-3">
                                        <a href="{{ route('employee.tasks') }}" class="px-5 py-2 rounded-xl text-[11px] font-black pink-gradient text-white shadow-md shadow-pink-100 inline-block uppercase">VIEW TASK</a>
                                    </div>
                                @endif
                                <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-3">{{ date('d M Y', strtotime($n->timestamp)) }}</p>
                            </div>
                        </div>
                        @endforeach
                    @else
                        <div class="p-20 text-center opacity-40">
                             <i data-lucide="inbox" class="w-12 h-12 text-pink-300 mx-auto mb-4"></i>
                             <p class="text-sm font-bold text-gray-400">Your notifications inbox is empty.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="space-y-8">
            <div class="glass-card p-10 relative overflow-hidden bg-white">
                <div class="absolute top-0 right-0 w-24 h-24 bg-pink-50 rounded-bl-full opacity-50"></div>
                <h3 class="text-xl font-extrabold text-[#1e293b] mb-8 relative">Quick Links</h3>
                
                <div class="space-y-4 relative">
                    <a href="{{ route('employee.tasks') }}" class="w-full p-5 bg-[#FF8FAB] hover:bg-[#FB6F92] text-white rounded-2xl text-[11px] font-black uppercase tracking-widest flex items-center gap-3 shadow-lg hover:scale-[1.02] transition-all duration-300">
                        <i data-lucide="clipboard-list" class="w-5 h-5 text-pink-500"></i> My Task Board
                    </a>
                    
                    <a href="{{ route('employee.skills') }}" class="w-full p-5 bg-white border-2 border-pink-50 text-[#1e293b] rounded-2xl text-[11px] font-black uppercase tracking-widest flex items-center gap-3 hover:border-[#FB6F92] hover:text-[#FB6F92] hover:bg-pink-50/50 transition-all">
                        <i data-lucide="user" class="w-5 h-5 text-[#FB6F92]"></i> Profile Settings
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
