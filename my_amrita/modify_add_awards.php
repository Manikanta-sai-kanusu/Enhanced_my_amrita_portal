<?php
$content = <<<'EOF'
<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$teacher_name = $_SESSION['user_name'];
$uid = $_SESSION['user_id'];
$msg = '';

$all_students_query = $pdo->query('SELECT id, name, enrollment_no, batch, department as branch, section, semester FROM students ORDER BY name');
$all_students = $all_students_query->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_award') {
    $sid = intval($_POST['student_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $type = trim($_POST['award_type'] ?? 'Award');
    $grace = intval($_POST['grace_marks'] ?? 0);
    
    $file_path = null;
    if (isset($_FILES['award_file']) && $_FILES['award_file']['error'] === UPLOAD_ERR_OK) {
        $dir = '../uploads/awards/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $ext = pathinfo($_FILES['award_file']['name'], PATHINFO_EXTENSION);
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $title) . '_' . time() . '.' . $ext;
        $dest = $dir . $safe;
        if (move_uploaded_file($_FILES['award_file']['tmp_name'], $dest)) {
            $file_path = '/uploads/awards/' . $safe;
        }
    }
    
    if ($sid && $title) {
        $pdo->prepare('INSERT INTO awards (student_id, title, description, award_type, grace_marks, certificate_file, awarded_by) VALUES (?,?,?,?,?,?,?)')->execute([$sid, $title, $desc, $type, $grace, $file_path, $teacher_name]);
        $pdo->prepare('INSERT INTO notifications (student_id, title, message, type) VALUES (?,?,?,"award")')->execute([
            $sid, 'New Award: '.$title, 'You have been awarded "'.$title.'" by '.$teacher_name.'. Grace marks: '.$grace
        ]);
        $msg = 'add_success';
    } else { $msg = 'add_error'; }
}

$aids = array_column($all_students, 'id');
$awards = [];
if (!empty($aids)) {
    // Only fetch awards for advisees maybe? Or all awards given by this teacher?
    $stmt = $pdo->prepare("SELECT a.*, s.name as student_name, s.enrollment_no FROM awards a JOIN students s ON a.student_id = s.id WHERE a.awarded_by = ? ORDER BY a.created_at DESC");
    $stmt->execute([$teacher_name]);
    $awards = $stmt->fetchAll();
}

$batches = ['2022-2026', '2023-2027', '2024-2028', '2025-2029'];
$branches = ['Computer Science & Engineering', 'Artificial Intelligence & Data Science', 'Robotics & Artificial Intelligence', 'Electronics & Communication', 'Electrical & Electronics', 'Electronics & Computer', 'Mechanical Engineering'];
$sections = ['A', 'B', 'C', 'D'];
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>Teacher - Awards</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .top-navbar { background: linear-gradient(135deg, #a4123f 0%, #c2185b 100%); }
        .award-card { background:#fff; border:1px solid #e8e8e8; border-radius:10px; padding:16px; margin-bottom:12px; border-left:4px solid #f39c12; }
    </style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Teacher Panel (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($teacher_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="home.php">Teacher Home</a> <span class="sep">/</span> Awards</div>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-trophy"></i> Awards & Grace Marks</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Teacher Home</a>
        </div>

        <?php if ($msg === 'add_success'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> Award added & student notified!</div>
        <?php elseif ($msg === 'add_error'): ?><div class="msg-error"><i class="fa fa-times-circle"></i> Fill all required fields.</div><?php endif; ?>

        <div class="card form-section">
            <h3><i class="fa fa-plus-circle"></i> Add Award/Publication</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_award">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Filter Batch (Optional)</label>
                        <select class="form-control" id="f_batch">
                            <option value="">-- All Batches --</option>
                            <?php foreach($batches as $b): ?><option value="<?php echo $b; ?>"><?php echo $b; ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Filter Branch (Optional)</label>
                        <select class="form-control" id="f_branch">
                            <option value="">-- All Branches --</option>
                            <?php foreach($branches as $b): ?><option value="<?php echo $b; ?>"><?php echo $b; ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Filter Section (Optional)</label>
                        <select class="form-control" id="f_section">
                            <option value="">-- All Sections --</option>
                            <?php foreach($sections as $s): ?><option value="<?php echo $s; ?>"><?php echo $s; ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Filter Semester (Optional)</label>
                        <select class="form-control" id="f_sem">
                            <option value="">-- All Semesters --</option>
                            <?php for($i=1;$i<=8;$i++): ?><option value="<?php echo $i; ?>">Semester <?php echo $i; ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Student</label>
                        <select class="form-control" name="student_id" id="f_student" required>
                            <option value="">-- Select Student --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Award Type</label>
                        <select class="form-control" name="award_type">
                            <option value="Award">Award</option>
                            <option value="Publication">Publication</option>
                            <option value="Competition">Competition</option>
                            <option value="Hackathon">Hackathon</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Title</label><input type="text" class="form-control" name="title" required placeholder="Award title..."></div>
                    <div class="form-group">
                        <label>Grace Marks</label>
                        <input type="number" class="form-control" name="grace_marks" min="0" value="0">
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:14px;"><label>Description</label><textarea class="form-control" name="description" rows="2" placeholder="Details..."></textarea></div>
                <div class="form-group">
                    <label>Certificate / Proof Document (Optional)</label>
                    <input type="file" class="form-control" name="award_file" accept=".pdf,.jpg,.jpeg,.png">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa fa-plus"></i> Add Award</button>
            </form>
        </div>

        <div class="card">
            <h2 class="card-title">My Award Entries</h2>
            <?php if (empty($awards)): ?>
                <div class="empty-state"><i class="fa fa-trophy"></i><p>No awards recorded yet.</p></div>
            <?php else: ?>
                <?php foreach ($awards as $aw): ?>
                <div class="award-card">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap;">
                        <div>
                            <strong style="font-size:14px;"><?php echo htmlspecialchars($aw['title']); ?></strong>
                            <span class="badge badge-approved" style="margin-left:6px;"><?php echo htmlspecialchars($aw['award_type'] ?? 'Award'); ?></span>
                            <div style="font-size:12px; color:#888; margin-top:4px;">
                                <i class="fa fa-user"></i> <?php echo htmlspecialchars($aw['student_name']); ?> (<?php echo htmlspecialchars($aw['enrollment_no']); ?>)
                                • Grace: <strong><?php echo $aw['grace_marks']; ?></strong>
                                • <?php echo date('d M Y', strtotime($aw['created_at'])); ?>
                            </div>
                            <?php if ($aw['description']): ?><div style="font-size:13px; color:#555; margin-top:6px;"><?php echo htmlspecialchars($aw['description']); ?></div><?php endif; ?>
                        </div>
                        <?php if ($aw['certificate_file']): ?>
                        <div style="display:flex; gap:6px;">
                            <a href="<?php echo htmlspecialchars($aw['certificate_file']); ?>" target="_blank" style="color:#2e7d32; font-size:11px; font-weight:600; padding:5px 12px; background:#e8f5e9; border-radius:6px; text-decoration:none;"><i class="fa fa-eye"></i> View</a>
                            <a href="<?php echo htmlspecialchars($aw['certificate_file']); ?>" download style="color:#1565c0; font-size:11px; font-weight:600; padding:5px 12px; background:#e3f2fd; border-radius:6px; text-decoration:none;"><i class="fa fa-download"></i> Download</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <script>
        const students = <?php echo json_encode($all_students); ?>;
        const b = document.getElementById('f_batch'),
              br = document.getElementById('f_branch'),
              se = document.getElementById('f_section'),
              sm = document.getElementById('f_sem'),
              st = document.getElementById('f_student');
              
        function updateStudents() {
            const vb = b.value, vbr = br.value, vse = se.value, vsm = sm.value;
            st.innerHTML = '<option value="">-- Select Student --</option>';
            students.forEach(s => {
                if ((!vb || s.batch === vb) &&
                    (!vbr || s.branch === vbr) &&
                    (!vse || s.section === vse) &&
                    (!vsm || s.semester == vsm)) {
                    const opt = document.createElement('option');
                    opt.value = s.id;
                    opt.textContent = s.name + ' (' + s.enrollment_no + ')';
                    st.appendChild(opt);
                }
            });
        }
        
        [b, br, se, sm].forEach(el => el.addEventListener('change', updateStudents));
        updateStudents();
    </script>
</body>
</html>
EOF;
file_put_contents('c:\xampp\htdocs\my_amrita\teacher\add_awards.php', $content);
?>
