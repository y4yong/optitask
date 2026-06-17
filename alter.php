<?php
require 'db_config.php';
$conn->query("ALTER TABLE users ADD COLUMN suspension_reason VARCHAR(255) DEFAULT NULL");
echo "Done";
?>
