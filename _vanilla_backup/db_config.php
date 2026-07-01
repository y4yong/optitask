<?php
// Database configuration for OptiTask
$servername = "localhost";
$username = "root";       
$password = "";           
$dbname = "optitask";     

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


$conn->set_charset("utf8mb4");

if (!function_exists('log_audit')) {
    function log_audit($conn, $user_id, $action, $details) {
        $user_id = $user_id ? $user_id : 'SYSTEM';
        $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sss", $user_id, $action, $details);
            $stmt->execute();
            $stmt->close();
        }
    }
}
?>