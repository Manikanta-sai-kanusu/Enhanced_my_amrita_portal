<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$teacher_name = $_SESSION['user_name'];
$uid = $_SESSION['user_id'];
$msg = '';

$tc = $pdo->prepare('SELECT course_code, course_name FROM teacher_courses WHERE user_id = ?');
$tc->execute([$uid]); $my_courses = $tc->fetchAll();

$selected_course = $_GET['course'] ?? '';

// Handle cohort filtering
$table_alias = 's';
require_once '../admin/filter_logic.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_assignment') {
    $course = trim($_POST['course_code'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $due = $_POST['due_date'] ?? '';
    $max_marks = intval($_POST['max_marks'] ?? 100);
    
    $valid = false; $cname = '';
    foreach ($my_courses as $c) { if ($c['course_code'] === $course) { $valid = true; $cname = $c['course_name']; break; } }
    
    $file_path = null;
    if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] === UPLOAD_ERR_OK) {
        $dir = '../uploads/assignments/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $ext = pathinfo($_FILES['assignment_file']['name'], PATHINFO_EXTENSION);
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $title) . '_' . time() . '.' . $ext;
        $dest = $dir . $safe;
        if (move_uploaded_file($_FILES['assignment_file']['tmp_name'], $dest)) {
            $file_path = '/uploads/assignments/' . $safe;
        }
    }
    
    if ($valid && $title && $due) {
        $pdo->prepare('INSERT INTO assignments (course_code, course_name, batch, branch, section, semester, title, description, assigned_by, due_date, total_marks, file_path) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$course, $cname, ($filter_batch!=='all'?$filter_batch:null), ($filter_branch!=='all'?$filter_branch:null), ($filter_section!=='all'?$filter_section:null), ($filter_semester!=='all'?$filter_semester:null), $title, $desc, $teacher_name, $due, $max_marks, $file_path]);
        // Notify students matching the cohort
        $stmt = $pdo->prepare('SELECT DISTINCT a.student_id FROM attendance a JOIN students s ON a.student_id = s.id WHERE a.course_code = ? ' . $filter_sql);
        $params = array_merge([$course], $filter_params);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $s) {
            $pdo->prepare('INSERT INTO notifications (student_id, title, message, type) VALUES (?,?,?,"assignment")')->execute([
                $s['student_id'], 'New Assignment: '.$title,
                'New assignment "'.$title.'" for '.$cname.' by '.$teacher_name.'. Due: '.$due
            ]);
        }
        $msg = 'upload_success';
    } else { $msg = 'upload_error'; }
}

// Get assignments for selected course
$assignments = [];
if ($selected_course) {
    // Only show assignments that match the cohort (or were assigned to ALL/NULL)
    $q = "SELECT * FROM assignments WHERE course_code = ?";
    $p = [$selected_course];
    if ($filter_batch !== 'all') { $q .= " AND (batch IS NULL OR batch = ?)"; $p[] = $filter_batch; }
    if ($filter_branch !== 'all') { $q .= " AND (branch IS NULL OR branch = ?)"; $p[] = $filter_branch; }
    if ($filter_section !== 'all') { $q .= " AND (section IS NULL OR section = ?)"; $p[] = $filter_section; }
    if ($filter_semester !== 'all') { $q .= " AND (semester IS NULL OR semester = ?)"; $p[] = $filter_semester; }
    $q .= " ORDER BY due_date DESC";
    
    $stmt = $pdo->prepare($q);
    $stmt->execute($p);
    $assignments = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>Teacher - Upload Assignments</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .top-navbar { background: linear-gradient(135deg, #a4123f 0%, #c2185b 100%); }
        .course-card { background:#fff; border:1px solid #e8e8e8; border-radius:10px; padding:16px; text-decoration:none; color:#333; transition:all 0.25s; display:block; border-left:4px solid #a4123f; }
        .course-card:hover { transform:translateY(-3px); box-shadow:0 6px 20px rgba(164,18,63,0.12); }
        .course-card.active { border-left-color:#27ae60; background:#f0fff4; }
        .assign-card { background:#fafafa; border:1px solid #e8e8e8; border-radius:8px; padding:14px; margin-bottom:10px; }
    </style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Teacher Panel (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($teacher_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="home.php">Teacher Home</a> <span class="sep">/</span> Upload Assignments</div>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-tasks"></i> Upload Assignments</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Teacher Home</a>
        </div>

        <?php if ($msg === 'upload_success'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> Assignment uploaded & students notified!</div>
        <?php elseif ($msg === 'upload_error'): ?><div class="msg-error"><i class="fa fa-times-circle"></i> Fill all required fields.</div><?php endif; ?>

        <?php 
        $extra_filters = '<label style="font-size:11px; font-weight:600; color:#888; text-transform:uppercase;">Select Course:</label>
            <select name="course" required style="padding:6px 12px; border:1px solid #e0e0e0; border-radius:6px; font-size:13px; font-family:\'Inter\',sans-serif;">
                <option value="">-- Choose Course --</option>';
        foreach ($my_courses as $c) {
            $sel = ($selected_course === $c['course_code']) ? 'selected' : '';
            $extra_filters .= '<option value="' . htmlspecialchars($c['course_code']) . '" ' . $sel . '>' . htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']) . '</option>';
        }
        $extra_filters .= '</select>';

        $filter_count = count($assignments); // dummy count
        include '../admin/filter_ui.php'; 
        ?>

        <?php if (!$selected_course): ?>
            <div class="card"><div class="empty-state"><i class="fa fa-hand-pointer-o"></i><p>Please select a course to upload or view assignments.</p></div></div>
        <?php else: ?>
        <div class="card form-section">
            <h3><i class="fa fa-upload"></i> Upload New Assignment</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_assignment">
                <input type="hidden" name="course_code" value="<?php echo htmlspecialchars($selected_course); ?>">
                <div class="form-row">
                    <div class="form-group"><label>Title</label><input type="text" class="form-control" name="title" required placeholder="Assignment title..."></div>
                    <div class="form-group"><label>Due Date</label><input type="date" class="form-control" name="due_date" required></div>
                    <div class="form-group"><label>Max Marks</label><input type="number" class="form-control" name="max_marks" value="100" min="1"></div>
                </div>
                <div class="form-group" style="margin-bottom:14px;"><label>Description</label><textarea class="form-control" name="description" rows="3" placeholder="Assignment instructions..."></textarea></div>
                <div class="form-group" style="margin-bottom:14px;"><label>Assignment File (PDF/Word/Image)</label><input type="file" class="form-control" name="assignment_file" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg,.zip"></div>
                <button type="submit" class="submit-btn" style="background:linear-gradient(135deg,#a4123f,#d4264f);"><i class="fa fa-upload"></i> Upload Assignment</button>
            </form>
        </div>

        <div class="card">
            <h2 class="card-title"><?php echo htmlspecialchars($selected_course); ?> – Existing Assignments</h2>
            <?php if (empty($assignments)): ?>
                <div class="empty-state"><i class="fa fa-tasks"></i><p>No assignments for this course yet.</p></div>
            <?php else: ?>
                <?php foreach ($assignments as $a): ?>
                <div class="assign-card">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap;">
                        <div>
                            <strong style="font-size:14px;"><?php echo htmlspecialchars($a['title']); ?></strong>
                            <div style="font-size:12px; color:#888; margin-top:4px;">Due: <strong style="color:#a4123f;"><?php echo date('d M Y', strtotime($a['due_date'])); ?></strong> · Max: <?php echo $a['max_marks'] ?? 100; ?> marks</div>
                            <?php if ($a['description']): ?><div style="font-size:13px; color:#555; margin-top:6px;"><?php echo htmlspecialchars($a['description']); ?></div><?php endif; ?>
                        </div>
                        <?php if (!empty($a['file_path'])): ?>
                        <div style="display:flex; gap:6px; margin-bottom:8px;">
                            <a href="..<?php echo htmlspecialchars($a['file_path']); ?>" target="_blank" style="color:#2e7d32; font-size:11px; font-weight:600; padding:5px 12px; background:#e8f5e9; border-radius:6px; text-decoration:none;"><i class="fa fa-eye"></i> View File</a>
                            <a href="..<?php echo htmlspecialchars($a['file_path']); ?>" download style="color:#1565c0; font-size:11px; font-weight:600; padding:5px 12px; background:#e3f2fd; border-radius:6px; text-decoration:none;"><i class="fa fa-download"></i> Download</a>
                        </div>
                        <?php endif; ?>
                        <div>
                            <a href="grade_assignment.php?id=<?php echo $a['id']; ?>&batch=<?php echo urlencode($filter_batch); ?>&branch=<?php echo urlencode($filter_branch); ?>&section=<?php echo urlencode($filter_section); ?>&semester=<?php echo urlencode($filter_semester); ?>" style="color:#fff; font-size:12px; font-weight:600; padding:8px 14px; background:linear-gradient(135deg,#a4123f,#d4264f); border-radius:6px; text-decoration:none; display:inline-block;"><i class="fa fa-check-square-o"></i> View Submissions & Grade</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
<script src="../js/upload_validator.js"></script>
</body>
</html>

