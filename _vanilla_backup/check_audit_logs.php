<?php
require_once 'db_config.php';
echo "=== AUDIT LOGS ===\n";
$res = $conn->query("SELECT * FROM audit_logs ORDER BY timestamp DESC LIMIT 30");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
