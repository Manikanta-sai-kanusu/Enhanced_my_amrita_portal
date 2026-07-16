<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$teacher_name = $_SESSION['user_name'];
$uid = $_SESSION['user_id'];

$batches = ['2022-2026','2023-2027','2024-2028','2025-2029'];
$selected_batch = $_GET['batch'] ?? '2023-2027';
$selected_sem_type = $_GET['sem_type'] ?? 'even';
$batch_parts = explode('-', $selected_batch);
$start_year = intval($batch_parts[0]);
$semester_map = [];
for ($y = 0; $y < 4; $y++) {
    $yr_label = ($start_year+$y).'-'.($start_year+$y+1);
    $semester_map[$yr_label] = ['odd' => ($y*2)+1, 'even' => ($y*2)+2];
}
$selected_year = $_GET['year'] ?? '';
if (!$selected_year) {
    $keys = array_keys($semester_map);
    $selected_year = $keys[min(2, count($keys)-1)] ?? $keys[0];
}
$current_sem = $semester_map[$selected_year][$selected_sem_type] ?? 6;

// Get advisee students for this batch
$stmt = $pdo->prepare("SELECT s.* FROM students s JOIN teacher_advisees ta ON s.id = ta.student_id WHERE ta.user_id = ? AND s.batch = ? ORDER BY s.enrollment_no");
$stmt->execute([$uid, $selected_batch]);
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>Teacher - My Advisees</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .top-navbar { background: linear-gradient(135deg, #a4123f 0%, #c2185b 100%); }
        .student-card { background:#fff; border:1px solid #e8e8e8; border-radius:10px; padding:18px; margin-bottom:14px; transition:all 0.2s; }
        .student-card:hover { box-shadow:0 4px 16px rgba(0,0,0,0.06); }
        .sgpa-mini { display:inline-block; background:linear-gradient(135deg,#a4123f,#d4264f); color:#fff; padding:3px 10px; border-radius:6px; font-size:12px; font-weight:700; margin-right:6px; }
        .cgpa-mini { display:inline-block; background:linear-gradient(135deg,#1a237e,#3949ab); color:#fff; padding:3px 10px; border-radius:6px; font-size:12px; font-weight:700; }
        .detail-panel { display:none; background:#fdf5f7; border:1px solid #f0d0d8; border-radius:8px; padding:16px; margin-top:12px; }
        .filter-row { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:16px; padding:14px 18px; background:#fff; border:1px solid #e8e8e8; border-radius:10px; }
        .filter-row label { font-size:11px; font-weight:600; color:#888; text-transform:uppercase; letter-spacing:0.5px; }
        .filter-row select { padding:6px 12px; border:1px solid #e0e0e0; border-radius:6px; font-size:13px; font-family:'Inter',sans-serif; }
    </style>
</head>
<body>
    <nav class="top-navbar">
        <span class="brand">Teacher Panel (Beta)</span>
        <div class="nav-links">
            <span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($teacher_name); ?></span>
            <a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a>
        </div>
    </nav>
    <div class="breadcrumb-bar"><a href="home.php">Teacher Home</a> <span class="sep">/</span> My Advisees</div>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-users"></i> My Advisees</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Teacher Home</a>
        </div>

        <!-- Batch & Semester Filter -->
        <div class="filter-row">
            <label>Batch:</label>
            <select onchange="applyFilter()" id="selBatch">
                <?php foreach ($batches as $b): ?>
                    <option value="<?php echo $b; ?>" <?php echo $b===$selected_batch?'selected':''; ?>><?php echo $b; ?></option>
                <?php endforeach; ?>
            </select>
            <label>Academic Year:</label>
            <select onchange="applyFilter()" id="selYear">
                <?php foreach (array_keys($semester_map) as $yr): ?>
                    <option value="<?php echo $yr; ?>" <?php echo $yr===$selected_year?'selected':''; ?>><?php echo $yr; ?></option>
                <?php endforeach; ?>
            </select>
            <label>Semester:</label>
            <select onchange="applyFilter()" id="selType">
                <option value="odd" <?php echo $selected_sem_type==='odd'?'selected':''; ?>>Odd</option>
                <option value="even" <?php echo $selected_sem_type==='even'?'selected':''; ?>>Even</option>
            </select>
            <span style="padding:6px 14px; background:linear-gradient(135deg,#a4123f,#d4264f); color:#fff; border-radius:8px; font-size:12px; font-weight:600;">Semester <?php echo $current_sem; ?> | <?php echo count($students); ?> students</span>
        </div>

        <?php if (empty($students)): ?>
            <div class="card"><div class="empty-state"><i class="fa fa-users"></i><p>No advisees found for batch <?php echo $selected_batch; ?>.</p></div></div>
        <?php else: ?>
            <?php foreach ($students as $s):
                // Calculate SGPA for current semester
                $mk = $pdo->prepare("SELECT m.*, c.credits FROM marks m LEFT JOIN courses c ON m.course_code=c.course_code WHERE m.student_id=? AND m.semester=?");
                $mk->execute([$s['id'], $current_sem]);
                $marks_data = $mk->fetchAll();
                $total_credits = 0; $total_points = 0;
                $grade_points = ['O'=>10,'A+'=>9,'A'=>8,'B+'=>7,'B'=>6,'C'=>5,'P'=>4,'F'=>0];
                foreach ($marks_data as $m) {
                    $cr = $m['credits'] ?? 3;
                    $gp = $grade_points[$m['grade']] ?? 0;
                    $total_credits += $cr;
                    $total_points += $cr * $gp;
                }
                $sgpa = $total_credits > 0 ? round($total_points / $total_credits, 2) : 0;

                // CGPA (all semesters)
                $all_mk = $pdo->prepare("SELECT m.*, c.credits FROM marks m LEFT JOIN courses c ON m.course_code=c.course_code WHERE m.student_id=?");
                $all_mk->execute([$s['id']]);
                $all_marks = $all_mk->fetchAll();
                $tc = 0; $tp = 0;
                foreach ($all_marks as $m) {
                    $cr = $m['credits'] ?? 3;
                    $gp = $grade_points[$m['grade']] ?? 0;
                    $tc += $cr; $tp += $cr * $gp;
                }
                $cgpa = $tc > 0 ? round($tp / $tc, 2) : 0;

                // Attendance
                $att = $pdo->prepare("SELECT * FROM attendance WHERE student_id=? AND semester=?");
                $att->execute([$s['id'], $current_sem]);
                $att_data = $att->fetchAll();
            ?>
            <div class="student-card">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <div>
                        <h3 style="margin:0; font-size:15px; color:#333;"><?php echo htmlspecialchars($s['name']); ?></h3>
                        <div style="font-size:12px; color:#888; margin-top:2px;">
                            <?php echo htmlspecialchars($s['enrollment_no']); ?> · <?php echo htmlspecialchars($s['department']); ?> · Sem <?php echo $s['semester']; ?> · Section <?php echo $s['section'] ?? 'B'; ?>
                        </div>
                    </div>
                    <div>
                        <span class="sgpa-mini">SGPA: <?php echo $sgpa; ?></span>
                        <span class="cgpa-mini">CGPA: <?php echo $cgpa; ?></span>
                    </div>
                </div>
                <div style="margin-top:10px;">
                    <button class="detail-toggle" onclick="toggleDetail(this)" style="background:linear-gradient(135deg,#a4123f,#d4264f); color:#fff; border:none; padding:5px 14px; border-radius:6px; font-size:11px; font-weight:600; cursor:pointer;">
                        <i class="fa fa-eye"></i> View Details
                    </button>
                </div>
                <div class="detail-panel">
                    <h4 style="color:#a4123f; margin:0 0 10px; font-size:14px;"><i class="fa fa-calendar-check-o"></i> Attendance (Semester <?php echo $current_sem; ?>)</h4>
                    <?php if (!empty($att_data)): ?>
                    <table class="data-table" style="margin-bottom:14px;">
                        <thead><tr><th>Course</th><th>Attended</th><th>Total</th><th>%</th></tr></thead>
                        <tbody>
                        <?php foreach ($att_data as $a): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($a['course_code']); ?></strong> – <?php echo htmlspecialchars($a['course_name']); ?></td>
                            <td><?php echo $a['attended']; ?></td>
                            <td><?php echo $a['total_classes']; ?></td>
                            <td><strong style="color:<?php echo $a['percentage']<75?'#e74c3c':'#27ae60'; ?>;"><?php echo number_format($a['percentage'],1); ?>%</strong></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?><p style="color:#999; font-size:13px;">No attendance data for this semester.</p><?php endif; ?>

                    <h4 style="color:#a4123f; margin:10px 0; font-size:14px;"><i class="fa fa-bar-chart"></i> Marks & Grades (Semester <?php echo $current_sem; ?>)</h4>
                    <?php if (!empty($marks_data)): ?>
                    <table class="data-table">
                        <thead><tr><th>Course</th><th>Internal</th><th>External</th><th>Total</th><th>Grade</th></tr></thead>
                        <tbody>
                        <?php foreach ($marks_data as $m): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($m['course_code']); ?></strong> – <?php echo htmlspecialchars($m['course_name']); ?></td>
                            <td><?php echo $m['internal']; ?></td>
                            <td><?php echo $m['external']; ?></td>
                            <td><strong><?php echo number_format($m['total'],1); ?></strong></td>
                            <td><span class="badge badge-approved"><?php echo htmlspecialchars($m['grade']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?><p style="color:#999; font-size:13px;">No marks data for this semester.</p><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <script>
    function applyFilter() {
        var b = document.getElementById('selBatch').value;
        var y = document.getElementById('selYear').value;
        var t = document.getElementById('selType').value;
        window.location.href = 'view_students.php?batch='+b+'&year='+y+'&sem_type='+t;
    }
    function toggleDetail(btn) {
        var panel = btn.parentElement.nextElementSibling;
        panel.style.display = panel.style.display === 'none' || !panel.style.display ? 'block' : 'none';
    }
    </script>
</body>
</html>
