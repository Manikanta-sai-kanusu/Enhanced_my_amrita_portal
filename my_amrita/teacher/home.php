<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php'); exit();
}
require_once '../api/db.php';
$teacher_name = $_SESSION['user_name'];
$uid = $_SESSION['user_id'];

// Stats
$advisees = $pdo->prepare('SELECT COUNT(*) FROM teacher_advisees WHERE user_id = ?'); $advisees->execute([$uid]);
$advisee_count = $advisees->fetchColumn();
$my_courses = $pdo->prepare('SELECT COUNT(*) FROM teacher_courses WHERE user_id = ?'); $my_courses->execute([$uid]);
$course_count = $my_courses->fetchColumn();
$pending_gp = $pdo->prepare('SELECT COUNT(*) FROM gate_passes g JOIN teacher_advisees ta ON g.student_id = ta.student_id WHERE ta.user_id = ? AND g.level1_status = "Pending"'); $pending_gp->execute([$uid]);
$pending_gp_count = $pending_gp->fetchColumn();
$pending_ml = $pdo->prepare('SELECT COUNT(*) FROM medical_leaves ml JOIN teacher_advisees ta ON ml.student_id = ta.student_id WHERE ta.user_id = ? AND ml.status IN ("Submitted","Under Review")'); $pending_ml->execute([$uid]);
$pending_ml_count = $pending_ml->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8">
    <title>My Amrita - Teacher Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link rel="icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin:0; padding:0; font-family:'Inter','Segoe UI',Arial,sans-serif; background:#f5f5f5; color:#333; }

        .top-navbar {
            background: linear-gradient(135deg, #a4123f 0%, #c2185b 100%);
            color:#fff; padding:10px 20px; display:flex; align-items:center;
            justify-content:space-between; box-shadow:0 2px 8px rgba(0,0,0,0.15);
            position:sticky; top:0; z-index:1000;
        }
        .top-navbar .brand { font-size:18px; font-weight:600; letter-spacing:0.5px; }
        .top-navbar .nav-links { display:flex; align-items:center; gap:18px; }
        .top-navbar .nav-links span { font-size:13px; opacity:0.9; }

        .logout-btn {
            background:none; border:1px solid rgba(255,255,255,0.4); color:#fff;
            padding:5px 14px; border-radius:6px; font-size:12px; cursor:pointer;
            transition:all 0.2s; text-decoration:none; font-family:'Inter',sans-serif;
        }
        .logout-btn:hover { background:rgba(255,255,255,0.15); border-color:#fff; }

        .breadcrumb-bar {
            background:#fff; padding:8px 20px; font-size:13px; color:#888;
            border-bottom:1px solid #e0e0e0;
        }
        .breadcrumb-bar a { color:#a4123f; text-decoration:none; }

        .main-content { max-width:1100px; margin:0 auto; padding:20px; }

        .welcome-heading { color:#a4123f; font-size:16px; font-weight:600; margin-bottom:6px; }
        .welcome-sub { font-size:13px; color:#888; margin-bottom:20px; }

        /* Stats */
        .stats-row { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:14px; margin-bottom:26px; }
        .stat-card {
            background:#fff; border-radius:8px; padding:18px; border:1px solid #e8e8e8;
            display:flex; align-items:center; gap:14px; transition:all 0.25s;
        }
        .stat-card:hover { transform:translateY(-3px); box-shadow:0 6px 20px rgba(164,18,63,0.10); }
        .stat-icon {
            width:46px; height:46px; border-radius:8px; display:flex;
            align-items:center; justify-content:center; font-size:20px; color:#fff; flex-shrink:0;
            background: linear-gradient(135deg, #a4123f, #d4264f);
        }
        .stat-info .stat-value { font-size:22px; font-weight:700; color:#333; }
        .stat-info .stat-label { font-size:11px; color:#888; text-transform:uppercase; letter-spacing:0.5px; }

        /* Section titles */
        .section-title { font-size:14px; font-weight:700; color:#a4123f; margin-bottom:12px; text-transform:uppercase; letter-spacing:0.5px; }

        /* Module grid */
        .modules-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:26px; }
        .module-card {
            background:#fff; border:1px solid #e8e8e8; border-radius:8px;
            padding:16px 14px; display:flex; align-items:center; justify-content:space-between;
            cursor:pointer; transition:all 0.25s ease; text-decoration:none; color:#333;
        }
        .module-card:hover { transform:translateY(-3px); box-shadow:0 6px 20px rgba(164,18,63,0.12); border-color:#d4264f; }
        .module-card .module-name { font-size:13px; font-weight:600; text-transform:uppercase; letter-spacing:0.3px; color:#444; }
        .module-card .module-desc { font-size:11px; color:#999; margin-top:3px; }
        .module-card .module-icon {
            width:36px; height:36px;
            background:linear-gradient(135deg,#a4123f,#d4264f);
            border-radius:6px; display:flex; align-items:center; justify-content:center;
            color:#fff; font-size:16px; flex-shrink:0;
        }

        @media (max-width:900px) { .modules-grid { grid-template-columns:repeat(2,1fr); } }
        @media (max-width:480px) { .modules-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
    <nav class="top-navbar">
        <span class="brand">Teacher Panel (Beta)</span>
        <div class="nav-links">
            <span><?php echo htmlspecialchars($teacher_name); ?></span>
            <a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a>
        </div>
    </nav>

    <div class="breadcrumb-bar">
        <a href="home.php">Home</a>
    </div>

    <div class="main-content">
        <div class="welcome-heading">Welcome! <?php echo htmlspecialchars($teacher_name); ?></div>
        <div class="welcome-sub">Manage your courses, advisees, and academic records</div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon"><i class="fa fa-users"></i></div>
                <div class="stat-info"><div class="stat-value"><?php echo $advisee_count; ?></div><div class="stat-label">My Advisees</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa fa-book"></i></div>
                <div class="stat-info"><div class="stat-value"><?php echo $course_count; ?></div><div class="stat-label">My Courses</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa fa-credit-card"></i></div>
                <div class="stat-info"><div class="stat-value"><?php echo $pending_gp_count; ?></div><div class="stat-label">Pending Gate Passes (L1)</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa fa-medkit"></i></div>
                <div class="stat-info"><div class="stat-value"><?php echo $pending_ml_count; ?></div><div class="stat-label">Pending Med. Leaves</div></div>
            </div>
        </div>

        <!-- Faculty Advisor Section -->
        <div class="section-title"><i class="fa fa-shield"></i> Faculty Advisor</div>
        <div class="modules-grid">
            <a class="module-card" href="view_students.php">
                <div><div class="module-name">My Advisees</div><div class="module-desc">View attendance &amp; marks of your students</div></div>
                <span class="module-icon"><i class="fa fa-users"></i></span>
            </a>
            <a class="module-card" href="manage_gatepasses.php">
                <div><div class="module-name">Gate Passes (L1)</div><div class="module-desc">Level-1 approve / reject gate passes</div></div>
                <span class="module-icon"><i class="fa fa-ticket"></i></span>
            </a>
            <a class="module-card" href="manage_medical_leaves.php">
                <div><div class="module-name">Medical Leaves</div><div class="module-desc">Approve / reject medical leave requests</div></div>
                <span class="module-icon"><i class="fa fa-medkit"></i></span>
            </a>
            <a class="module-card" href="advisor_edit_marks.php">
                <div><div class="module-name">Advisee Marks & Grades</div><div class="module-desc">Edit/delete marks for advisees (all courses)</div></div>
                <span class="module-icon"><i class="fa fa-pencil-square-o"></i></span>
            </a>
            <a class="module-card" href="advisor_edit_attendance.php">
                <div><div class="module-name">Edit Advisee Attendance</div><div class="module-desc">Edit/delete attendance for advisees</div></div>
                <span class="module-icon"><i class="fa fa-check-square-o"></i></span>
            </a>
        </div>

        <!-- Course Faculty Section -->
        <div class="section-title"><i class="fa fa-pencil"></i> Course Faculty</div>
        <div class="modules-grid">
            <a class="module-card" href="edit_marks.php">
                <div><div class="module-name">Marks & Grades</div><div class="module-desc">Manage marks for your assigned courses</div></div>
                <span class="module-icon"><i class="fa fa-bar-chart-o"></i></span>
            </a>
            <a class="module-card" href="edit_attendance.php">
                <div><div class="module-name">Edit Attendance</div><div class="module-desc">Update attendance for your courses</div></div>
                <span class="module-icon"><i class="fa fa-check-square"></i></span>
            </a>
            <a class="module-card" href="view_issues.php">
                <div><div class="module-name">Issue Resolution</div><div class="module-desc">Resolve student marks & attendance issues</div></div>
                <span class="module-icon"><i class="fa fa-flag"></i></span>
            </a>
            <a class="module-card" href="add_notes.php">
                <div><div class="module-name">Add Notes</div><div class="module-desc">Upload notes for your courses</div></div>
                <span class="module-icon"><i class="fa fa-book"></i></span>
            </a>
            <a class="module-card" href="upload_assignments.php">
                <div><div class="module-name">Upload Assignments</div><div class="module-desc">Upload assignments for your courses</div></div>
                <span class="module-icon"><i class="fa fa-tasks"></i></span>
            </a>
        </div>

        <!-- General -->
        <div class="section-title"><i class="fa fa-star"></i> General</div>
        <div class="modules-grid">
            <a class="module-card" href="add_awards.php">
                <div><div class="module-name">Add Awards</div><div class="module-desc">Add rewards, publications &amp; grace marks</div></div>
                <span class="module-icon"><i class="fa fa-trophy"></i></span>
            </a>
            <a class="module-card" href="view_feedback.php">
                <div><div class="module-name">TLP Feedback</div><div class="module-desc">View student feedback for your courses</div></div>
                <span class="module-icon"><i class="fa fa-comments"></i></span>
            </a>
        </div>
    </div>

    <script src="../js/jquery-2.0.2.min.js"></script>
</body>
</html>
