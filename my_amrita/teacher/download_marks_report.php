<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php'); exit();
}
require_once '../api/db.php';

$uid = $_SESSION['user_id'];
$tc = $pdo->prepare('SELECT course_code FROM teacher_courses WHERE user_id = ?');
$tc->execute([$uid]);
$my_courses = $tc->fetchAll(PDO::FETCH_COLUMN);

if (empty($my_courses)) { exit('No courses assigned.'); }

$table_alias = 's';
require_once '../admin/filter_logic.php';

$filter_course = $_GET['course'] ?? '';
$course_sql = '';
if ($filter_course && in_array($filter_course, $my_courses)) {
    $course_sql = " AND m.course_code = ? ";
    $filter_params[] = $filter_course;
}

$placeholders = implode(',', array_fill(0, count($my_courses), '?'));
$filter_params_merged = array_merge($my_courses, $filter_params);
$marks = $pdo->prepare("SELECT m.*, s.name as student_name, s.enrollment_no, s.batch, s.department as branch, s.section, s.semester as current_sem 
                        FROM marks m 
                        JOIN students s ON m.student_id = s.id 
                        WHERE m.course_code IN ($placeholders) " . $filter_sql . $course_sql . " 
                        ORDER BY s.name, m.course_code");
$marks->execute($filter_params_merged);
$results = $marks->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=marks_report.csv');
$output = fopen('php://output', 'w');

fputcsv($output, ['Enrollment No', 'Student Name', 'Batch', 'Branch', 'Section', 'Semester', 'Course Code', 'Course Name', 'Internal Marks', 'External Marks', 'Total Marks', 'Grade']);

foreach ($results as $row) {
    fputcsv($output, [
        $row['enrollment_no'],
        $row['student_name'],
        $row['batch'],
        $row['branch'],
        $row['section'],
        $row['current_sem'],
        $row['course_code'],
        $row['course_name'],
        $row['internal'] !== null ? $row['internal'] : '—',
        $row['external'] !== null ? $row['external'] : '—',
        $row['total'] !== null ? $row['total'] : '—',
        $row['grade'] !== null ? $row['grade'] : '—'
    ]);
}
fclose($output);
exit();
