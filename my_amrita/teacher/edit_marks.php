<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php'); exit();
}
require_once '../api/db.php';
$teacher_name = $_SESSION['user_name'];
$uid = $_SESSION['user_id'];
$msg = '';

// Get teacher's assigned courses
$tc = $pdo->prepare('SELECT course_code FROM teacher_courses WHERE user_id = ?');
$tc->execute([$uid]);
$my_courses = $tc->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_marks') {
    $mid = intval($_POST['mark_id'] ?? 0);
    $student_id = intval($_POST['student_id'] ?? 0);
    $course_code = trim($_POST['course_code'] ?? '');
    
    if ($mid && in_array($course_code, $my_courses)) {
        // Calculate dynamic components
        $stmt_comps = $pdo->prepare("SELECT * FROM course_evaluation_components WHERE course_code = ? ORDER BY id");
        $stmt_comps->execute([$course_code]);
        $course_comps = $stmt_comps->fetchAll(PDO::FETCH_ASSOC);
        
        $total_internal = 0;
        
        if (!empty($course_comps)) {
            foreach ($course_comps as $comp) {
                $cid = $comp['id'];
                $scored = floatval($_POST['comp_' . $cid] ?? 0);
                
                // Save component mark
                $chk = $pdo->prepare("SELECT id FROM student_component_marks WHERE student_id=? AND component_id=?");
                $chk->execute([$student_id, $cid]);
                if ($chk->fetch()) {
                    $pdo->prepare("UPDATE student_component_marks SET scored_marks=? WHERE student_id=? AND component_id=?")->execute([$scored, $student_id, $cid]);
                } else {
                    $pdo->prepare("INSERT INTO student_component_marks (student_id, component_id, scored_marks) VALUES (?,?,?)")->execute([$student_id, $cid, $scored]);
                }
                
                // Weightage calculation: (scored / max_marks) * weightage
                if ($comp['max_marks'] > 0) {
                    $total_internal += ($scored / $comp['max_marks']) * $comp['weightage'];
                }
            }
        } else {
            // Fallback for legacy
            $a1 = floatval($_POST['assignment1'] ?? 0);
            $a2 = floatval($_POST['assignment2'] ?? 0);
            $q1 = floatval($_POST['quiz1'] ?? 0);
            $q2 = floatval($_POST['quiz2'] ?? 0);
            $midterm = floatval($_POST['midterm'] ?? 0);
            $total_internal = $a1 + $a2 + $q1 + $q2 + $midterm;
        }

        $ext = floatval($_POST['external'] ?? 0);
        $grade = trim($_POST['grade'] ?? '');
        $total = $total_internal + $ext;
        if ($ext == 0) { $grade = ''; }
        
        $pdo->prepare('UPDATE marks SET internal=?, external=?, total=?, grade=? WHERE id=?')->execute([$total_internal, $ext, $total, $grade, $mid]);
        
        // Notify student
        $pdo->prepare('INSERT INTO notifications (student_id, title, message, type) VALUES (?, ?, ?, "marks")')->execute([
            $student_id,
            'Marks Updated',
            'Your marks for ' . $course_code . ' have been updated by ' . $teacher_name . '.'
        ]);
        $msg = 'update_success';
    } else { 
        $msg = 'unauthorized'; 
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_comp') {
        $c_code = $_POST['course_code'];
        $c_name = $_POST['comp_name'];
        $c_max = floatval($_POST['max_marks']);
        $c_wgt = floatval($_POST['weightage']);
        if (in_array($c_code, $my_courses)) {
            $pdo->prepare("INSERT INTO course_evaluation_components (course_code, component_name, max_marks, weightage) VALUES (?,?,?,?)")->execute([$c_code, $c_name, $c_max, $c_wgt]);
            $msg = 'comp_added';
        }
    } elseif ($_POST['action'] === 'delete_comp') {
        $cid = intval($_POST['comp_id']);
        $c_code = $_POST['course_code'];
        if (in_array($c_code, $my_courses)) {
            $pdo->prepare("DELETE FROM student_component_marks WHERE component_id=?")->execute([$cid]);
            $pdo->prepare("DELETE FROM course_evaluation_components WHERE id=? AND course_code=?")->execute([$cid, $c_code]);
            
            // Recalculate internals for all students in this course
            $stmt_all_marks = $pdo->prepare("SELECT id, student_id, external FROM marks WHERE course_code=?");
            $stmt_all_marks->execute([$c_code]);
            $all_course_marks = $stmt_all_marks->fetchAll();
            
            $stmt_comps = $pdo->prepare("SELECT id, max_marks, weightage FROM course_evaluation_components WHERE course_code=?");
            $stmt_comps->execute([$c_code]);
            $course_comps = $stmt_comps->fetchAll();
            
            foreach ($all_course_marks as $m_row) {
                $total_internal = 0;
                foreach ($course_comps as $comp) {
                    $stmt_scored = $pdo->prepare("SELECT scored_marks FROM student_component_marks WHERE student_id=? AND component_id=?");
                    $stmt_scored->execute([$m_row['student_id'], $comp['id']]);
                    $s_mark = $stmt_scored->fetch();
                    if ($s_mark && $comp['max_marks'] > 0) {
                        $total_internal += (floatval($s_mark['scored_marks']) / $comp['max_marks']) * $comp['weightage'];
                    }
                }
                $total = $total_internal + floatval($m_row['external']);
                $pdo->prepare('UPDATE marks SET internal=?, total=? WHERE id=?')->execute([$total_internal, $total, $m_row['id']]);
            }
            
            $msg = 'comp_deleted';
        }
    }
}

// Handle filtering
$table_alias = 's';
require_once '../admin/filter_logic.php';

$filter_course = $_GET['course'] ?? '';
$course_sql = '';
if ($filter_course && in_array($filter_course, $my_courses)) {
    $course_sql = " AND m.course_code = ? ";
    // Do NOT append $filter_course to $filter_params here because it messes up the SQL parameter order!
}

$marks = [];
$dynamic_comps = [];
if ($filter_course && in_array($filter_course, $my_courses)) {
    // Get dynamic components for this course
    $stmt_comps = $pdo->prepare("SELECT * FROM course_evaluation_components WHERE course_code = ? ORDER BY id");
    $stmt_comps->execute([$filter_course]);
    $dynamic_comps = $stmt_comps->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($dynamic_comps)) {
        // Auto-initialize defaults so they can be deleted/managed
        $defaults = [
            ['Quiz 1', 15, 10], ['Quiz 2', 15, 10], ['Assignment 1', 20, 15], ['Assignment 2', 20, 15], ['Mid Term', 50, 20]
        ];
        $ins = $pdo->prepare("INSERT INTO course_evaluation_components (course_code, component_name, max_marks, weightage) VALUES (?, ?, ?, ?)");
        foreach ($defaults as $d) {
            $ins->execute([$filter_course, $d[0], $d[1], $d[2]]);
        }
        $stmt_comps->execute([$filter_course]);
        $dynamic_comps = $stmt_comps->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $filter_params_merged = array_merge([$filter_course], $filter_params);
    $marks_q = $pdo->prepare("SELECT m.*, s.name as student_name, s.enrollment_no, s.batch, s.department as branch, s.section, s.semester as current_sem
                            FROM marks m 
                            JOIN students s ON m.student_id = s.id 
                            WHERE m.course_code = ? " . str_replace("AND m.course_code = ?","",$course_sql) . $filter_sql . " 
                            ORDER BY s.name");
    $marks_q->execute($filter_params_merged);
    $marks = $marks_q->fetchAll(PDO::FETCH_ASSOC);
    
    // Pre-fetch all component marks for these students
    if (!empty($marks) && !empty($dynamic_comps)) {
        $student_ids = array_column($marks, 'student_id');
        $in = str_repeat('?,', count($student_ids) - 1) . '?';
        $sm_q = $pdo->prepare("SELECT student_id, component_id, scored_marks FROM student_component_marks WHERE student_id IN ($in)");
        $sm_q->execute($student_ids);
        $sm_rows = $sm_q->fetchAll(PDO::FETCH_ASSOC);
        
        $sm_map = [];
        foreach ($sm_rows as $row) {
            $sm_map[$row['student_id']][$row['component_id']] = $row['scored_marks'];
        }
        
        foreach ($marks as &$m) {
            $m['comp_marks'] = $sm_map[$m['student_id']] ?? [];
        }
        unset($m);
    }
}
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8">
    <title>My Amrita Teacher - Edit Marks</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .edit-inline { width:60px; padding:4px 6px; border:1px solid #ddd; border-radius:4px; font-size:12px; text-align:center; }
        .grade-inline { width:40px; padding:4px 6px; border:1px solid #ddd; border-radius:4px; font-size:12px; text-align:center; }
        .save-btn { background:linear-gradient(135deg,#a4123f,#d4264f); color:#fff; border:none; padding:4px 10px; border-radius:4px; font-size:11px; cursor:pointer; font-weight:600; }
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
    <div class="breadcrumb-bar"><a href="home.php">Teacher Home</a> <span class="sep">/</span> Edit Marks</div>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-bar-chart"></i> Edit Component Marks</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Teacher Home</a>
        </div>

        <?php if ($msg === 'update_success'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Marks updated & student notified!</div>
        <?php elseif ($msg === 'comp_added'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Evaluation component added!</div>
        <?php elseif ($msg === 'comp_deleted'): ?>
            <div class="msg-success"><i class="fa fa-trash"></i> Evaluation component deleted!</div>
        <?php elseif ($msg === 'unauthorized'): ?>
            <div class="msg-error"><i class="fa fa-times-circle"></i> You can only edit marks for your assigned courses.</div>
        <?php endif; ?>

        <?php 
        $extra_filters = '<label style="font-size:11px; font-weight:600; color:#888; text-transform:uppercase;">Select Course:</label>
            <select name="course" required style="padding:6px 12px; border:1px solid #e0e0e0; border-radius:6px; font-size:13px; font-family:\'Inter\',sans-serif;">
                <option value="">-- Choose Course --</option>';
        foreach ($my_courses as $c) {
            $sel = ($filter_course === $c) ? 'selected' : '';
            $extra_filters .= '<option value="' . htmlspecialchars($c) . '" ' . $sel . '>' . htmlspecialchars($c) . '</option>';
        }
        $extra_filters .= '</select>';
        
        $filter_count = count($marks);
        include '../admin/filter_ui.php'; 
        ?>

        <?php if (!$filter_course): ?>
            <div class="card"><div class="empty-state"><i class="fa fa-hand-pointer-o"></i><p>Please select a course to enter evaluation marks.</p></div></div>
        <?php else: ?>
        
        <!-- Manage Components UI -->
        <div class="card" style="margin-bottom:20px; border-top:4px solid #a4123f;">
            <h3 style="font-size:16px; margin:0 0 15px 0;"><i class="fa fa-sliders"></i> Manage Course Evaluations</h3>
            <div style="display:flex; gap:15px; flex-wrap:wrap; margin-bottom:15px;">
                <?php foreach ($dynamic_comps as $c): ?>
                    <div style="background:#f9f9f9; border:1px solid #ddd; padding:10px 15px; border-radius:6px; display:flex; align-items:center; gap:15px;">
                        <div>
                            <strong style="color:#333; font-size:13px;"><?php echo htmlspecialchars($c['component_name']); ?></strong>
                            <div style="font-size:11px; color:#666;">Max: <?php echo floatval($c['max_marks']); ?> | Wgt: <?php echo floatval($c['weightage']); ?></div>
                        </div>
                        <form method="POST" style="margin:0;" onsubmit="return confirm('Delete this evaluation? All student marks for it will be lost!');">
                            <input type="hidden" name="action" value="delete_comp">
                            <input type="hidden" name="comp_id" value="<?php echo $c['id']; ?>">
                            <input type="hidden" name="course_code" value="<?php echo htmlspecialchars($filter_course); ?>">
                            <button type="submit" style="background:#fee2e2; border:1px solid #fca5a5; color:#ef4444; border-radius:4px; padding:4px 8px; font-size:11px; font-weight:700; cursor:pointer;" title="Delete Component"><i class="fa fa-trash"></i> Delete</button>
                        </form>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($dynamic_comps)): ?>
                    <div style="font-size:12px; color:#888;">No custom components configured. (Using legacy defaults)</div>
                <?php endif; ?>
            </div>
            
            <form method="POST" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
                <input type="hidden" name="action" value="add_comp">
                <input type="hidden" name="course_code" value="<?php echo htmlspecialchars($filter_course); ?>">
                <div>
                    <label style="font-size:11px; font-weight:600; color:#888; display:block;">Evaluation Name</label>
                    <input type="text" name="comp_name" required placeholder="e.g. Quiz 3" style="padding:6px 10px; border:1px solid #ddd; border-radius:4px; font-size:12px;">
                </div>
                <div>
                    <label style="font-size:11px; font-weight:600; color:#888; display:block;">Max Marks</label>
                    <input type="number" step="0.01" name="max_marks" required placeholder="e.g. 15" style="padding:6px 10px; border:1px solid #ddd; border-radius:4px; font-size:12px; width:80px;">
                </div>
                <div>
                    <label style="font-size:11px; font-weight:600; color:#888; display:block;">Weightage</label>
                    <input type="number" step="0.01" name="weightage" required placeholder="e.g. 10" style="padding:6px 10px; border:1px solid #ddd; border-radius:4px; font-size:12px; width:80px;">
                </div>
                <button type="submit" style="background:#27ae60; color:#fff; border:none; padding:6px 12px; border-radius:4px; font-size:12px; font-weight:600; cursor:pointer;"><i class="fa fa-plus"></i> Add Component</button>
            </form>
        </div>

        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h2 class="card-title" style="margin:0;">Component Marks – <?php echo htmlspecialchars($filter_course); ?></h2>
            </div>
            
            <?php if (empty($dynamic_comps)): ?>
                <div class="msg-error" style="background:#fff3cd; color:#856404; border-color:#ffeeba;"><i class="fa fa-exclamation-triangle"></i> This course does not have detailed components defined. Defaulting to standard internal evaluation.</div>
            <?php endif; ?>

            <?php if (empty($marks)): ?>
                <div class="empty-state"><i class="fa fa-bar-chart"></i><p>No students found for this filter.</p></div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                <table class="data-table" style="font-size:11px; min-width:800px;">
                    <thead><tr>
                        <th>Student</th>
                        <?php if (!empty($dynamic_comps)): ?>
                            <?php foreach ($dynamic_comps as $c): ?>
                                <th title="Max: <?php echo $c['max_marks']; ?> | Weightage: <?php echo $c['weightage']; ?>">
                                    <?php echo htmlspecialchars($c['component_name']); ?><br>
                                    <small style="color:#888;">Max: <?php echo rtrim(rtrim($c['max_marks'], '0'), '.'); ?> (W:<?php echo rtrim(rtrim($c['weightage'], '0'), '.'); ?>)</small>
                                </th>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <th>Assignment 1</th><th>Assignment 2</th><th>Quiz 1</th><th>Quiz 2</th><th>Midterm</th>
                        <?php endif; ?>
                        <th>Calc Internal</th>
                        <th>External</th>
                        <th>Total</th>
                        <th>Grade</th>
                        <th>Save</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($marks as $m): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_marks">
                        <input type="hidden" name="mark_id" value="<?php echo $m['id']; ?>">
                        <input type="hidden" name="student_id" value="<?php echo $m['student_id']; ?>">
                        <input type="hidden" name="course_code" value="<?php echo htmlspecialchars($m['course_code']); ?>">
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($m['student_name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($m['enrollment_no']); ?></small>
                            </td>
                            <?php if (!empty($dynamic_comps)): ?>
                                <?php foreach ($dynamic_comps as $c): 
                                    $scored = $m['comp_marks'][$c['id']] ?? 0;
                                ?>
                                <td><input type="number" step="0.01" class="edit-inline" name="comp_<?php echo $c['id']; ?>" value="<?php echo $scored; ?>" max="<?php echo $c['max_marks']; ?>"></td>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <td><input type="number" step="0.01" class="edit-inline" name="assignment1" value="0"></td>
                                <td><input type="number" step="0.01" class="edit-inline" name="assignment2" value="0"></td>
                                <td><input type="number" step="0.01" class="edit-inline" name="quiz1" value="0"></td>
                                <td><input type="number" step="0.01" class="edit-inline" name="quiz2" value="0"></td>
                                <td><input type="number" step="0.01" class="edit-inline" name="midterm" value="0"></td>
                            <?php endif; ?>
                            <td><strong style="color:#a4123f;"><?php echo number_format($m['internal'], 2); ?></strong></td>
                            <td><input type="number" step="0.01" class="edit-inline" name="external" value="<?php echo $m['external']; ?>" style="width:45px;"></td>
                            <td><strong><?php echo number_format($m['total'], 1); ?></strong></td>
                            <td><input type="text" class="grade-inline" name="grade" value="<?php echo htmlspecialchars($m['grade']); ?>"></td>
                            <td><button type="submit" class="save-btn"><i class="fa fa-save"></i> Save</button></td>
                        </tr>
                    </form>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
