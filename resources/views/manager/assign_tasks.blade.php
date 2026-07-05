@extends('layouts.dashboard')

@section('title', 'OptiTask | Assign Tasks')

@section('styles')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

  <style>
    .choices__inner { background-color: #FFF9FA !important; border-radius: 1rem !important; border: 1px solid #FFD1DC !important; min-height: 52px !important; font-weight: 700 !important; }
    .flatpickr-calendar { font-family: 'Quicksand', sans-serif !important; border-radius: 1.5rem !important; border: 2px solid #FFE4EA !important; }
    .flatpickr-day.selected { background: #FB6F92 !important; border-color: #FB6F92 !important; }
  </style>
@endsection

@section('content')
  <header class="mb-10">
    <h1 class="text-4xl font-extrabold text-[#1e293b] tracking-tight uppercase">Assign Tasks</h1>
    <p class="text-pink-400 mt-1 font-bold">Delegate work orders with precision.</p>
  </header>

  @if($errors->any())
      <div class="mb-6 p-4 rounded-2xl text-center text-sm font-semibold bg-red-50 text-red-400">
          {{ $errors->first() }}
      </div>
  @endif

  @if(session('success'))
      <div class="mb-6 p-4 rounded-2xl text-center text-sm font-semibold bg-green-50 text-green-500">
          {{ session('success') }}
      </div>
  @endif

  <form action="{{ route('manager.assign_tasks') }}" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    @csrf
    <div class="lg:col-span-2 space-y-6">
      <div class="glass-card p-8 space-y-6">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          
          <div class="md:col-span-1">
            <label class="text-[11px] font-black text-pink-300 uppercase tracking-widest ml-1">Department Filter</label>
            <select onchange="window.location.href='{{ route('manager.assign_tasks') }}?dept_filter=' + this.value" class="mt-2 w-full bg-[#FFF9FA] rounded-2xl px-6 py-4 text-sm font-bold text-[#1e293b] outline-none border-2 border-pink-50 focus:border-[#FB6F92] transition-all">
                <option value="all" {{ $selectedDept == 'all' ? 'selected' : '' }}>Show All Departments</option>
                @foreach ($departments as $d)
                    <option value="{{ $d->dept_id }}" {{ $selectedDept == $d->dept_id ? 'selected' : '' }}>{{ $d->dept_name }}</option>
                @endforeach
            </select>
          </div>

          <div class="md:col-span-1">
            <label class="text-[11px] font-black text-pink-300 uppercase tracking-widest ml-1">Required Skill (AI Suggestion)</label>
            <div class="flex gap-2 mt-2">
                <select id="skill-select" class="flex-1 bg-[#FFF9FA] rounded-2xl px-6 py-4 text-sm font-bold text-[#1e293b] outline-none border-2 border-pink-50 focus:border-[#FB6F92] transition-all">
                    <option value="">-- Select Skill --</option>
                    @foreach ($skills as $s)
                        <option value="{{ $s->skill_id }}">{{ $s->skill_name }}</option>
                    @endforeach
                </select>
                <button type="button" id="ai-suggest-btn" class="w-14 shrink-0 pink-gradient text-white rounded-2xl flex items-center justify-center hover:scale-105 transition-transform shadow-md shadow-pink-100" title="Suggest Candidate">
                    <i data-lucide="wand-2" class="w-5 h-5"></i>
                </button>
            </div>
          </div>

          <div class="md:col-span-2">
            <label class="text-[11px] font-black text-pink-300 uppercase tracking-widest ml-1">Work Type</label>
            <select name="task_type" id="task-type-select" class="mt-2 w-full bg-[#FFF9FA] rounded-2xl px-6 py-4 text-sm font-bold text-[#1e293b] outline-none border-2 border-pink-50 focus:border-[#FB6F92] transition-all cursor-pointer">
                <option value="Personal" {{ old('task_type') === 'Personal' ? 'selected' : '' }}>Personal Task (Single Assignee)</option>
                <option value="Group" {{ old('task_type') === 'Group' ? 'selected' : '' }}>Group Project (Multiple Assignees)</option>
            </select>
          </div>

          <div class="md:col-span-2">
            <label class="text-[11px] font-black text-pink-300 uppercase tracking-widest ml-1">Assignee(s)</label>
            <div class="mt-2">
                <select name="assignee[]" id="employee-select" multiple required>
                  @foreach ($employees as $emp)
                    <option value="{{ $emp->user_id }}">{{ $emp->username }} ({{ $emp->user_id }})</option>
                  @endforeach
                </select>
            </div>
          </div>

          <div class="md:col-span-2">
            <label class="text-[11px] font-black text-pink-300 uppercase tracking-widest ml-1">Task Headline</label>
            <input type="text" name="task_title" value="{{ old('task_title') }}" required class="mt-2 w-full bg-[#FFF9FA] rounded-2xl px-6 py-4 text-sm font-bold text-[#1e293b] outline-none border-2 border-pink-50 focus:border-[#FB6F92] transition-all" placeholder="e.g. Develop Marketplace API">
          </div>

          <div>
            <label class="text-[11px] font-black text-pink-300 uppercase tracking-widest ml-1">Deadline</label>
            <div class="relative mt-2">
              <input type="text" id="pretty-date" name="deadline" value="{{ old('deadline') }}" readonly required class="w-full bg-[#FFF9FA] rounded-2xl px-6 py-4 text-sm font-bold text-[#1e293b] outline-none border-2 border-pink-50 cursor-pointer" placeholder="DD-MM-YYYY">
              <i data-lucide="calendar" class="absolute right-5 top-1/2 -translate-y-1/2 w-5 h-5 text-pink-300"></i>
            </div>
          </div>

          <div class="md:col-span-2">
            <label class="text-[11px] font-black text-pink-300 uppercase tracking-widest ml-1">Files / Attachments</label>
            <input type="file" name="task_file" class="mt-2 w-full bg-[#FFF9FA] rounded-2xl px-6 py-3 text-sm font-bold border-2 border-dashed border-pink-100 text-gray-400">
          </div>

          <div class="md:col-span-2">
            <label class="text-[11px] font-black text-pink-300 uppercase tracking-widest ml-1">Priority Level</label>
            <div class="flex gap-4 mt-2">
              @php 
                $priorities = [
                    'Low' => 'border-green-100 text-green-500 bg-green-50 peer-checked:border-green-500', 
                    'Medium' => 'border-yellow-100 text-yellow-600 bg-yellow-50 peer-checked:border-yellow-500', 
                    'High' => 'border-red-100 text-red-500 bg-red-50 peer-checked:border-red-500'
                ];
              @endphp
              @foreach($priorities as $p => $style)
              <label class="flex-1 cursor-pointer">
                <input type="radio" name="priority" value="{{ $p }}" class="hidden peer" {{ (old('priority', 'Medium') === $p) ? 'checked' : '' }}>
                <div class="py-4 text-center rounded-2xl border-2 border-gray-100 font-extrabold text-sm text-gray-400 transition-all {{ $style }}">
                  {{ $p }}
                </div>
              </label>
              @endforeach
            </div>
          </div>

          <div class="md:col-span-2">
            <label class="text-[11px] font-black text-pink-300 uppercase tracking-widest ml-1">Instructions</label>
            <textarea name="description" rows="4" class="mt-2 w-full bg-[#FFF9FA] rounded-2xl px-6 py-4 text-sm font-bold text-[#1e293b] outline-none border-2 border-pink-50 focus:border-[#FB6F92] transition-all" placeholder="What should the employee do?">{{ old('description') }}</textarea>
          </div>
        </div>

        <button type="submit" class="w-full pink-gradient text-white py-5 rounded-2xl font-extrabold shadow-lg shadow-pink-100 flex items-center justify-center gap-3 hover:scale-[1.01] transition-all uppercase tracking-[0.2em] text-sm">
          <i data-lucide="zap" class="w-5 h-5"></i> Assign Task
        </button>
      </div>
    </div>

    <div class="space-y-6">
      <div class="glass-card p-8 h-[550px] flex flex-col relative overflow-hidden bg-white">
        <div class="flex items-center gap-2 mb-6 relative">
          <div class="w-3 h-3 rounded-full bg-pink-500 shadow-sm shadow-pink-200"></div>
          <h3 class="font-extrabold text-[#1e293b] text-xl italic tracking-tight">Sticky Notes</h3>
        </div>
        <textarea id="sticky-notes" name="manager_notes" class="flex-1 w-full bg-[#FFFDF0] rounded-2xl p-6 text-sm font-bold text-gray-600 outline-none border-none resize-none shadow-inner leading-relaxed" placeholder="Write internal notes here...">{{ old('manager_notes') }}</textarea>
      </div>
    </div>
  </form>
@endsection

@section('scripts')
<script>
  // Dynamic Assignee Limiting based on Task Type
  let empSelect;
  function initChoices(maxItems) {
      if (empSelect) {
          empSelect.destroy();
      }
      empSelect = new Choices('#employee-select', {
        removeItemButton: true,
        searchEnabled: true,
        placeholder: true,
        placeholderValue: maxItems === 1 ? 'Select exactly one employee...' : 'Select multiple employees...',
        itemSelectText: 'Click to select',
        maxItemCount: maxItems
      });
  }

  const taskTypeSelect = document.getElementById('task-type-select');
  initChoices(taskTypeSelect.value === 'Personal' ? 1 : -1);

  taskTypeSelect.addEventListener('change', function() {
      initChoices(this.value === 'Personal' ? 1 : -1);
  });

  // AI Suggestion Logic
  document.getElementById('ai-suggest-btn').addEventListener('click', async function() {
      const skillId = document.getElementById('skill-select').value;
      const deptId = '{{ $selectedDept }}';
      
      if (!skillId) {
          Swal.fire({ icon: 'warning', title: 'Oops!', text: 'Please select a required skill first.', confirmButtonColor: '#FF8FAB' });
          return;
      }

      const btn = this;
      btn.innerHTML = '<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i>';
      lucide.createIcons();

      try {
          const res = await fetch('{{ route("manager.suggest_candidate") }}?skill_id=' + skillId + '&dept_id=' + deptId);
          const data = await res.json();
          
          if (data.success) {
              empSelect.setChoiceByValue(data.user_id.toString());
              
              Swal.fire({
                  title: 'Perfect Match Found!',
                  html: `<b class="text-xl text-[#1e293b]">${data.username}</b><br><br><span class='text-sm text-gray-500'>${data.reason}</span>`,
                  icon: 'success',
                  confirmButtonColor: '#FF8FAB'
              });
          } else {
              Swal.fire({ icon: 'error', title: 'No Match', text: data.message, confirmButtonColor: '#FF8FAB' });
          }
      } catch (error) {
          Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to contact AI matching service.', confirmButtonColor: '#FF8FAB' });
      }

      btn.innerHTML = '<i data-lucide="wand-2" class="w-5 h-5"></i>';
      lucide.createIcons();
  });

  flatpickr("#pretty-date", { dateFormat: "d-m-Y", minDate: "today" });

  const stickyNote = document.getElementById('sticky-notes');
  stickyNote.value = localStorage.getItem('optitask_sticky_note') || stickyNote.value || '';
  stickyNote.addEventListener('input', () => { localStorage.setItem('optitask_sticky_note', stickyNote.value); });
</script>
@endsection
