<?php
/**
 * OptiTask System - AJAX AI Assistant Endpoint
 * Directly processes requests for task deconstruction and performance audits,
 * and returns styled HTML outputs instead of raw prompts.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/ai_coach_helper.php';

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Employee') {
    http_response_code(403);
    echo "<p class='text-xs text-red-500 font-bold p-4 bg-red-50 rounded-xl border border-red-100'>Unauthorized. Please log in again.</p>";
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'audit') {
        // Fetch tasks for the employee to run audit
        $tasks_query = "SELECT task_title, task_status, priority, due_date FROM tasks WHERE employee_id = ? ORDER BY due_date DESC";
        $stmt = $conn->prepare($tasks_query);
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        $tasks = [];
        $totalTasks = 0;
        $completedTasks = 0;
        while ($row = $res->fetch_assoc()) {
            $tasks[] = $row;
            $totalTasks++;
            if ($row['task_status'] === 'Verified' || $row['task_status'] === 'Done') {
                $completedTasks++;
            }
        }
        $stmt->close();
        
        $completion_rate = $totalTasks > 0 ? ($completedTasks / $totalTasks) * 100 : 0;
        
        // Return full audit HTML
        echo get_full_velocity_audit($tasks, $completion_rate);
        
    } elseif ($action === 'deconstruct') {
        if (!isset($_POST['task_title']) || empty(trim($_POST['task_title']))) {
            echo "<p class='text-xs text-red-500 font-bold p-4 bg-red-50 rounded-xl border border-red-100'>Please select a valid task to deconstruct.</p>";
            exit();
        }
        
        $task_title = $_POST['task_title'];
        
        // Return task deconstruction HTML
        echo get_task_deconstruction($task_title);
    } else {
        http_response_code(400);
        echo "<p class='text-xs text-red-500 font-bold p-4 bg-red-50 rounded-xl border border-red-100'>Invalid Action.</p>";
    }
} else {
    http_response_code(400);
    echo "<p class='text-xs text-red-500 font-bold p-4 bg-red-50 rounded-xl border border-red-100'>Bad Request.</p>";
}
?>
