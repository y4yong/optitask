<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Task;
use App\Models\Notification;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Http;

class DetectStuckTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:detect-stuck';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect tasks stuck in progress for over 3 days and inject coaching hints';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("OptiTask Stuck Task Detector CLI Worker Starting...");

        // Fetch tasks marked as 'In Progress'
        $tasks = Task::where('task_status', 'In Progress')->get();

        $processedCount = 0;
        $triggeredCount = 0;

        foreach ($tasks as $task) {
            $processedCount++;

            // Find starting timestamp from audit log or fall back to task start_date
            $log = AuditLog::where('action', 'START_TASK')
                ->where(function($q) use ($task) {
                    $q->where('details', 'LIKE', "%Started task ID " . $task->task_id . "%")
                      ->orWhere('details', 'LIKE', "%" . $task->task_id . "%");
                })
                ->orderBy('timestamp', 'desc')
                ->first();

            if ($log) {
                $startTimestamp = strtotime($log->timestamp);
                $sourceDesc = "audit log";
            } elseif ($task->start_date) {
                $startTimestamp = strtotime($task->start_date);
                $sourceDesc = "tasks.start_date";
            } else {
                $startTimestamp = strtotime("-4 days");
                $sourceDesc = "default fallback (4 days ago)";
            }

            $daysElapsed = floor((time() - $startTimestamp) / 86400);

            $this->info("Analyzing Task #{$task->task_id} ('{$task->task_title}'): In Progress for {$daysElapsed} days (Source: {$sourceDesc})");

            if ($daysElapsed >= 3) {
                // Prevent duplicate notifications in last 24h
                $hasDupe = Notification::where('user_id', $task->employee_id)
                    ->where('notification_type', 'Insight')
                    ->where('message', 'LIKE', "%#" . $task->task_id . "%")
                    ->where('timestamp', '>', now()->subDay())
                    ->exists();

                if ($hasDupe) {
                    $this->warn("  -> Insight notification already sent within last 24 hours. Skipping.");
                    continue;
                }

                // Generate Coach Hint
                $hint = $this->getStuckTaskHint($task->task_title, $daysElapsed);

                $notifMsg = "System Insight: Your active task '#{$task->task_id} - {$task->task_title}' has been in progress for {$daysElapsed} consecutive days. Coach Tip: {$hint}";

                Notification::create([
                    'user_id' => $task->employee_id,
                    'notification_type' => 'Insight',
                    'message' => $notifMsg,
                    'status' => 'unread'
                ]);

                $triggeredCount++;

                AuditLog::log(
                    'SYSTEM', 
                    'STUCK_DETECTOR_TRIGGERED', 
                    "Injected stuck coach tip for task ID {$task->task_id} (stuck for {$daysElapsed} days) to employee ID {$task->employee_id}"
                );

                $this->info("  -> SUCCESS: Stuck hint injected: \"{$hint}\"");
            }
        }

        $this->info("Stuck detector completed. Scanned: {$processedCount} tasks, Injected: {$triggeredCount} tips.");
    }

    private function getStuckTaskHint($taskTitle, $days)
    {
        $apiKey = env('GEMINI_API_KEY');

        if (empty($apiKey)) {
            return $this->generateLocalStuckHint($taskTitle, $days);
        }

        $prompt = "An employee task in OptiTask has been stuck in the \"In Progress\" status for {$days} consecutive days without any status update or documented activity.
Task Title: \"{$taskTitle}\"

Act as an executive performance coach and technical assistant. Generate a highly specific, short, helpful coaching hint, resource advice, or a 3-step breakdown of how to tackle this task.
Keep it extremely concise (maximum 2 sentences). Do not use generic advice; speak directly about the task topic \"{$taskTitle}\".
Format: Return only the text of the hint, no wrappers.";

        try {
            $response = Http::timeout(6)
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
                    $hint = trim($result['candidates'][0]['content']['parts'][0]['text']);
                    if (!empty($hint)) {
                        return $hint;
                    }
                }
            }
        } catch (\Exception $e) {
            // Fallback
        }

        return $this->generateLocalStuckHint($taskTitle, $days);
    }

    private function generateLocalStuckHint($taskTitle, $days)
    {
        $titleLower = strtolower($taskTitle);
        
        if (str_contains($titleLower, 'php') || str_contains($titleLower, 'code') || str_contains($titleLower, 'backend')) {
            return "Stuck on PHP/backend logic for '{$taskTitle}'? Try writing a raw PHP scratch script to debug database queries and API bindings in isolation first.";
        } elseif (str_contains($titleLower, 'ui') || str_contains($titleLower, 'ux') || str_contains($titleLower, 'design') || str_contains($titleLower, 'css')) {
            return "Stuck on design/styling for '{$taskTitle}'? Check standard component designs in the workspace. Simplify layout structures first, then add styling elements.";
        } elseif (str_contains($titleLower, 'db') || str_contains($titleLower, 'database') || str_contains($titleLower, 'sql')) {
            return "Stuck on DB queries for '{$taskTitle}'? Write down and test your raw SQL queries in a SQL client before embedding them in your PHP statements.";
        } elseif (str_contains($titleLower, 'test') || str_contains($titleLower, 'debug')) {
            return "Stuck on testing '{$taskTitle}'? Draft a structured step-by-step checklist of input conditions and expected outputs to trace logic execution pathing.";
        }
        
        return "This task has been In Progress for {$days} days. Try deconstructing it into 3 smaller milestones taking less than 15 minutes each, or contact your manager to resolve blockages.";
    }
}
