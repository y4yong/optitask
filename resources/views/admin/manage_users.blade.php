@extends('layouts.dashboard')

@section('title', 'OptiTask | User Accounts')

@section('content')
  <header class="flex justify-between items-end mb-12">
    <div>
      <h1 class="text-4xl font-extrabold text-[#1e293b] tracking-tight uppercase">User Accounts</h1>
      <p class="text-pink-400 mt-1 font-bold">Create, manage, and verify user profiles in system directory.</p>
    </div>

    <div class="flex gap-4">
      <div class="relative">
        <input type="text" id="searchInput" placeholder="Search users by name or ID..." class="bg-white border border-pink-100 rounded-full pl-12 pr-6 py-3 text-xs font-bold focus:ring-2 focus:ring-[#FB6F92]/20 outline-none w-72 transition-all shadow-sm">
        <i data-lucide="search" class="w-4.5 h-4.5 text-pink-300 absolute left-4 top-1/2 -translate-y-1/2"></i>
      </div>
      <button onclick="openCreateModal()" class="bg-[#FF8FAB] hover:bg-[#FB6F92] text-white px-6 py-3 rounded-full font-bold shadow-lg shadow-pink-100 transition-all text-xs flex items-center gap-2 hover:scale-[1.02] transform duration-300">
        <i data-lucide="user-plus" class="w-4 h-4"></i> Create User
      </button>
    </div>
  </header>

  <!-- Metrics Grid -->
  <div class="grid grid-cols-1 lg:grid-cols-4 gap-8 mb-12">
    <div class="glass-card p-6 relative overflow-hidden group">
      <p class="text-gray-400 text-[10px] font-bold uppercase tracking-widest relative z-10">Total Accounts</p>
      <h2 class="text-3xl font-black text-gray-800 mt-2 relative z-10">{{ $users->count() }}</h2>
      <div class="absolute -right-4 -bottom-4 w-20 h-20 bg-pink-50 rounded-full group-hover:bg-pink-100/50 transition-colors"></div>
    </div>
    <div class="glass-card p-6 relative overflow-hidden group">
      <p class="text-gray-400 text-[10px] font-bold uppercase tracking-widest relative z-10">Employees</p>
      <h2 class="text-3xl font-black text-gray-800 mt-2 relative z-10">{{ $users->where('role', 'Employee')->count() }}</h2>
      <div class="absolute -right-4 -bottom-4 w-20 h-20 bg-pink-50 rounded-full group-hover:bg-pink-100/50 transition-colors"></div>
    </div>
    <div class="glass-card p-6 relative overflow-hidden group">
      <p class="text-gray-400 text-[10px] font-bold uppercase tracking-widest relative z-10">Managers</p>
      <h2 class="text-3xl font-black text-gray-800 mt-2 relative z-10">{{ $users->where('role', 'Manager')->count() }}</h2>
      <div class="absolute -right-4 -bottom-4 w-20 h-20 bg-pink-50 rounded-full group-hover:bg-pink-100/50 transition-colors"></div>
    </div>
    <div class="glass-card p-6 relative overflow-hidden group">
      <p class="text-gray-400 text-[10px] font-bold uppercase tracking-widest relative z-10">Admins</p>
      <h2 class="text-3xl font-black text-gray-800 mt-2 relative z-10">{{ $users->where('role', 'Admin')->count() }}</h2>
      <div class="absolute -right-4 -bottom-4 w-20 h-20 bg-pink-50 rounded-full group-hover:bg-pink-100/50 transition-colors"></div>
    </div>
  </div>

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
    <!-- User table -->
    <div class="lg:col-span-2 glass-card overflow-hidden">
      <div class="p-8 border-b border-pink-50 flex justify-between items-center bg-white/50">
        <h3 class="font-extrabold text-[#1e293b] text-xl flex items-center gap-2">
          <i data-lucide="users" class="w-5 h-5 text-[#FB6F92]"></i> System Directory
        </h3>
        <div class="flex gap-2">
          <select id="roleFilter" class="bg-white border border-pink-100 rounded-full px-5 py-2 text-xs font-bold text-gray-600 outline-none focus:ring-2 focus:ring-pink-200">
            <option value="">All Roles</option>
            <option value="Employee">Employee</option>
            <option value="Manager">Manager</option>
            <option value="Admin">Admin</option>
          </select>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left">
          <thead>
            <tr class="text-[10px] uppercase font-black text-pink-300 tracking-[0.1em] border-b border-pink-50 bg-[#FFF9FA]">
              <th class="px-8 py-5">User</th>
              <th class="px-8 py-5">Role</th>
              <th class="px-8 py-5">Department</th>
              <th class="px-8 py-5">Status</th>
              <th class="px-8 py-5 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="text-sm font-semibold divide-y divide-pink-50">
            @foreach($users as $user)
            <tr class="user-row hover:bg-[#FFF9FA] transition-colors" data-role="{{ $user->role }}" data-search="{{ strtolower($user->user_id . ' ' . $user->username) }}">
              <td class="px-8 py-5">
                <div class="flex items-center gap-3 cursor-pointer group" 
                     data-id="{{ $user->user_id }}"
                     data-name="{{ $user->username }}"
                     data-email="{{ $user->email }}"
                     data-role="{{ $user->role }}"
                     data-status="{{ $user->account_status }}"
                     data-reason="{{ $user->suspension_reason ?? '' }}"
                     data-dept-id="{{ $user->dept_id ?? '' }}"
                     data-dept-name="{{ $user->department->dept_name ?? '' }}"
                     onclick="showUserDetails(this.dataset.id, this.dataset.name, this.dataset.email, this.dataset.role, this.dataset.status, this.dataset.reason, this.dataset.deptId, this.dataset.deptName)">
                  <div class="w-10 h-10 rounded-xl bg-pink-50 text-[#FB6F92] flex items-center justify-center font-black text-xs uppercase shadow-sm border border-pink-100 group-hover:scale-105 transition-all">
                      {{ substr($user->username, 0, 2) }}
                  </div>
                  <div>
                    <p class="text-sm font-black text-gray-800 group-hover:text-[#FB6F92] transition-colors">
                        {{ $user->username }}
                    </p>
                    <span class="text-[9px] font-mono text-gray-400 font-bold tracking-widest mt-0.5 inline-block">#{{ $user->user_id }}</span>
                  </div>
                </div>
              </td>
              <td class="px-8 py-5">
                @php 
                  $role_color = 'bg-gray-50 text-gray-500 border-gray-100';
                  if ($user->role === 'Admin') $role_color = 'bg-gray-800 text-white border-gray-800';
                  if ($user->role === 'Manager') $role_color = 'bg-pink-50 text-[#FB6F92] border-pink-100';
                @endphp
                <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider {{ $role_color }} border">
                    {{ $user->role }}
                </span>
              </td>
              <td class="px-8 py-5 text-gray-500 text-xs font-bold">
                {{ $user->department->dept_name ?? 'None' }}
              </td>
              <td class="px-8 py-5">
                @if ($user->account_status === 'Suspended')
                  <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[9px] font-black uppercase bg-red-50 text-red-500 border border-red-100">
                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> SUSPENDED
                  </span>
                @else
                  <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[9px] font-black uppercase bg-green-50 text-green-500 border border-green-100">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span> ACTIVE
                  </span>
                @endif
              </td>
              <td class="px-8 py-5 text-right">
                <div class="flex items-center justify-end gap-2">
                    <button 
                      data-id="{{ $user->user_id }}"
                      data-name="{{ $user->username }}"
                      data-role="{{ $user->role }}"
                      data-status="{{ $user->account_status }}"
                      data-reason="{{ $user->suspension_reason ?? '' }}"
                      data-dept-id="{{ $user->dept_id ?? '' }}"
                      onclick="openEditor(this.dataset.id, this.dataset.name, this.dataset.role, this.dataset.status, this.dataset.reason, this.dataset.deptId)" 
                      class="text-[#FB6F92] hover:bg-pink-50 p-2.5 rounded-xl transition-all border border-transparent hover:border-pink-100 shadow-sm bg-white" title="Edit">
                      <i data-lucide="edit-2" class="w-3.5 h-3.5"></i>
                    </button>
                    @if($user->user_id !== 'AD001' && $user->user_id !== auth()->id())
                      <button onclick="confirmDelete('{{ $user->user_id }}')" class="text-red-400 hover:bg-red-50 p-2.5 rounded-xl transition-all border border-transparent hover:border-red-100 shadow-sm bg-white" title="Delete">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                      </button>
                    @endif
                </div>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    <!-- Side panel: Quick Editor -->
    <div class="glass-card p-8 relative flex flex-col h-fit">
      <h3 class="font-extrabold text-[#1e293b] text-xl mb-8 flex items-center gap-2">
        <i data-lucide="shield" class="w-5 h-5 text-[#FB6F92]"></i> Access Control
      </h3>

      <div id="editor-placeholder" class="text-center py-16 text-gray-400 text-xs font-bold italic">
          Select a user from directory list to manage status or passwords.
      </div>

      <div id="editor-form-container" class="space-y-6 hidden">
        <form method="POST" id="edit-form" class="space-y-6">
            @csrf
            <div id="update-form-route-placeholder"></div>
            
            <div>
              <label class="text-[10px] font-black text-pink-300 uppercase tracking-widest ml-1">Selected User</label>
              <input id="edit_display_user" class="mt-2 w-full bg-[#FFF9FA] border-2 border-pink-50 rounded-2xl px-5 py-4 text-xs font-bold text-gray-700 outline-none" readonly />
            </div>

            <div>
              <label class="text-[10px] font-black text-pink-300 uppercase tracking-widest ml-1">System Role</label>
              <input type="text" id="edit_role" class="mt-2 w-full bg-gray-50 border border-gray-100 rounded-2xl px-5 py-4 text-xs font-bold text-gray-400 outline-none" readonly />
            </div>

            <div>
              <label class="text-[10px] font-black text-pink-300 uppercase tracking-widest ml-1">Account Status</label>
              <select name="account_status" id="edit_status" onchange="toggleSuspensionFields()" class="mt-2 w-full bg-[#FFF9FA] border-2 border-pink-50 rounded-2xl px-5 py-4 text-xs font-bold text-gray-700 outline-none focus:border-[#FB6F92] cursor-pointer transition-all">
                <option value="Active">Active</option>
                <option value="Suspended">Suspended</option>
              </select>
            </div>

            <div id="suspension_reason_container" class="hidden">
              <label class="text-[10px] font-black text-pink-300 uppercase tracking-widest ml-1">Suspension Reason</label>
              <select name="suspension_reason" id="suspension_reason" onchange="toggleCustomReason()" class="mt-2 w-full bg-[#FFF9FA] border-2 border-pink-50 rounded-2xl px-5 py-4 text-xs font-bold text-gray-700 outline-none focus:border-[#FB6F92] cursor-pointer transition-all">
                  <option value="">Select a reason...</option>
                  <option value="Security Violation">Security Violation</option>
                  <option value="Inactivity">Inactivity</option>
                  <option value="Pending Investigation">Pending Investigation</option>
                  <option value="Other">Other</option>
              </select>
            </div>

            <div id="custom_reason_container" class="hidden">
              <label class="text-[10px] font-black text-pink-300 uppercase tracking-widest ml-1">Custom Reason</label>
              <input type="text" name="custom_reason" id="custom_reason" class="mt-2 w-full bg-[#FFF9FA] border-2 border-pink-50 rounded-2xl px-5 py-4 text-xs font-bold text-gray-700 outline-none focus:border-[#FB6F92]" placeholder="Describe reason here...">
            </div>

            <div id="dept_container">
              <label class="text-[10px] font-black text-pink-300 uppercase tracking-widest ml-1">Department</label>
              <select name="dept_id" id="edit_dept_id" class="mt-2 w-full bg-[#FFF9FA] border-2 border-pink-50 rounded-2xl px-5 py-4 text-xs font-bold text-gray-700 outline-none focus:border-[#FB6F92] cursor-pointer transition-all">
                <option value="">None / Unassigned</option>
                @foreach ($departments as $d)
                  <option value="{{ $d->dept_id }}">{{ $d->dept_name }} (ID: {{ $d->dept_id }})</option>
                @endforeach
              </select>
            </div>

            <button type="submit" class="w-full pink-gradient text-white py-4 rounded-2xl font-extrabold shadow-lg shadow-pink-100 transition-all text-[10px] uppercase tracking-widest flex items-center justify-center gap-2 hover:scale-[1.01] transform duration-300">
              <i data-lucide="save" class="w-4 h-4"></i> Save Details
            </button>
        </form>

        <form method="POST" id="reset-form" class="pt-2">
            @csrf
            <div id="reset-form-route-placeholder"></div>
            <button type="button" onclick="confirmReset()" class="w-full py-3.5 border-2 border-pink-100 rounded-2xl text-[10px] font-bold text-[#FB6F92] hover:bg-pink-50/50 hover:border-[#FB6F92] transition-all uppercase tracking-widest shadow-sm">
              Reset Password
            </button>
        </form>
      </div>
    </div>
  </div>

  <form method="POST" id="delete-form" class="hidden">
      @csrf
      <div id="delete-form-route-placeholder"></div>
  </form>
@endsection

@section('scripts')
<script>
  // Filtering Logic
  const searchInput = document.getElementById('searchInput');
  const roleFilter = document.getElementById('roleFilter');
  const userRows = document.querySelectorAll('.user-row');

  function filterTable() {
      const searchTerm = searchInput.value.toLowerCase();
      const roleTerm = roleFilter.value;

      userRows.forEach(row => {
          const matchesSearch = row.dataset.search.includes(searchTerm);
          const matchesRole = roleTerm === '' || row.dataset.role === roleTerm;
          
          if (matchesSearch && matchesRole) {
              row.style.display = '';
          } else {
              row.style.display = 'none';
          }
      });
  }

  if (searchInput) searchInput.addEventListener('keyup', filterTable);
  if (roleFilter) roleFilter.addEventListener('change', filterTable);

  function openCreateModal() {
      Swal.fire({
          title: 'Create New User',
          html: `
              <form id="create-user-form" action="{{ route('admin.create_user') }}" method="POST" class="text-left space-y-4 mt-4">
                  @csrf
                  <div>
                      <label class="block text-[10px] font-black text-pink-300 uppercase tracking-widest mb-1">User ID</label>
                      <input type="text" name="new_user_id" class="w-full bg-[#FFF9FA] border border-pink-100 rounded-xl px-4 py-3 text-xs font-bold text-gray-700 outline-none focus:border-[#FB6F92]" placeholder="e.g. EM005" required>
                  </div>
                  <div>
                      <label class="block text-[10px] font-black text-pink-300 uppercase tracking-widest mb-1">Username</label>
                      <input type="text" name="new_username" class="w-full bg-[#FFF9FA] border border-pink-100 rounded-xl px-4 py-3 text-xs font-bold text-gray-700 outline-none focus:border-[#FB6F92]" placeholder="Full Name" required>
                  </div>
                  <div>
                      <label class="block text-[10px] font-black text-pink-300 uppercase tracking-widest mb-1">Email</label>
                      <input type="email" name="new_email" class="w-full bg-[#FFF9FA] border border-pink-100 rounded-xl px-4 py-3 text-xs font-bold text-gray-700 outline-none focus:border-[#FB6F92]" placeholder="name@company.com" required>
                  </div>
                  <div>
                      <label class="block text-[10px] font-black text-pink-300 uppercase tracking-widest mb-1">Role</label>
                      <select name="new_role" class="w-full bg-[#FFF9FA] border border-pink-100 rounded-xl px-4 py-3 text-xs font-bold text-gray-700 outline-none focus:border-[#FB6F92] cursor-pointer">
                          <option value="Employee">Employee</option>
                          <option value="Manager">Manager</option>
                          <option value="Admin">Admin</option>
                      </select>
                  </div>
                  <p class="text-[9px] text-[#FB6F92] font-black tracking-wider mt-2 italic">* Default password will be set to 'kyungsoo'</p>
              </form>
          `,
          showCancelButton: true,
          confirmButtonColor: '#FF8FAB',
          cancelButtonColor: '#94a3b8',
          confirmButtonText: 'Create Account',
          background: '#FFF9FA',
          customClass: {
              popup: 'rounded-[2.5rem] border-2 border-pink-100 p-6',
              title: 'font-black text-[#1e293b] font-outfit',
              confirmButton: 'rounded-xl px-6 py-3 font-bold',
              cancelButton: 'rounded-xl px-6 py-3 font-bold'
          },
          preConfirm: () => {
              const form = document.getElementById('create-user-form');
              if (form.checkValidity()) {
                  form.submit();
              } else {
                  Swal.showValidationMessage('Please fill out all required fields correctly.');
                  return false;
              }
          }
      });
  }

  function toggleSuspensionFields() {
      const status = document.getElementById('edit_status').value;
      const reasonContainer = document.getElementById('suspension_reason_container');
      if (status === 'Suspended') {
          reasonContainer.classList.remove('hidden');
      } else {
          reasonContainer.classList.add('hidden');
      }
      toggleCustomReason();
  }

  function toggleCustomReason() {
      const status = document.getElementById('edit_status').value;
      const reason = document.getElementById('suspension_reason').value;
      const customContainer = document.getElementById('custom_reason_container');
      
      if (status === 'Suspended' && reason === 'Other') {
          customContainer.classList.remove('hidden');
      } else {
          customContainer.classList.add('hidden');
      }
  }

  function openEditor(userId, username, role, status, reason, deptId) {
      document.getElementById('editor-placeholder').classList.add('hidden');
      document.getElementById('editor-form-container').classList.remove('hidden');

      // Set Route Actions dynamically
      let updateRoute = "{{ route('admin.update_user', ['user_id' => ':user_id']) }}".replace(':user_id', userId);
      document.getElementById('edit-form').setAttribute('action', updateRoute);
      
      let resetRoute = "{{ route('admin.reset_password', ['user_id' => ':user_id']) }}".replace(':user_id', userId);
      document.getElementById('reset-form').setAttribute('action', resetRoute);

      document.getElementById('edit_display_user').value = username + ' (' + userId + ')';
      document.getElementById('edit_role').value = role;
      
      const deptSelect = document.getElementById('edit_dept_id');
      const deptContainer = document.getElementById('dept_container');
      if (role === 'Admin') {
          deptSelect.value = '';
          deptContainer.classList.add('hidden');
      } else {
          deptSelect.value = deptId || '';
          deptContainer.classList.remove('hidden');
      }
      
      const statusSelect = document.getElementById('edit_status');
      if (status) {
          statusSelect.value = status;
      } else {
          statusSelect.value = 'Active';
      }

      const reasonSelect = document.getElementById('suspension_reason');
      const customInput = document.getElementById('custom_reason');
      reasonSelect.value = '';
      customInput.value = '';

      if (status === 'Suspended' && reason) {
          const predefined = Array.from(reasonSelect.options).map(opt => opt.value);
          if (predefined.includes(reason)) {
              reasonSelect.value = reason;
          } else {
              reasonSelect.value = 'Other';
              customInput.value = reason;
          }
      }

      toggleSuspensionFields();
  }

  function showUserDetails(id, name, email, role, status, reason, deptId, deptName) {
      let statusHtml = status === 'Suspended' 
          ? `<span class="bg-red-100 text-red-600 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-widest">SUSPENDED</span>`
          : `<span class="bg-green-100 text-green-600 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-widest">ACTIVE</span>`;
          
      let reasonHtml = (status === 'Suspended' && reason) 
          ? `<div class="mt-6 p-5 bg-red-50 rounded-2xl border border-red-100 text-left">
               <p class="text-[10px] font-black text-red-400 uppercase tracking-widest mb-1 flex items-center gap-2"><i data-lucide="alert-circle" class="w-3 h-3"></i> Suspension Reason</p>
               <p class="text-sm font-bold text-red-700 mt-2">${reason}</p>
             </div>`
          : '';

      fetch(`get-user-skills/${encodeURIComponent(id)}`)
          .then(res => res.json())
          .then(data => {
              let skillsHtml = '';
              if (data && data.length > 0) {
                  skillsHtml = `
                      <div class="bg-gray-50 p-5 rounded-3xl border border-gray-100 col-span-2 text-left">
                          <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 flex items-center gap-1">
                              <i data-lucide="award" class="w-3.5 h-3.5 text-[#FB6F92]"></i> Skills & Proficiency Levels
                          </p>
                          <div class="space-y-2 mt-2">
                  `;
                  data.forEach(skill => {
                      let stars = '';
                      for(let i=1; i<=5; i++) {
                          if (i <= skill.proficiency_level) {
                              stars += `<i data-lucide="star" class="w-3 h-3 fill-[#FB6F92] text-[#FB6F92]"></i>`;
                          } else {
                              stars += `<i data-lucide="star" class="w-3 h-3 text-gray-300"></i>`;
                          }
                      }
                      skillsHtml += `
                          <div class="flex items-center justify-between text-xs font-bold text-gray-700">
                              <span>${skill.skill_name}</span>
                              <div class="flex items-center gap-1">
                                  <span class="text-[10px] text-gray-400 mr-1">(Lvl ${skill.proficiency_level})</span>
                                  ${stars}
                              </div>
                          </div>
                      `;
                  });
                  skillsHtml += `
                          </div>
                      </div>
                  `;
              } else {
                  skillsHtml = `
                      <div class="bg-gray-50 p-5 rounded-3xl border border-gray-100 col-span-2 text-left">
                          <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 flex items-center gap-1">
                              <i data-lucide="award" class="w-3.5 h-3.5 text-[#FB6F92]"></i> Skills & Proficiency Levels
                          </p>
                          <p class="text-xs text-gray-400 italic">No skills registered for this user.</p>
                      </div>
                  `;
              }

              Swal.fire({
                  html: `
                      <div class="text-center pt-4">
                          <div class="w-24 h-24 bg-pink-100 text-[#FB6F92] rounded-full flex items-center justify-center font-black text-3xl mx-auto mb-5 uppercase shadow-inner border-4 border-white">
                              ${name.substring(0, 2)}
                          </div>
                          <h2 class="text-2xl font-black text-gray-800 tracking-tight">${name}</h2>
                          <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-8">${id}</p>
                          
                          <div class="grid grid-cols-2 gap-4 text-left mb-6 font-quicksand">
                              <div class="bg-gray-50 p-5 rounded-3xl border border-gray-100">
                                  <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Email Address</p>
                                  <p class="text-sm font-bold text-gray-700 break-all">${email || 'Not provided'}</p>
                              </div>
                              <div class="bg-gray-50 p-5 rounded-3xl border border-gray-100">
                                  <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Role</p>
                                  <p class="text-sm font-bold text-gray-700 uppercase">${role}</p>
                              </div>
                              <div class="bg-gray-50 p-5 rounded-3xl border border-gray-100">
                                  <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Department ID</p>
                                  <p class="text-sm font-bold text-gray-700 uppercase">${deptId ? deptId : 'None'}</p>
                              </div>
                              <div class="bg-gray-50 p-5 rounded-3xl border border-gray-100">
                                  <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Department Name</p>
                                  <p class="text-sm font-bold text-gray-700 uppercase">${deptName ? deptName : 'None'}</p>
                              </div>
                              ${skillsHtml}
                          </div>
                          
                          <div class="flex flex-col items-center justify-center gap-3">
                              <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Account Status</p>
                              ${statusHtml}
                          </div>
                          ${reasonHtml}
                      </div>
                  `,
                  showConfirmButton: true,
                  confirmButtonColor: '#FF8FAB',
                  confirmButtonText: 'Close Profile',
                  background: '#FFF9FA',
                  didOpen: () => {
                      lucide.createIcons();
                  },
                  customClass: {
                      popup: 'rounded-[2.5rem] border-2 border-pink-100',
                      confirmButton: 'rounded-2xl px-10 py-4 font-bold uppercase tracking-widest text-xs font-outfit'
                  }
              });
          })
          .catch(err => {
              console.error(err);
              Swal.fire({
                  icon: 'error',
                  title: 'Error',
                  text: 'Failed to fetch user skills.',
                  confirmButtonColor: '#FF8FAB'
              });
          });
  }

  function confirmDelete(userId) {
      Swal.fire({
          title: 'Delete User?',
          text: `Are you sure you want to delete ${userId}? This action cannot be undone.`,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#ef4444',
          cancelButtonColor: '#94a3b8',
          confirmButtonText: 'Yes, Delete',
          background: '#FFF9FA',
          customClass: {
              popup: 'rounded-[2.5rem] border-2 border-red-100',
              title: 'font-black text-[#1e293b]',
              confirmButton: 'rounded-xl px-6 py-3',
              cancelButton: 'rounded-xl px-6 py-3'
          }
      }).then((result) => {
          if (result.isConfirmed) {
              let deleteRoute = "{{ route('admin.delete_user', ['user_id' => ':user_id']) }}".replace(':user_id', userId);
              const form = document.getElementById('delete-form');
              form.setAttribute('action', deleteRoute);
              form.submit();
          }
      });
  }

  function confirmReset() {
      Swal.fire({
          title: 'Reset Password?',
          text: "This will reset the user's password to 'kyungsoo'. Proceed?",
          icon: 'question',
          showCancelButton: true,
          confirmButtonColor: '#f97316',
          cancelButtonColor: '#94a3b8',
          confirmButtonText: 'Yes, Reset',
          background: '#FFF9FA',
          customClass: {
              popup: 'rounded-[2.5rem] border-2 border-orange-100',
              title: 'font-black text-[#1e293b]',
              confirmButton: 'rounded-xl px-6 py-3',
              cancelButton: 'rounded-xl px-6 py-3'
          }
      }).then((result) => {
          if (result.isConfirmed) {
              document.getElementById('reset-form').submit();
          }
      });
  }
</script>
@endsection
