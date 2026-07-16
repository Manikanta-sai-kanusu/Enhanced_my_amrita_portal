<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php'); exit();
}
require_once '../api/db.php';

$uid = $_SESSION['user_id'];
$teacher_name = $_SESSION['user_name'];

// Get courses assigned to this teacher
$tc = $pdo->prepare("SELECT course_code, course_name FROM teacher_courses WHERE user_id = ?");
$tc->execute([$uid]);
$my_courses = $tc->fetchAll();
$my_codes = array_column($my_courses, 'course_code');

$table_alias = 's';
require_once '../admin/filter_logic.php';

$filter_course = $_GET['course'] ?? '';
$course_sql = '';
if ($filter_course && in_array($filter_course, $my_codes)) {
    $course_sql = " AND cf.course_code = ? ";
    $filter_params[] = $filter_course;
}

$feedbacks = [];
if (!empty($my_codes)) {
    $placeholders = implode(',', array_fill(0, count($my_codes), '?'));
    $params = array_merge($my_codes, $filter_params);
    $stmt = $pdo->prepare("SELECT cf.*, s.name as student_name, s.enrollment_no, s.batch, s.department as branch, s.section, s.semester as current_sem
        FROM course_feedback cf 
        JOIN students s ON cf.student_id = s.id 
        WHERE cf.course_code IN ($placeholders) " . $filter_sql . $course_sql . "
        ORDER BY cf.created_at DESC");
    $stmt->execute($params);
    $feedbacks = $stmt->fetchAll();
}

try {
    $name_pattern = '%' . str_replace(['.', ' '], '%', $teacher_name) . '%';
    $params2 = array_merge([$name_pattern, $teacher_name], $filter_params);
    $stmt2 = $pdo->prepare("SELECT cf.*, s.name as student_name, s.enrollment_no, s.batch, s.department as branch, s.section, s.semester as current_sem 
        FROM course_feedback cf 
        JOIN students s ON cf.student_id = s.id 
        WHERE (cf.faculty_name LIKE ? OR cf.faculty_name = ?) " . $filter_sql . $course_sql . "
        ORDER BY cf.created_at DESC");
    $stmt2->execute($params2);
    $extra = $stmt2->fetchAll();
    $existing_ids = array_column($feedbacks, 'id');
    foreach ($extra as $e) {
        if (!in_array($e['id'], $existing_ids)) $feedbacks[] = $e;
    }
} catch(Exception $e) {}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=feedback_report.csv');
$output = fopen('php://output', 'w');

$questions = [
    'q1' => 'Q1 Knowledge', 'q2' => 'Q2 Communication', 'q3' => 'Q3 Availability', 'q4' => 'Q4 Participation', 
    'q5' => 'Q5 Fair Exams', 'q6' => 'Q6 Pacing', 'q7' => 'Q7 Missed Classes', 'q8' => 'Q8 Punctuality', 'q9' => 'Q9 Overall'
];

fputcsv($output, ['Enrollment No', 'Student Name', 'Batch', 'Branch', 'Section', 'Semester', 'Course Code', 'Course Name', 'Q1 Knowledge', 'Q2 Communication', 'Q3 Availability', 'Q4 Participation', 'Q5 Fair Exams', 'Q6 Pacing', 'Q7 Missed Classes', 'Q8 Punctuality', 'Q9 Overall', 'Comments']);

foreach ($feedbacks as $row) {
    $answers = json_decode($row['answers_json'] ?? '{}', true) ?: [];
    fputcsv($output, [
        $row['enrollment_no'],
        $row['student_name'],
        $row['batch'],
        $row['branch'],
        $row['section'],
        $row['current_sem'],
        $row['course_code'],
        $row['course_name'],
        $answers['q1'] ?? '—',
        $answers['q2'] ?? '—',
        $answers['q3'] ?? '—',
        $answers['q4'] ?? '—',
        $answers['q5'] ?? '—',
        $answers['q6'] ?? '—',
        $answers['q7'] ?? '—',
        $answers['q8'] ?? '—',
        $answers['q9'] ?? '—',
        $row['comments'] ?? ''
    ]);
}
fclose($output);
exit();
