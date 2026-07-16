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

if (empty($my_courses)) {
    $my_courses = ['NONE'];
}

// Handle Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_issue') {
    $issue_id = intval($_POST['issue_id'] ?? 0);
    $issue_type = $_POST['issue_type'] ?? ''; // 'marks' or 'attendance'
    $new_status = $_POST['status'] ?? '';
    
    if ($issue_id && in_array($new_status, ['Submitted', 'Under Review', 'Resolved', 'Rejected'])) {
        if ($my_courses[0] === 'NONE') {
            if ($issue_type === 'marks') {
                $stmt = $pdo->prepare('UPDATE marks_issues SET status = ? WHERE id = ?');
            } else {
                $stmt = $pdo->prepare('UPDATE attendance_issues SET status = ? WHERE id = ?');
            }
            $params = [$new_status, $issue_id];
        } else {
            if ($issue_type === 'marks') {
                $stmt = $pdo->prepare('UPDATE marks_issues SET status = ? WHERE id = ? AND course_code IN (' . implode(',', array_fill(0, count($my_courses), '?')) . ')');
            } else {
                $stmt = $pdo->prepare('UPDATE attendance_issues SET status = ? WHERE id = ? AND course_code IN (' . implode(',', array_fill(0, count($my_courses), '?')) . ')');
            }
            $params = array_merge([$new_status, $issue_id], $my_courses);
        }
        
        $stmt->execute($params);
        $msg = 'Status updated successfully!';
    }
}

// Include filter logic
include '../admin/filter_logic.php';
$filter_course = $_GET['course'] ?? '';

$marks_issues_sql = "
    SELECT mi.*, s.name as student_name, s.enrollment_no as roll_no,
    (SELECT COUNT(*) FROM file_attachments fa WHERE fa.ref_type='marks_issue' AND fa.ref_id=mi.id) as file_count
    FROM marks_issues mi 
    JOIN students s ON mi.student_id = s.id 
    WHERE 1=1 " . $filter_sql;
$marks_params = $filter_params;

$att_issues_sql = "
    SELECT ai.*, s.name as student_name, s.enrollment_no as roll_no,
    (SELECT COUNT(*) FROM file_attachments fa WHERE fa.ref_type='attendance_issue' AND fa.ref_id=ai.id) as file_count
    FROM attendance_issues ai 
    JOIN students s ON ai.student_id = s.id 
    WHERE 1=1 " . $filter_sql;
$att_params = $filter_params;

if ($my_courses[0] !== 'NONE') {
    if ($filter_course && in_array($filter_course, $my_courses)) {
        $marks_issues_sql .= " AND mi.course_code = ?";
        $marks_params[] = $filter_course;
        $att_issues_sql .= " AND ai.course_code = ?";
        $att_params[] = $filter_course;
    } else {
        $in_marks = str_repeat('?,', count($my_courses) - 1) . '?';
        $marks_issues_sql .= " AND mi.course_code IN ($in_marks)";
        $marks_params = array_merge($marks_params, $my_courses);
        $att_issues_sql .= " AND ai.course_code IN ($in_marks)";
        $att_params = array_merge($att_params, $my_courses);
    }
} else {
    if ($filter_course) {
        $marks_issues_sql .= " AND mi.course_code = ?";
        $marks_params[] = $filter_course;
        $att_issues_sql .= " AND ai.course_code = ?";
        $att_params[] = $filter_course;
    }
}

$marks_issues_sql .= " ORDER BY mi.created_at DESC";
$stmt_marks = $pdo->prepare($marks_issues_sql);
$stmt_marks->execute($marks_params);
$marks_issues = $stmt_marks->fetchAll(PDO::FETCH_ASSOC);

$att_issues_sql .= " ORDER BY ai.created_at DESC";
$stmt_att = $pdo->prepare($att_issues_sql);
$stmt_att->execute($att_params);
$att_issues = $stmt_att->fetchAll(PDO::FETCH_ASSOC);


?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8">
    <title>Issue Resolution - Teacher Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .page-header { background: linear-gradient(135deg, #1a5276, #2980b9); color: white; padding: 24px; border-radius: 12px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 15px rgba(26, 82, 118, 0.2); }
        .page-header h1 { margin: 0; font-size: 24px; font-weight: 700; }
        .back-btn { background: rgba(255,255,255,0.2); color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 14px; font-weight: 600; transition: background 0.3s; }
        .back-btn:hover { background: rgba(255,255,255,0.3); color: white; }
        
        .tab-nav { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #eee; }
        .tab-btn { background: transparent; border: none; padding: 12px 24px; font-size: 15px; font-weight: 600; color: #666; cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -2px; }
        .tab-btn.active { color: #1a5276; border-bottom-color: #1a5276; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .issue-card { background: #fff; border: 1px solid #eaeaea; border-radius: 10px; padding: 20px; margin-bottom: 16px; display: flex; flex-direction: column; gap: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        .issue-header { display: flex; justify-content: space-between; align-items: flex-start; }
        .issue-course { font-size: 13px; font-weight: 700; color: #1a5276; text-transform: uppercase; }
        .issue-student { font-size: 16px; font-weight: 700; color: #333; margin-top: 4px; }
        .issue-roll { font-size: 13px; color: #888; }
        .issue-type-badge { background: #f0f4f8; color: #1a5276; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        
        .issue-desc { background: #f9f9f9; padding: 16px; border-radius: 8px; font-size: 14px; color: #444; line-height: 1.5; border-left: 3px solid #1a5276; }
        
        .issue-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; padding-top: 16px; border-top: 1px solid #eaeaea; }
        .status-form { display: flex; gap: 10px; align-items: center; }
        .status-select { padding: 8px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 13px; font-family: 'Inter', sans-serif; font-weight: 600; }
        .update-btn { background: #1a5276; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .update-btn:hover { background: #154360; }
        
        .attachment-badge { color: #c2185b; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
    </style>
</head>
<body style="background-color: #f4f6f9;">
    <nav class="top-navbar">
        <span class="brand">Teacher Portal</span>
        <div class="nav-links">
            <span style="font-size:13px; opacity:0.9; margin-right: 15px;">Welcome, <?php echo htmlspecialchars($teacher_name); ?></span>
            <a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a>
        </div>
    </nav>
    
    <div class="main-content" style="max-width: 1000px; margin: 30px auto;">
        <div class="page-header">
            <h1><i class="fa fa-exclamation-circle"></i> Issue Resolution</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Teacher Home</a>
        </div>

        <?php if ($msg): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>

        <?php 
        $extra_filters = '<label style="font-size:11px; font-weight:600; color:#888; text-transform:uppercase;">Select Course:</label>
            <select name="course" style="padding:6px 12px; border:1px solid #e0e0e0; border-radius:6px; font-size:13px; font-family:\'Inter\',sans-serif;">
                <option value="">-- All My Courses --</option>';
        foreach ($my_courses as $c) {
            $sel = ($filter_course === $c) ? 'selected' : '';
            $extra_filters .= '<option value="' . htmlspecialchars($c) . '" ' . $sel . '>' . htmlspecialchars($c) . '</option>';
        }
        $extra_filters .= '</select>';
        
        $filter_count = count($marks_issues) + count($att_issues);
        include '../admin/filter_ui.php'; 
        ?>
        
        <div class="tab-nav" style="margin-top: 20px;">
            <button class="tab-btn active" onclick="switchTab('marks', this)">Marks Issues (<?php echo count($marks_issues); ?>)</button>
            <button class="tab-btn" onclick="switchTab('attendance', this)">Attendance Issues (<?php echo count($att_issues); ?>)</button>
        </div>
        
        <div class="tab-content active" id="tab-marks">
            <?php if (empty($marks_issues)): ?>
                <div style="background: #fff; padding: 40px; text-align: center; border-radius: 10px; color: #888;">
                    <i class="fa fa-check-circle-o" style="font-size: 40px; color: #27ae60; margin-bottom: 10px;"></i>
                    <p style="font-size: 16px; font-weight: 600;">No pending marks issues for your courses.</p>
                </div>
            <?php else: ?>
                <?php foreach ($marks_issues as $mi): ?>
                    <div class="issue-card">
                        <div class="issue-header">
                            <div>
                                <div class="issue-course"><?php echo htmlspecialchars($mi['course_code'] . ' - ' . $mi['course_name']); ?></div>
                                <div class="issue-student"><?php echo htmlspecialchars($mi['student_name']); ?> <span class="issue-roll">(<?php echo htmlspecialchars($mi['roll_no']); ?>)</span></div>
                            </div>
                            <div class="issue-type-badge"><i class="fa fa-file-text-o"></i> <?php echo htmlspecialchars($mi['exam_type']); ?></div>
                        </div>
                        <div class="issue-desc">
                            <strong>Description:</strong><br>
                            <?php echo nl2br(htmlspecialchars($mi['description'])); ?>
                            <?php 
                            if ($mi['file_count'] > 0): 
                                $fa_stmt = $pdo->prepare("SELECT file_path FROM file_attachments WHERE ref_type='marks_issue' AND ref_id=?");
                                $fa_stmt->execute([$mi['id']]);
                                $files = $fa_stmt->fetchAll();
                                foreach ($files as $f):
                                    $disp_path = $f['file_path'];
                                    if (strpos($disp_path, '/uploads/') === 0) {
                                        $disp_path = '..' . $disp_path;
                                    }
                            ?>
                                <div style="margin-top:10px; display:flex; gap:10px;">
                                    <a href="<?php echo htmlspecialchars($disp_path); ?>" target="_blank" style="color:#2e7d32; font-size:12px; font-weight:600; padding:6px 12px; background:#e8f5e9; border-radius:6px; text-decoration:none;"><i class="fa fa-eye"></i> View Proof</a>
                                    <a href="<?php echo htmlspecialchars($disp_path); ?>" download style="color:#1565c0; font-size:12px; font-weight:600; padding:6px 12px; background:#e3f2fd; border-radius:6px; text-decoration:none;"><i class="fa fa-download"></i> Download</a>
                                </div>
                            <?php 
                                endforeach;
                            endif; 
                            ?>
                        </div>
                        <div class="issue-actions">
                            <div>
                                <?php if ($mi['file_count'] > 0): 
                                    $stmt_fa = $pdo->prepare("SELECT file_name, file_path FROM file_attachments WHERE ref_type='marks_issue' AND ref_id=?");
                                    $stmt_fa->execute([$mi['id']]);
                                    $files = $stmt_fa->fetchAll();
                                ?>
                                    <div style="display:flex; flex-direction:column; gap:8px;">
                                        <?php foreach ($files as $f): ?>
                                            <div style="display:flex; align-items:center; gap:10px;">
                                                <span class="attachment-badge"><i class="fa fa-paperclip"></i> <?php echo htmlspecialchars($f['file_name']); ?></span>
                                                <a href="..<?php echo htmlspecialchars($f['file_path']); ?>" target="_blank" style="color:#2e7d32; font-size:11px; font-weight:600; padding:4px 8px; background:#e8f5e9; border-radius:4px; text-decoration:none;"><i class="fa fa-eye"></i> View</a>
                                                <a href="..<?php echo htmlspecialchars($f['file_path']); ?>" download style="color:#1565c0; font-size:11px; font-weight:600; padding:4px 8px; background:#e3f2fd; border-radius:4px; text-decoration:none;"><i class="fa fa-download"></i> Download</a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #999; font-size: 13px;"><i class="fa fa-ban"></i> No attachments</span>
                                <?php endif; ?>
                            </div>
                            <form method="POST" class="status-form">
                                <input type="hidden" name="action" value="update_issue">
                                <input type="hidden" name="issue_type" value="marks">
                                <input type="hidden" name="issue_id" value="<?php echo $mi['id']; ?>">
                                <select name="status" class="status-select">
                                    <option value="Submitted" <?php echo $mi['status'] === 'Submitted' ? 'selected' : ''; ?>>Submitted</option>
                                    <option value="Under Review" <?php echo $mi['status'] === 'Under Review' ? 'selected' : ''; ?>>Under Review</option>
                                    <option value="Resolved" <?php echo $mi['status'] === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                                    <option value="Rejected" <?php echo $mi['status'] === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                                <button type="submit" class="update-btn">Update Status</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="tab-content" id="tab-attendance">
            <?php if (empty($att_issues)): ?>
                <div style="background: #fff; padding: 40px; text-align: center; border-radius: 10px; color: #888;">
                    <i class="fa fa-check-circle-o" style="font-size: 40px; color: #27ae60; margin-bottom: 10px;"></i>
                    <p style="font-size: 16px; font-weight: 600;">No pending attendance issues for your courses.</p>
                </div>
            <?php else: ?>
                <?php foreach ($att_issues as $ai): ?>
                    <div class="issue-card">
                        <div class="issue-header">
                            <div>
                                <div class="issue-course"><?php echo htmlspecialchars($ai['course_code'] . ' - ' . $ai['course_name']); ?></div>
                                <div class="issue-student"><?php echo htmlspecialchars($ai['student_name']); ?> <span class="issue-roll">(<?php echo htmlspecialchars($ai['roll_no']); ?>)</span></div>
                            </div>
                            <div class="issue-type-badge"><i class="fa fa-calendar"></i> <?php echo $ai['issue_date'] ? date('d M Y', strtotime($ai['issue_date'])) : 'General'; ?></div>
                        </div>
                        <div class="issue-desc">
                            <strong>Description:</strong><br>
                            <?php echo nl2br(htmlspecialchars($ai['description'])); ?>
                            <?php 
                            if ($ai['file_count'] > 0): 
                                $fa_stmt = $pdo->prepare("SELECT file_path FROM file_attachments WHERE ref_type='attendance_issue' AND ref_id=?");
                                $fa_stmt->execute([$ai['id']]);
                                $files = $fa_stmt->fetchAll();
                                foreach ($files as $f):
                                    $disp_path = $f['file_path'];
                                    if (strpos($disp_path, '/uploads/') === 0) {
                                        $disp_path = '..' . $disp_path;
                                    }
                            ?>
                                <div style="margin-top:10px; display:flex; gap:10px;">
                                    <a href="<?php echo htmlspecialchars($disp_path); ?>" target="_blank" style="color:#2e7d32; font-size:12px; font-weight:600; padding:6px 12px; background:#e8f5e9; border-radius:6px; text-decoration:none;"><i class="fa fa-eye"></i> View Proof</a>
                                    <a href="<?php echo htmlspecialchars($disp_path); ?>" download style="color:#1565c0; font-size:12px; font-weight:600; padding:6px 12px; background:#e3f2fd; border-radius:6px; text-decoration:none;"><i class="fa fa-download"></i> Download</a>
                                </div>
                            <?php 
                                endforeach;
                            endif; 
                            ?>
                        </div>
                        <div class="issue-actions">
                            <div>
                                <?php if ($ai['file_count'] > 0): 
                                    $stmt_fa = $pdo->prepare("SELECT file_name, file_path FROM file_attachments WHERE ref_type='attendance_issue' AND ref_id=?");
                                    $stmt_fa->execute([$ai['id']]);
                                    $files = $stmt_fa->fetchAll();
                                ?>
                                    <div style="display:flex; flex-direction:column; gap:8px;">
                                        <?php foreach ($files as $f): ?>
                                            <div style="display:flex; align-items:center; gap:10px;">
                                                <span class="attachment-badge"><i class="fa fa-paperclip"></i> <?php echo htmlspecialchars($f['file_name']); ?></span>
                                                <a href="..<?php echo htmlspecialchars($f['file_path']); ?>" target="_blank" style="color:#2e7d32; font-size:11px; font-weight:600; padding:4px 8px; background:#e8f5e9; border-radius:4px; text-decoration:none;"><i class="fa fa-eye"></i> View</a>
                                                <a href="..<?php echo htmlspecialchars($f['file_path']); ?>" download style="color:#1565c0; font-size:11px; font-weight:600; padding:4px 8px; background:#e3f2fd; border-radius:4px; text-decoration:none;"><i class="fa fa-download"></i> Download</a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #999; font-size: 13px;"><i class="fa fa-ban"></i> No attachments</span>
                                <?php endif; ?>
                            </div>
                            <form method="POST" class="status-form">
                                <input type="hidden" name="action" value="update_issue">
                                <input type="hidden" name="issue_type" value="attendance">
                                <input type="hidden" name="issue_id" value="<?php echo $ai['id']; ?>">
                                <select name="status" class="status-select">
                                    <option value="Submitted" <?php echo $ai['status'] === 'Submitted' ? 'selected' : ''; ?>>Submitted</option>
                                    <option value="Under Review" <?php echo $ai['status'] === 'Under Review' ? 'selected' : ''; ?>>Under Review</option>
                                    <option value="Resolved" <?php echo $ai['status'] === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                                    <option value="Rejected" <?php echo $ai['status'] === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                                <button type="submit" class="update-btn">Update Status</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
    </div>
    
    <script>
        function switchTab(tab, btn) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('tab-' + tab).classList.add('active');
            if (btn) btn.classList.add('active');
        }
    </script>
</body>
</html>
