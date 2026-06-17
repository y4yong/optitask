<?php
session_start();
require_once '../db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$skill_id = isset($_GET['skill_id']) ? (int)$_GET['skill_id'] : 0;
$dept_id = isset($_GET['dept_id']) ? $_GET['dept_id'] : 'all';

if ($skill_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid skill ID']);
    exit();
}

// AI Selection Logic:
// 1. Must have the skill.
// 2. Highest proficiency level.
// 3. Lowest active workload (tie-breaker).

$query = "SELECT es.user_id, u.username, es.proficiency_level,
                 (SELECT COUNT(*) FROM tasks t WHERE t.employee_id = es.user_id AND t.task_status NOT IN ('Done', 'Verified')) as active_tasks
          FROM employee_skills es
          JOIN users u ON es.user_id = u.user_id
          WHERE es.skill_id = ? AND u.role = 'Employee'";

if ($dept_id !== 'all') {
    $query .= " AND u.dept_id = " . (int)$dept_id;
}

$query .= " ORDER BY es.proficiency_level DESC, active_tasks ASC LIMIT 1";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $skill_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res && $res->num_rows > 0) {
    $candidate = $res->fetch_assoc();
    
    // Determine skill description
    $prof_labels = [1 => 'Beginner', 2 => 'Novice', 3 => 'Intermediate', 4 => 'Advanced', 5 => 'Expert'];
    $prof_text = $prof_labels[$candidate['proficiency_level']] ?? 'Unknown';
    
    echo json_encode([
        'success' => true,
        'user_id' => $candidate['user_id'],
        'username' => $candidate['username'],
        'proficiency' => $candidate['proficiency_level'],
        'reason' => "Selected for {$prof_text} proficiency (Level {$candidate['proficiency_level']}) with only {$candidate['active_tasks']} active tasks."
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'No matching candidates found for this skill in the selected department.']);
}
?>
