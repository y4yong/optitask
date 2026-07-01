<?php
require 'db_config.php';
$res = $conn->query("SHOW COLUMNS FROM users");
$cols = [];
while($r = $res->fetch_assoc()) $cols[] = $r['Field'];
echo json_encode($cols);
?>
