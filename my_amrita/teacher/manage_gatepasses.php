<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$teacher_name = $_SESSION['user_name'];
$uid = $_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $gpid = intval($_POST['gatepass_id'] ?? 0);
    $check = $pdo->prepare('SELECT g.*, s.name as student_name FROM gate_passes g JOIN teacher_advisees ta ON g.student_id = ta.student_id JOIN students s ON g.student_id = s.id WHERE g.id = ? AND ta.user_id = ?');
    $check->execute([$gpid, $uid]);
    $row = $check->fetch();
    if ($row) {
        if ($_POST['action'] === 'approve') {
            $reason = trim($_POST['approval_reason'] ?? '');
            $pdo->prepare('UPDATE gate_passes SET level1_status="Approved", level1_by=?, level1_at=NOW(), approval_reason=? WHERE id=?')->execute([$teacher_name, $reason ?: null, $gpid]);
            $reason_msg = $reason ? " Reason: $reason" : "";
            $pdo->prepare('INSERT INTO notifications (student_id, title, message, type) VALUES (?, ?, ?, "gatepass")')->execute([
                $row['student_id'], 'Gate Pass Level-1 Approved',
                'Your gate pass has been approved at Level-1 by ' . $teacher_name . '. Awaiting Warden Level-2 approval.' . $reason_msg
            ]);
            $msg = 'approved';
        } elseif ($_POST['action'] === 'reject') {
            $reason = trim($_POST['rejection_reason'] ?? '');
            if (empty($reason)) { $msg = 'reason_required'; }
            else {
                $pdo->prepare('UPDATE gate_passes SET level1_status="Rejected", level1_by=?, level1_at=NOW(), status="Rejected", rejection_reason=? WHERE id=?')->execute([$teacher_name, $reason, $gpid]);
                $pdo->prepare('INSERT INTO notifications (student_id, title, message, type) VALUES (?, ?, ?, "gatepass")')->execute([
                    $row['student_id'], 'Gate Pass Rejected (L1)',
                    'Your gate pass request has been rejected by ' . $teacher_name . '. Reason: ' . $reason
                ]);
                $msg = 'rejected';
            }
        }
    }
}

// Filter params
$filter_urgency = $_GET['urgency'] ?? 'all';
$filter_student = $_GET['student'] ?? 'all';

// Get all advisee students for dropdown
$advisee_students = $pdo->prepare('SELECT s.id, s.name, s.enrollment_no FROM students s JOIN teacher_advisees ta ON s.id = ta.student_id WHERE ta.user_id = ? ORDER BY s.name');
$advisee_students->execute([$uid]);
$advisee_list = $advisee_students->fetchAll();

// Build query with filters
$sql = 'SELECT g.*, s.name as student_name, s.enrollment_no, s.hostel_block, s.hostel_room FROM gate_passes g JOIN teacher_advisees ta ON g.student_id = ta.student_id JOIN students s ON g.student_id = s.id WHERE ta.user_id = ?';
$params = [$uid];
if ($filter_urgency !== 'all') { $sql .= ' AND g.urgency = ?'; $params[] = $filter_urgency; }
if ($filter_student !== 'all') { $sql .= ' AND g.student_id = ?'; $params[] = intval($filter_student); }
$sql .= ' ORDER BY FIELD(g.level1_status,"Pending","Approved","Rejected"), g.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$passes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>Teacher - Gate Passes (Level-1)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .top-navbar { background: linear-gradient(135deg, #a4123f 0%, #c2185b 100%); }
        .approve-btn { background:linear-gradient(135deg,#27ae60,#2ecc71); color:#fff; border:none; padding:6px 14px; border-radius:6px; font-size:11px; font-weight:600; cursor:pointer; font-family:'Inter',sans-serif; transition:all 0.2s; }
        .reject-btn { background:linear-gradient(135deg,#c0392b,#e74c3c); color:#fff; border:none; padding:6px 14px; border-radius:6px; font-size:11px; font-weight:600; cursor:pointer; font-family:'Inter',sans-serif; transition:all 0.2s; }
        .approve-btn:hover, .reject-btn:hover { transform:translateY(-1px); }
        .reason-input { width:100%; padding:6px 10px; border:1px solid #e0e0e0; border-radius:6px; font-size:12px; font-family:'Inter',sans-serif; margin-top:6px; resize:vertical; }
        .filter-row { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:16px; padding:14px 18px; background:#fff; border:1px solid #e8e8e8; border-radius:10px; }
        .filter-row label { font-size:11px; font-weight:600; color:#888; text-transform:uppercase; letter-spacing:0.5px; }
        .filter-row select { padding:6px 12px; border:1px solid #e0e0e0; border-radius:6px; font-size:13px; font-family:'Inter',sans-serif; }
        .pass-card { background:#fafafa; border:1px solid #e8e8e8; border-radius:10px; padding:16px; margin-bottom:12px; }
        .pass-card.pending { border-left:4px solid #f39c12; }
        .pass-card.approved { border-left:4px solid #27ae60; }
        .pass-card.rejected { border-left:4px solid #e74c3c; }
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
    <div class="breadcrumb-bar"><a href="home.php">Teacher Home</a> <span class="sep">/</span> Gate Passes (L1)</div>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-ticket"></i> Gate Passes – Level-1 Approval</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Teacher Home</a>
        </div>

        <?php if ($msg === 'approved'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Gate pass approved at Level-1! Forwarded to Warden (Level-2).</div>
        <?php elseif ($msg === 'rejected'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Gate pass rejected. Student notified with reason.</div>
        <?php elseif ($msg === 'reason_required'): ?>
            <div class="msg-error"><i class="fa fa-exclamation-circle"></i> Rejection reason is mandatory. Please provide a reason for rejection.</div>
        <?php endif; ?>

        <div class="alert-banner info"><i class="fa fa-info-circle"></i><span class="alert-text">As a faculty advisor, you provide Level-1 approval. After your approval, gate passes go to Warden (L2) → Chief Warden (L3). <strong>Rejection reason is mandatory.</strong></span></div>

        <!-- Filters -->
        <div class="filter-row">
            <label>Urgency Type:</label>
            <select onchange="applyFilter()" id="selUrgency">
                <option value="all" <?php echo $filter_urgency==='all'?'selected':''; ?>>All Types</option>
                <option value="Emergency" <?php echo $filter_urgency==='Emergency'?'selected':''; ?>>🔴 Emergency</option>
                <option value="Urgent" <?php echo $filter_urgency==='Urgent'?'selected':''; ?>>🟡 Urgent</option>
                <option value="Normal" <?php echo $filter_urgency==='Normal'?'selected':''; ?>>🟢 Normal</option>
            </select>
            <label>Student:</label>
            <select onchange="applyFilter()" id="selStudent">
                <option value="all" <?php echo $filter_student==='all'?'selected':''; ?>>All Students</option>
                <?php foreach ($advisee_list as $as): ?>
                    <option value="<?php echo $as['id']; ?>" <?php echo $filter_student==strval($as['id'])?'selected':''; ?>><?php echo htmlspecialchars($as['name'].' ('.$as['enrollment_no'].')'); ?></option>
                <?php endforeach; ?>
            </select>
            <span style="margin-left:auto; font-size:12px; color:#888;"><?php echo count($passes); ?> pass(es)</span>
        </div>

        <?php if (empty($passes)): ?>
            <div class="card"><div class="empty-state"><i class="fa fa-ticket"></i><p>No gate pass requests matching your filters.</p></div></div>
        <?php else: ?>
            <?php foreach ($passes as $g):
                $status_class = strtolower($g['level1_status']);
            ?>
            <div class="pass-card <?php echo $status_class; ?>">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px;">
                    <div style="flex:1;">
                        <div style="font-size:14px; font-weight:600; color:#333;"><?php echo htmlspecialchars($g['student_name']); ?></div>
                        <div style="font-size:12px; color:#888;"><?php echo htmlspecialchars($g['enrollment_no']); ?> · <?php echo htmlspecialchars($g['hostel_room'] ?? '—'); ?></div>
                        <div style="margin-top:8px; font-size:13px; color:#555;"><i class="fa fa-comment" style="color:#a4123f;"></i> <?php echo htmlspecialchars($g['reason']); ?></div>
                        <div style="display:flex; gap:16px; margin-top:8px; font-size:12px; color:#666;">
                            <span><i class="fa fa-calendar" style="color:#a4123f;"></i> <?php echo date('d M Y H:i', strtotime($g['from_date'])); ?> → <?php echo date('d M Y H:i', strtotime($g['to_date'])); ?></span>
                            <span><?php
                                $urg = $g['urgency'] ?? 'Normal';
                                $uc = $urg === 'Emergency' ? 'badge-failed' : ($urg === 'Urgent' ? 'badge-pending' : 'badge-approved');
                                echo "<span class='badge $uc'>$urg</span>";
                            ?></span>
                        </div>
                        <?php if (!empty($g['rejection_reason'])): ?>
                            <div style="margin-top:8px; padding:8px 12px; background:#fde8e8; border-radius:6px; font-size:12px; color:#c62828;">
                                <strong><i class="fa fa-times-circle"></i> Rejection Reason:</strong> <?php echo htmlspecialchars($g['rejection_reason']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($g['approval_reason'])): ?>
                            <div style="margin-top:8px; padding:8px 12px; background:#e8f5e9; border-radius:6px; font-size:12px; color:#2e7d32;">
                                <strong><i class="fa fa-check-circle"></i> Approval Note:</strong> <?php echo htmlspecialchars($g['approval_reason']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div style="text-align:right;">
                        <div style="margin-bottom:4px;"><span class="badge badge-<?php echo $status_class; ?>">L1: <?php echo $g['level1_status']; ?></span></div>
                        <div><span class="badge badge-<?php echo strtolower($g['status']); ?>">Overall: <?php echo $g['status']; ?></span></div>
                    </div>
                </div>

                <?php if ($g['level1_status'] === 'Pending'): ?>
                <div style="margin-top:14px; padding-top:14px; border-top:1px solid #e8e8e8;">
                    <form method="POST" style="display:flex; flex-direction:column; gap:10px;">
                        <input type="hidden" name="gatepass_id" value="<?php echo $g['id']; ?>">
                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                            <div style="flex:1; min-width:200px;">
                                <label style="font-size:11px; font-weight:600; color:#c0392b; text-transform:uppercase;">Rejection Reason <span style="color:red;">*</span></label>
                                <textarea name="rejection_reason" class="reason-input" rows="2" placeholder="Mandatory reason for rejection..."></textarea>
                            </div>
                            <div style="flex:1; min-width:200px;">
                                <label style="font-size:11px; font-weight:600; color:#27ae60; text-transform:uppercase;">Approval Note (optional)</label>
                                <textarea name="approval_reason" class="reason-input" rows="2" placeholder="Optional note for approval..."></textarea>
                            </div>
                        </div>
                        <div style="display:flex; gap:8px;">
                            <button type="submit" name="action" value="approve" class="approve-btn"><i class="fa fa-check"></i> Approve (L1)</button>
                            <button type="submit" name="action" value="reject" class="reject-btn" onclick="var r=this.form.rejection_reason.value.trim(); if(!r){alert('Rejection reason is mandatory!');return false;}"><i class="fa fa-times"></i> Reject</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <script>
    function applyFilter() {
        var u = document.getElementById('selUrgency').value;
        var s = document.getElementById('selStudent').value;
        window.location.href = 'manage_gatepasses.php?urgency='+u+'&student='+s;
    }
    </script>
</body>
</html>
