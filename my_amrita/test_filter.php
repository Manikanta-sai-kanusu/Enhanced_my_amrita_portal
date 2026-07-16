<?php
require 'c:/xampp/htdocs/my_amrita/api/db.php';
$_GET = ['course'=>'23CSE311', 'batch'=>'2023-2027', 'branch'=>'Computer Science & Engineering', 'section'=>'B', 'semester'=>'6'];
$selected_course = $_GET['course'];
$table_alias = 's';
require 'c:/xampp/htdocs/my_amrita/admin/filter_logic.php';
$stmt = $pdo->prepare("SELECT DISTINCT s.id FROM students s JOIN attendance a ON s.id = a.student_id WHERE a.course_code = ? " . $filter_sql);
$params = array_merge([$selected_course], $filter_params);
$stmt->execute($params);
var_dump(count($stmt->fetchAll()));
?>
