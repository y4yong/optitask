<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Task;
use App\Models\Department;
use App\Models\Skill;
use App\Models\Notification;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    /**
     * Employee Dashboard.
     */
    public function dashboard()
    {
        $userId = Auth::id();
        $user = Auth::user();

        // Check for Unread Notifications
        $unreadCount = Notification::where('user_id', $userId)->where('status', 'unread')->count();

        $allTasks = Task::where('employee_id', $userId)->get();
        $totalT = $allTasks->count();
        $doneT = $allTasks->filter(function($t) {
            return in_array($t->task_status, ['Done', 'Verified']);
        })->count();
        $performance = ($totalT > 0) ? ($doneT / $totalT) * 100 : 0;

        $activeTasks = Task::where('employee_id', $userId)
            ->whereIn('task_status', ['To-Do', 'In Progress', 'Done'])
            ->orderByRaw("CASE 
                WHEN task_status = 'In Progress' THEN 1 
                WHEN task_status = 'To-Do' THEN 2 
                ELSE 3 
             END ASC")
            ->orderBy('due_date', 'asc')
            ->get();

        $verifiedTasks = Task::where('employee_id', $userId)
            ->where('task_status', 'Verified')
            ->orderBy('due_date', 'desc')
            ->get();

        return view('employee.dashboard', compact('unreadCount', 'performance', 'activeTasks', 'verifiedTasks'));
    }

    /**
     * My Tasks Inventory.
     */
    public function tasks()
    {
        $userId = Auth::id();
        $user = Auth::user();

        $unreadCount = Notification::where('user_id', $userId)->where('status', 'unread')->count();

        $allTasks = Task::where('employee_id', $userId)->orderBy('due_date', 'asc')->get();

        $tasksArray = [];
        $counts = ['todo' => 0, 'progress' => 0, 'done' => 0, 'verified' => 0];

        foreach ($allTasks as $row) {
            $raw = $row->task_status;
            $statusKey = 'todo';
            if ($raw === 'In Progress') { 
                $statusKey = 'inprogress'; 
                $counts['progress']++; 
            } elseif ($raw === 'Done' || $raw === 'Review') { 
                $statusKey = 'done'; 
                $counts['done']++; 
            } elseif ($raw === 'Verified') { 
                $statusKey = 'verified'; 
                $counts['verified']++; 
            } else { 
                $counts['todo']++; 
            }

            $tasksArray[] = [
                'id'       => $row->task_id,
                'title'    => $row->task_title,
                'due'      => $row->due_date,
                'due_display' => date('d M Y', strtotime($row->due_date)),
                'status'   => $statusKey, 
                'raw_status' => $raw,
                'priority' => strtoupper($row->priority),
                'desc'     => $row->description,
                'notes'    => $row->manager_notes ?? '',
                'task_file' => $row->task_file_url ?? '',
                'submission_file' => $row->submission_file_url ?? '',
                'submission_file_name' => $row->submission_file ? basename($row->submission_file) : '',
                'evidence_link' => $row->evidence_link ?? '',
            ];
        }

        return view('employee.tasks', compact('unreadCount', 'tasksArray', 'counts'));
    }

    /**
     * Start a Task.
     */
    public function startTask(Request $request)
    {
        $request->validate([
            'task_id' => 'required|string|exists:tasks,task_id'
        ]);

        $tid = $request->input('task_id');
        $userId = Auth::id();

        $task = Task::where('task_id', $tid)->where('employee_id', $userId)->first();

        if ($task && $task->task_status === 'To-Do') {
            $task->update(['task_status' => 'In Progress']);
            AuditLog::log($userId, 'START_TASK', "Started task ID {$tid}");
            
            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Task started.']);
            }
            return back()->with('success', 'Task started.');
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => false, 'message' => 'Task cannot be started.']);
        }
        return back()->with('error', 'Task cannot be started.');
    }

    /**
     * Submit work for verification.
     */
    public function submitWork(Request $request)
    {
        $request->validate([
            'task_id' => 'required|string|exists:tasks,task_id',
            'attachment' => 'nullable|file|max:10240',
            'evidence_link' => 'nullable|url|max:255'
        ]);

        $tid = $request->input('task_id');
        $evidence = $request->input('evidence_link');
        $userId = Auth::id();
        $user = Auth::user();

        if (!$request->hasFile('attachment') && !$evidence) {
            return back()->with('error', 'Error: Please provide either a file upload or evidence link.');
        }

        $task = Task::where('task_id', $tid)->where('employee_id', $userId)->first();

        if (!$task || !in_array($task->task_status, ['To-Do', 'In Progress', 'Review'])) {
            return back()->with('error', 'Task submission is invalid.');
        }

        $path = $task->submission_file;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('uploads/submissions', $filename, 'public');
            if ($path) {
                @mkdir(public_path('uploads/submissions'), 0777, true);
                @copy(storage_path('app/public/' . $path), public_path($path));
            }
        }

        $task->update([
            'task_status' => 'Review', // Pending verification
            'submission_file' => $path,
            'evidence_link' => $evidence ?: $task->evidence_link
        ]);

        $logMsg = "Submitted task ID {$tid}";
        if ($path) $logMsg .= " with attachment: " . basename($path);
        if ($evidence) $logMsg .= " with evidence: {$evidence}";
        AuditLog::log($userId, 'SUBMIT_TASK', $logMsg);

        // 1. Notify Manager
        $managerId = $task->manager_id;
        $notifMsg = "Employee {$user->username} ({$userId}) has submitted task '{$task->task_title}' (#{$tid}) for verification.";

        if (!$managerId) {
            $managers = User::where('role', 'Manager')->where('dept_id', $user->dept_id)->get();
            if ($managers->isEmpty()) {
                $managers = User::where('role', 'Manager')->get();
            }
        } else {
            $managers = User::where('user_id', $managerId)->get();
        }

        foreach ($managers as $mgr) {
            Notification::create([
                'user_id' => $mgr->user_id,
                'notification_type' => 'Submission',
                'message' => $notifMsg,
                'status' => 'unread'
            ]);

            // Send SMTP mail to Manager (wrapped — SMTP failure must not crash the request)
            if ($mgr->email) {
                try {
                    $emailContent = "<strong>Task:</strong> " . e($task->task_title) . " (#{$tid})<br>" .
                                     "<strong>Submitted By:</strong> " . e($user->username) . " ({$userId})<br>";
                    if ($path) {
                        $emailContent .= "<strong>Attached File:</strong> " . e(basename($path)) . "<br>";
                    }
                    if ($evidence) {
                        $emailContent .= "<strong>Evidence Link:</strong> <a href='" . e($evidence) . "'>" . e($evidence) . "</a><br>";
                    }
                    $emailContent .= "<br><strong>Details:</strong> The employee has completed the task and submitted it for verification.";

                    Mail::send('emails.notification', [
                        'to_name' => $mgr->username,
                        'message_content' => $emailContent
                    ], function($message) use ($mgr, $task) {
                        $message->to($mgr->email)->subject("Task Submission: {$task->task_title}");
                    });
                } catch (\Exception $e) {
                    Log::error('Task submission email failed for manager ' . $mgr->user_id . ': ' . $e->getMessage());
                }
            }
        }

        return back()->with('success', 'Work submitted successfully.');
    }

    /**
     * Profile & Skills Inventory.
     */
    public function skills()
    {
        $userId = Auth::id();
        $user = Auth::user();

        $unreadCount = Notification::where('user_id', $userId)->where('status', 'unread')->count();

        // Fetch current department info
        $myDeptName = $user->department->dept_name ?? 'None';
        
        $mySkills = DB::table('employee_skills')
            ->join('skills', 'employee_skills.skill_id', '=', 'skills.skill_id')
            ->where('employee_skills.user_id', $userId)
            ->orderBy('skills.skill_name', 'asc')
            ->select('skills.skill_id', 'skills.skill_name', 'employee_skills.proficiency_level')
            ->get();

        $allSkills = Skill::orderBy('skill_name', 'asc')->get();
        $allDepts = Department::orderBy('dept_name', 'asc')->get();

        $profLabels = [
            1 => 'Beginner (Level 1)',
            2 => 'Novice (Level 2)',
            3 => 'Intermediate (Level 3)',
            4 => 'Advanced (Level 4)',
            5 => 'Expert (Level 5)'
        ];

        return view('employee.skills', compact('unreadCount', 'mySkills', 'allSkills', 'allDepts', 'profLabels', 'user'));
    }

    /**
     * Save/Update Skill.
     */
    public function saveSkill(Request $request)
    {
        $request->validate([
            'skill_id' => 'required|integer|exists:skills,skill_id',
            'proficiency_level' => 'required|integer|min:1|max:5'
        ]);

        $userId = Auth::id();
        $skillId = $request->input('skill_id');
        $level = $request->input('proficiency_level');

        DB::table('employee_skills')->updateOrInsert(
            ['user_id' => $userId, 'skill_id' => $skillId],
            ['proficiency_level' => $level]
        );

        AuditLog::log($userId, 'UPDATE_SKILL', "Saved skill ID {$skillId} with level {$level}");

        return back()->with('success', 'Skill saved successfully.');
    }

    /**
     * Remove Skill.
     */
    public function deleteSkill(Request $request)
    {
        $request->validate([
            'skill_id' => 'required|integer'
        ]);

        $userId = Auth::id();
        $skillId = $request->input('skill_id');

        DB::table('employee_skills')
            ->where('user_id', $userId)
            ->where('skill_id', $skillId)
            ->delete();

        AuditLog::log($userId, 'DELETE_SKILL', "Deleted skill ID {$skillId}");

        return back()->with('success', 'Skill removed successfully.');
    }

    /**
     * Save Department (one-time selection).
     */
    public function saveDepartment(Request $request)
    {
        $request->validate([
            'dept_id' => 'required|integer|exists:departments,dept_id'
        ]);

        $user = Auth::user();
        $userId = Auth::id();

        if ($user->dept_id) {
            return back()->with('error', 'Error: You have already selected a department. Only Admin can update it.');
        }

        $deptId = $request->input('dept_id');
        $user->update(['dept_id' => $deptId]);

        AuditLog::log($userId, 'UPDATE_DEPARTMENT', "Updated department to ID {$deptId}");

        return back()->with('success', 'Department saved successfully.');
    }

    /**
     * Update User Email (maximum 3 attempts).
     */
    public function updateEmail(Request $request)
    {
        $user = Auth::user();
        $userId = Auth::id();

        if ($user->email_updates_remaining <= 0) {
            return back()->with('error', 'Error: You have no email update attempts remaining.');
        }

        $request->validate([
            'email' => 'required|email|max:100|unique:users,email,' . $userId . ',user_id'
        ]);

        $newEmail = $request->input('email');

        if ($newEmail === $user->email) {
            return back()->with('success', 'Email was not changed.');
        }

        $oldEmail = $user->email;
        
        DB::transaction(function() use ($user, $newEmail, $userId, $oldEmail) {
            // Decrement remaining attempts
            $remaining = $user->email_updates_remaining - 1;
            
            $user->update([
                'email' => $newEmail,
                'email_updates_remaining' => $remaining
            ]);
            
            AuditLog::log($userId, 'UPDATE_EMAIL', "Changed email from {$oldEmail} to {$newEmail}. Remaining attempts: {$remaining}");
        });

        return back()->with('success', 'Email address updated successfully. Attempts remaining: ' . $user->email_updates_remaining);
    }

    /**
     * Employee Performance stats & insights.
     */
    public function performance()
    {
        $userId = Auth::id();
        $unreadCount = Notification::where('user_id', $userId)->where('status', 'unread')->count();

        // Compute performance percentage
        $allTasks = Task::where('employee_id', $userId)->get();
        $totalTasks = $allTasks->count();
        $completedTasks = $allTasks->filter(function($t) {
            return in_array($t->task_status, ['Done', 'Review', 'Verified']);
        })->count();
        $performance = ($totalTasks > 0) ? ($completedTasks / $totalTasks) * 100 : 0;

        // Map tasks array for the AI helper and table breakdown
        $tasksArray = $allTasks->map(function($t) {
            return [
                'task_id' => $t->task_id,
                'task_title' => $t->task_title,
                'task_status' => $t->task_status,
                'priority' => $t->priority,
                'due_date' => $t->due_date
            ];
        })->all();

        $insights = $this->getPerformanceInsights($tasksArray, $performance);

        return view('employee.performance', compact(
            'unreadCount', 'performance', 'completedTasks', 'totalTasks', 'tasksArray', 'insights'
        ));
    }

    /**
     * Notifications feed for Employee.
     */
    public function notifications()
    {
        $userId = Auth::id();
        $notifications = Notification::where('user_id', $userId)
            ->orderBy('timestamp', 'desc')
            ->get();

        // Mark all as read
        Notification::where('user_id', $userId)->update(['status' => 'read']);

        return view('employee.notification', compact('notifications'));
    }

    /**
     * AI Coach API AJAX assistant endpoint.
     */
    public function aiCoach(Request $request)
    {
        $request->validate([
            'action' => 'required|string|in:audit,deconstruct',
            'task_title' => 'nullable|string'
        ]);

        $userId = Auth::id();
        $action = $request->input('action');

        if ($action === 'audit') {
            $allTasks = Task::where('employee_id', $userId)->get();
            $totalT = $allTasks->count();
            $doneT = $allTasks->filter(function($t) {
                return in_array($t->task_status, ['Done', 'Verified']);
            })->count();
            $performance = ($totalT > 0) ? ($doneT / $totalT) * 100 : 0;

            $tasksData = $allTasks->map(function($t) {
                return [
                    'task_id' => $t->task_id,
                    'task_title' => $t->task_title,
                    'task_status' => $t->task_status,
                    'priority' => $t->priority,
                    'due_date' => $t->due_date
                ];
            })->all();

            $insights = $this->getPerformanceInsights($tasksData, $performance);

            // Output structured HTML representing audit insights
            $tipsHtml = '';
            foreach ($insights['actionable_tips'] as $tip) {
                $tipsHtml .= "<li class='text-xs font-semibold text-gray-600 mb-1.5 flex items-start gap-2'><span class='text-pink-500 font-bold'>&bull;</span> <span>{$tip}</span></li>";
            }

            return response()->json([
                'success' => true,
                'html' => "
                    <div class='space-y-6 text-left'>
                        <div class='bg-pink-50/20 p-5 rounded-2xl border border-pink-100/50'>
                            <p class='text-[10px] font-black text-pink-400 uppercase tracking-wider mb-2 flex justify-between'>
                                <span>Velocity Prediction</span>
                                <span class='text-gray-400 font-normal'>Source: {$insights['source']}</span>
                            </p>
                            <p class='text-xs font-semibold text-gray-700 leading-relaxed'>{$insights['completion_rate_prediction']}</p>
                        </div>
                        <div class='bg-rose-50/30 p-5 rounded-2xl border border-rose-100/40'>
                            <p class='text-[10px] font-black text-rose-400 uppercase tracking-wider mb-2'>Active Pipeline Audit</p>
                            <p class='text-xs font-semibold text-gray-700 leading-relaxed'>{$insights['bottleneck_warning']}</p>
                        </div>
                        <div class='bg-emerald-50/20 p-5 rounded-2xl border border-emerald-100/50'>
                            <p class='text-[10px] font-black text-emerald-500 uppercase tracking-wider mb-3'>Actionable Optimization Tips</p>
                            <ul class='list-none pl-0'>{$tipsHtml}</ul>
                        </div>
                    </div>
                "
            ]);

        } elseif ($action === 'deconstruct') {
            $taskTitle = $request->input('task_title');
            if (empty($taskTitle)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select a valid task to deconstruct.'
                ]);
            }

            $html = $this->getTaskDeconstruction($taskTitle);

            return response()->json([
                'success' => true,
                'html' => $html
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Invalid action']);
    }

    /**
     * Gemini integration
     */
    private function getPerformanceInsights($tasks, $completionRate)
    {
        $apiKey = env('GEMINI_API_KEY');

        if (empty($apiKey)) {
            return $this->generateLocalInsights($tasks, $completionRate);
        }

        $taskSummaries = [];
        foreach ($tasks as $t) {
            $taskSummaries[] = sprintf(
                "- ID: %s, Title: %s, Status: %s, Priority: %s, Due Date: %s",
                $t['task_id'],
                $t['task_title'],
                $t['task_status'],
                $t['priority'],
                $t['due_date']
            );
        }
        $tasksStr = implode("\n", $taskSummaries);

        $prompt = "You are an AI Executive Performance Coach integrated into OptiTask, a task management system.
Analyze the following employee task list and historical performance data to generate hyper-personalized coaching insights:

Employee Completion Rate: {$completionRate}%

Active and Historical Tasks:
{$tasksStr}

Please generate the following three insights in JSON format:
1. \"completion_rate_prediction\": A proactive, prediction-based analysis of the employee's completion rate and likelihood of meeting deadlines based on their past velocity. Mention specific task IDs or deadlines. Keep it professional, encouraging but direct.
2. \"bottleneck_warning\": A warning identifying potential bottlenecks (e.g. overdue tasks, too many \"In Progress\" tasks, high priority overload, or lack of updates). Be specific about what is blocking progress.
3. \"actionable_tips\": An array of exactly 3 specific, actionable performance tips. For example, suggesting they prioritize a specific task, deconstruct a large High priority task, or request manager assistance.

Your response MUST be raw JSON. Do not include markdown code block syntax (like ```json ... ```). Output only the JSON object.
JSON Schema:
{
  \"completion_rate_prediction\": \"string\",
  \"bottleneck_warning\": \"string\",
  \"actionable_tips\": [
    \"string\",
    \"string\",
    \"string\"
  ]
}";

        try {
            $response = Http::timeout(8)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey, [
                    "contents" => [
                        [
                            "parts" => [
                                ["text" => $prompt]
                            ]
                        ]
                    ],
                    "generationConfig" => [
                        "responseMimeType" => "application/json"
                    ]
                ]);

            if ($response->successful()) {
                $result = $response->json();
                if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                    $jsonText = trim($result['candidates'][0]['content']['parts'][0]['text']);
                    $insights = json_decode($jsonText, true);
                    if ($insights && isset($insights['completion_rate_prediction']) && isset($insights['bottleneck_warning']) && isset($insights['actionable_tips'])) {
                        $insights['source'] = 'Gemini Live AI';
                        return $insights;
                    }
                }
            }
        } catch (\Exception $e) {
            // Log it if needed
        }

        $insights = $this->generateLocalInsights($tasks, $completionRate);
        $insights['source'] = 'OptiTask Analytics (Fallback)';
        return $insights;
    }

    private function generateLocalInsights($tasks, $completionRate)
    {
        $todo = 0;
        $inProgress = 0;
        $done = 0;
        $verified = 0;
        $overdueTasks = [];
        $urgentTasks = [];
        $highPriorityActive = [];
        
        $today = date('Y-m-d');
        $threeDaysLater = date('Y-m-d', strtotime('+3 days'));

        foreach ($tasks as $t) {
            $status = $t['task_status'];
            $priority = $t['priority'];
            $due = $t['due_date'];
            
            if ($status === 'To-Do') $todo++;
            elseif ($status === 'In Progress') $inProgress++;
            elseif ($status === 'Review' || $status === 'Done') $done++;
            elseif ($status === 'Verified') $verified++;

            $isActive = in_array($status, ['To-Do', 'In Progress']);
            if ($isActive) {
                if ($due < $today) {
                    $overdueTasks[] = $t;
                } elseif ($due <= $threeDaysLater) {
                    $urgentTasks[] = $t;
                }
                
                if ($priority === 'HIGH' || $priority === 'High') {
                    $highPriorityActive[] = $t;
                }
            }
        }

        if ($completionRate >= 80) {
            $prediction = "Excellent velocity! Based on your current " . number_format($completionRate, 1) . "% completion rate, you are on track to maintain an A+ badge. You have a 92% probability of hitting all active targets before their deadlines if your current rhythm is maintained.";
        } elseif ($completionRate >= 60) {
            $prediction = "Solid progress. Your current completion rate is " . number_format($completionRate, 1) . "%. You have a 70% probability of achieving an A+ badge by the end of this cycle. Focus on converting your {$inProgress} 'In Progress' task(s) into completed submissions to secure the boost.";
        } else {
            $prediction = "Attention required. With a current completion rate of " . number_format($completionRate, 1) . "%, you have a 45% chance of missing performance targets. Proactive completion of active items is critical to push your metrics back into the A+ zone.";
        }

        if (!empty($overdueTasks)) {
            $firstOverdue = $overdueTasks[0]['task_title'];
            $countOverdue = count($overdueTasks);
            $bottleneck = "Critical Bottleneck: You have {$countOverdue} overdue task(s) outstanding (e.g. '{$firstOverdue}'). These overdue items represent immediate blockages. Clear these first before starting any new assignments.";
        } elseif ($inProgress > 2) {
            $bottleneck = "Multitasking Bottleneck: You currently have {$inProgress} tasks marked as 'In Progress' simultaneously. Spreading your focus thin increases cognitive overhead and risks delaying multiple due dates. Concentrate on completing one task to 100% first.";
        } elseif (!empty($highPriorityActive)) {
            $firstHigh = $highPriorityActive[0]['task_title'];
            $bottleneck = "Priority Concentration Warning: You have " . count($highPriorityActive) . " High-Priority task(s) active (e.g. '{$firstHigh}'). Ensure these receive your primary morning focus to prevent late-stage delays.";
        } else {
            $bottleneck = "Healthy Pipeline: Excellent workflow allocation. You have 0 overdue tasks and no structural bottlenecks detected. Continue maintaining this healthy balance.";
        }

        $tips = [];
        if (!empty($overdueTasks)) {
            $tips[] = "Prioritize Overdue Task: Immediately address '#" . $overdueTasks[0]['task_id'] . " - " . $overdueTasks[0]['task_title'] . "' which has passed its due date of " . date('M d', strtotime($overdueTasks[0]['due_date'])) . ".";
        } elseif (!empty($urgentTasks)) {
            $tips[] = "Upcoming Deadline: Focus on '" . $urgentTasks[0]['task_title'] . "' which is due within the next 3 days (" . date('M d', strtotime($urgentTasks[0]['due_date'])) . ").";
        } elseif (!empty($highPriorityActive)) {
            $tips[] = "Start High Priority Item: Tackle the High Priority task '" . $highPriorityActive[0]['task_title'] . "' first today using the 80% rule.";
        } else {
            $tips[] = "Proactive Planning: Review upcoming assignments and pre-emptively start task definitions to establish momentum early.";
        }

        if ($inProgress > 1) {
            $tips[] = "Apply the Single-Tasking rule: Set aside 90-minute deep-work blocks specifically to push one active task from 'In Progress' to 'Done'.";
        } else {
            $tips[] = "Aggressive Deconstruction: Before beginning any new task, spend 5 minutes breaking it into micro-tasks.";
        }
        $tips[] = "Proactive Updates: Always document your actions or upload draft files when updating progress so managers can verify your work faster.";

        return [
            "completion_rate_prediction" => $prediction,
            "bottleneck_warning" => $bottleneck,
            "actionable_tips" => $tips,
            "source" => "OptiTask Local Engine"
        ];
    }

    private function getTaskDeconstruction($taskTitle)
    {
        $apiKey = env('GEMINI_API_KEY');

        if (empty($apiKey)) {
            return $this->generateLocalDeconstruction($taskTitle);
        }

        $prompt = "You are an AI Executive Performance Coach integrated into OptiTask.
Deconstruct the following employee task title: \"{$taskTitle}\"

Please provide exactly:
1. A breakdown of 3 to 4 logical sub-tasks that take less than 30 minutes each.
2. A clear, high-quality \"Definition of Done\" so the developer knows exactly when the task is complete and doesn't waste time over-engineering it.

Format the response nicely in clean HTML (using tags like <p>, <strong>, <ul class=\"list-disc pl-4 space-y-1.5 text-xs font-semibold text-gray-600\">, <li>). Do not wrap the output in markdown block wrappers (like ```html). Keep it direct, encouraging, and highly professional.";

        try {
            $response = Http::timeout(8)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey, [
                    "contents" => [
                        [
                            "parts" => [
                                ["text" => $prompt]
                            ]
                        ]
                    ]
                ]);

            if ($response->successful()) {
                $result = $response->json();
                if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                    $text = trim($result['candidates'][0]['content']['parts'][0]['text']);
                    if (!empty($text)) {
                        return $text;
                    }
                }
            }
        } catch (\Exception $e) {
            // Log if needed
        }

        return $this->generateLocalDeconstruction($taskTitle);
    }

    private function generateLocalDeconstruction($taskTitle)
    {
        $titleLower = strtolower($taskTitle);
        $cleanTitle = htmlspecialchars($taskTitle);
        
        $subtasks = [];
        $dod = [];

        if (str_contains($titleLower, 'auth') || str_contains($titleLower, 'login') || str_contains($titleLower, 'signup') || str_contains($titleLower, 'session') || str_contains($titleLower, 'password')) {
            $subtasks = [
                "Check password hashing mechanics and session parameters (10 mins).",
                "Implement sanitization checks for login inputs and prepared queries (20 mins).",
                "Set session variables and construct route redirection controls (20 mins).",
                "Test credentials verification flows with valid and invalid input logs (15 mins)."
            ];
            $dod = [
                "Passwords use strong hashes (e.g. bcrypt) and sessions initiate securely.",
                "Auth flow blocks unauthorized users and redirects them to the login screen."
            ];
        } elseif (str_contains($titleLower, 'mail') || str_contains($titleLower, 'email') || str_contains($titleLower, 'smtp') || str_contains($titleLower, 'notification') || str_contains($titleLower, 'alert')) {
            $subtasks = [
                "Verify SMTP configurations and mail connection parameters (15 mins).",
                "Design the message content template matching the Ultra-Pink style (15 mins).",
                "Implement database insert queries for the in-app notification list (20 mins).",
                "Test background email delivery outputs and error handler logging (15 mins)."
            ];
            $dod = [
                "In-app notifications trigger and write successfully to the database.",
                "SMTP mail delivers to recipient address without connection timeouts."
            ];
        } elseif (str_contains($titleLower, 'ui') || str_contains($titleLower, 'ux') || str_contains($titleLower, 'design') || str_contains($titleLower, 'css') || str_contains($titleLower, 'layout') || str_contains($titleLower, 'modal')) {
            $subtasks = [
                "Review visual templates and confirm layout grid guidelines (10 mins).",
                "Implement markup blocks aligning flex containers and border radii (20 mins).",
                "Apply visual accents, hover transitions, and glassmorphism styling (20 mins).",
                "Perform responsive viewport rendering checks on mobile screens (15 mins)."
            ];
            $dod = [
                "Component layouts align perfectly with the Ultra-Pink design style.",
                "No horizontal scroll overflow occurs, and content scales beautifully."
            ];
        } else {
            $subtasks = [
                "Define specific input criteria, milestones, and goals for '{$cleanTitle}' (10 mins).",
                "Set up raw files, logical hooks, and dependencies for the task (15 mins).",
                "Write core operations and validation loops to resolve '{$cleanTitle}' (25 mins).",
                "Verify syntax correctness and test logical flows with test inputs (15 mins)."
            ];
            $dod = [
                "Action items for '{$cleanTitle}' are completed fully according to requirements.",
                "Verify execution logs display correct results and no errors persist."
            ];
        }

        $subtasksHtml = '';
        foreach ($subtasks as $st) {
            $subtasksHtml .= "<li>" . htmlspecialchars($st) . "</li>";
        }

        $dodHtml = '';
        foreach ($dod as $d) {
            $dodHtml .= "<li>" . htmlspecialchars($d) . "</li>";
        }

        return "
            <div class='text-left space-y-4'>
                <p class='text-xs font-bold text-[#FB6F92] uppercase tracking-wider mb-2'>AI Suggested Sub-Tasks:</p>
                <ul class='list-disc pl-5 text-xs text-gray-600 font-semibold space-y-1'>
                    {$subtasksHtml}
                </ul>
                <p class='text-xs font-bold text-[#FB6F92] uppercase tracking-wider mt-4 mb-2'>Definition of Done:</p>
                <ul class='list-disc pl-5 text-xs text-gray-600 font-semibold space-y-1'>
                    {$dodHtml}
                </ul>
            </div>
        ";
    }
}
