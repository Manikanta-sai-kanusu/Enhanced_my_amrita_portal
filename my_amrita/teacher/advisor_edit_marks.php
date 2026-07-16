<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$teacher_name = $_SESSION['user_name'];
$uid = $_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'edit_mark') {
        $mid = intval($_POST['mark_id'] ?? 0);
        $new_val = floatval($_POST['new_value'] ?? 0);
        // Verify this mark belongs to an advisee
        $check = $pdo->prepare('SELECT m.id FROM marks m JOIN teacher_advisees ta ON m.student_id = ta.student_id WHERE m.id = ? AND ta.user_id = ?');
        $check->execute([$mid, $uid]);
        if ($check->fetch()) {
            $pdo->prepare('UPDATE marks SET marks_obtained = ?, grade = ? WHERE id = ?')->execute([$new_val, $grade, $mid]);
            $msg = 'edit_success';
        }
    } elseif ($action === 'delete_mark') {
        $mid = intval($_POST['mark_id'] ?? 0);
        $check = $pdo->prepare('SELECT m.id FROM marks m JOIN teacher_advisees ta ON m.student_id = ta.student_id WHERE m.id = ? AND ta.user_id = ?');
        $check->execute([$mid, $uid]);
        if ($check->fetch()) {
            $pdo->prepare('DELETE FROM marks WHERE id = ?')->execute([$mid]);
            $msg = 'delete_success';
        }
    }
}

// Get all advisees
$stmt = $pdo->prepare('SELECT s.id, s.name, s.enrollment_no FROM teacher_advisees ta JOIN students s ON ta.student_id = s.id WHERE ta.user_id = ? ORDER BY s.name');
$stmt->execute([$uid]);
$advisees = $stmt->fetchAll();

$selected_student = $_GET['student_id'] ?? '';
$marks = [];
if ($selected_student) {
    $verify = $pdo->prepare('SELECT 1 FROM teacher_advisees WHERE user_id = ? AND student_id = ?');
    $verify->execute([$uid, $selected_student]);
    if ($verify->fetch()) {
        $stmt = $pdo->prepare('SELECT * FROM marks WHERE student_id = ? ORDER BY course_code, exam_type');
        $stmt->execute([$selected_student]);
        $marks = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>Teacher - Edit Advisee Marks</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>.edit-input{width:70px;padding:4px 8px;border:1px solid #ddd;border-radius:6px;font-size:13px;text-align:center;font-family:'Inter',sans-serif;} .action-btn{display:inline-block;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;border:none;cursor:pointer;font-family:'Inter',sans-serif;} .save-btn{background:#27ae60;color:#fff;} .del-btn{background:#c0392b;color:#fff;}</style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Teacher Panel (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($teacher_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="home.php">Teacher Home</a> <span class="sep">/</span> Edit Advisee Marks</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-pencil-square-o"></i> Edit Advisee Marks</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Teacher Home</a>
        </div>

        <?php if ($msg === 'edit_success'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> Mark updated!</div>
        <?php elseif ($msg === 'delete_success'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> Mark entry deleted.</div>
        <?php endif; ?>

        <div class="card">
            <form method="GET" style="display:flex; gap:14px; align-items:flex-end;">
                <div class="form-group" style="flex:1;">
                    <label>Select Advisee</label>
                    <select class="form-control" name="student_id" onchange="this.form.submit()">
                        <option value="">-- Select Student --</option>
                        <?php foreach ($advisees as $a): ?>
                            <option value="<?php echo $a['id']; ?>" <?php echo $selected_student == $a['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($a['enrollment_no'] . ' – ' . $a['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <?php if ($selected_student && !empty($marks)): ?>
        <div class="card">
            <h2 class="card-title">Marks for <?php echo htmlspecialchars($marks[0]['course_code'] ?? ''); ?> (<?php echo count($marks); ?> entries)</h2>
            <table class="data-table">
                <thead><tr><th>Course</th><th>Exam Type</th><th>Max Marks</th><th>Obtained</th><th>Edit</th><th>Delete</th></tr></thead>
                <tbody>
                    <?php foreach ($marks as $m): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($m['course_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($m['exam_type']); ?></td>
                        <td><?php echo $m['max_marks']; ?></td>
                        <td>
                            <form method="POST" style="display:inline-flex; gap:6px; align-items:center;">
                                <input type="hidden" name="action" value="edit_mark">
                                <input type="hidden" name="mark_id" value="<?php echo $m['id']; ?>">
                                <input type="number" class="edit-input" name="new_value" value="<?php echo $m['marks_obtained']; ?>" step="0.5" min="0" max="<?php echo $m['max_marks']; ?>">
                                <button type="submit" class="action-btn save-btn"><i class="fa fa-save"></i></button>
                            </form>
                        </td>
                        <td><?php echo $m['marks_obtained']; ?> / <?php echo $m['max_marks']; ?></td>
                        <td>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this mark entry?');">
                                <input type="hidden" name="action" value="delete_mark">
                                <input type="hidden" name="mark_id" value="<?php echo $m['id']; ?>">
                                <button type="submit" class="action-btn del-btn"><i class="fa fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php elseif ($selected_student): ?>
        <div class="card"><div class="empty-state"><i class="fa fa-bar-chart"></i><p>No marks found for this student.</p></div></div>
        <?php endif; ?>
    </div>
</body>
</html>
