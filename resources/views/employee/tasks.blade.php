@extends('layouts.dashboard')

@section('title', 'OptiTask | My Tasks Kanban')

@section('styles')
    <style>
        .state-todo { background-color: #FFE4EA; color: #FB6F92; border: 1px solid #FFD1DC; }
        .state-progress { background-color: #FFFbeb; color: #d97706; border: 1px solid #fde68a; }
        .state-done { background-color: #ECFDF5; color: #10B981; border: 1px solid #D1FAE5; }
        .state-verified { background-color: #EFF6FF; color: #3B82F6; border: 1px solid #DBEAFE; }

        .kanban-column {
            transition: all 0.3s ease;
        }
        .kanban-column.drag-over {
            background-color: rgba(251, 111, 146, 0.05);
            border-color: #FB6F92;
        }

        .task-card {
            transition: all 0.2s ease, transform 0.2s ease;
            border: 1px solid rgba(255, 228, 234, 0.6);
            background: #ffffff;
            cursor: grab;
        }
        .task-card:active {
            cursor: grabbing;
        }
        .task-card.dragging {
            opacity: 0.5;
            transform: scale(0.98);
        }
        .task-card:hover {
            transform: translateY(-3px);
            border-color: rgba(251, 111, 146, 0.3);
            box-shadow: 0 10px 20px rgba(251, 111, 146, 0.05);
        }
    </style>
@endsection

@section('content')
    <header class="flex justify-between items-end mb-12">
        <div>
            <h1 class="text-4xl font-extrabold text-[#1e293b] tracking-tight uppercase">Task Board</h1>
            <p class="text-pink-400 mt-1 font-bold">Drag and drop cards to update status and submit workloads.</p>
        </div>
        <div class="relative w-80 z-20">
            <input id="searchInput" type="text" placeholder="Search task name or ID..." autocomplete="off" class="bg-white border-2 border-pink-50 rounded-2xl pl-12 pr-6 py-3 text-sm font-bold w-full outline-none focus:border-pink-300 shadow-sm text-gray-700">
            <i data-lucide="search" class="w-5 h-5 text-pink-200 absolute left-4 top-1/2 -translate-y-1/2"></i>
            
            <div id="searchDropdown" class="absolute left-0 right-0 mt-2 bg-white border border-pink-100 rounded-2xl shadow-2xl z-50 hidden max-h-60 overflow-y-auto divide-y divide-pink-50/50"></div>
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

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 pb-12">
        <div class="kanban-column flex flex-col bg-white border border-pink-50 rounded-[2rem] p-6 min-h-[600px] shadow-sm" data-status="todo" ondragover="allowDrop(event)" ondragleave="dragLeave(event)" ondrop="handleDrop(event, 'todo')">
            <h3 class="text-base font-black text-gray-800 mb-6 flex justify-between items-center px-2">
                <span class="flex items-center gap-2 uppercase tracking-wider text-xs">
                    <span class="w-2 h-2 rounded-full bg-[#FB6F92]"></span> To-Do
                </span>
                <span id="count-todo" class="text-[10px] font-black bg-pink-50 text-[#FB6F92] px-2.5 py-1 rounded-full border border-pink-100 shadow-sm">0</span>
            </h3>
            <div class="space-y-4 flex-1 flex flex-col" id="col-todo"></div>
        </div>

        <div class="kanban-column flex flex-col bg-white border border-pink-50 rounded-[2rem] p-6 min-h-[600px] shadow-sm" data-status="inprogress" ondragover="allowDrop(event)" ondragleave="dragLeave(event)" ondrop="handleDrop(event, 'inprogress')">
            <h3 class="text-base font-black text-gray-800 mb-6 flex justify-between items-center px-2">
                <span class="flex items-center gap-2 uppercase tracking-wider text-xs">
                    <span class="w-2 h-2 rounded-full bg-yellow-500"></span> Active
                </span>
                <span id="count-inprogress" class="text-[10px] font-black bg-yellow-50 text-yellow-600 px-2.5 py-1 rounded-full border border-yellow-100 shadow-sm">0</span>
            </h3>
            <div class="space-y-4 flex-1 flex flex-col" id="col-inprogress"></div>
        </div>

        <div class="kanban-column flex flex-col bg-white border border-pink-50 rounded-[2rem] p-6 min-h-[600px] shadow-sm" data-status="done" ondragover="allowDrop(event)" ondragleave="dragLeave(event)" ondrop="handleDrop(event, 'done')">
            <h3 class="text-base font-black text-gray-800 mb-6 flex justify-between items-center px-2">
                <span class="flex items-center gap-2 uppercase tracking-wider text-xs">
                    <span class="w-2 h-2 rounded-full bg-green-500"></span> In Review
                </span>
                <span id="count-done" class="text-[10px] font-black bg-green-50 text-green-600 px-2.5 py-1 rounded-full border border-green-100 shadow-sm">0</span>
            </h3>
            <div class="space-y-4 flex-1 flex flex-col" id="col-done"></div>
        </div>

        <div class="kanban-column flex flex-col bg-white border border-pink-50 rounded-[2rem] p-6 min-h-[600px] shadow-sm" data-status="verified" ondragover="allowDrop(event)" ondragleave="dragLeave(event)" ondrop="handleDrop(event, 'verified')">
            <h3 class="text-base font-black text-gray-800 mb-6 flex justify-between items-center px-2">
                <span class="flex items-center gap-2 uppercase tracking-wider text-xs">
                    <span class="w-2 h-2 rounded-full bg-blue-500"></span> Verified
                </span>
                <span id="count-verified" class="text-[10px] font-black bg-blue-50 text-blue-600 px-2.5 py-1 rounded-full border border-blue-100 shadow-sm">0</span>
            </h3>
            <div class="space-y-4 flex-1 flex flex-col" id="col-verified"></div>
        </div>
    </div>

    <div id="modalBackdrop" class="fixed inset-0 z-50 hidden items-center justify-center p-6 bg-slate-900/40 backdrop-blur-sm">
        <div class="w-full max-w-xl bg-white rounded-[2rem] shadow-2xl overflow-hidden border border-pink-50">
            <div class="px-8 py-6 border-b border-pink-50/50 flex justify-between items-center bg-white">
                <div>
                    <span id="mId" class="text-[10px] font-bold text-slate-500 uppercase tracking-wider font-mono block mb-1"></span>
                    <h3 id="mTitle" class="text-2xl font-extrabold text-[#1e293b] leading-tight">Task Detail</h3>
                </div>
                <button id="closeModal" class="w-10 h-10 rounded-xl bg-pink-50 hover:bg-pink-100/80 text-[#FB6F92] flex items-center justify-center hover:rotate-90 transition-all duration-300">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="p-8 space-y-6 bg-white max-h-[65vh] overflow-y-auto">
                <div class="flex flex-wrap items-center gap-3">
                    <span id="mStatus" class="px-3.5 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-wider border"></span>
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-[9px] font-black bg-slate-50 text-slate-500 border border-slate-100 uppercase">
                        <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400"></i>
                        <span id="mDue"></span>
                    </span>
                </div>

                <div class="bg-[#FFF9FA]/30 rounded-2xl p-6 border border-pink-50/80 shadow-sm">
                    <p class="text-[9px] font-black text-pink-400 uppercase tracking-widest mb-2">Requirement</p>
                    <p id="mDesc" class="text-xs font-semibold text-gray-600 leading-relaxed"></p>
                </div>

                <div id="mFileContainer" class="bg-white rounded-2xl p-5 border border-pink-100/60 flex items-center justify-between shadow-sm hidden">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-xl bg-pink-50 text-[#FB6F92] flex items-center justify-center shrink-0">
                            <i data-lucide="paperclip" class="w-4.5 h-4.5"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[8px] font-black text-pink-400 uppercase tracking-widest">Attachment</p>
                            <p id="mFileName" class="text-xs font-extrabold text-[#1e293b] truncate"></p>
                        </div>
                    </div>
                    <a id="mFileLink" href="#" download class="px-4 py-2 bg-pink-50 hover:bg-pink-100 text-[#FB6F92] text-[10px] font-black uppercase rounded-lg transition-all flex items-center gap-1 shadow-sm shrink-0">
                        <i data-lucide="download" class="w-3 h-3"></i> Download
                    </a>
                </div>

                <div id="modal-action-area" class="p-6 bg-slate-50 border border-slate-100 rounded-2xl text-center">
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    const tasks = @json($tasksArray);
    const searchInput = document.getElementById("searchInput");

    function getStatusClass(status) {
        if(status === "todo") return 'state-todo';
        if(status === "inprogress") return 'state-progress';
        if(status === "done") return 'state-done';
        return 'state-verified'; 
    }

    function render() {
        const columns = {
            todo: document.getElementById('col-todo'),
            inprogress: document.getElementById('col-inprogress'),
            done: document.getElementById('col-done'),
            verified: document.getElementById('col-verified')
        };

        Object.values(columns).forEach(col => col.innerHTML = '');

        const counts = { todo: 0, inprogress: 0, done: 0, verified: 0 };

        tasks.forEach(t => {
            counts[t.status]++;

            const card = document.createElement("div");
            card.id = `card-${t.id}`;
            card.className = "task-card rounded-2xl p-6 flex flex-col shadow-sm";
            card.draggable = t.status !== 'verified' && t.status !== 'done';
            card.onclick = () => openModal(t);

            card.addEventListener('dragstart', (ev) => {
                card.classList.add('dragging');
                ev.dataTransfer.setData("text", t.id);
            });

            card.addEventListener('dragend', () => {
                card.classList.remove('dragging');
            });

            card.innerHTML = `
                <div class="mb-4">
                    <span class="px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-wider ${getStatusClass(t.status)}">${t.raw_status}</span>
                </div>
                <h3 class="text-sm font-extrabold text-[#1e293b] leading-snug mb-4">${t.title}</h3>
                <div class="mt-auto pt-4 border-t border-pink-50/50 flex items-center justify-between">
                    <div class="flex items-center gap-1.5 text-[9px] font-bold text-gray-400 uppercase">
                        <i data-lucide="calendar" class="w-3.5 h-3.5 text-[#FB6F92]"></i>
                        <span>${t.due_display}</span>
                    </div>
                    <span class="text-[10px] font-extrabold text-slate-700 font-mono tracking-wider">${t.id}</span>
                </div>
            `;

            if (columns[t.status]) {
                columns[t.status].appendChild(card);
            }
        });

        document.getElementById('count-todo').innerText = counts.todo;
        document.getElementById('count-inprogress').innerText = counts.inprogress;
        document.getElementById('count-done').innerText = counts.done;
        document.getElementById('count-verified').innerText = counts.verified;

        Object.entries(columns).forEach(([status, col]) => {
            if (col.children.length === 0) {
                col.innerHTML = `
                    <div class="flex-1 flex flex-col items-center justify-center py-10 opacity-30">
                        <i data-lucide="inbox" class="w-8 h-8 text-pink-200 mb-2"></i>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Empty</span>
                    </div>
                `;
            }
        });

        lucide.createIcons();
    }

    function allowDrop(ev) {
        ev.preventDefault();
        const col = ev.currentTarget;
        col.classList.add('drag-over');
    }

    function dragLeave(ev) {
        const col = ev.currentTarget;
        col.classList.remove('drag-over');
    }

    function handleDrop(ev, targetStatus) {
        ev.preventDefault();
        const col = ev.currentTarget;
        col.classList.remove('drag-over');

        const taskId = ev.dataTransfer.getData("text");
        const task = tasks.find(t => t.id === taskId);

        if (!task) return;

        const originalStatus = task.status;

        if (originalStatus === 'verified') {
            Swal.fire({
                icon: 'error',
                title: 'Action Locked',
                text: 'Verified tasks cannot be modified.',
                confirmButtonColor: '#FB6F92'
            });
            return;
        }

        if (originalStatus === 'done') {
            Swal.fire({
                icon: 'error',
                title: 'In Review',
                text: 'This task is locked in review and cannot be dragged.',
                confirmButtonColor: '#FB6F92'
            });
            return;
        }

        if (targetStatus === 'todo') {
            Swal.fire({
                icon: 'warning',
                title: 'Action Restrained',
                text: 'You cannot move an active or completed task back to To-Do.',
                confirmButtonColor: '#FB6F92'
            });
            return;
        }

        if (targetStatus === 'verified') {
            Swal.fire({
                icon: 'error',
                title: 'Access Denied',
                text: 'Only managers have permission to verify workloads.',
                confirmButtonColor: '#FB6F92'
            });
            return;
        }

        if (originalStatus === 'todo' && targetStatus === 'inprogress') {
            startTaskAjax(task);
        } else if (originalStatus === 'inprogress' && targetStatus === 'done') {
            openModal(task);
        }
    }

    function startTaskAjax(task) {
        Swal.fire({
            title: 'Start this task?',
            text: `Confirm moving "${task.title}" to In Progress.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#FF8FAB',
            cancelButtonColor: '#1e293b',
            confirmButtonText: 'Yes, Start',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('{{ route("employee.start_task") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: new URLSearchParams({ task_id: task.id })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        task.status = 'inprogress';
                        task.raw_status = 'In Progress';
                        render();
                        Swal.fire({
                            icon: 'success',
                            title: 'Active!',
                            text: 'You have started the task successfully.',
                            confirmButtonColor: '#FB6F92'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Failure',
                            text: data.message || 'Unable to start task.',
                            confirmButtonColor: '#FB6F92'
                        });
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to process request.',
                        confirmButtonColor: '#FB6F92'
                    });
                });
            }
        });
    }

    function openModal(t) {
        document.getElementById("mId").textContent = t.id;
        document.getElementById("mTitle").textContent = t.title;
        document.getElementById("mDesc").textContent = t.desc;
        document.getElementById("mDue").textContent = t.due_display;
        
        const mStatus = document.getElementById("mStatus");
        mStatus.textContent = t.raw_status;
        mStatus.className = `px-3.5 py-1.5 rounded-lg text-[9px] font-black uppercase border ${getStatusClass(t.status)}`;

        const fileContainer = document.getElementById("mFileContainer");
        if (t.task_file && t.task_file.trim() !== "") {
            const fileName = t.task_file.split('/').pop().split('\\').pop();
            const displayName = fileName.includes('_') ? fileName.substring(fileName.indexOf('_') + 1) : fileName;
            document.getElementById("mFileName").textContent = displayName;
            document.getElementById("mFileLink").href = t.task_file;
            fileContainer.classList.remove("hidden");
        } else {
            fileContainer.classList.add("hidden");
        }

        const actionArea = document.getElementById("modal-action-area");
        if(t.status === 'todo') {
            actionArea.innerHTML = `
                <form action="{{ route('employee.start_task') }}" method="POST">
                    @csrf
                    <input type="hidden" name="task_id" value="${t.id}">
                    <button type="submit" class="w-full bg-[#FF8FAB] hover:bg-[#FB6F92] text-white py-4 rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl hover:scale-[1.02] transform duration-300">
                        Start Task Now
                    </button>
                </form>
            `;
        } else if(t.status === 'inprogress') {
            actionArea.innerHTML = `
                <form action="{{ route('employee.submit_work') }}" method="POST" enctype="multipart/form-data" class="space-y-4 text-left">
                    @csrf
                    <input type="hidden" name="task_id" value="${t.id}">
                    
                    <div>
                        <label class="text-[9px] font-black text-pink-400 uppercase tracking-widest">Evidence URL (e.g. Git Repository / Live Link)</label>
                        <input type="url" name="evidence_link" class="mt-1 w-full bg-[#FFF9FA] rounded-xl px-4 py-2.5 text-xs font-bold text-[#1e293b] outline-none border border-pink-100 focus:border-[#FB6F92] transition-all" placeholder="https://...">
                    </div>

                    <div>
                        <label class="text-[9px] font-black text-pink-400 uppercase tracking-widest">Or File Upload</label>
                        <input type="file" name="attachment" class="mt-1 block w-full text-xs text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-pink-50 file:text-[#FB6F92] file:font-black cursor-pointer">
                    </div>

                    <button type="submit" class="w-full pink-gradient text-white py-3.5 rounded-2xl font-black text-xs uppercase tracking-widest shadow-lg hover:scale-[1.02] transform duration-300">
                        Submit Deliverable
                    </button>
                </form>
            `;
        } else if(t.status === 'done') {
            actionArea.innerHTML = `<div class="py-4 text-green-500 font-black uppercase text-xs">Waiting for Manager Approval</div>`;
        } else {
            actionArea.innerHTML = `<p class="text-blue-500 font-black uppercase text-xs flex items-center justify-center gap-2"><i data-lucide="shield-check"></i> Task Verified</p>`;
        }
        
        document.getElementById("modalBackdrop").classList.remove("hidden");
        document.getElementById("modalBackdrop").classList.add("flex");
        lucide.createIcons();
    }

    document.getElementById("closeModal").onclick = () => {
        document.getElementById("modalBackdrop").classList.add("hidden");
        document.getElementById("modalBackdrop").classList.remove("flex");
    };

    const searchDropdown = document.getElementById("searchDropdown");

    searchInput.addEventListener("input", function() {
        const query = this.value.toLowerCase().trim();
        searchDropdown.innerHTML = "";

        if (query.length === 0) {
            searchDropdown.classList.add("hidden");
            return;
        }

        const matched = tasks.filter(t => 
            t.title.toLowerCase().includes(query) || t.id.toLowerCase().includes(query)
        );

        if (matched.length === 0) {
            const emptyEl = document.createElement("div");
            emptyEl.className = "px-6 py-4 text-xs text-gray-400 font-bold uppercase tracking-wider text-center";
            emptyEl.innerText = "No matches found";
            searchDropdown.appendChild(emptyEl);
        } else {
            matched.forEach(t => {
                const item = document.createElement("button");
                item.type = "button";
                item.className = "w-full text-left px-6 py-3.5 hover:bg-[#FFF9FA] transition-colors flex flex-col focus:outline-none";
                item.onclick = () => {
                    openModal(t);
                    searchInput.value = "";
                    searchDropdown.classList.add("hidden");
                };
                item.innerHTML = `
                    <span class="text-xs font-extrabold text-[#1e293b] leading-tight">${t.title}</span>
                    <span class="text-[9px] font-bold text-slate-500 font-mono mt-1">${t.id} (${t.raw_status})</span>
                `;
                searchDropdown.appendChild(item);
            });
        }

        searchDropdown.classList.remove("hidden");
    });

    document.addEventListener("click", function(e) {
        if (!searchInput.contains(e.target) && !searchDropdown.contains(e.target)) {
            searchDropdown.classList.add("hidden");
        }
    });

    render();
</script>
@endsection
