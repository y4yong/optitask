<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Task;
use App\Models\Department;
use App\Models\Skill;
use App\Models\Notification;
use App\Models\AuditLog;
use Illuminate\Support\Str;

class ManagerController extends Controller
{
    /**
     * Manager Dashboard.
     */
    public function dashboard(Request $request)
    {
        $userId = Auth::id();
        
        // Auto-assign tasks with no manager to logged-in manager for testing/seeding consistency
        Task::whereNull('manager_id')->orWhere('manager_id', '')->update(['manager_id' => $userId]);

        $departments = Department::orderBy('dept_name', 'asc')->get();

        // 1. Stats
        $totalTasks = Task::where('manager_id', $userId)->count();
        $totalEmployees = Task::where('manager_id', $userId)->distinct('employee_id')->count('employee_id');
        $totalVerified = Task::where('manager_id', $userId)->where('task_status', 'Verified')->count();

        // Tasks Lists
        $assignedTasks = Task::with('employee')
            ->where('manager_id', $userId)
            ->orderBy('due_date', 'desc')
            ->get();

        $verifiedTasks = Task::with('employee')
            ->where('manager_id', $userId)
            ->where('task_status', 'Verified')
            ->orderBy('due_date', 'desc')
            ->get();

        // 2. Workforce Monitoring Data
        $workforceEmployees = User::where('role', 'Employee')
            ->whereIn('user_id', function ($query) use ($userId) {
                $query->select('employee_id')->from('tasks')->where('manager_id', $userId);
            })
            ->limit(5)
            ->get();

        $workforceData = $workforceEmployees->map(function ($emp) use ($userId) {
            $completed = Task::where('employee_id', $emp->user_id)
                ->whereIn('task_status', ['Done', 'Verified'])
                ->where('manager_id', $userId)
                ->count();
            $total = Task::where('employee_id', $emp->user_id)
                ->where('manager_id', $userId)
                ->count();
            $percentage = ($total > 0) ? ($completed / $total) * 100 : 0;
            return [
                'user_id' => $emp->user_id,
                'username' => $emp->username,
                'completed' => $completed,
                'total_tasks' => $total,
                'performance_percentage' => $percentage
            ];
        });

        // 3. Leaderboard
        $selectedDept = $request->query('dept_filter', 'all');

        $leaderboardQuery = User::where('role', 'Employee')
            ->whereIn('user_id', function ($query) use ($userId) {
                $query->select('employee_id')->from('tasks')->where('manager_id', $userId);
            });

        if ($selectedDept !== 'all') {
            $leaderboardQuery->where('dept_id', $selectedDept);
        }

        $leaderboardRes = $leaderboardQuery->get();

        $rankedEmployees = $leaderboardRes->map(function ($emp) use ($userId) {
            $completed = Task::where('employee_id', $emp->user_id)
                ->whereIn('task_status', ['Done', 'Verified'])
                ->where('manager_id', $userId)
                ->count();
            $total = Task::where('employee_id', $emp->user_id)
                ->where('manager_id', $userId)
                ->count();
            $score = ($total > 0) ? ($completed / $total) * 100 : 0;
            return [
                'user_id' => $emp->user_id,
                'username' => $emp->username,
                'dept_name' => $emp->department->dept_name ?? 'None',
                'completed' => $completed,
                'total_tasks' => $total,
                'score' => $score
            ];
        })->sortByDesc('score')->values()->all();

        $top3 = array_slice($rankedEmployees, 0, 3);

        return view('manager.dashboard', compact(
            'totalTasks', 'totalEmployees', 'totalVerified', 'assignedTasks', 
            'verifiedTasks', 'workforceData', 'departments', 'selectedDept', 'rankedEmployees', 'top3'
        ));
    }

    /**
     * Show task assignment form.
     */
    public function assignTaskForm(Request $request)
    {
        $userId = Auth::id();
        $manager = Auth::user();
        
        $selectedDept = $request->query('dept_filter', $manager->dept_id ?? 1);
        
        $departments = Department::orderBy('dept_name', 'asc')->get();
        $skills = Skill::orderBy('skill_name', 'asc')->get();

        $employeesQuery = User::where('role', 'Employee');
        if ($selectedDept !== 'all') {
            $employeesQuery->where('dept_id', $selectedDept);
        }
        $employees = $employeesQuery->orderBy('username', 'asc')->get();

        return view('manager.assign_tasks', compact('departments', 'skills', 'employees', 'selectedDept'));
    }

    //Assign task to one or more employees.
    public function assignTask(Request $request)
    {
        $request->validate([
            'task_title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|string|in:Low,Medium,High',
            'deadline' => 'required|date|after_or_equal:today',
            'assignee' => 'required|array|min:1',
            'task_type' => 'required|string|in:Personal,Assigned',
            'task_file' => 'nullable|file|max:10240', // Max 10MB
            'manager_notes' => 'nullable|string',
        ]);

        $assignees = $request->input('assignee');
        $taskType = $request->input('task_type');

        if ($taskType === 'Personal' && count($assignees) > 1) {
            return back()->withErrors(['assignee' => 'Error: Personal tasks can only have 1 assignee!'])->withInput();
        }

        // Upload attachment to storage/app/public so it's accessible via storage symlink
        $targetFile = null;
        if ($request->hasFile('task_file')) {
            $file = $request->file('task_file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $targetFile = $file->storeAs('uploads/tasks', $filename, 'public');
        }

        $title = $request->input('task_title');
        $desc = $request->input('description');
        $priority = $request->input('priority');
        $dueDate = date('Y-m-d', strtotime($request->input('deadline')));
        $mgrNotes = $request->input('manager_notes');
        $userId = Auth::id();

        foreach ($assignees as $empId) {
            $taskId = 'TK-' . strtoupper(Str::random(6));

            // Create Task
            Task::create([
                'task_id' => $taskId,
                'task_title' => $title,
                'description' => $desc,
                'start_date' => now()->toDateString(),
                'due_date' => $dueDate,
                'task_status' => 'To-Do',
                'priority' => $priority,
                'employee_id' => $empId,
                'manager_notes' => $mgrNotes,
                'task_type' => $taskType,
                'task_file' => $targetFile,
                'manager_id' => $userId,
            ]);

            AuditLog::log($userId, 'ASSIGN_TASK', "Assigned task {$taskId} to {$empId}");

            // Create Database Notification
            $notifMsg = "You have been assigned a new task: '{$title}'. Due date: " . date('d-m-Y', strtotime($dueDate)) . ".";
            Notification::create([
                'user_id' => $empId,
                'notification_type' => 'Assignment',
                'message' => $notifMsg,
                'status' => 'unread'
            ]);

            // Email Notification (wrapped — SMTP failure must not crash the request)
            $emp = User::find($empId);
            if ($emp && $emp->email) {
                try {
                    $formattedDue = date('d-m-Y', strtotime($dueDate));
                    $emailContent = "<strong>Task:</strong> " . e($title) . "<br>" .
                                     "<strong>Assignee:</strong> " . e($emp->username) . " ({$empId})<br>" .
                                     "<strong>Due Date:</strong> {$formattedDue}<br><br>" .
                                     "<strong>Details:</strong> " . nl2br(e($desc ?? 'No instructions provided.'));

                    Mail::send('emails.notification', [
                        'to_name' => $emp->username,
                        'message_content' => $emailContent
                    ], function($message) use ($emp, $title) {
                        $message->to($emp->email)->subject("New Task Assigned: {$title}");
                    });
                    Log::info('Task assignment email sent successfully to ' . $emp->email);
                } catch (\Exception $e) {
                    Log::error('Task assignment email failed for ' . $empId . ' (' . $emp->email . '): ' . $e->getMessage());
                }
            } else {
                Log::warning('Task assignment email skipped for ' . $empId . ': user or email missing.');
            }
        }

        return redirect()->route('manager.dashboard')->with('success', "Successfully assigned to " . count($assignees) . " employees!");
    }

    /**
     * Show task verification form.
     */
    public function verifyTaskForm()
    {
        $userId = Auth::id();

        $tasks = Task::with('employee')
            ->where('manager_id', $userId)
            ->whereIn('task_status', ['Review', 'Done', 'Verified'])
            ->orderByRaw("CASE 
                WHEN task_status IN ('Review', 'Done') THEN 1 
                ELSE 2 
             END ASC")
            ->orderBy('due_date', 'asc')
            ->get();

        return view('manager.verify_tasks', compact('tasks'));
    }

    //Approve or reject a submitted task
    public function verifyTask(Request $request, $taskId, $action)
    {
        $task = Task::find($taskId);

        if (!$task) {
            return back()->with('error', 'Task not found.');
        }

        $request->validate([
            'manager_notes' => 'nullable|string',
        ]);

        $mgrNotes = $request->input('manager_notes');
        $userId = Auth::id();

        if ($action === 'approve') {
            $task->update([
                'task_status' => 'Verified',
                'manager_notes' => $mgrNotes
            ]);

            AuditLog::log($userId, 'VERIFY_TASK', "Approved submission for task {$taskId}");

            Notification::create([
                'user_id' => $task->employee_id,
                'notification_type' => 'Verification',
                'message' => "Your submission for task '#{$taskId} - {$task->task_title}' was APPROVED by the manager.",
                'status' => 'unread'
            ]);

            // Email employee (wrapped — SMTP failure must not crash the request)
            if ($task->employee && $task->employee->email) {
                try {
                    Mail::send('emails.notification', [
                        'to_name' => $task->employee->username,
                        'message_content' => "Your submission for task '#{$taskId} - {$task->task_title}' was <strong>APPROVED</strong> by the manager.<br>Notes: " . nl2br(e($mgrNotes))
                    ], function($message) use ($task) {
                        $message->to($task->employee->email)->subject("Task Approved: {$task->task_title}");
                    });
                } catch (\Exception $e) {
                    Log::error('Task approval email failed for task ' . $task->task_id . ': ' . $e->getMessage());
                }
            }

            return back()->with('success', "Task {$taskId} successfully approved.");
        } elseif ($action === 'reject') {
            $task->update([
                'task_status' => 'In Progress',
                'manager_notes' => $mgrNotes
            ]);

            AuditLog::log($userId, 'REJECT_TASK', "Rejected submission for task {$taskId}");

            Notification::create([
                'user_id' => $task->employee_id,
                'notification_type' => 'Verification',
                'message' => "Your submission for task '#{$taskId} - {$task->task_title}' was REJECTED. Please revise and resubmit.",
                'status' => 'unread'
            ]);

            // Email employee (wrapped — SMTP failure must not crash the request)
            if ($task->employee && $task->employee->email) {
                try {
                    Mail::send('emails.notification', [
                        'to_name' => $task->employee->username,
                        'message_content' => "Your submission for task '#{$taskId} - {$task->task_title}' was <strong>REJECTED</strong>. Please revise and resubmit.<br>Notes: " . nl2br(e($mgrNotes))
                    ], function($message) use ($task) {
                        $message->to($task->employee->email)->subject("Task Rejected: {$task->task_title}");
                    });
                } catch (\Exception $e) {
                    Log::error('Task rejection email failed for task ' . $task->task_id . ': ' . $e->getMessage());
                }
            }

            return back()->with('success', "Task {$taskId} rejected and set back to In Progress.");
        }

        return back()->with('error', 'Invalid verification action.');
    }

    /**
     * Suggest the best candidate for a task using skill matching and workload balance.
     */
    public function suggestCandidate(Request $request)
    {
        $skillId = (int)$request->query('skill_id', 0);
        $deptId = $request->query('dept_id', 'all');

        if ($skillId <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid skill ID']);
        }

        $query = User::where('role', 'Employee')
            ->whereHas('skills', function ($q) use ($skillId) {
                $q->where('skills.skill_id', $skillId);
            });

        if ($deptId !== 'all') {
            $query->where('dept_id', $deptId);
        }

        $candidates = $query->get();

        $scoredCandidates = $candidates->map(function ($candidate) use ($skillId) {
            $skillRelation = $candidate->skills()->where('skills.skill_id', $skillId)->first();
            $proficiency = $skillRelation ? $skillRelation->pivot->proficiency_level : 0;
            
            // active workload counts tasks NOT Done and NOT Verified
            $activeWorkload = Task::where('employee_id', $candidate->user_id)
                ->whereNotIn('task_status', ['Done', 'Verified'])
                ->count();

            return [
                'user_id' => $candidate->user_id,
                'username' => $candidate->username,
                'proficiency_level' => $proficiency,
                'active_tasks' => $activeWorkload
            ];
        });

        // Sort by proficiency DESC, then active workload ASC
        $sorted = $scoredCandidates->sort(function ($a, $b) {
            if ($a['proficiency_level'] === $b['proficiency_level']) {
                return $a['active_tasks'] <=> $b['active_tasks'];
            }
            return $b['proficiency_level'] <=> $a['proficiency_level'];
        })->values();

        if ($sorted->isNotEmpty()) {
            $candidate = $sorted->first();
            $profLabels = [1 => 'Beginner', 2 => 'Novice', 3 => 'Intermediate', 4 => 'Advanced', 5 => 'Expert'];
            $profText = $profLabels[$candidate['proficiency_level']] ?? 'Unknown';

            return response()->json([
                'success' => true,
                'user_id' => $candidate['user_id'],
                'username' => $candidate['username'],
                'proficiency' => $candidate['proficiency_level'],
                'reason' => "Selected for {$profText} proficiency (Level {$candidate['proficiency_level']}) with only {$candidate['active_tasks']} active tasks."
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No matching candidates found for this skill in the selected department.'
        ]);
    }

    /**
     * View notifications for the manager.
     */
    public function notifications()
    {
        $userId = Auth::id();
        $notifications = Notification::where('user_id', $userId)
            ->orderBy('timestamp', 'desc')
            ->get();

        // Mark all as read
        Notification::where('user_id', $userId)->update(['status' => 'read']);

        return view('manager.notification', compact('notifications'));
    }
}
