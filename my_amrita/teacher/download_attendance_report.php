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

$selected_course = $_GET['course'] ?? '';
if (!$selected_course || !in_array($selected_course, $my_courses)) {
    exit('Invalid or unauthorized course.');
}

$table_alias = 's';
require_once '../admin/filter_logic.php';

$stmt = $pdo->prepare("SELECT s.enrollment_no, s.name as student_name, s.batch, s.department as branch, s.section, s.semester as current_sem, 
                       a.course_code, a.total_classes, a.attended, a.percentage
                       FROM students s 
                       JOIN attendance a ON s.id = a.student_id 
                       WHERE a.course_code = ? " . $filter_sql . " 
                       ORDER BY s.enrollment_no");
$params = array_merge([$selected_course], $filter_params);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=attendance_report_' . $selected_course . '.csv');
$output = fopen('php://output', 'w');

fputcsv($output, ['Enrollment No', 'Student Name', 'Batch', 'Branch', 'Section', 'Semester', 'Course Code', 'Total Classes', 'Classes Attended', 'Attendance %']);

foreach ($results as $row) {
    fputcsv($output, [
        $row['enrollment_no'],
        $row['student_name'],
        $row['batch'],
        $row['branch'],
        $row['section'],
        $row['current_sem'],
        $row['course_code'],
        $row['total_classes'],
        $row['attended'],
        $row['percentage'] . '%'
    ]);
}
fclose($output);
exit();
