<?php
require 'api/db.php';
$stmt = $pdo->query('SELECT * FROM internal_marks LIMIT 5');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
