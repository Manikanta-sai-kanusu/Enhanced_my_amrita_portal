<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$teacher_name = $_SESSION['user_name'];
$uid = $_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $mlid = intval($_POST['ml_id'] ?? 0);
    $check = $pdo->prepare('SELECT ml.*, s.name as student_name FROM medical_leaves ml JOIN teacher_advisees ta ON ml.student_id = ta.student_id JOIN students s ON ml.student_id = s.id WHERE ml.id = ? AND ta.user_id = ?');
    $check->execute([$mlid, $uid]);
    $row = $check->fetch();
    if ($row) {
        if ($_POST['action'] === 'approve') {
            $pdo->prepare("UPDATE medical_leaves SET status='Approved', reviewed_by=? WHERE id=?")->execute([$teacher_name, $mlid]);
            $pdo->prepare('INSERT INTO notifications (student_id, title, message, type) VALUES (?,?,?,"medical")')->execute([
                $row['student_id'], 'Medical Leave Approved', 'Your medical leave has been approved by '.$teacher_name.'.'
            ]);
            $msg = 'approved';
        } elseif ($_POST['action'] === 'reject') {
            $reason = trim($_POST['rejection_reason'] ?? '');
            if (empty($reason)) { $msg = 'reason_required'; }
            else {
                $pdo->prepare("UPDATE medical_leaves SET status='Rejected', reviewed_by=?, rejection_reason=? WHERE id=?")->execute([$teacher_name, $reason, $mlid]);
                $pdo->prepare('INSERT INTO notifications (student_id, title, message, type) VALUES (?,?,?,"medical")')->execute([
                    $row['student_id'], 'Medical Leave Rejected', 'Your medical leave has been rejected by '.$teacher_name.'. Reason: '.$reason
                ]);
                $msg = 'rejected';
            }
        }
    }
}

// Filter
$filter_student = $_GET['student'] ?? 'all';
$advisees = $pdo->prepare('SELECT s.id, s.name, s.enrollment_no FROM students s JOIN teacher_advisees ta ON s.id = ta.student_id WHERE ta.user_id = ? ORDER BY s.name');
$advisees->execute([$uid]);
$advisee_list = $advisees->fetchAll();

$sql = 'SELECT ml.*, s.name as student_name, s.enrollment_no FROM medical_leaves ml JOIN teacher_advisees ta ON ml.student_id = ta.student_id JOIN students s ON ml.student_id = s.id WHERE ta.user_id = ?';
$params = [$uid];
if ($filter_student !== 'all') { $sql .= ' AND ml.student_id = ?'; $params[] = intval($filter_student); }
$sql .= ' ORDER BY FIELD(ml.status,"Submitted","Under Review","Approved","Rejected"), ml.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$leaves = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>Teacher - Medical Leaves</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .top-navbar { background: linear-gradient(135deg, #a4123f 0%, #c2185b 100%); }
        .approve-btn { background:linear-gradient(135deg,#27ae60,#2ecc71); color:#fff; border:none; padding:6px 14px; border-radius:6px; font-size:11px; font-weight:600; cursor:pointer; font-family:'Inter',sans-serif; }
        .reject-btn { background:linear-gradient(135deg,#c0392b,#e74c3c); color:#fff; border:none; padding:6px 14px; border-radius:6px; font-size:11px; font-weight:600; cursor:pointer; font-family:'Inter',sans-serif; }
        .leave-card { background:#fff; border:1px solid #e8e8e8; border-radius:10px; padding:18px; margin-bottom:14px; }
        .reason-input { width:100%; padding:6px 10px; border:1px solid #e0e0e0; border-radius:6px; font-size:12px; font-family:'Inter',sans-serif; margin-top:6px; }
        .filter-row { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:16px; padding:14px 18px; background:#fff; border:1px solid #e8e8e8; border-radius:10px; }
        .filter-row label { font-size:11px; font-weight:600; color:#888; text-transform:uppercase; }
        .filter-row select { padding:6px 12px; border:1px solid #e0e0e0; border-radius:6px; font-size:13px; font-family:'Inter',sans-serif; }
    </style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Teacher Panel (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($teacher_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="home.php">Teacher Home</a> <span class="sep">/</span> Medical Leaves</div>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-medkit"></i> Medical Leave Requests</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Teacher Home</a>
        </div>

        <?php if ($msg === 'approved'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Medical leave approved. Student notified.</div>
        <?php elseif ($msg === 'rejected'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Medical leave rejected with reason. Student notified.</div>
        <?php elseif ($msg === 'reason_required'): ?>
            <div class="msg-error"><i class="fa fa-exclamation-circle"></i> Rejection reason is mandatory!</div>
        <?php endif; ?>

        <!-- Student Filter -->
        <div class="filter-row">
            <label>Select Student:</label>
            <select onchange="window.location.href='manage_medical_leaves.php?student='+this.value" id="selStudent">
                <option value="all" <?php echo $filter_student==='all'?'selected':''; ?>>All Students</option>
                <?php foreach ($advisee_list as $as): ?>
                    <option value="<?php echo $as['id']; ?>" <?php echo $filter_student==strval($as['id'])?'selected':''; ?>><?php echo htmlspecialchars($as['name'].' ('.$as['enrollment_no'].')'); ?></option>
                <?php endforeach; ?>
            </select>
            <span style="margin-left:auto; font-size:12px; color:#888;"><?php echo count($leaves); ?> request(s)</span>
        </div>

        <?php if (empty($leaves)): ?>
            <div class="card"><div class="empty-state"><i class="fa fa-medkit"></i><p>No medical leave requests.</p></div></div>
        <?php else: ?>
            <?php foreach ($leaves as $ml): ?>
            <div class="leave-card" style="border-left:4px solid <?php echo match($ml['status']) { 'Approved'=>'#27ae60', 'Rejected'=>'#e74c3c', default=>'#f39c12' }; ?>;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px;">
                    <div>
                        <strong style="font-size:14px; color:#333;"><?php echo htmlspecialchars($ml['student_name']); ?></strong>
                        <span style="font-size:12px; color:#888; margin-left:8px;"><?php echo htmlspecialchars($ml['enrollment_no']); ?></span>
                        <div style="font-size:13px; color:#555; margin-top:6px;"><i class="fa fa-stethoscope" style="color:#a4123f;"></i> <?php echo htmlspecialchars($ml['condition_desc']); ?></div>
                        <div style="font-size:12px; color:#666; margin-top:4px;">
                            <i class="fa fa-calendar"></i> <?php echo date('d M Y', strtotime($ml['from_date'])); ?> → <?php echo date('d M Y', strtotime($ml['to_date'])); ?>
                            <?php if ($ml['doctor_name']): ?> · <i class="fa fa-user-md"></i> <?php echo htmlspecialchars($ml['doctor_name']); ?><?php endif; ?>
                            <?php if ($ml['hospital']): ?> · <i class="fa fa-hospital-o"></i> <?php echo htmlspecialchars($ml['hospital']); ?><?php endif; ?>
                        </div>
                        <?php if ($ml['medical_cert_file']): ?>
                            <div style="margin-top:6px;">
                                <a href="<?php echo htmlspecialchars($ml['medical_cert_file']); ?>" target="_blank" style="color:#2e7d32; font-size:11px; font-weight:600; padding:4px 10px; background:#e8f5e9; border-radius:6px; text-decoration:none;"><i class="fa fa-eye"></i> View Certificate</a>
                                <a href="<?php echo htmlspecialchars($ml['medical_cert_file']); ?>" download style="color:#1565c0; font-size:11px; font-weight:600; padding:4px 10px; background:#e3f2fd; border-radius:6px; text-decoration:none; margin-left:4px;"><i class="fa fa-download"></i> Download</a>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($ml['rejection_reason'])): ?>
                            <div style="margin-top:8px; padding:8px 12px; background:#fde8e8; border-radius:6px; font-size:12px; color:#c62828;">
                                <strong><i class="fa fa-times-circle"></i> Rejection Reason:</strong> <?php echo htmlspecialchars($ml['rejection_reason']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div><span class="badge badge-<?php echo strtolower(str_replace(' ','-',$ml['status'])); ?>"><?php echo $ml['status']; ?></span></div>
                </div>

                <?php if (in_array($ml['status'], ['Submitted','Under Review'])): ?>
                <div style="margin-top:14px; padding-top:14px; border-top:1px solid #e8e8e8;">
                    <form method="POST">
                        <input type="hidden" name="ml_id" value="<?php echo $ml['id']; ?>">
                        <div style="margin-bottom:10px;">
                            <label style="font-size:11px; font-weight:600; color:#c0392b; text-transform:uppercase;">Rejection Reason <span style="color:red;">*</span></label>
                            <textarea name="rejection_reason" class="reason-input" rows="2" placeholder="Mandatory reason for rejection..."></textarea>
                        </div>
                        <div style="display:flex; gap:8px;">
                            <button type="submit" name="action" value="approve" class="approve-btn"><i class="fa fa-check"></i> Approve</button>
                            <button type="submit" name="action" value="reject" class="reject-btn" onclick="var r=this.form.rejection_reason.value.trim(); if(!r){alert('Rejection reason is mandatory!');return false;}"><i class="fa fa-times"></i> Reject</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
