@extends('layouts.dashboard')

@section('title', 'OptiTask | Verify Work')

@section('content')
    <header class="mb-10">
        <h1 class="text-4xl font-extrabold text-[#1e293b] uppercase tracking-tight">Verify Submissions</h1>
        <p class="text-pink-400 font-bold mt-1">Review employee files and approve their final work.</p>
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

    <div class="glass-card overflow-hidden bg-white">
        <table class="w-full text-left">
            <thead>
                <tr class="text-[11px] uppercase font-black text-pink-300 tracking-widest border-b border-pink-50 bg-[#FFF9FA]">
                    <th class="px-8 py-6">Employee</th>
                    <th class="px-8 py-6">Task Details</th>
                    <th class="px-8 py-6 text-right">Action Control</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-pink-50">
                @if($tasks->count() > 0)
                    @foreach($tasks as $row)
                    <tr class="hover:bg-[#FFF9FA] transition-all">
                        <td class="px-8 py-6">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-pink-50 text-[#FB6F92] flex items-center justify-center font-black text-xs">
                                    {{ strtoupper(substr($row->employee->username ?? 'EM', 0, 2)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-extrabold text-[#1e293b]">{{ $row->employee->username ?? 'Unknown' }}</p>
                                    <p class="text-[10px] text-pink-300 font-bold">{{ $row->employee_id }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-8 py-6">
                            <p class="text-sm font-extrabold text-[#1e293b]">{{ $row->task_title }}</p>
                            @if(!empty($row->evidence_link))
                                <a href="{{ $row->evidence_link }}" target="_blank" class="text-[10px] text-blue-500 font-bold flex items-center gap-1 mt-1 hover:underline">
                                    <i data-lucide="link" class="w-3 h-3"></i> View Evidence Link
                                </a>
                            @elseif(!empty($row->submission_file))
                                <a href="{{ asset('storage/' . $row->submission_file) }}" target="_blank" class="text-[10px] text-blue-500 font-bold flex items-center gap-1 mt-1 hover:underline">
                                    <i data-lucide="file-text" class="w-3 h-3"></i> View Submission File
                                </a>
                            @endif
                        </td>
                        <td class="px-8 py-6 text-right">
                            <div class="flex justify-end gap-3">
                                @if($row->task_status === 'Review')
                                    <button onclick="verifyAction('{{ $row->task_id }}', 'approve')" class="bg-green-500 text-white px-5 py-2.5 rounded-xl text-[10px] font-black uppercase shadow-lg shadow-green-100 hover:scale-[1.02] transform transition-transform">Approve</button>
                                    <button onclick="promptReject('{{ $row->task_id }}')" class="bg-white border-2 border-pink-50 text-pink-400 px-5 py-2.5 rounded-xl text-[10px] font-black uppercase hover:text-red-500 hover:border-red-100 transition-colors">Reject</button>
                                @else
                                    <span class="inline-block px-3 py-1 bg-gray-50 border border-gray-100 rounded-xl text-xs text-gray-400 font-bold uppercase">{{ $row->task_status }}</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                @else
                    <tr><td colspan="3" class="p-20 text-center text-gray-400 font-bold italic">No pending submissions found.</td></tr>
                @endif
            </tbody>
        </table>
    </div>

    <!-- Dynamic Action Forms -->
    <form id="verify-form" method="POST" class="hidden">
        @csrf
        <input type="text" name="manager_notes" id="verify-notes">
    </form>
@endsection

@section('scripts')
<script>
    function verifyAction(taskId, action, notes = '') {
        const form = document.getElementById('verify-form');
        document.getElementById('verify-notes').value = notes;
        
        let route = "{{ route('manager.verify_task_action', ['task_id' => ':task_id', 'action' => ':action']) }}"
            .replace(':task_id', taskId)
            .replace(':action', action);

        form.setAttribute('action', route);
        form.submit();
    }

    function promptReject(taskId) {
        Swal.fire({
            title: 'Reject Submission',
            html: `
                <p class="text-xs text-gray-500 mb-4 font-bold uppercase tracking-wider text-left">Please select a reason to reject this submission:</p>
                <select id="swal-reject-reason" class="w-full bg-[#FFF9FA] rounded-2xl px-5 py-4 text-sm font-bold text-[#1e293b] outline-none border-2 border-pink-50 focus:border-[#FB6F92] mb-4">
                    <option value="Incomplete work">Incomplete work</option>
                    <option value="Incorrect file or invalid link">Incorrect file or invalid link</option>
                    <option value="Does not meet criteria / instructions">Does not meet criteria / instructions</option>
                    <option value="Quality below standard">Quality below standard</option>
                    <option value="Other">Other (Type custom reason below)</option>
                </select>
                <textarea id="swal-custom-reason" placeholder="Type custom reason here..." disabled class="w-full bg-[#FFF9FA] rounded-2xl p-5 text-sm font-bold text-gray-600 outline-none border-2 border-pink-50 focus:border-[#FB6F92] resize-none h-24 transition-opacity opacity-50"></textarea>
            `,
            showCancelButton: true,
            confirmButtonColor: '#EF4444',
            cancelButtonColor: '#1e293b',
            confirmButtonText: 'Confirm Rejection',
            cancelButtonText: 'Cancel',
            background: '#FFF9FA',
            customClass: {
                popup: 'rounded-[2.5rem] border-2 border-pink-100',
                title: 'font-black text-[#1e293b] font-outfit',
                confirmButton: 'rounded-xl px-6 py-3 font-bold font-outfit',
                cancelButton: 'rounded-xl px-6 py-3 font-bold font-outfit'
            },
            didOpen: () => {
                const select = document.getElementById('swal-reject-reason');
                const textarea = document.getElementById('swal-custom-reason');
                select.addEventListener('change', () => {
                    if (select.value === 'Other') {
                        textarea.disabled = false;
                        textarea.classList.remove('opacity-50');
                        textarea.focus();
                    } else {
                        textarea.disabled = true;
                        textarea.classList.add('opacity-50');
                        textarea.value = '';
                    }
                });
            },
            preConfirm: () => {
                const select = document.getElementById('swal-reject-reason');
                const textarea = document.getElementById('swal-custom-reason');
                let reason = select.value;
                if (reason === 'Other') {
                    reason = textarea.value.trim();
                    if (!reason) {
                        Swal.showValidationMessage('Please type a custom reason');
                        return false;
                    }
                }
                return reason;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                verifyAction(taskId, 'reject', result.value);
            }
        });
    }
</script>
@endsection
