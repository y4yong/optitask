<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\Department;

class AdminController extends Controller
{
    /**
     * Display the Admin Dashboard.
     */
    public function dashboard()
    {
        $totalTasks = \App\Models\Task::count();
        $completedTasks = \App\Models\Task::whereIn('task_status', ['Done', 'Verified'])->count();
        $pendingTasks = \App\Models\Task::where('task_status', 'Review')->count();
        $totalUsers = User::count();
        $countEmp = User::where('role', 'Employee')->count();
        $countMgr = User::where('role', 'Manager')->count();
        $countAdmin = User::where('role', 'Admin')->count();
        $activeAccts = User::where('account_status', 'Active')->count();
        $suspendedAccts = User::where('account_status', 'Suspended')->count();
        
        $recentLogs = AuditLog::leftJoin('users', 'audit_logs.user_id', '=', 'users.user_id')
            ->select('audit_logs.*', 'users.username')
            ->orderBy('audit_logs.timestamp', 'desc')
            ->limit(5)
            ->get();

        $recentTasks = \App\Models\Task::with('employee')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Chart Data: Task Status Distribution
        $statusRes = \App\Models\Task::select('task_status', \DB::raw('count(*) as count'))
            ->groupBy('task_status')
            ->get();
        $statusLabels = $statusRes->pluck('task_status');
        $statusCounts = $statusRes->pluck('count');

        // Chart Data: Department Productivity
        $deptRes = Department::leftJoin('users', 'departments.dept_id', '=', 'users.dept_id')
            ->leftJoin('tasks', function($join) {
                $join->on('users.user_id', '=', 'tasks.employee_id')
                     ->whereIn('tasks.task_status', ['Done', 'Verified']);
            })
            ->select('departments.dept_name', \DB::raw('count(tasks.task_id) as completed'))
            ->groupBy('departments.dept_id', 'departments.dept_name')
            ->get();
        $deptLabels = $deptRes->pluck('dept_name');
        $deptCounts = $deptRes->pluck('completed');

        return view('admin.dashboard', compact(
            'totalTasks', 'completedTasks', 'pendingTasks', 'totalUsers', 
            'countEmp', 'countMgr', 'countAdmin', 'activeAccts', 'suspendedAccts', 
            'recentLogs', 'recentTasks', 'statusLabels', 'statusCounts', 'deptLabels', 'deptCounts'
        ));
    }

    /**
     * Display User Management panel.
     */
    public function manageUsers()
    {
        $users = User::with('department')->get();
        $departments = Department::all();

        return view('admin.manage_users', compact('users', 'departments'));
    }

    /**
     * Toggle status (Suspend/Activate) of a specific user.
     */
    public function toggleUserStatus(Request $request, $userId)
    {
        $user = User::find($userId);

        if (!$user) {
            return back()->with('error', 'User not found.');
        }

        if ($user->user_id === 'AD001') {
            return back()->with('error', 'Critical Error: System Administrator (AD001) cannot be suspended.');
        }

        if ($user->account_status === 'Active') {
            $reason = $request->input('suspension_reason', 'No reason provided');
            $user->update([
                'account_status' => 'Suspended',
                'suspension_reason' => $reason
            ]);
            AuditLog::log(auth()->id(), 'SUSPEND_USER', "Suspended user ID {$userId}. Reason: {$reason}");
            return back()->with('success', "User {$userId} has been suspended.");
        } else {
            $user->update([
                'account_status' => 'Active',
                'suspension_reason' => null
            ]);
            AuditLog::log(auth()->id(), 'ACTIVATE_USER', "Activated user ID {$userId}");
            return back()->with('success', "User {$userId} has been activated.");
        }
    }

    /**
     * Display System Audit Logs.
     */
    public function auditLogs(Request $request)
    {
        $dateFilter = $request->query('date');
        
        // Default to today's date on first page load
        if (!$request->has('date')) {
            $dateFilter = date('Y-m-d');
        }

        $query = AuditLog::leftJoin('users', 'audit_logs.user_id', '=', 'users.user_id')
            ->select('audit_logs.*', 'users.username', 'users.role')
            ->orderBy('audit_logs.timestamp', 'desc');

        if ($dateFilter && $dateFilter !== 'all') {
            $query->whereDate('audit_logs.timestamp', $dateFilter);
        }

        // Get matching collection for stats computation before pagination
        $allLogs = $query->get();

        $totalLogs = $allLogs->count();
        
        $uniqueActors = $allLogs->map(function ($log) {
            return $log->username ?: $log->user_id ?: 'System';
        })->filter(function ($actor) {
            return $actor !== 'System';
        })->unique();
        $totalActors = $uniqueActors->count();

        $alertCount = $allLogs->filter(function ($log) {
            $act = strtoupper($log->action);
            return str_contains($act, 'DELETE') || str_contains($act, 'RESET') || str_contains($act, 'SUSPEND');
        })->count();

        $loginCount = $allLogs->filter(function ($log) {
            return strtoupper($log->action) === 'LOGIN';
        })->count();

        $recentLogs = $allLogs->take(5);

        // Paginate the query
        $logs = $query->paginate(15)->withQueryString();

        return view('admin.audit', compact(
            'logs', 'dateFilter', 'totalLogs', 'totalActors', 'alertCount', 'loginCount', 'recentLogs'
        ));
    }

    /**
     * Create a new user account.
     */
    public function createUser(Request $request)
    {
        $request->validate([
            'new_user_id' => 'required|string|unique:users,user_id',
            'new_username' => 'required|string|max:100',
            'new_email' => 'required|email|max:100|unique:users,email',
            'new_role' => 'required|string|in:Admin,Manager,Employee',
        ]);

        $userId = strtoupper($request->input('new_user_id'));
        $username = $request->input('new_username');
        $email = $request->input('new_email');
        $role = $request->input('new_role');
        $passwordHash = \Illuminate\Support\Facades\Hash::make('kyungsoo');

        User::create([
            'user_id' => $userId,
            'username' => $username,
            'email' => $email,
            'password' => $passwordHash,
            'role' => $role,
            'account_status' => 'Active',
            'dept_id' => $role === 'Admin' ? null : 1, // Default to IT Department for non-admins
        ]);

        AuditLog::log(auth()->id(), 'CREATE_USER', "Created user {$userId} with role {$role}");

        return back()->with('success', "User {$userId} created successfully (Default password: 'kyungsoo').");
    }

    /**
     * Update user details (Status, Reason, Department).
     */
    public function updateUser(Request $request, $userId)
    {
        $user = User::find($userId);

        if (!$user) {
            return back()->with('error', 'User not found.');
        }

        $accountStatus = $request->input('account_status', 'Active');
        $suspensionReason = null;
        
        if ($accountStatus === 'Suspended') {
            $suspensionReason = $request->input('suspension_reason');
            if ($suspensionReason === 'Other') {
                $suspensionReason = $request->input('custom_reason');
            }
        }

        $deptId = ($user->role === 'Admin') ? null : $request->input('dept_id');

        $user->update([
            'account_status' => $accountStatus,
            'suspension_reason' => $suspensionReason,
            'dept_id' => $deptId
        ]);

        AuditLog::log(auth()->id(), 'UPDATE_USER_PROFILE', "Updated details and department of {$userId}");

        return back()->with('success', 'User details updated successfully.');
    }

    /**
     * Delete user account.
     */
    public function deleteUser($userId)
    {
        $user = User::find($userId);

        if (!$user) {
            return back()->with('error', 'User not found.');
        }

        if ($user->user_id === 'AD001' || $user->user_id === auth()->id()) {
            return back()->with('error', 'Critical Error: Cannot delete this user.');
        }

        $user->delete();

        AuditLog::log(auth()->id(), 'DELETE_USER', "Deleted user {$userId}");

        return back()->with('success', "User {$userId} has been deleted.");
    }

    /**
     * Reset user password to default 'kyungsoo'.
     */
    public function resetPassword($userId)
    {
        $user = User::find($userId);

        if (!$user) {
            return back()->with('error', 'User not found.');
        }

        $user->update([
            'password' => \Illuminate\Support\Facades\Hash::make('kyungsoo')
        ]);

        AuditLog::log(auth()->id(), 'RESET_PASSWORD', "Reset password for user {$userId}");

        return back()->with('success', "Password reset to 'kyungsoo' successfully.");
    }

    /**
     * Fetch user's skills for AJAX display.
     */
    public function getUserSkills($userId)
    {
        $user = User::with('skills')->find($userId);

        if (!$user) {
            return response()->json([], 404);
        }

        $skills = $user->skills->map(function ($skill) {
            return [
                'skill_name' => $skill->skill_name,
                'proficiency_level' => $skill->pivot->proficiency_level
            ];
        });

        return response()->json($skills);
    }
}
