<?php
session_start();
require_once '../db_config.php';

// Check role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_GET['user_id'] ?? '';

if (empty($user_id)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit();
}

// Fetch skills
$stmt = $conn->prepare("
    SELECT s.skill_name, es.proficiency_level 
    FROM employee_skills es
    JOIN skills s ON es.skill_id = s.skill_id
    WHERE es.user_id = ?
    ORDER BY s.skill_name ASC
");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$res = $stmt->get_result();

$skills = [];
while ($row = $res->fetch_assoc()) {
    $skills[] = $row;
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'skills' => $skills
]);
?>
