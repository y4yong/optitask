<?php
/**
 * OptiTask System - AI Coaching & Insights Helper
 * Core Logic for calling Gemini API or generating Local Heuristic Coaching.
 */

// Load API Configuration if available
if (file_exists(__DIR__ . '/../config_ai.php')) {
    require_once __DIR__ . '/../config_ai.php';
}

if (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', '');
}

/**
 * Call the Gemini API to get structured coaching insights.
 * Falls back to local heuristics if the API key is not configured or request fails.
 */
function get_performance_insights($tasks, $completion_rate) {
    $api_key = trim(GEMINI_API_KEY);
    
    if (empty($api_key)) {
        return generate_local_insights($tasks, $completion_rate);
    }

    // Prepare task list description for the LLM
    $task_summaries = [];
    foreach ($tasks as $t) {
        $task_summaries[] = sprintf(
            "- ID: %s, Title: %s, Status: %s, Priority: %s, Due Date: %s",
            $t['task_id'],
            $t['task_title'],
            $t['task_status'],
            $t['priority'],
            $t['due_date']
        );
    }
    $tasks_str = implode("\n", $task_summaries);

    // Build the Prompt
    $prompt = <<<PROMPT
You are an AI Executive Performance Coach integrated into OptiTask, a task management system.
Analyze the following employee task list and historical performance data to generate hyper-personalized coaching insights:

Employee Completion Rate: {$completion_rate}%

Active and Historical Tasks:
{$tasks_str}

Please generate the following three insights in JSON format:
1. "completion_rate_prediction": A proactive, prediction-based analysis of the employee's completion rate and likelihood of meeting deadlines based on their past velocity. Mention specific task IDs or deadlines. Keep it professional, encouraging but direct.
2. "bottleneck_warning": A warning identifying potential bottlenecks (e.g. overdue tasks, too many "In Progress" tasks, high priority overload, or lack of updates). Be specific about what is blocking progress.
3. "actionable_tips": An array of exactly 3 specific, actionable performance tips. For example, suggesting they prioritize a specific task, deconstruct a large High priority task, or request manager assistance.

Your response MUST be raw JSON. Do not include markdown code block syntax (like ```json ... ```). Output only the JSON object.
JSON Schema:
{
  "completion_rate_prediction": "string",
  "bottleneck_warning": "string",
  "actionable_tips": [
    "string",
    "string",
    "string"
  ]
}
PROMPT;

    // Call Gemini API via cURL
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $api_key;
    
    $data = [
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
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8); // 8-second timeout for snappy UI
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Local setup certificate compatibility

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && $response) {
        $result = json_decode($response, true);
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $json_text = trim($result['candidates'][0]['content']['parts'][0]['text']);
            $insights = json_decode($json_text, true);
            if ($insights && isset($insights['completion_rate_prediction']) && isset($insights['bottleneck_warning']) && isset($insights['actionable_tips'])) {
                $insights['source'] = 'Gemini Live AI';
                return $insights;
            }
        }
    }

    // Fall back to local heuristic engine if API call fails
    $insights = generate_local_insights($tasks, $completion_rate);
    $insights['source'] = 'OptiTask Analytics (Fallback)';
    return $insights;
}

/**
 * Generate highly detailed, custom-tailored local insights based on actual tasks database contents.
 */
function generate_local_insights($tasks, $completion_rate) {
    $total = count($tasks);
    $todo = 0;
    $in_progress = 0;
    $done = 0;
    $verified = 0;
    
    $overdue_tasks = [];
    $urgent_tasks = [];
    $high_priority_active = [];
    
    $today = date('Y-m-d');
    $three_days_later = date('Y-m-d', strtotime('+3 days'));

    foreach ($tasks as $t) {
        $status = $t['task_status'];
        $priority = $t['priority'];
        $due = $t['due_date'];
        
        if ($status === 'To-Do') $todo++;
        elseif ($status === 'In Progress') $in_progress++;
        elseif ($status === 'Done') $done++;
        elseif ($status === 'Verified') $verified++;

        $is_active = ($status === 'To-Do' || $status === 'In Progress');
        if ($is_active) {
            if ($due < $today) {
                $overdue_tasks[] = $t;
            } elseif ($due <= $three_days_later) {
                $urgent_tasks[] = $t;
            }
            
            if ($priority === 'High') {
                $high_priority_active[] = $t;
            }
        }
    }

    // 1. Completion Rate Prediction
    if ($completion_rate >= 80) {
        $prediction = "Excellent velocity! Based on your current " . number_format($completion_rate, 1) . "% completion rate, you are on track to maintain an A+ badge. You have a 92% probability of hitting all active targets before their deadlines if your current rhythm is maintained.";
    } elseif ($completion_rate >= 60) {
        $prediction = "Solid progress. Your current completion rate is " . number_format($completion_rate, 1) . "%. You have a 70% probability of achieving an A+ badge by the end of this cycle. Focus on converting your " . $in_progress . " 'In Progress' task(s) into completed submissions to secure the boost.";
    } else {
        $prediction = "Attention required. With a current completion rate of " . number_format($completion_rate, 1) . "%, you have a 45% chance of missing performance targets. Proactive completion of active items is critical to push your metrics back into the A+ zone.";
    }

    // 2. Bottleneck Warning
    if (!empty($overdue_tasks)) {
        $first_overdue = $overdue_tasks[0]['task_title'];
        $count_overdue = count($overdue_tasks);
        $bottleneck = "Critical Bottleneck: You have {$count_overdue} overdue task(s) outstanding (e.g. '{$first_overdue}'). These overdue items represent immediate blockages. Clear these first before starting any new assignments.";
    } elseif ($in_progress > 2) {
        $bottleneck = "Multitasking Bottleneck: You currently have {$in_progress} tasks marked as 'In Progress' simultaneously. Spreading your focus thin increases cognitive overhead and risks delaying multiple due dates. Concentrate on completing one task to 100% first.";
    } elseif (!empty($high_priority_active)) {
        $first_high = $high_priority_active[0]['task_title'];
        $bottleneck = "Priority Concentration Warning: You have " . count($high_priority_active) . " High-Priority task(s) active (e.g. '{$first_high}'). Ensure these receive your primary morning focus to prevent late-stage delays.";
    } else {
        $bottleneck = "Healthy Pipeline: Excellent workflow allocation. You have 0 overdue tasks and no structural bottlenecks detected. Continue maintaining this healthy balance.";
    }

    // 3. Actionable Tips
    $tips = [];
    
    // Tip 1: Target specific task based on due date / status
    if (!empty($overdue_tasks)) {
        $tips[] = "Prioritize Overdue Task: Immediately address '#" . $overdue_tasks[0]['task_id'] . " - " . $overdue_tasks[0]['task_title'] . "' which has passed its due date of " . date('M d', strtotime($overdue_tasks[0]['due_date'])) . ".";
    } elseif (!empty($urgent_tasks)) {
        $tips[] = "Upcoming Deadline: Focus on '" . $urgent_tasks[0]['task_title'] . "' which is due within the next 3 days (" . date('M d', strtotime($urgent_tasks[0]['due_date'])) . ").";
    } elseif (!empty($high_priority_active)) {
        $tips[] = "Start High Priority Item: Tackle the High Priority task '" . $high_priority_active[0]['task_title'] . "' first today using the 80% rule (break it down into sub-tasks).";
    } else {
        $tips[] = "Proactive Planning: Review upcoming assignments and pre-emptively start task definitions to establish momentum early.";
    }

    // Tip 2: Methodological tips
    if ($in_progress > 1) {
        $tips[] = "Apply the Single-Tasking rule: Set aside 90-minute deep-work blocks specifically to push one active task from 'In Progress' to 'Done'.";
    } else {
        $tips[] = "Aggressive Deconstruction: Before beginning any new task, spend 5 minutes breaking it into micro-tasks that take less than 30 minutes each.";
    }

    // Tip 3: System best practices
    $tips[] = "Proactive Updates: Always document your actions or upload draft files when updating progress so managers can verify your work faster.";

    return [
        "completion_rate_prediction" => $prediction,
        "bottleneck_warning" => $bottleneck,
        "actionable_tips" => $tips,
        "source" => "OptiTask Local Engine"
    ];
}

/**
 * Generate an AI hint for a task that is detected as stuck.
 */
function get_stuck_task_hint($task_title, $days) {
    $api_key = trim(GEMINI_API_KEY);
    
    if (empty($api_key)) {
        return generate_local_stuck_hint($task_title, $days);
    }

    $prompt = <<<PROMPT
An employee task in OptiTask has been stuck in the "In Progress" status for {$days} consecutive days without any status update or documented activity.
Task Title: "{$task_title}"

Act as an executive performance coach and technical assistant. Generate a highly specific, short, helpful coaching hint, resource advice, or a 3-step breakdown of how to tackle this task.
Keep it extremely concise (maximum 2 sentences). Do not use generic advice; speak directly about the task topic "{$task_title}".
Format: Return only the text of the hint, no wrappers.
PROMPT;

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $api_key;
    
    $data = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && $response) {
        $result = json_decode($response, true);
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $hint = trim($result['candidates'][0]['content']['parts'][0]['text']);
            if (!empty($hint)) {
                return $hint;
            }
        }
    }

    return generate_local_stuck_hint($task_title, $days);
}

/**
 * Generate a smart heuristic stuck hint locally.
 */
function generate_local_stuck_hint($task_title, $days) {
    // Basic smart heuristics based on words in the title
    $title_lower = strtolower($task_title);
    
    if (strpos($title_lower, 'php') !== false || strpos($title_lower, 'code') !== false || strpos($title_lower, 'backend') !== false) {
        return "Stuck on PHP/backend logic for '{$task_title}'? Try writing a raw PHP scratch script to debug database queries and API bindings in isolation first.";
    } elseif (strpos($title_lower, 'ui') !== false || strpos($title_lower, 'ux') !== false || strpos($title_lower, 'design') !== false || strpos($title_lower, 'css') !== false) {
        return "Stuck on design/styling for '{$task_title}'? Check standard component designs in the workspace. Simplify layout structures first, then add styling elements.";
    } elseif (strpos($title_lower, 'db') !== false || strpos($title_lower, 'database') !== false || strpos($title_lower, 'sql') !== false) {
        return "Stuck on DB queries for '{$task_title}'? Write down and test your raw SQL queries in a SQL client (e.g. phpMyAdmin) before embedding them in your PHP prepared statements.";
    } elseif (strpos($title_lower, 'test') !== false || strpos($title_lower, 'debug') !== false) {
        return "Stuck on testing '{$task_title}'? Draft a structured step-by-step checklist of input conditions and expected outputs to trace logic execution pathing.";
    }
    
    return "This task has been In Progress for {$days} days. Try deconstructing it into 3 smaller milestones taking less than 15 minutes each, or contact your manager to resolve blockages.";
}

/**
 * Call the Gemini API to deconstruct a specific task into sub-tasks and definition of done.
 * Falls back to local deconstruction if the key is empty or request fails.
 */
function get_task_deconstruction($task_title) {
    $api_key = trim(GEMINI_API_KEY);
    
    if (empty($api_key)) {
        return generate_local_deconstruction($task_title);
    }

    $prompt = <<<PROMPT
You are an AI Executive Performance Coach integrated into OptiTask.
Deconstruct the following employee task title: "{$task_title}"

Please provide exactly:
1. A breakdown of 3 to 4 logical sub-tasks that take less than 30 minutes each.
2. A clear, high-quality "Definition of Done" so the developer knows exactly when the task is complete and doesn't waste time over-engineering it.

Format the response nicely in clean HTML (using tags like <p>, <strong>, <ul class="list-disc pl-4 space-y-1.5 text-xs font-semibold text-gray-600">, <li>). Do not wrap the output in markdown block wrappers (like ```html). Keep it direct, encouraging, and highly professional.
PROMPT;

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $api_key;
    
    $data = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && $response) {
        $result = json_decode($response, true);
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $text = trim($result['candidates'][0]['content']['parts'][0]['text']);
            if (!empty($text)) {
                return $text;
            }
        }
    }

    return generate_local_deconstruction($task_title);
}

/**
 * Generate a smart heuristic task deconstruction locally.
 */
function generate_local_deconstruction($task_title) {
    $title_lower = strtolower($task_title);
    $clean_title = htmlspecialchars($task_title);
    
    $subtasks = [];
    $dod = [];

    if (strpos($title_lower, 'auth') !== false || strpos($title_lower, 'login') !== false || strpos($title_lower, 'signup') !== false || strpos($title_lower, 'session') !== false || strpos($title_lower, 'password') !== false) {
        $subtasks = [
            "Check password hashing mechanics and session parameters (10 mins).",
            "Implement sanitization checks for login inputs and prepared queries (20 mins).",
            "Set session variables and construct route redirection controls (20 mins).",
            "Test credentials verification flows with valid and invalid input logs (15 mins)."
        ];
        $dod = [
            "Passwords use strong hashes (e.g. PASSWORD_DEFAULT) and sessions initiate securely.",
            "Auth flow blocks unauthorized users and redirects them to the login screen."
        ];
    } elseif (strpos($title_lower, 'mail') !== false || strpos($title_lower, 'email') !== false || strpos($title_lower, 'smtp') !== false || strpos($title_lower, 'notification') !== false || strpos($title_lower, 'alert') !== false) {
        $subtasks = [
            "Verify SMTP configurations and PHPMailer connection parameters (15 mins).",
            "Design the message content template matching the Ultra-Pink style (15 mins).",
            "Implement database insert queries for the in-app notification list (20 mins).",
            "Test background email delivery outputs and error handler logging (15 mins)."
        ];
        $dod = [
            "In-app notifications trigger and write successfully to the database.",
            "SMTP mail delivers to recipient address without connection timeouts."
        ];
    } elseif (strpos($title_lower, 'audit') !== false || strpos($title_lower, 'log') !== false || strpos($title_lower, 'history') !== false) {
        $subtasks = [
            "Inspect database audit_logs table schema and parameters (10 mins).",
            "Write the log_audit helper execution block with prepared structures (15 mins).",
            "Bind logging hooks to critical auth, submit, and assign events (25 mins).",
            "Verify that log records display correct actions and timestamp logs (10 mins)."
        ];
        $dod = [
            "All actions write correctly to database audit logs with correct user IDs.",
            "Timestamps match local timezone execution schedules."
        ];
    } elseif (strpos($title_lower, 'report') !== false || strpos($title_lower, 'export') !== false || strpos($title_lower, 'pdf') !== false) {
        $subtasks = [
            "Construct structured data arrays from database task metrics (15 mins).",
            "Integrate PDF script wrappers or layout layout rendering blocks (20 mins).",
            "Style printable formats matching the system's Quicksand typography (15 mins).",
            "Verify that exported files print layouts without alignment overflow (15 mins)."
        ];
        $dod = [
            "Export trigger downloads formatted documents with correct file headers.",
            "Data corresponds exactly with matching dashboard KPI scores."
        ];
    } elseif (strpos($title_lower, 'verify') !== false || strpos($title_lower, 'approve') !== false || strpos($title_lower, 'reject') !== false) {
        $subtasks = [
            "Draft status-change queries (e.g. transition from Done to Verified) (15 mins).",
            "Construct required response fields (e.g. rejection reason dropdowns) (20 mins).",
            "Implement notifications logic to alert the employee of the outcome (15 mins).",
            "Test state updates and verify status fields refresh in real-time (10 mins)."
        ];
        $dod = [
            "Status column transitions correctly between statuses in database.",
            "Submitting status alterations fires matching system alerts."
        ];
    } elseif (strpos($title_lower, 'refactor') !== false || strpos($title_lower, 'fix') !== false || strpos($title_lower, 'bug') !== false || strpos($title_lower, 'clean') !== false) {
        $subtasks = [
            "Locate lines of code triggering errors and trace variable scopes (15 mins).",
            "Rewrite logical statement errors and add fallback validations (20 mins).",
            "Clean up redundant code lines and align code tab indenting (15 mins).",
            "Perform checks on affected pages to verify functionality works (15 mins)."
        ];
        $dod = [
            "PHP errors are resolved and verified in debugger console logs.",
            "No formatting anomalies exist, and page structures load cleanly."
        ];
    } elseif (strpos($title_lower, 'db') !== false || strpos($title_lower, 'database') !== false || strpos($title_lower, 'sql') !== false || strpos($title_lower, 'index') !== false || strpos($title_lower, 'query') !== false) {
        $subtasks = [
            "Inspect target database schemas and build raw SQL query tests (15 mins).",
            "Locate scanning bottlenecks using SELECT EXPLAIN statements (15 mins).",
            "Add index key mappings or modify database query layouts (20 mins).",
            "Benchmark query speeds to ensure execution speeds stay under 100ms (15 mins)."
        ];
        $dod = [
            "Queries utilize correct table keys without triggering full scans.",
            "Data queries bind values using prepared statements for safety."
        ];
    } elseif (strpos($title_lower, 'ui') !== false || strpos($title_lower, 'ux') !== false || strpos($title_lower, 'design') !== false || strpos($title_lower, 'css') !== false || strpos($title_lower, 'layout') !== false || strpos($title_lower, 'modal') !== false) {
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
        // Dynamic fallback that uses the actual task title!
        $subtasks = [
            "Define specific input criteria, milestones, and goals for '{$clean_title}' (10 mins).",
            "Set up raw files, logical hooks, and directory dependencies for the task (15 mins).",
            "Write core operations and validation loops to resolve '{$clean_title}' (25 mins).",
            "Verify syntax correctness and test logical flows with test inputs (15 mins)."
        ];
        $dod = [
            "Completed task '{$clean_title}' satisfies all described requirements.",
            "Code syntax is clean and matches directory formatting rules."
        ];
    }

    $html = '<div class="space-y-4 text-left">';
    $html .= '<p class="text-xs font-black text-[#FB6F92] uppercase">Deconstructing: <span class="text-gray-700 font-extrabold normal-case">"' . $clean_title . '"</span></p>';
    $html .= '<p class="text-xs font-black text-[#FB6F92] uppercase">Recommended Sub-Tasks (<30 min each):</p>';
    $html .= '<ul class="list-disc pl-4 space-y-1.5 text-xs font-semibold text-gray-600">';
    foreach ($subtasks as $st) {
        $html .= '<li>' . $st . '</li>';
    }
    $html .= '</ul>';
    
    $html .= '<p class="text-xs font-black text-[#FB6F92] uppercase mt-4">Definition of Done:</p>';
    $html .= '<ul class="list-disc pl-4 space-y-1.5 text-xs font-semibold text-gray-600">';
    foreach ($dod as $d) {
        $html .= '<li>' . $d . '</li>';
    }
    $html .= '</ul>';
    $html .= '</div>';

    return $html;
}

/**
 * Call the Gemini API to get a full velocity audit report.
 * Falls back to local heuristics if key is empty or request fails.
 */
function get_full_velocity_audit($tasks, $completion_rate) {
    $api_key = trim(GEMINI_API_KEY);
    
    if (empty($api_key)) {
        return generate_local_audit($tasks, $completion_rate);
    }

    $task_summaries = [];
    foreach ($tasks as $t) {
        $task_summaries[] = sprintf(
            "- Title: %s, Status: %s, Priority: %s, Due: %s",
            $t['task_title'],
            $t['task_status'],
            $t['priority'],
            $t['due_date']
        );
    }
    $tasks_str = implode("\n", $task_summaries);

    $prompt = <<<PROMPT
You are an AI Executive Performance Coach.
Analyze the following employee tasks and performance metrics:
Completion Rate: {$completion_rate}%

Tasks List:
{$tasks_str}

Please generate a professional Executive Velocity Audit Report containing:
1. A brief analysis of their current pace and deadlines.
2. A recommended priority sequence of active tasks today.
3. A realistic hourly/daily schedule (e.g. Morning block, Afternoon block) to achieve a >80% completion rate.

Format the response nicely in clean HTML using <p>, <strong>, <ul>, <li class="text-xs font-semibold text-gray-600 mb-1">, etc. Do not wrap in markdown syntax (like ```html). Keep it concise, direct, and actionable.
PROMPT;

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $api_key;
    
    $data = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && $response) {
        $result = json_decode($response, true);
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $text = trim($result['candidates'][0]['content']['parts'][0]['text']);
            if (!empty($text)) {
                return $text;
            }
        }
    }

    return generate_local_audit($tasks, $completion_rate);
}

/**
 * Generate a smart heuristic detailed audit locally.
 */
function generate_local_audit($tasks, $completion_rate) {
    $active_tasks = [];
    foreach ($tasks as $t) {
        if ($t['task_status'] === 'To-Do' || $t['task_status'] === 'In Progress') {
            $active_tasks[] = $t;
        }
    }

    $html = '<div class="space-y-4 text-left">';
    $html .= '<p class="text-xs font-black text-[#FB6F92] uppercase">1. Velocity Analysis:</p>';
    if ($completion_rate >= 80) {
        $html .= '<p class="text-xs font-semibold text-gray-600">Your velocity is outstanding at ' . number_format($completion_rate, 1) . '%. You have minimal backlogs. Focus on maintainability and prompt submission verification.</p>';
    } else {
        $html .= '<p class="text-xs font-semibold text-gray-600">Your velocity is currently ' . number_format($completion_rate, 1) . '%. Active backlogs (' . count($active_tasks) . ' tasks) are affecting your momentum. Clearing these is critical to push your rating above 80%.</p>';
    }

    $html .= '<p class="text-xs font-black text-[#FB6F92] uppercase mt-4">2. Time ROI Action Priority:</p>';
    if (empty($active_tasks)) {
        $html .= '<p class="text-xs font-semibold text-gray-600">No active tasks. Request new assignments from your manager or prepare pre-requisites.</p>';
    } else {
        $html .= '<ul class="list-disc pl-4 space-y-1 text-xs font-semibold text-gray-600">';
        // Sort active tasks by priority (High, Medium, Low)
        usort($active_tasks, function($a, $b) {
            $order = ['High' => 3, 'Medium' => 2, 'Low' => 1];
            return ($order[$b['priority']] ?? 0) <=> ($order[$a['priority']] ?? 0);
        });
        foreach ($active_tasks as $t) {
            $html .= '<li><strong>' . htmlspecialchars($t['task_title']) . '</strong> (' . $t['priority'] . ' Priority) - due ' . date('M d', strtotime($t['due_date'])) . '</li>';
        }
        $html .= '</ul>';
    }

    $html .= '<p class="text-xs font-black text-[#FB6F92] uppercase mt-4">3. Recommended Performance Schedule:</p>';
    $html .= '<ul class="list-disc pl-4 space-y-1 text-xs font-semibold text-gray-600">';
    $html .= '<li><strong>09:00 - 11:30 AM (Deep Work):</strong> Focus exclusively on your highest priority active task.</li>';
    $html .= '<li><strong>11:30 - 12:00 PM (Documentation):</strong> Push code drafts/updates to keep managers informed.</li>';
    $html .= '<li><strong>02:00 - 04:30 PM (Secondary Focus):</strong> Address secondary tasks or check pending feedback.</li>';
    $html .= '<li><strong>04:30 - 05:00 PM (Daily Sync):</strong> Plan tomorrow\'s queue and verify completion checklist.</li>';
    $html .= '</ul>';
    $html .= '</div>';

    return $html;
}
