<?php
/**
 * OptiTask System - Stuck Task Detector (Automated Coach Cron Script)
 * Designed to run daily. Scans for tasks stuck in "In Progress" for > 3 days,
 * generates AI-guided productivity advice, and inserts it into the user's dashboard insights.
 */

// If accessed via CLI or browser, set proper headers
if (php_sapi_name() === 'cli') {
    echo "OptiTask Stuck Task Detector CLI Worker Starting...\n";
} else {
    echo "<h3>OptiTask Stuck Task Detector Background Worker Running...</h3><pre>";
}

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/employee/ai_coach_helper.php';

// 1. Fetch all tasks currently marked as 'In Progress'
$query = "SELECT task_id, task_title, start_date, employee_id FROM tasks WHERE task_status = 'In Progress'";
$result = $conn->query($query);

if (!$result) {
    die("Database query failed: " . $conn->error . "\n");
}

$processed_count = 0;
$triggered_count = 0;

while ($task = $result->fetch_assoc()) {
    $task_id = $task['task_id'];
    $task_title = $task['task_title'];
    $employee_id = $task['employee_id'];
    
    $processed_count++;
    
    // 2. Determine when the task went "In Progress"
    // First, search the audit logs for the START_TASK action
    $log_stmt = $conn->prepare("
        SELECT timestamp FROM audit_logs 
        WHERE action = 'START_TASK' 
          AND (details LIKE ? OR details LIKE ?) 
        ORDER BY timestamp DESC LIMIT 1
    ");
    
    $detail_match_1 = "%Started task ID " . $task_id . "%";
    $detail_match_2 = "%" . $task_id . "%";
    
    $log_stmt->bind_param("ss", $detail_match_1, $detail_match_2);
    $log_stmt->execute();
    $log_res = $log_stmt->get_result()->fetch_assoc();
    $log_stmt->close();
    
    $start_timestamp = null;
    if ($log_res) {
        $start_timestamp = strtotime($log_res['timestamp']);
        $source_desc = "audit log";
    } else {
        // Fallback: check tasks table start_date
        if (!empty($task['start_date']) && $task['start_date'] !== '0000-00-00') {
            $start_timestamp = strtotime($task['start_date']);
            $source_desc = "tasks.start_date";
        } else {
            // Default fallback for test data: assume it started 4 days ago
            $start_timestamp = strtotime("-4 days");
            $source_desc = "default fallback (4 days ago)";
        }
    }
    
    // Calculate the difference in days
    $seconds_elapsed = time() - $start_timestamp;
    $days_elapsed = floor($seconds_elapsed / 86400);
    
    if (php_sapi_name() === 'cli') {
        echo "Analyzing Task #{$task_id} ('{$task_title}'): In Progress for {$days_elapsed} days (Source: {$source_desc})\n";
    } else {
        echo "Analyzing Task #{$task_id} ('{$task_title}'): In Progress for {$days_elapsed} days (Source: {$source_desc})\n";
    }
    
    // If the task has been stuck for 3 or more consecutive days
    if ($days_elapsed >= 3) {
        // 3. Prevent duplicate notifications within the last 24 hours
        $check_stmt = $conn->prepare("
            SELECT COUNT(*) as count FROM notifications 
            WHERE user_id = ? 
              AND notification_type = 'Insight' 
              AND message LIKE ? 
              AND timestamp > NOW() - INTERVAL 1 DAY
        ");
        $like_msg = "%#" . $task_id . "%";
        $check_stmt->bind_param("ss", $employee_id, $like_msg);
        $check_stmt->execute();
        $dupe_res = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        if ($dupe_res['count'] > 0) {
            if (php_sapi_name() === 'cli') {
                echo "  -> Insight notification already sent within last 24 hours. Skipping.\n";
            } else {
                echo "  -> Insight notification already sent within last 24 hours. Skipping.\n";
            }
            continue;
        }
        
        // 4. Generate AI Coach Hint
        $hint = get_stuck_task_hint($task_title, $days_elapsed);
        
        // 5. Inject Hint into employee's notification center as an 'Insight'
        $notif_msg = "System Insight: Your active task '#{$task_id} - {$task_title}' has been in progress for {$days_elapsed} consecutive days. Coach Tip: {$hint}";
        
        $notif_stmt = $conn->prepare("
            INSERT INTO notifications (user_id, notification_type, message, status) 
            VALUES (?, 'Insight', ?, 'unread')
        ");
        $notif_stmt->bind_param("ss", $employee_id, $notif_msg);
        
        if ($notif_stmt->execute()) {
            $triggered_count++;
            
            // 6. Log the event to audit logs
            log_audit(
                $conn, 
                'SYSTEM', 
                'STUCK_DETECTOR_TRIGGERED', 
                "Injected stuck coach tip for task ID {$task_id} (stuck for {$days_elapsed} days) to employee ID {$employee_id}"
            );
            
            if (php_sapi_name() === 'cli') {
                echo "  -> SUCCESS: Stuck hint injected: \"{$hint}\"\n";
            } else {
                echo "  -> <span style='color:green;'>SUCCESS</span>: Stuck hint injected: \"{$hint}\"\n";
            }
        } else {
            if (php_sapi_name() === 'cli') {
                echo "  -> ERROR: Failed to insert notification: {$conn->error}\n";
            } else {
                echo "  -> <span style='color:red;'>ERROR</span>: Failed to insert notification: {$conn->error}\n";
            }
        }
        $notif_stmt->close();
    }
}

if (php_sapi_name() === 'cli') {
    echo "Stuck detector completed. Scanned: {$processed_count} tasks, Injected: {$triggered_count} tips.\n";
} else {
    echo "\nStuck detector completed. Scanned: {$processed_count} tasks, Injected: {$triggered_count} tips.</pre>";
}
