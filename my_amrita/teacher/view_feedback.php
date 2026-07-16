<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php'); exit();
}
require_once '../api/db.php';
$teacher_name = $_SESSION['user_name'];
$uid = $_SESSION['user_id'];

// Get courses assigned to this teacher
$tc = $pdo->prepare("SELECT course_code, course_name FROM teacher_courses WHERE user_id = ?");
$tc->execute([$uid]);
$my_courses = $tc->fetchAll();
$my_codes = array_column($my_courses, 'course_code');

// Handle cohort filtering
$table_alias = 's';
require_once '../admin/filter_logic.php';

$filter_course = $_GET['course'] ?? '';
$course_sql = '';
if ($filter_course && in_array($filter_course, $my_codes)) {
    $course_sql = " AND cf.course_code = ? ";
    $filter_params[] = $filter_course;
}

// Get feedback for my courses (match by faculty_name OR course_code in teacher_courses)
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

// Also match by faculty_name with fuzzy matching (handles dots/spaces differences)
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

// Group by course
$by_course = [];
foreach ($feedbacks as $f) {
    $key = $f['course_code'];
    if (!isset($by_course[$key])) $by_course[$key] = ['name' => $f['course_name'], 'items' => []];
    $by_course[$key]['items'][] = $f;
}

$selected_course = $_GET['course'] ?? '';
$detail_id = intval($_GET['detail'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>TLP Feedback - Teacher</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;}
        body{margin:0;font-family:'Inter','Segoe UI',sans-serif;background:#f5f5f5;color:#333;}
        .top-navbar{background:linear-gradient(135deg,#a4123f,#c2185b);color:#fff;padding:10px 20px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 2px 8px rgba(0,0,0,.15);position:sticky;top:0;z-index:1000;}
        .top-navbar .brand{font-size:18px;font-weight:600;letter-spacing:.5px;}
        .top-navbar .nav-links{display:flex;align-items:center;gap:18px;}
        .logout-btn{background:none;border:1px solid rgba(255,255,255,.4);color:#fff;padding:5px 14px;border-radius:6px;font-size:12px;text-decoration:none;transition:all .2s;}
        .logout-btn:hover{background:rgba(255,255,255,.15);border-color:#fff;}
        .breadcrumb-bar{background:#fff;padding:8px 20px;font-size:13px;color:#888;border-bottom:1px solid #e0e0e0;}
        .breadcrumb-bar a{color:#a4123f;text-decoration:none;}
        .main-content{max-width:1100px;margin:0 auto;padding:20px;}
        .page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;}
        .page-header h1{font-size:22px;color:#a4123f;margin:0;}
        .back-btn{background:linear-gradient(135deg,#a4123f,#d4264f);color:#fff;padding:8px 16px;border-radius:8px;font-size:12px;text-decoration:none;font-weight:600;}
        .card{background:#fff;border:1px solid #e8e8e8;border-radius:12px;padding:24px;margin-bottom:20px;}
        .card-title{font-size:16px;font-weight:700;color:#333;margin:0 0 16px;}
        .data-table{width:100%;border-collapse:collapse;font-size:13px;}
        .data-table th{background:linear-gradient(135deg,#a4123f,#d4264f);color:#fff;padding:10px 12px;text-align:left;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;}
        .data-table td{padding:10px 12px;border-bottom:1px solid #eee;vertical-align:middle;}
        .data-table tr:hover{background:#f8f9fa;}
        .course-chip{display:inline-block;background:#fef5f7;color:#a4123f;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;margin-right:6px;}
        .stat-card{display:inline-flex;align-items:center;gap:10px;background:#fff;border:1px solid #e8e8e8;border-radius:8px;padding:14px 18px;margin:0 10px 10px 0;}
        .stat-card .sv{font-size:22px;font-weight:700;color:#a4123f;}
        .stat-card .sl{font-size:11px;color:#888;text-transform:uppercase;}
        .answer-row{display:flex;gap:8px;align-items:center;padding:8px 0;border-bottom:1px solid #f0f0f0;}
        .answer-row .aq{font-weight:500;color:#333;font-size:13px;flex:1;}
        .answer-row .aa{font-weight:600;font-size:13px;padding:3px 10px;border-radius:6px;}
        .aa-excellent,.aa-good,.aa-strongly-agree,.aa-agree,.aa-very-satisfied,.aa-satisfied,.aa-below-5{background:#e8f5e9;color:#2e7d32;}
        .aa-average,.aa-neutral,.aa-5-10{background:#fff8e1;color:#e65100;}
        .aa-poor,.aa-disagree,.aa-strongly-disagree,.aa-dissatisfied,.aa-very-dissatisfied,.aa-10-20,.aa-more-than-20{background:#fde8e8;color:#e74c3c;}
        .empty-state{text-align:center;padding:40px;color:#bbb;}
        .empty-state i{font-size:36px;margin-bottom:10px;display:block;}
    </style>
</head>
<body>
    <nav class="top-navbar">
        <span class="brand">Teacher Panel (Beta)</span>
        <div class="nav-links"><span style="font-size:13px;opacity:.9;"><?php echo htmlspecialchars($teacher_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div>
    </nav>
    <div class="breadcrumb-bar"><a href="home.php">Home</a> <span>/</span> TLP Feedback</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-commenting-o"></i> TLP Feedback Received</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Home</a>
        </div>

        <!-- Stats -->
        <div style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <div class="stat-card"><div><div class="sv"><?php echo count($feedbacks); ?></div><div class="sl">Total Responses</div></div></div>
                <div class="stat-card"><div><div class="sv"><?php echo count($by_course); ?></div><div class="sl">Courses</div></div></div>
            </div>
            <a href="download_feedback_report.php?course=<?php echo urlencode($filter_course); ?>&batch=<?php echo urlencode($filter_batch); ?>&branch=<?php echo urlencode($filter_branch); ?>&section=<?php echo urlencode($filter_section); ?>&semester=<?php echo urlencode($filter_semester); ?>" class="back-btn" style="background:#27ae60;"><i class="fa fa-download"></i> Download Report</a>
        </div>

        <?php 
        $extra_filters = '<label style="font-size:11px; font-weight:600; color:#888; text-transform:uppercase;">Course:</label>
            <select name="course" style="padding:6px 12px; border:1px solid #e0e0e0; border-radius:6px; font-size:13px; font-family:\'Inter\',sans-serif;">
                <option value="">All My Courses</option>';
        foreach ($my_courses as $c) {
            $sel = ($filter_course === $c['course_code']) ? 'selected' : '';
            $extra_filters .= '<option value="' . htmlspecialchars($c['course_code']) . '" ' . $sel . '>' . htmlspecialchars($c['course_code']) . '</option>';
        }
        $extra_filters .= '</select>';
        
        $filter_count = count($feedbacks);
        include '../admin/filter_ui.php'; 
        ?>

        <?php if (empty($feedbacks)): ?>
        <div class="card"><div class="empty-state"><i class="fa fa-inbox"></i><p>No TLP feedback received yet.</p></div></div>
        <?php else: ?>

        <?php if ($detail_id): ?>
            <!-- Detail View -->
            <?php
            $detail = null;
            foreach ($feedbacks as $f) { if ($f['id'] == $detail_id) { $detail = $f; break; } }
            if ($detail):
                $answers = json_decode($detail['answers_json'] ?? '{}', true) ?: [];
                $questions = [
                    'q1' => "1. How would you rate the instructor's knowledge of the subject matter?",
                    'q2' => '2. How effectively did the instructor communicate the course material?',
                    'q3' => '3. Was the instructor available and helpful during office hours?',
                    'q4' => '4. Did the instructor encourage participation and facilitate a positive learning environment?',
                    'q5' => '5. Were the assignments and exams fair and reflective of the course material?',
                    'q6' => '6. How satisfied were you with the pacing of the teacher?',
                    'q7' => '7. How many classes has the teacher missed in the past?',
                    'q8' => "8. How would you rate the teacher's overall attendance and punctuality?",
                    'q9' => "9. How would you rate the instructor's overall teaching effectiveness?",
                ];
            ?>
            <div class="card">
                <h2 class="card-title"><i class="fa fa-file-text" style="color:#a4123f;"></i> Feedback Detail</h2>
                <div style="margin-bottom:16px;font-size:13px;color:#666;">
                    <strong>Student:</strong> <?php echo htmlspecialchars($detail['student_name']); ?> (<?php echo htmlspecialchars($detail['enrollment_no']); ?>)<br>
                    <strong>Course:</strong> <?php echo htmlspecialchars($detail['course_code'] . ' – ' . $detail['course_name']); ?><br>
                    <strong>Date:</strong> <?php echo date('d M Y, h:i A', strtotime($detail['created_at'])); ?>
                </div>
                <?php foreach ($questions as $qk => $qt):
                    $ans = $answers[$qk] ?? '—';
                    $cls = strtolower(str_replace([' ', '%'], ['-', ''], $ans));
                ?>
                <div class="answer-row">
                    <div class="aq"><?php echo $qt; ?></div>
                    <div class="aa aa-<?php echo $cls; ?>"><?php echo htmlspecialchars($ans); ?></div>
                </div>
                <?php endforeach; ?>
                <?php if (!empty($detail['comments'])): ?>
                <div style="margin-top:16px;padding:14px;background:#f8f9fa;border-radius:8px;">
                    <strong style="font-size:12px;color:#888;text-transform:uppercase;">Additional Comments</strong>
                    <p style="margin:6px 0 0;font-size:13px;color:#333;"><?php echo nl2br(htmlspecialchars($detail['comments'])); ?></p>
                </div>
                <?php endif; ?>
                <a href="view_feedback.php" style="display:inline-block;margin-top:16px;color:#a4123f;font-weight:600;font-size:13px;text-decoration:none;"><i class="fa fa-arrow-left"></i> Back to all feedback</a>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- List by Course -->
            <?php foreach ($by_course as $code => $data): ?>
            <div class="card">
                <h2 class="card-title"><span class="course-chip"><?php echo htmlspecialchars($code); ?></span> <?php echo htmlspecialchars($data['name']); ?> <span style="font-size:12px;color:#888;font-weight:400;margin-left:8px;">(<?php echo count($data['items']); ?> responses)</span></h2>
                <table class="data-table">
                    <thead><tr><th>Student</th><th>Enrollment</th><th>Date</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($data['items'] as $fb): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($fb['student_name']); ?></strong><br>
                                <span style="font-size:10px; color:#888;"><?php echo htmlspecialchars($fb['batch'] . ' | ' . $fb['branch'] . ' | Sec ' . $fb['section'] . ' | Sem ' . $fb['current_sem']); ?></span>
                            </td>
                            <td><code style="font-size:11px;"><?php echo htmlspecialchars($fb['enrollment_no']); ?></code></td>
                            <td><?php echo date('d M Y', strtotime($fb['created_at'])); ?></td>
                            <td><a href="view_feedback.php?detail=<?php echo $fb['id']; ?>" style="color:#1565c0;font-weight:600;font-size:12px;">View Details</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
