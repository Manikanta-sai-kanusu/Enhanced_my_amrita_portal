<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$teacher_name = $_SESSION['user_name'];
$uid = $_SESSION['user_id'];
$msg = '';

// Get teacher's courses
$tc = $pdo->prepare('SELECT tc.course_code, tc.course_name FROM teacher_courses tc WHERE tc.user_id = ?');
$tc->execute([$uid]); $my_courses = $tc->fetchAll();
$selected_course = $_GET['course'] ?? '';
$mark_date = $_GET['date'] ?? date('Y-m-d');

// Include filter logic
$table_alias = 's';
require_once '../admin/filter_logic.php';

// Check if course is a lab
$is_lab = false;
if ($selected_course) {
    $lab_check = $pdo->prepare("SELECT COUNT(*) FROM timetable WHERE course_code = ? AND course_name LIKE '%Lab%' LIMIT 1");
    $lab_check->execute([$selected_course]);
    $is_lab = $lab_check->fetchColumn() > 0;
}

// Get period from timetable for today
$today_day = date('l');
$periods_today = [];
if ($selected_course) {
    $ps = $pdo->prepare("SELECT DISTINCT time_slot FROM timetable WHERE course_code = ? AND day_name = ? LIMIT 4");
    $ps->execute([$selected_course, $today_day]);
    $periods_today = $ps->fetchAll(PDO::FETCH_COLUMN);
}

// Handle marking attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'mark_attendance') {
        $date = $_POST['mark_date'];
        $course = $_POST['course_code'];
        $start_period = intval($_POST['period_number']);
        $num_periods = intval($_POST['num_periods'] ?? 1);
        $statuses = $_POST['status'] ?? [];
        
        // Check 10-day lockout
        $diff = (strtotime(date('Y-m-d')) - strtotime($date)) / 86400;
        if ($diff > 10) {
            $msg = 'locked';
        } else {
            foreach ($statuses as $sid => $status) {
                // Loop for number of periods
                for ($p = 0; $p < $num_periods; $p++) {
                    $current_period = $start_period + $p;
                    // Delete existing for same date/course/period
                    $pdo->prepare("DELETE FROM attendance_records WHERE student_id=? AND course_code=? AND date=? AND period_number=?")->execute([$sid, $course, $date, $current_period]);
                    $pdo->prepare("INSERT INTO attendance_records (student_id, course_code, date, status, period_number, marked_by, is_lab) VALUES (?,?,?,?,?,?,?)")
                        ->execute([$sid, $course, $date, $status, $current_period, $teacher_name, $is_lab ? 1 : 0]);
                }
                
                // If lab, also mark an extra period natively? The user can just select num_periods=2, but we keep old logic if is_lab and num_periods is 1.
                if ($is_lab && $num_periods == 1) {
                    $pdo->prepare("DELETE FROM attendance_records WHERE student_id=? AND course_code=? AND date=? AND period_number=?")->execute([$sid, $course, $date, $start_period+1]);
                    $pdo->prepare("INSERT INTO attendance_records (student_id, course_code, date, status, period_number, marked_by, is_lab) VALUES (?,?,?,?,?,?,?)")
                        ->execute([$sid, $course, $date, $status, $start_period+1, $teacher_name, 1]);
                }
            }
            // Recalculate attendance summary
            foreach ($statuses as $sid => $status) {
                $total = $pdo->prepare("SELECT COUNT(*) FROM attendance_records WHERE student_id=? AND course_code=?");
                $total->execute([$sid, $course]); $t = $total->fetchColumn();
                $present = $pdo->prepare("SELECT COUNT(*) FROM attendance_records WHERE student_id=? AND course_code=? AND status='Present'");
                $present->execute([$sid, $course]); $p = $present->fetchColumn();
                $pct = $t > 0 ? round(($p/$t)*100, 2) : 0;
                $pdo->prepare("UPDATE attendance SET total_classes=?, attended=?, percentage=? WHERE student_id=? AND course_code=?")
                    ->execute([$t, $p, $pct, $sid, $course]);
            }
            $msg = 'marked';
        }
    } elseif ($_POST['action'] === 'request_edit') {
        $pdo->prepare("INSERT INTO attendance_edit_requests (teacher_id, teacher_name, student_id, course_code, record_date, reason) VALUES (?,?,?,?,?,?)")
            ->execute([$uid, $teacher_name, $_POST['student_id'], $_POST['course_code'], $_POST['record_date'], $_POST['reason']]);
        $msg = 'request_sent';
    }
}

// Get students for selected course
$students = [];
if ($selected_course) {
    // Add cohort filters to students fetch
    $stmt = $pdo->prepare("SELECT DISTINCT s.id, s.name, s.enrollment_no, s.batch, s.department as branch, s.section, s.semester as current_sem 
                           FROM students s JOIN attendance a ON s.id = a.student_id 
                           WHERE a.course_code = ? " . $filter_sql . " 
                           ORDER BY s.enrollment_no");
    $params = array_merge([$selected_course], $filter_params);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
    
    // Get existing records for selected date
    if (!empty($students)) {
        $existing = [];
        $ex = $pdo->prepare("SELECT student_id, status, period_number FROM attendance_records WHERE course_code = ? AND date = ?");
        $ex->execute([$selected_course, $mark_date]);
        foreach ($ex->fetchAll() as $r) $existing[$r['student_id'].'_'.$r['period_number']] = $r['status'];
    }
}
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>Teacher - Mark Attendance</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .top-navbar{background:linear-gradient(135deg,#a4123f,#c2185b);}
        .course-card{background:#fff;border:1px solid #e8e8e8;border-radius:10px;padding:16px;text-decoration:none;color:#333;transition:all .25s;display:block;border-left:4px solid #a4123f;}
        .course-card:hover{transform:translateY(-3px);box-shadow:0 6px 20px rgba(164,18,63,.12);}
        .course-card.active{border-left-color:#27ae60;background:#f0fff4;}
        .mark-table{width:100%;border-collapse:collapse;font-size:13px;}
        .mark-table th{background:linear-gradient(135deg,#a4123f,#d4264f);color:#fff;padding:10px 12px;font-size:11px;font-weight:600;text-transform:uppercase;}
        .mark-table td{padding:10px 12px;border-bottom:1px solid #eee;text-align:center;}
        .mark-table tbody tr:hover{background:#f8f9fa;}
        .radio-p,.radio-a{appearance:none;width:20px;height:20px;border:2px solid #ccc;border-radius:50%;cursor:pointer;transition:.2s;}
        .radio-p:checked{background:#27ae60;border-color:#27ae60;box-shadow:inset 0 0 0 3px #fff;}
        .radio-a:checked{background:#e74c3c;border-color:#e74c3c;box-shadow:inset 0 0 0 3px #fff;}
        .date-input{padding:8px 14px;border:1px solid #e0e0e0;border-radius:8px;font-size:14px;font-family:'Inter',sans-serif;}
        .lock-banner{background:#fff3cd;border:1px solid #f0d060;border-radius:10px;padding:14px 20px;margin-bottom:16px;display:flex;align-items:center;gap:10px;font-size:13px;color:#856404;}
        .lab-badge{background:#f3e5f5;color:#7b1fa2;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;}
        .mark-all-btn{padding:4px 12px;border:none;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;font-family:'Inter',sans-serif;}
    </style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Teacher Panel (Beta)</span><div class="nav-links"><span style="font-size:13px;opacity:.9;"><?php echo htmlspecialchars($teacher_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="home.php">Teacher Home</a> <span class="sep">/</span> Mark Attendance</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-calendar-check-o"></i> Mark Attendance</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Teacher Home</a>
        </div>

        <?php if ($msg === 'marked'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> Attendance marked successfully!</div>
        <?php elseif ($msg === 'locked'): ?><div class="msg-error"><i class="fa fa-lock"></i> Cannot edit attendance older than 10 days. Please request admin access.</div>
        <?php elseif ($msg === 'request_sent'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> Edit request sent to admin.</div><?php endif; ?>

        <?php 
        $filter_count = count($students);
        include '../admin/filter_ui.php'; 
        ?>

        <!-- Step 1: Course Selection -->
        <div class="card">
            <h2 class="card-title" style="margin-bottom:8px;"><i class="fa fa-book" style="color:#a4123f;"></i> My Courses</h2>
            <p style="font-size:12px;color:#888;margin-bottom:12px;">Select a course to mark attendance</p>
            <?php if (empty($my_courses)): ?>
                <div class="empty-state"><i class="fa fa-book"></i><p>No courses assigned.</p></div>
            <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px;">
                <?php foreach ($my_courses as $c): ?>
                <a href="edit_attendance.php?course=<?php echo urlencode($c['course_code']); ?>&date=<?php echo $mark_date; ?>&batch=<?php echo urlencode($filter_batch); ?>&branch=<?php echo urlencode($filter_branch); ?>&section=<?php echo urlencode($filter_section); ?>&semester=<?php echo urlencode($filter_semester); ?>" class="course-card <?php echo $selected_course===$c['course_code']?'active':''; ?>">
                    <div style="font-size:11px;color:#a4123f;font-weight:700;"><?php echo htmlspecialchars($c['course_code']); ?></div>
                    <div style="font-size:14px;font-weight:600;margin-top:2px;"><?php echo htmlspecialchars($c['course_name']); ?></div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($selected_course && !empty($students)): ?>
        <?php
        $date_diff = (strtotime(date('Y-m-d')) - strtotime($mark_date)) / 86400;
        $is_locked = $date_diff > 10;
        ?>

        <!-- Date & Period Selector -->
        <div class="card" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
            <div>
                <label style="font-size:11px;font-weight:600;color:#888;text-transform:uppercase;display:block;">Date</label>
                <input type="date" class="date-input" value="<?php echo $mark_date; ?>" onchange="window.location.href='edit_attendance.php?course=<?php echo urlencode($selected_course); ?>&date='+this.value">
            </div>
            <?php if ($is_lab): ?><span class="lab-badge"><i class="fa fa-flask"></i> LAB — 2 Periods</span><?php endif; ?>
            <?php if (!empty($periods_today)): ?>
            <div style="font-size:12px;color:#666;"><i class="fa fa-clock-o" style="color:#a4123f;"></i> Today's slots: <?php echo implode(', ', $periods_today); ?></div>
            <?php endif; ?>
        </div>

        <?php if ($is_locked): ?>
        <div class="lock-banner">
            <i class="fa fa-lock" style="font-size:18px;"></i>
            <span><strong>Locked:</strong> This date is older than 10 days. You need admin approval to edit.</span>
        </div>
        <?php endif; ?>

        <!-- Step 2: Mark Attendance -->
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h2 class="card-title" style="margin:0;"><i class="fa fa-users" style="color:#a4123f;"></i> <?php echo htmlspecialchars($selected_course); ?> — <?php echo date('d M Y (l)', strtotime($mark_date)); ?></h2>
                <a href="download_attendance_report.php?course=<?php echo urlencode($selected_course); ?>&batch=<?php echo urlencode($filter_batch); ?>&branch=<?php echo urlencode($filter_branch); ?>&section=<?php echo urlencode($filter_section); ?>&semester=<?php echo urlencode($filter_semester); ?>" class="submit-btn" style="text-decoration:none; margin:0; padding:8px 16px;"><i class="fa fa-download"></i> Download Report</a>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="mark_attendance">
                <input type="hidden" name="course_code" value="<?php echo htmlspecialchars($selected_course); ?>">
                <input type="hidden" name="mark_date" value="<?php echo $mark_date; ?>">
                <div style="margin-bottom:12px;display:flex;gap:10px;align-items:center;">
                    <label style="font-size:12px;font-weight:600;color:#888;">Start Period:</label>
                    <select name="period_number" class="date-input" style="padding:6px 12px;">
                        <?php for ($p = 1; $p <= 8; $p++): ?>
                        <option value="<?php echo $p; ?>"><?php echo $p; ?></option>
                        <?php endfor; ?>
                    </select>
                    <label style="font-size:12px;font-weight:600;color:#888;margin-left:10px;">Number of Periods:</label>
                    <input type="number" name="num_periods" value="1" min="1" max="8" class="date-input" style="padding:6px 12px; width:60px;">
                    <button type="button" class="mark-all-btn" style="background:#e8f5e9;color:#27ae60;margin-left:auto;" onclick="document.querySelectorAll('.radio-p').forEach(r=>r.checked=true)">✓ All Present</button>
                    <button type="button" class="mark-all-btn" style="background:#fde8e8;color:#e74c3c;" onclick="document.querySelectorAll('.radio-a').forEach(r=>r.checked=true)">✗ All Absent</button>
                </div>
                <table class="mark-table">
                    <thead><tr><th>#</th><th style="text-align:left;">Student Name</th><th>Registration No.</th><th>Present</th><th>Absent</th></tr></thead>
                    <tbody>
                    <?php foreach ($students as $si => $s):
                        // Find any existing status for this student on this date, prioritizing 'Absent'
                        $ex_status = '';
                        for ($p = 1; $p <= 8; $p++) {
                            $key = $s['id'].'_'.$p;
                            if (isset($existing[$key])) {
                                if ($existing[$key] === 'Absent') {
                                    $ex_status = 'Absent';
                                    break; // Found an absent, we can stop
                                } else {
                                    $ex_status = 'Present'; // Keep looking in case there's an absent in another period
                                }
                            }
                        }
                    ?>
                    <tr>
                        <td><strong><?php echo $si+1; ?></strong></td>
                        <td style="text-align:left;">
                            <strong style="font-weight:600;"><?php echo htmlspecialchars($s['name']); ?></strong><br>
                            <span style="font-size:10px; color:#888;"><?php echo htmlspecialchars($s['batch'] . ' | ' . $s['branch'] . ' | Sec ' . $s['section'] . ' | Sem ' . $s['current_sem']); ?></span>
                        </td>
                        <td><code style="font-size:11px;"><?php echo htmlspecialchars($s['enrollment_no']); ?></code></td>
                        <td><input type="radio" name="status[<?php echo $s['id']; ?>]" value="Present" class="radio-p" <?php echo ($ex_status==='Present' || !$ex_status)?'checked':''; ?> <?php echo $is_locked?'disabled':''; ?>></td>
                        <td><input type="radio" name="status[<?php echo $s['id']; ?>]" value="Absent" class="radio-a" <?php echo $ex_status==='Absent'?'checked':''; ?> <?php echo $is_locked?'disabled':''; ?>></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!$is_locked): ?>
                <button type="submit" class="submit-btn" style="width:100%;padding:14px;margin-top:16px;"><i class="fa fa-save"></i> Save Attendance</button>
                <?php else: ?>
                <!-- Request Admin Access -->
                <div style="margin-top:16px;padding:16px;background:#fff8e1;border-radius:10px;border:1px solid #f0d060;">
                    <strong style="color:#856404;"><i class="fa fa-key"></i> Request Admin Access to Edit</strong>
                    <form method="POST" style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
                        <input type="hidden" name="action" value="request_edit">
                        <input type="hidden" name="course_code" value="<?php echo $selected_course; ?>">
                        <input type="hidden" name="record_date" value="<?php echo $mark_date; ?>">
                        <input type="hidden" name="student_id" value="0">
                        <input type="text" name="reason" placeholder="Reason for editing old attendance..." class="date-input" style="flex:1;min-width:200px;" required>
                        <button type="submit" class="submit-btn" style="padding:8px 20px;"><i class="fa fa-paper-plane"></i> Send Request</button>
                    </form>
                </div>
                <?php endif; ?>
            </form>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
