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
$categories = ['Class Notes','Lab','Case Studies','Reference Books','PPTs','Assignments'];

// Handle cohort filtering
$table_alias = 's';
require_once '../admin/filter_logic.php';

// Upload note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_note') {
        $course = trim($_POST['course_code'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? 'Class Notes');
        $valid = false; $course_name_val = '';
        foreach ($my_courses as $c) { if ($c['course_code'] === $course) { $valid = true; $course_name_val = $c['course_name']; break; } }
        
        if ($valid && $title && isset($_FILES['note_file']) && $_FILES['note_file']['error'] === UPLOAD_ERR_OK) {
            $dir = '../uploads/notes/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $ext = pathinfo($_FILES['note_file']['name'], PATHINFO_EXTENSION);
            $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $title) . '_' . time() . '.' . $ext;
            $dest = $dir . $safe;
            if (move_uploaded_file($_FILES['note_file']['tmp_name'], $dest)) {
                $file_path = '/uploads/notes/' . $safe;
                // Insert for ALL students who have this course AND match the cohort filter
                $stmt = $pdo->prepare('SELECT DISTINCT a.student_id, s.semester FROM attendance a JOIN students s ON a.student_id = s.id WHERE a.course_code = ? ' . $filter_sql);
                $params = array_merge([$course], $filter_params);
                $stmt->execute($params);
                $sids = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($sids)) { $sids = $pdo->query("SELECT id as student_id, semester FROM students")->fetchAll(PDO::FETCH_ASSOC); }
                foreach ($sids as $row) {
                    $pdo->prepare('INSERT INTO notes (student_id, course_code, course_name, semester, title, file_name, file_path, category) VALUES (?,?,?,?,?,?,?,?)')->execute([$row['student_id'], $course, $course_name_val, $row['semester'], $title, $_FILES['note_file']['name'], $file_path, $category]);
                }
                // Notify
                foreach ($sids as $row) {
                    $pdo->prepare('INSERT INTO notifications (student_id, title, message, type) VALUES (?,?,?,"notes")')->execute([
                        $row['student_id'], 'New '.$category.' Added', 'New "'.$title.'" added for '.$course_name_val.' by '.$teacher_name.'.'
                    ]);
                }
                $msg = 'add_success';
            }
        } else { $msg = 'add_error'; }
    } elseif ($_POST['action'] === 'reply_doubt') {
        $did = intval($_POST['doubt_id'] ?? 0);
        $reply = trim($_POST['reply'] ?? '');
        if ($did && $reply) {
            $pdo->prepare("UPDATE notes_doubts SET response=?, status='Resolved' WHERE id=?")->execute([$reply, $did]);
            $d = $pdo->prepare("SELECT nd.*, n.title as note_title FROM notes_doubts nd JOIN notes n ON nd.note_id = n.id WHERE nd.id=?"); $d->execute([$did]); $doubt = $d->fetch();
            if ($doubt) {
                $pdo->prepare('INSERT INTO notifications (student_id, title, message, type) VALUES (?,?,?,"notes")')->execute([
                    $doubt['student_id'], 'Doubt Answered', 'Your doubt regarding "'.$doubt['note_title'].'" has been answered by '.$teacher_name.'.'
                ]);
            }
            $msg = 'reply_success';
        }
    }
}

// Get notes for selected course (only show distinct notes uploaded by this teacher/for this course)
$notes = []; $doubts = [];
if ($selected_course) {
    // Only show notes that apply to the selected cohort
    $stmt = $pdo->prepare("SELECT n.*, COUNT(DISTINCT n.student_id) as dist_count 
                           FROM notes n 
                           JOIN students s ON n.student_id = s.id
                           WHERE n.course_code = ? " . $filter_sql . " 
                           GROUP BY n.title, n.category 
                           ORDER BY n.category, n.uploaded_at DESC");
    $params = array_merge([$selected_course], $filter_params);
    $stmt->execute($params);
    $notes = $stmt->fetchAll();
    
    // Fetch doubts from students matching the cohort
    $dstmt = $pdo->prepare("SELECT nd.*, n.title as note_title, s.name as student_name, s.enrollment_no, s.batch, s.department as branch, s.section, s.semester as current_sem 
                            FROM notes_doubts nd 
                            JOIN students s ON nd.student_id = s.id 
                            JOIN notes n ON nd.note_id = n.id
                            WHERE nd.course_code = ? " . $filter_sql . "
                            ORDER BY nd.status ASC, nd.created_at DESC");
    $dparams = array_merge([$selected_course], $filter_params);
    $dstmt->execute($dparams);
    $doubts = $dstmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>Teacher - Notes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .top-navbar { background: linear-gradient(135deg, #a4123f 0%, #c2185b 100%); }
        .course-card { background:#fff; border:1px solid #e8e8e8; border-radius:10px; padding:18px; text-decoration:none; color:#333; transition:all 0.25s; display:block; border-left:4px solid #a4123f; }
        .course-card:hover { transform:translateY(-3px); box-shadow:0 6px 20px rgba(164,18,63,0.12); }
        .course-card.active { border-left-color:#27ae60; background:#f0fff4; }
        .note-item { background:#fafafa; border:1px solid #e8e8e8; border-radius:8px; padding:12px 16px; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center; }
        .cat-label { font-size:10px; font-weight:700; text-transform:uppercase; padding:2px 8px; border-radius:4px; display:inline-block; }
        .cat-class-notes { background:#e3f2fd; color:#1565c0; }
        .cat-lab { background:#f3e5f5; color:#7b1fa2; }
        .cat-case-studies { background:#fff3e0; color:#e65100; }
        .cat-reference-books { background:#e8f5e9; color:#2e7d32; }
        .cat-ppts { background:#fce4ec; color:#c2185b; }
        .cat-assignments { background:#ede7f6; color:#4527a0; }
        .doubt-card { background:#fff8e1; border:1px solid #f0e0a0; border-radius:8px; padding:14px; margin-bottom:10px; }
        .reply-area { margin-top:8px; }
        .reply-area textarea { width:100%; padding:6px 10px; border:1px solid #e0e0e0; border-radius:6px; font-size:12px; font-family:'Inter',sans-serif; }
    </style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Teacher Panel (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($teacher_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="home.php">Teacher Home</a> <span class="sep">/</span> Add Notes</div>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-book"></i> Notes & Materials</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Teacher Home</a>
        </div>

        <?php if ($msg === 'add_success'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Note uploaded & students notified!</div>
        <?php elseif ($msg === 'add_error'): ?>
            <div class="msg-error"><i class="fa fa-times-circle"></i> Failed to upload. Fill all fields and select a file.</div>
        <?php elseif ($msg === 'reply_success'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Reply sent to student!</div>
        <?php endif; ?>

        <?php 
        $extra_filters = '<label style="font-size:11px; font-weight:600; color:#888; text-transform:uppercase;">Select Course:</label>
            <select name="course" required style="padding:6px 12px; border:1px solid #e0e0e0; border-radius:6px; font-size:13px; font-family:\'Inter\',sans-serif;">
                <option value="">-- Choose Course --</option>';
        foreach ($my_courses as $c) {
            $sel = ($selected_course === $c['course_code']) ? 'selected' : '';
            $extra_filters .= '<option value="' . htmlspecialchars($c['course_code']) . '" ' . $sel . '>' . htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']) . '</option>';
        }
        $extra_filters .= '</select>';

        $filter_count = count($doubts);
        include '../admin/filter_ui.php'; 
        ?>

        <?php if (!$selected_course): ?>
            <div class="card"><div class="empty-state"><i class="fa fa-hand-pointer-o"></i><p>Please select a course to upload or view notes.</p></div></div>
        <?php else: ?>
        <?php $cname = ''; foreach ($my_courses as $c) { if ($c['course_code']===$selected_course) $cname = $c['course_name']; } ?>
        
        <!-- Upload Form -->
        <div class="card form-section">
            <h3><i class="fa fa-upload"></i> Upload Material for <?php echo htmlspecialchars($selected_course); ?></h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_note">
                <input type="hidden" name="course_code" value="<?php echo htmlspecialchars($selected_course); ?>">
                <div class="form-row">
                    <div class="form-group"><label>Title</label><input type="text" class="form-control" name="title" placeholder="e.g. Chapter 5 Summary" required></div>
                    <div class="form-group">
                        <label>Category</label>
                        <select class="form-control" name="category" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:14px;"><label>File</label><input type="file" class="form-control" name="note_file" required accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.png,.jpg,.jpeg,.zip"></div>
                <button type="submit" class="submit-btn" style="background:linear-gradient(135deg,#a4123f,#d4264f);"><i class="fa fa-upload"></i> Upload Material</button>
            </form>
        </div>

        <!-- Existing Notes by Category -->
        <div class="card">
            <h2 class="card-title"><?php echo htmlspecialchars($selected_course.' – '.$cname); ?> Materials</h2>
            <?php if (empty($notes)): ?>
                <div class="empty-state"><i class="fa fa-book"></i><p>No materials uploaded yet.</p></div>
            <?php else: ?>
                <?php
                $grouped = [];
                foreach ($notes as $n) { $grouped[$n['category'] ?? 'Class Notes'][] = $n; }
                foreach ($grouped as $cat => $items): $cat_cls = 'cat-'.strtolower(str_replace(' ','-',$cat));
                ?>
                <h4 style="margin:16px 0 8px; font-size:13px; color:#666;"><span class="cat-label <?php echo $cat_cls; ?>"><?php echo $cat; ?></span> (<?php echo count($items); ?>)</h4>
                <?php foreach ($items as $n): ?>
                <div class="note-item">
                    <div>
                        <strong style="font-size:13px;"><?php echo htmlspecialchars($n['title']); ?></strong>
                        <div style="font-size:11px; color:#888;"><?php echo htmlspecialchars($n['file_name'] ?? ''); ?> · <?php echo date('d M Y', strtotime($n['uploaded_at'])); ?></div>
                    </div>
                    <div style="display:flex; gap:6px;">
                        <a href="..<?php echo htmlspecialchars($n['file_path']); ?>" target="_blank" style="color:#2e7d32; font-size:11px; font-weight:600; padding:4px 10px; background:#e8f5e9; border-radius:6px; text-decoration:none;"><i class="fa fa-eye"></i> View</a>
                        <a href="..<?php echo htmlspecialchars($n['file_path']); ?>" download style="color:#1565c0; font-size:11px; font-weight:600; padding:4px 10px; background:#e3f2fd; border-radius:6px; text-decoration:none;"><i class="fa fa-download"></i> Download</a>
                    </div>
                </div>
                <?php endforeach; endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Student Doubts/Issues -->
        <div class="card">
            <h2 class="card-title"><i class="fa fa-question-circle" style="color:#f39c12;"></i> Student Doubts & Issues</h2>
            <?php if (empty($doubts)): ?>
                <div class="empty-state"><i class="fa fa-question-circle"></i><p>No doubts/issues from students.</p></div>
            <?php else: ?>
                <?php foreach ($doubts as $d): ?>
                <div class="doubt-card" style="<?php echo $d['status']==='Resolved' ? 'border-color:#a5d6a7; background:#f0fff4;' : ''; ?>">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                        <div>
                            <strong style="color:#333;">Regarding: <?php echo htmlspecialchars($d['note_title']); ?></strong>
                            <div style="font-size:12px; color:#888; margin-top:2px;">by <?php echo htmlspecialchars($d['student_name']); ?> (<?php echo htmlspecialchars($d['enrollment_no']); ?>) · <?php echo htmlspecialchars($d['batch'] . ' | ' . $d['branch'] . ' | Sec ' . $d['section'] . ' | Sem ' . $d['current_sem']); ?> · <?php echo date('d M Y', strtotime($d['created_at'])); ?></div>
                            <div style="font-size:13px; color:#555; margin-top:6px;"><strong>Q: </strong><?php echo htmlspecialchars($d['issue_text']); ?></div>
                            <?php if ($d['proof_file']): ?>
                                <div style="margin-top:4px;"><a href="<?php echo htmlspecialchars($d['proof_file']); ?>" target="_blank" style="color:#1565c0; font-size:11px;"><i class="fa fa-paperclip"></i> Attached proof</a></div>
                            <?php endif; ?>
                        </div>
                        <span class="badge badge-<?php echo $d['status']==='Open'?'pending':'approved'; ?>"><?php echo $d['status']; ?></span>
                    </div>
                    <?php if ($d['response']): ?>
                        <div style="margin-top:10px; padding:10px; background:#e8f5e9; border-radius:6px;">
                            <strong style="font-size:12px; color:#2e7d32;"><i class="fa fa-reply"></i> Reply by Teacher:</strong>
                            <div style="font-size:13px; color:#333; margin-top:4px;"><?php echo htmlspecialchars($d['response']); ?></div>
                        </div>
                    <?php elseif ($d['status'] === 'Open'): ?>
                        <form method="POST" class="reply-area">
                            <input type="hidden" name="action" value="reply_doubt">
                            <input type="hidden" name="doubt_id" value="<?php echo $d['id']; ?>">
                            <textarea name="reply" rows="2" placeholder="Type your reply..." required></textarea>
                            <button type="submit" style="margin-top:6px; background:linear-gradient(135deg,#27ae60,#2ecc71); color:#fff; border:none; padding:5px 14px; border-radius:6px; font-size:11px; font-weight:600; cursor:pointer;"><i class="fa fa-reply"></i> Reply</button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
<script src="../js/upload_validator.js"></script>
</body>
</html>

