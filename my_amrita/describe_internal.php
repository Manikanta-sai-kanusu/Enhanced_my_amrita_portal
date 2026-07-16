<?php
require 'api/db.php';
$stmt = $pdo->query('SHOW CREATE TABLE internal_marks');
print_r($stmt->fetch(PDO::FETCH_ASSOC));
