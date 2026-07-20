@extends('layouts.dashboard')

@section('title', 'OptiTask | My Profile & Skills')

@section('content')
    <header class="flex justify-between items-end mb-12">
        <div>
            <h1 class="text-4xl font-extrabold text-[#1e293b] tracking-tight uppercase">Profile & Skills</h1>
            <p class="text-pink-400 mt-1 font-bold">Manage your department and expertise portfolio.</p>
        </div>
    </header>

    @if(session('success'))
        <div class="mb-6 p-4 rounded-2xl text-center text-sm font-semibold bg-green-50 text-green-500">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 p-4 rounded-2xl text-center text-sm font-semibold bg-red-50 text-red-400">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left Column: Add Skill & Skill Inventory -->
        <div class="lg:col-span-2 space-y-8 flex flex-col">
            <!-- Add Skill Card -->
            <div class="glass-card p-6 bg-white">
                <form action="{{ route('employee.save_skill') }}" method="POST" class="flex flex-col md:flex-row items-end gap-4">
                    @csrf
                    <div class="flex-1 w-full">
                        <label class="text-[10px] font-black text-pink-400 uppercase tracking-widest ml-1">Select Skill</label>
                        <select name="skill_id" required class="mt-1.5 w-full bg-[#FFF9FA] rounded-xl px-4 py-2.5 text-xs font-bold text-[#1e293b] outline-none border border-pink-100 focus:border-[#FB6F92] cursor-pointer">
                            <option value="">-- Choose Skill --</option>
                            @foreach ($allSkills as $s)
                                <option value="{{ $s->skill_id }}">{{ $s->skill_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex-1 w-full">
                        <label class="text-[10px] font-black text-pink-400 uppercase tracking-widest ml-1">Expertise Level</label>
                        <select name="proficiency_level" required class="mt-1.5 w-full bg-[#FFF9FA] rounded-xl px-4 py-2.5 text-xs font-bold text-[#1e293b] outline-none border border-pink-100 focus:border-[#FB6F92] cursor-pointer">
                            <option value="">-- Choose Level --</option>
                            <option value="1">Level 1 - Beginner</option>
                            <option value="2">Level 2 - Novice</option>
                            <option value="3">Level 3 - Intermediate</option>
                            <option value="4">Level 4 - Advanced</option>
                            <option value="5">Level 5 - Expert</option>
                        </select>
                    </div>

                    <button type="submit" class="w-full md:w-auto pink-gradient text-white px-6 py-2.5 rounded-xl font-extrabold shadow-lg shadow-pink-100 flex items-center justify-center gap-1.5 hover:scale-[1.01] transition-all uppercase tracking-wider text-xs h-[38px] shrink-0">
                        <i data-lucide="plus" class="w-4 h-4"></i> Add Skill
                    </button>
                </form>
            </div>

            <!-- Skills List Grid -->
            <div class="glass-card p-8 bg-white/70 flex-1">
                <h3 class="font-extrabold text-[#1e293b] text-xl mb-6 flex items-center gap-2">
                    <i data-lucide="award" class="w-5 h-5 text-[#FB6F92]"></i> Current Skill Inventory
                </h3>
                
                @if ($mySkills->count() > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach ($mySkills as $ms)
                            <div class="bg-white border border-pink-100 rounded-2xl p-5 shadow-sm flex items-center justify-between hover:border-[#FB6F92] transition-colors">
                                <div>
                                    <h4 class="font-extrabold text-sm text-gray-800">{{ $ms->skill_name }}</h4>
                                    <span class="inline-block mt-1 text-[10px] font-black text-[#FB6F92] uppercase bg-pink-50 px-2 py-0.5 rounded">
                                        {{ $profLabels[$ms->proficiency_level] ?? 'Level ' . $ms->proficiency_level }}
                                    </span>
                                </div>
                                <button onclick="confirmDeleteSkill('{{ $ms->skill_id }}', '{{ $ms->skill_name }}')" class="w-8 h-8 rounded-full bg-pink-50/50 flex items-center justify-center text-pink-300 hover:text-red-500 hover:bg-red-50 transition-all shadow-sm border border-pink-100/30">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-16 text-center opacity-40">
                         <i data-lucide="award" class="w-12 h-12 text-pink-300 mx-auto mb-4"></i>
                         <p class="text-sm font-bold text-gray-400">No skills registered yet. Use the tool above to add some!</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Right Column: Profile details & Department Selection -->
        <div class="lg:col-span-1 space-y-8">
            <div class="glass-card p-8 bg-white flex flex-col justify-between h-fit">
                <div>
                    <h3 class="font-extrabold text-[#1e293b] text-xl mb-6 flex items-center gap-2">
                        <i data-lucide="user" class="w-5 h-5 text-[#FB6F92]"></i> Account Details
                    </h3>
                    <div class="space-y-4 mb-8">
                        <div>
                            <p class="text-[9px] font-black text-gray-400 uppercase tracking-wider">Employee ID</p>
                            <p class="text-sm font-extrabold text-gray-700">{{ $user->user_id }}</p>
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-gray-400 uppercase tracking-wider">Full Username</p>
                            <p class="text-sm font-extrabold text-gray-700">{{ $user->username }}</p>
                        </div>
                        <div>
                            <div class="flex justify-between items-center">
                                <p class="text-[9px] font-black text-gray-400 uppercase tracking-wider">Email Address</p>
                                <span class="text-[9px] font-black uppercase tracking-wider {{ $user->email_updates_remaining > 0 ? 'text-pink-400' : 'text-red-400' }}">
                                    {{ $user->email_updates_remaining }} {{ Str::plural('attempt', $user->email_updates_remaining) }} left
                                </span>
                            </div>
                            
                            <div id="email-display-wrapper" class="{{ $errors->has('email') ? 'hidden' : 'flex' }} justify-between items-center mt-1">
                                <p class="text-sm font-extrabold text-gray-700 truncate max-w-[200px]" title="{{ $user->email }}">{{ $user->email }}</p>
                                @if ($user->email_updates_remaining > 0)
                                    <button onclick="toggleEmailEdit(true)" class="text-pink-400 hover:text-[#FB6F92] transition-colors p-1" title="Edit Email">
                                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                                    </button>
                                @endif
                            </div>

                            @if ($user->email_updates_remaining > 0)
                                <form id="email-edit-form" action="{{ route('employee.update_email') }}" method="POST" class="{{ $errors->has('email') ? '' : 'hidden' }} mt-2 flex items-center gap-2">
                                    @csrf
                                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required 
                                        class="flex-1 bg-[#FFF9FA] border border-pink-100 rounded-xl px-3 py-1.5 text-xs font-bold text-gray-700 outline-none focus:border-[#FB6F92] min-w-0">
                                    <button type="submit" class="pink-gradient text-white px-3 py-1.5 rounded-xl text-xs font-black uppercase tracking-wider shadow-sm hover:scale-[1.02] transition-transform shrink-0">
                                        Save
                                    </button>
                                    <button type="button" onclick="toggleEmailEdit(false)" class="bg-gray-100 text-gray-500 px-3 py-1.5 rounded-xl text-xs font-black uppercase tracking-wider shadow-sm hover:bg-gray-200 transition-colors shrink-0">
                                        Cancel
                                    </button>
                                </form>
                                @error('email')
                                    <p class="text-[10px] text-red-400 font-bold mt-1">{{ $message }}</p>
                                @enderror
                            @else
                                <p class="text-[10px] text-red-400 font-bold mt-1 uppercase tracking-wider">Maximum attempts reached.</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="border-t border-pink-50 pt-6">
                    <p class="text-[10px] font-black text-pink-400 uppercase tracking-widest mb-3 ml-1">Department Settings</p>
                    @if ($user->dept_id)
                        <div class="bg-[#FFF9FA] border border-pink-100 rounded-2xl p-4 flex items-center justify-between">
                            <div>
                                <p class="text-[9px] font-black text-pink-300 uppercase">Current Division</p>
                                <p class="text-sm font-black text-gray-800">{{ $user->department->dept_name ?? 'Division' }}</p>
                            </div>
                            <span class="px-3 py-1 bg-green-50 text-green-600 rounded-xl text-[9px] font-black uppercase tracking-wider">Locked</span>
                        </div>
                    @else
                        <form action="{{ route('employee.save_department') }}" method="POST" class="space-y-4">
                            @csrf
                            <select name="dept_id" required class="w-full bg-[#FFF9FA] border border-pink-100 rounded-2xl px-4 py-3 text-xs font-bold text-gray-700 outline-none focus:border-[#FB6F92] cursor-pointer">
                                <option value="">-- Select Department --</option>
                                @foreach ($allDepts as $d)
                                    <option value="{{ $d->dept_id }}">{{ $d->dept_name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="w-full pink-gradient text-white py-3 rounded-2xl text-xs font-black uppercase tracking-wider shadow-md hover:scale-[1.01] transition-transform">
                                Lock Department
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden delete form -->
    <form id="delete-skill-form" action="{{ route('employee.delete_skill') }}" method="POST" class="hidden">
        @csrf
        <input type="hidden" name="skill_id" id="delete-skill-id">
    </form>
@endsection

@section('scripts')
<script>
    function toggleEmailEdit(show) {
        const display = document.getElementById('email-display-wrapper');
        const form = document.getElementById('email-edit-form');
        if (show) {
            display.classList.add('hidden');
            form.classList.remove('hidden');
        } else {
            display.classList.remove('hidden');
            form.classList.add('hidden');
        }
    }

    function confirmDeleteSkill(skillId, name) {
        Swal.fire({
            title: 'Remove Skill?',
            html: `Are you sure you want to remove <b class="text-[#FB6F92]">${name}</b> from your profile?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#EF4444',
            cancelButtonColor: '#1e293b',
            confirmButtonText: 'Yes, Remove It',
            background: '#FFF9FA',
            customClass: {
                popup: 'rounded-[2.5rem] border-2 border-pink-100',
                title: 'font-black text-[#1e293b] font-outfit',
                confirmButton: 'rounded-xl px-6 py-3 font-bold',
                cancelButton: 'rounded-xl px-6 py-3 font-bold'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.getElementById('delete-skill-form');
                document.getElementById('delete-skill-id').value = skillId;
                form.submit();
            }
        });
    }
</script>
@endsection
