<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$teacher_name = $_SESSION['user_name'];
$uid = $_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'edit_att') {
        $aid = intval($_POST['att_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? '';
        $check = $pdo->prepare('SELECT a.id FROM attendance a JOIN teacher_advisees ta ON a.student_id = ta.student_id WHERE a.id = ? AND ta.user_id = ?');
        $check->execute([$aid, $uid]);
        if ($check->fetch() && in_array($new_status, ['Present','Absent','Late'])) {
            $pdo->prepare('UPDATE attendance SET status = ? WHERE id = ?')->execute([$new_status, $aid]);
            $msg = 'edit_success';
        }
    } elseif ($action === 'delete_att') {
        $aid = intval($_POST['att_id'] ?? 0);
        $check = $pdo->prepare('SELECT a.id FROM attendance a JOIN teacher_advisees ta ON a.student_id = ta.student_id WHERE a.id = ? AND ta.user_id = ?');
        $check->execute([$aid, $uid]);
        if ($check->fetch()) {
            $pdo->prepare('DELETE FROM attendance WHERE id = ?')->execute([$aid]);
            $msg = 'delete_success';
        }
    }
}

$stmt = $pdo->prepare('SELECT s.id, s.name, s.enrollment_no FROM teacher_advisees ta JOIN students s ON ta.student_id = s.id WHERE ta.user_id = ? ORDER BY s.name');
$stmt->execute([$uid]);
$advisees = $stmt->fetchAll();

$selected_student = $_GET['student_id'] ?? '';
$records = [];
if ($selected_student) {
    $verify = $pdo->prepare('SELECT 1 FROM teacher_advisees WHERE user_id = ? AND student_id = ?');
    $verify->execute([$uid, $selected_student]);
    if ($verify->fetch()) {
        $stmt = $pdo->prepare('SELECT * FROM attendance WHERE student_id = ? ORDER BY course_code, attendance_date DESC');
        $stmt->execute([$selected_student]);
        $records = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>Teacher - Edit Advisee Attendance</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>.action-btn{display:inline-block;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;border:none;cursor:pointer;font-family:'Inter',sans-serif;} .save-btn{background:#27ae60;color:#fff;} .del-btn{background:#c0392b;color:#fff;}</style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Teacher Panel (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($teacher_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="home.php">Teacher Home</a> <span class="sep">/</span> Edit Advisee Attendance</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-check-square-o"></i> Edit Advisee Attendance</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Teacher Home</a>
        </div>

        <?php if ($msg === 'edit_success'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> Attendance updated!</div>
        <?php elseif ($msg === 'delete_success'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> Attendance entry deleted.</div>
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

        <?php if ($selected_student && !empty($records)): ?>
        <div class="card">
            <h2 class="card-title">Attendance Records (<?php echo count($records); ?>)</h2>
            <table class="data-table">
                <thead><tr><th>Course</th><th>Date</th><th>Current Status</th><th>Edit</th><th>Delete</th></tr></thead>
                <tbody>
                    <?php foreach ($records as $r): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($r['course_code']); ?></strong></td>
                        <td><?php echo date('d M Y', strtotime($r['attendance_date'])); ?></td>
                        <td>
                            <form method="POST" style="display:inline-flex; gap:6px; align-items:center;">
                                <input type="hidden" name="action" value="edit_att">
                                <input type="hidden" name="att_id" value="<?php echo $r['id']; ?>">
                                <select name="new_status" class="form-control" style="width:auto; padding:4px 8px; font-size:12px;">
                                    <option value="Present" <?php echo $r['status']==='Present'?'selected':''; ?>>Present</option>
                                    <option value="Absent" <?php echo $r['status']==='Absent'?'selected':''; ?>>Absent</option>
                                    <option value="Late" <?php echo $r['status']==='Late'?'selected':''; ?>>Late</option>
                                </select>
                                <button type="submit" class="action-btn save-btn"><i class="fa fa-save"></i></button>
                            </form>
                        </td>
                        <td><span class="badge badge-<?php echo strtolower($r['status']); ?>"><?php echo $r['status']; ?></span></td>
                        <td>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?');">
                                <input type="hidden" name="action" value="delete_att">
                                <input type="hidden" name="att_id" value="<?php echo $r['id']; ?>">
                                <button type="submit" class="action-btn del-btn"><i class="fa fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php elseif ($selected_student): ?>
        <div class="card"><div class="empty-state"><i class="fa fa-calendar-check-o"></i><p>No attendance records found for this student.</p></div></div>
        <?php endif; ?>
    </div>
</body>
</html>
