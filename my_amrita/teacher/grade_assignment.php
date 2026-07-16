<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php'); exit();
}
require_once '../api/db.php';
$teacher_name = $_SESSION['user_name'];
$uid = $_SESSION['user_id'];

$assignment_id = intval($_GET['id'] ?? 0);
if (!$assignment_id) { die("Invalid Assignment ID"); }

// Fetch assignment
$stmt = $pdo->prepare("SELECT * FROM assignments WHERE id = ?");
$stmt->execute([$assignment_id]);
$assignment = $stmt->fetch();
if (!$assignment) { die("Assignment not found"); }

// Verify teacher has access to this course
$tc = $pdo->prepare("SELECT course_code FROM teacher_courses WHERE user_id = ? AND course_code = ?");
$tc->execute([$uid, $assignment['course_code']]);
if (!$tc->fetch()) { die("Unauthorized access to this course."); }

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'grade') {
    $sid = intval($_POST['student_id']);
    $marks = floatval($_POST['marks']);
    $feedback = trim($_POST['feedback'] ?? '');
    
    // Update or Insert grade
    $chk = $pdo->prepare("SELECT id FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?");
    $chk->execute([$assignment_id, $sid]);
    if ($chk->fetch()) {
        $pdo->prepare("UPDATE assignment_submissions SET marks_awarded = ?, feedback = ?, status = 'Graded' WHERE assignment_id = ? AND student_id = ?")
            ->execute([$marks, $feedback, $assignment_id, $sid]);
    } else {
        // Teacher grading without submission (e.g. offline submission)
        $pdo->prepare("INSERT INTO assignment_submissions (assignment_id, student_id, marks_awarded, feedback, status) VALUES (?, ?, ?, ?, 'Graded')")
            ->execute([$assignment_id, $sid, $marks, $feedback]);
    }
    
    // Notify student
    $pdo->prepare("INSERT INTO notifications (student_id, title, message, type) VALUES (?, ?, ?, 'assignment')")->execute([
        $sid, 
        'Assignment Graded: ' . $assignment['title'],
        'Your assignment has been graded. Marks: ' . $marks . '/' . $assignment['total_marks']
    ]);
    $msg = 'graded';
}

$table_alias = 's';
require_once '../admin/filter_logic.php';

// Fetch students for this course and cohort, with their submissions
$q = "SELECT DISTINCT s.id, s.name, s.enrollment_no, s.batch, s.department as branch, s.section, s.semester as current_sem,
      sub.file_name, sub.file_path, sub.submitted_at, sub.status, sub.marks_awarded, sub.feedback 
      FROM students s 
      JOIN attendance a ON s.id = a.student_id 
      LEFT JOIN assignment_submissions sub ON sub.student_id = s.id AND sub.assignment_id = ?
      WHERE a.course_code = ? " . $filter_sql . "
      ORDER BY s.enrollment_no";

$params = array_merge([$assignment_id, $assignment['course_code']], $filter_params);
$stmt = $pdo->prepare($q);
$stmt->execute($params);
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>Teacher - Grade Assignment</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .top-navbar { background: linear-gradient(135deg, #a4123f 0%, #c2185b 100%); }
        .data-table { width:100%; border-collapse:collapse; font-size:13px; }
        .data-table th { background:linear-gradient(135deg,#a4123f,#d4264f); color:#fff; padding:10px 12px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
        .data-table td { padding:10px 12px; border-bottom:1px solid #eee; vertical-align:middle; }
        .data-table tr:hover { background:#f8f9fa; }
    </style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Teacher Panel (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($teacher_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="home.php">Teacher Home</a> <span class="sep">/</span> <a href="upload_assignments.php?course=<?php echo urlencode($assignment['course_code']); ?>">Assignments</a> <span class="sep">/</span> Grade Submissions</div>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-check-square-o"></i> Grade Submissions</h1>
            <a href="upload_assignments.php?course=<?php echo urlencode($assignment['course_code']); ?>" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Assignments</a>
        </div>

        <?php if ($msg === 'graded'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> Grade submitted and student notified!</div><?php endif; ?>

        <div class="card" style="border-left:4px solid #a4123f;">
            <h2 class="card-title"><?php echo htmlspecialchars($assignment['title']); ?></h2>
            <div style="font-size:13px; color:#555; margin-bottom:8px;"><strong>Course:</strong> <?php echo htmlspecialchars($assignment['course_code'] . ' - ' . $assignment['course_name']); ?></div>
            <div style="font-size:13px; color:#555; margin-bottom:8px;"><strong>Due Date:</strong> <?php echo date('d M Y', strtotime($assignment['due_date'])); ?></div>
            <div style="font-size:13px; color:#555; margin-bottom:8px;"><strong>Max Marks:</strong> <?php echo $assignment['total_marks']; ?></div>
            <?php if ($assignment['file_path']): 
                  $disp_path = $assignment['file_path'];
                  if (strpos($disp_path, '/uploads/') === 0) {
                      $disp_path = '..' . $disp_path;
                  }
            ?>
                <a href="<?php echo htmlspecialchars($disp_path); ?>" target="_blank" style="color:#1565c0; font-size:13px; font-weight:600;"><i class="fa fa-download"></i> Download Assignment File</a>
            <?php endif; ?>
        </div>

        <?php 
        $filter_count = count($students);
        include '../admin/filter_ui.php'; 
        ?>

        <div class="card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Enrollment No</th>
                        <th>Submission</th>
                        <th>Status</th>
                        <th>Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $s): 
                        $status = $s['status'] ?: 'Pending';
                        $status_color = match($status) { 'Graded' => '#27ae60', 'Submitted' => '#3498db', default => '#f5a623' };
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($s['name']); ?></strong><br>
                            <span style="font-size:10px; color:#888;"><?php echo htmlspecialchars($s['batch'] . ' | ' . $s['branch'] . ' | Sec ' . $s['section']); ?></span>
                        </td>
                        <td><code style="font-size:11px;"><?php echo htmlspecialchars($s['enrollment_no']); ?></code></td>
                        <td>
                            <?php if ($s['file_path']): 
                                $disp_path2 = $s['file_path'];
                                if (strpos($disp_path2, '/uploads/') === 0) {
                                    $disp_path2 = '..' . $disp_path2;
                                }
                            ?>
                                <div style="font-size:11px; color:#888; margin-bottom:4px;"><?php echo date('d M Y H:i', strtotime($s['submitted_at'])); ?></div>
                                <a href="<?php echo htmlspecialchars($disp_path2); ?>" target="_blank" style="color:#1565c0; font-size:12px; font-weight:600; text-decoration:none;"><i class="fa fa-eye"></i> View File</a>
                            <?php else: ?>
                                <span style="font-size:12px; color:#888;">No submission</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge" style="background:<?php echo $status_color; ?>; color:#fff;"><?php echo $status; ?></span></td>
                        <td>
                            <form method="POST" style="display:flex; flex-direction:column; gap:6px;">
                                <input type="hidden" name="action" value="grade">
                                <input type="hidden" name="student_id" value="<?php echo $s['id']; ?>">
                                <div style="display:flex; gap:6px; align-items:center;">
                                    <input type="number" name="marks" value="<?php echo $s['marks_awarded'] ?? ''; ?>" max="<?php echo $assignment['total_marks']; ?>" min="0" step="0.5" class="form-control" style="width:70px; padding:4px 8px; font-size:12px;" required placeholder="Marks">
                                    <span style="font-size:12px; color:#888;">/ <?php echo $assignment['total_marks']; ?></span>
                                </div>
                                <textarea name="feedback" rows="1" class="form-control" style="font-size:12px; padding:4px 8px;" placeholder="Feedback (optional)"><?php echo htmlspecialchars($s['feedback'] ?? ''); ?></textarea>
                                <button type="submit" style="background:#27ae60; color:#fff; border:none; padding:4px 10px; border-radius:4px; font-size:11px; font-weight:600; cursor:pointer;"><i class="fa fa-save"></i> Save</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($students)): ?>
                    <tr><td colspan="5" style="text-align:center; padding:20px; color:#888;">No students found for this cohort.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
