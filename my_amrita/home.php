<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
$role = $_SESSION['role'] ?? 'student';
if ($role === 'admin') { header('Location: admin/home.php'); exit(); }
if ($role === 'teacher') { header('Location: teacher/home.php'); exit(); }
if ($role === 'warden') { header('Location: warden/home.php'); exit(); }
if ($role === 'chief_warden') { header('Location: chief_warden/home.php'); exit(); }

$student_name = $_SESSION['student_name'];
$enrollment   = $_SESSION['enrollment_no'];
$student_id   = $_SESSION['student_id'];

require_once 'api/db.php';

$stmt_res = $pdo->prepare('SELECT residence_type FROM students WHERE id = ?');
$stmt_res->execute([$student_id]);
$res_type = $stmt_res->fetchColumn();
$is_day_scholar = ($res_type === 'Day Scholar');
$att_alerts = [];
try {
    $stmt = $pdo->prepare('SELECT * FROM attendance_alerts WHERE student_id = ? AND is_read = 0');
    $stmt->execute([$student_id]);
    $att_alerts = $stmt->fetchAll();
} catch(Exception $e) {}

$fee_pending = [];
$fee_overdue = [];
try {
    $stmt = $pdo->prepare('SELECT * FROM fee_notifications WHERE student_id = ? AND status != "Completed" ORDER BY deadline ASC');
    $stmt->execute([$student_id]);
    $fee_pending = $stmt->fetchAll();
    $fee_overdue = array_filter($fee_pending, fn($f) => $f['status'] === 'Overdue');
} catch(Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en-us">

<head>
    <meta charset="utf-8">
    <title>My Amrita - Student Portal</title>
    <meta name="description" content="My Amrita Student Portal - Home Dashboard">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <link rel="stylesheet" type="text/css" media="screen" href="css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" media="screen" href="css/font-awesome.min.css">

    <link rel="shortcut icon" href="images/am.png" type="image/x-icon">
    <link rel="icon" href="images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0; padding: 0;
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
            background: #f5f5f5; color: #333;
        }

        .top-navbar {
            background: linear-gradient(135deg, #a4123f 0%, #c2185b 100%);
            color: #fff; padding: 10px 20px;
            display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            position: sticky; top: 0; z-index: 1000;
        }

        .top-navbar .brand { font-size: 18px; font-weight: 600; letter-spacing: 0.5px; }

        .top-navbar .nav-links { display: flex; align-items: center; gap: 18px; }

        .top-navbar .nav-links a {
            color: rgba(255, 255, 255, 0.85); text-decoration: none;
            font-size: 13px; transition: color 0.2s;
        }

        .top-navbar .nav-links a:hover { color: #fff; }

        .breadcrumb-bar {
            background: #fff; padding: 8px 20px;
            font-size: 13px; color: #888;
            border-bottom: 1px solid #e0e0e0;
        }

        .breadcrumb-bar a { color: #a4123f; text-decoration: none; }

        .main-content { max-width: 1100px; margin: 0 auto; padding: 20px; }

        .welcome-heading {
            color: #a4123f; font-size: 16px;
            font-weight: 600; margin-bottom: 16px;
        }

        .top-section { display: flex; gap: 20px; margin-bottom: 30px; }

        .quote-card {
            flex: 2.5; background: transparent;
            padding: 20px 30px; position: relative;
            display: flex; flex-direction: column; justify-content: space-between;
            border: none;
        }


        .quote-card .big-quote {
            font-size: 70px; color: #facc15;
            font-family: Georgia, serif; line-height: 0.8; margin-bottom: 8px;
            text-shadow: 0 2px 4px rgba(250, 204, 21, 0.3);
        }

        .quote-card .quote-text {
            font-size: 14.5px; color: #b33951; line-height: 1.6;
            margin-bottom: 24px; max-width: 500px;
            font-weight: 400;
        }

        .quote-card .quote-author { color: #a4123f; font-weight: 700; font-size: 15px; }

        .amma-photo-container {
            position: absolute; top: 10px; right: 40px;
        }

        .quote-card .amma-photo {
            width: 140px; height: 140px; border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 0 0 3px #6be2f5;
        }

        .amma-photo-container::after {
            content: ''; position: absolute;
            bottom: 12px; right: 18px;
            width: 14px; height: 14px;
            background: #17c6c4;
            border: 2px solid #fff;
            border-radius: 50%;
        }

        .alerts-card {
            flex: 1; background: #fff;
            border: 1px solid #e8e8e8; border-radius: 8px;
            padding: 0; max-height: 260px;
            display: flex; flex-direction: column;
        }

        .alerts-card .alerts-header {
            color: #a4123f; font-size: 16px; font-weight: 700;
            padding: 16px 20px 12px; border-bottom: 1px solid #eee;
        }

        .alerts-card .alerts-body { padding: 12px 20px; overflow-y: auto; flex: 1; }

        .alerts-card .alert-item {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 0; border-bottom: 1px solid #f2f2f2;
            font-size: 13px; color: #444; cursor: pointer; transition: color 0.2s;
        }

        .alerts-card .alert-item:last-child { border-bottom: none; }
        .alerts-card .alert-item:hover { color: #a4123f; }
        .alerts-card .alert-item i { color: #c2185b; font-size: 11px; }
        .alerts-card .alert-item .alert-icon { color: #a4123f; font-size: 14px; }

        .alerts-card .alerts-footer { text-align: center; padding: 8px; border-top: 1px solid #eee; }
        .alerts-card .alerts-footer i { color: #888; cursor: pointer; transition: color 0.2s; }
        .alerts-card .alerts-footer i:hover { color: #a4123f; }

        .section-label {
            font-size: 13px; font-weight: 700; color: #a4123f; margin-bottom: 10px;
            text-transform: uppercase; letter-spacing: 0.5px;
        }

        .modules-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 24px; }

        .module-card {
            background: #fff; border: 1px solid #e8e8e8; border-radius: 8px;
            padding: 16px 14px; display: flex; align-items: center;
            justify-content: space-between; cursor: pointer;
            transition: all 0.25s ease; text-decoration: none; color: #333;
        }

        .module-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(164, 18, 63, 0.12);
            border-color: #d4264f;
        }

        .module-card .module-name {
            font-size: 13px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.3px; color: #444;
        }

        .module-card .module-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, #a4123f, #d4264f);
            border-radius: 6px; display: flex; align-items: center;
            justify-content: center; color: #fff; font-size: 16px; flex-shrink: 0;
        }

        .logout-btn {
            background: none; border: 1px solid rgba(255, 255, 255, 0.4);
            color: #fff; padding: 5px 14px; border-radius: 6px;
            font-size: 12px; cursor: pointer; transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .logout-btn:hover { background: rgba(255, 255, 255, 0.15); border-color: #fff; }

        @media (max-width: 900px) {
            .modules-grid { grid-template-columns: repeat(2, 1fr); }
            .top-section { flex-direction: column; }
        }

        @media (max-width: 480px) {
            .modules-grid { grid-template-columns: 1fr; }
            .top-navbar .brand { font-size: 15px; }
        }
    </style>
</head>

<body>

    <!-- TOP NAVBAR -->
    <nav class="top-navbar">
        <span class="brand">Student Portal (Beta)</span>
        <div class="nav-links">
            <span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($student_name); ?></span>
            <a href="logout.php" class="logout-btn">
                <i class="fa fa-sign-out"></i> Logout
            </a>
        </div>
    </nav>

    <!-- BREADCRUMB -->
    <div class="breadcrumb-bar">
        <a href="home.php">Home</a>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">

        <!-- Attendance Alert Banner -->
        <?php if (!empty($att_alerts)): ?>
        <div style="padding:14px 20px; border-radius:10px; margin-bottom:16px; display:flex; align-items:center; gap:12px; font-size:13px; font-weight:500; background:linear-gradient(135deg,#fff8e6,#fff3cd); border:1px solid #f0d060; color:#856404; animation:slideDown 0.4s ease;">
            <i class="fa fa-exclamation-triangle" style="font-size:18px;"></i>
            <span style="flex:1;"><strong>Attendance Alert:</strong> Your attendance is low in <?php echo count($att_alerts); ?> course(s). <a href="pages/attendance.php" style="color:#a4123f; font-weight:600;">View Details →</a></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($fee_overdue)): ?>
        <div style="padding:14px 20px; border-radius:10px; margin-bottom:16px; display:flex; align-items:center; gap:12px; font-size:13px; font-weight:500; background:linear-gradient(135deg,#fde8e8,#f8d7da); border:1px solid #f5c6c6; color:#721c24;">
            <i class="fa fa-exclamation-circle" style="font-size:18px;"></i>
            <span style="flex:1;"><strong>Fee Overdue:</strong> You have <?php echo count($fee_overdue); ?> overdue payment(s). <a href="pages/fee.php" style="color:#a4123f; font-weight:600;">Pay Now →</a></span>
        </div>
        <?php elseif (!empty($fee_pending)): ?>
        <div style="padding:14px 20px; border-radius:10px; margin-bottom:16px; display:flex; align-items:center; gap:12px; font-size:13px; font-weight:500; background:linear-gradient(135deg,#e3f2fd,#e1f5fe); border:1px solid #b3e5fc; color:#0277bd;">
            <i class="fa fa-info-circle" style="font-size:18px;"></i>
            <span style="flex:1;"><strong>Fee Notice:</strong> You have <?php echo count($fee_pending); ?> pending fee payment(s). <a href="pages/fee.php" style="color:#a4123f; font-weight:600;">View Details →</a></span>
        </div>
        <?php endif; ?>

        <!-- Welcome -->
        <div class="welcome-heading">
            Welcome! <?php echo htmlspecialchars($student_name); ?>( <?php echo htmlspecialchars($enrollment); ?> )
        </div>

        <!-- Top section: Quote + Alerts -->
        <div class="top-section">
            <div class="quote-card">
                <div>
                    <div class="big-quote">&ldquo;</div>
                    <p class="quote-text">
                        Youngsters need to understand the real purpose of life. They need
                        courage and wisdom to face the challenges of life. Only with that
                        understanding they can become the light of the world. If we care for
                        them responsibly and mold their whole character with love, then the
                        future of the world will be safe
                    </p>
                    <div class="quote-author">&mdash; Amma</div>
                </div>
                <div class="amma-photo-container">
                    <img src="images/amma.png" alt="Amma" class="amma-photo">
                </div>
            </div>

            <div class="alerts-card">
                <div class="alerts-header">Alerts</div>
                <div class="alerts-body">
                    <?php if (!empty($fee_pending)): foreach ($fee_pending as $fee): ?>
                    <div class="alert-item" onclick="window.location.href='pages/fee.php'" title="<?php echo htmlspecialchars($fee['title']); ?>">
                        <i class="fa fa-chevron-right"></i>
                        <span class="alert-icon" style="color:<?php echo $fee['status']==='Overdue'?'#e74c3c':'#f5a623'; ?>;"><i class="fa fa-money"></i></span>
                        <span>Fee Due: <strong><?php echo date('d M Y', strtotime($fee['deadline'])); ?></strong> (₹<?php echo $fee['new_amount']; ?>)</span>
                    </div>
                    <?php endforeach; endif; ?>
                    <div class="alert-item">
                        <i class="fa fa-chevron-right"></i>
                        <span class="alert-icon"><i class="fa fa-info-circle"></i></span>
                        <span>Payment Help Document</span>
                    </div>
                    <div class="alert-item">
                        <i class="fa fa-chevron-right"></i>
                        <span class="alert-icon"><i class="fa fa-info-circle"></i></span>
                        <span>Propeild loan process flow</span>
                    </div>
                </div>
                <div class="alerts-footer">
                    <i class="fa fa-chevron-down"></i>
                </div>
            </div>
        </div>

        <!-- Academic Services -->
        <div class="section-label"><i class="fa fa-graduation-cap"></i> Academic</div>
        <div class="modules-grid">
            <a class="module-card" href="pages/attendance.php">
                <span class="module-name">Class Attendance</span>
                <span class="module-icon"><i class="fa fa-check-square"></i></span>
            </a>
            <a class="module-card" href="pages/marks.php">
                <span class="module-name">Marks & Grades</span>
                <span class="module-icon"><i class="fa fa-bar-chart-o"></i></span>
            </a>
            <a class="module-card" href="pages/timetable.php">
                <span class="module-name">Timetable</span>
                <span class="module-icon"><i class="fa fa-clock-o"></i></span>
            </a>
            <a class="module-card" href="pages/notes.php">
                <span class="module-name">Course Notes</span>
                <span class="module-icon"><i class="fa fa-book"></i></span>
            </a>
            <a class="module-card" href="pages/academic_calendar.php">
                <span class="module-name">Academic Calendar</span>
                <span class="module-icon"><i class="fa fa-calendar"></i></span>
            </a>
            <a class="module-card" href="pages/course_feedback.php">
                <span class="module-name">Course Feedback</span>
                <span class="module-icon"><i class="fa fa-comment"></i></span>
            </a>
            <a class="module-card" href="pages/tlp_feedback.php">
                <span class="module-name">TLP Feedback</span>
                <span class="module-icon"><i class="fa fa-comments"></i></span>
            </a>
            <a class="module-card" href="pages/supplementary.php">
                <span class="module-name">Supplementary</span>
                <span class="module-icon"><i class="fa fa-plus-square"></i></span>
            </a>
        </div>

        <!-- Personal -->
        <div class="section-label"><i class="fa fa-user"></i> Personal</div>
        <div class="modules-grid">
            <a class="module-card" href="pages/profile.php">
                <span class="module-name">Profile</span>
                <span class="module-icon"><i class="fa fa-user"></i></span>
            </a>
            <a class="module-card" href="pages/id_card.php">
                <span class="module-name">ID Card</span>
                <span class="module-icon"><i class="fa fa-credit-card"></i></span>
            </a>
            <?php if (!$is_day_scholar): ?>
            <a class="module-card" href="pages/hostel_attendance.php">
                <span class="module-name">Hostel Attendance</span>
                <span class="module-icon"><i class="fa fa-building"></i></span>
            </a>
            <?php endif; ?>
            <a class="module-card" href="pages/leave.php">
                <span class="module-name">Leave List</span>
                <span class="module-icon"><i class="fa fa-calendar"></i></span>
            </a>
            <?php if (!$is_day_scholar): ?>
            <a class="module-card" href="pages/gatepass.php">
                <span class="module-name">Gate Pass</span>
                <span class="module-icon"><i class="fa fa-ticket"></i></span>
            </a>
            <?php endif; ?>
            <a class="module-card" href="pages/counselling.php">
                <span class="module-name">Counselling</span>
                <span class="module-icon"><i class="fa fa-heart"></i></span>
            </a>
            <a class="module-card" href="pages/awards.php">
                <span class="module-name">Awards</span>
                <span class="module-icon"><i class="fa fa-trophy"></i></span>
            </a>
            <a class="module-card" href="pages/documents.php">
                <span class="module-name">Documents</span>
                <span class="module-icon"><i class="fa fa-upload"></i></span>
            </a>
        </div>

        <!-- Services & Finance -->
        <div class="section-label"><i class="fa fa-cogs"></i> Services & Finance</div>
        <div class="modules-grid">
            <a class="module-card" href="pages/payments.php">
                <span class="module-name">Payments</span>
                <span class="module-icon"><i class="fa fa-credit-card"></i></span>
            </a>
            <a class="module-card" href="pages/fee.php">
                <span class="module-name">Fee & Pay</span>
                <span class="module-icon"><i class="fa fa-money"></i></span>
            </a>
            <a class="module-card" href="pages/refund.php">
                <span class="module-name">Refund</span>
                <span class="module-icon"><i class="fa fa-undo"></i></span>
            </a>
            <a class="module-card" href="pages/services.php">
                <span class="module-name">Services & Complaints</span>
                <span class="module-icon"><i class="fa fa-cogs"></i></span>
            </a>
            <a class="module-card" href="pages/incidents.php">
                <span class="module-name">Incidents</span>
                <span class="module-icon"><i class="fa fa-exclamation-circle"></i></span>
            </a>
            <a class="module-card" href="pages/downloads.php">
                <span class="module-name">Downloads</span>
                <span class="module-icon"><i class="fa fa-download"></i></span>
            </a>
        </div>

        <!-- Examinations -->
        <div class="section-label"><i class="fa fa-pencil-square-o"></i> Examinations</div>
        <div class="modules-grid">
            <a class="module-card" href="pages/admitcard.php">
                <span class="module-name">Admit Card</span>
                <span class="module-icon"><i class="fa fa-file-text-o"></i></span>
            </a>
            <a class="module-card" href="pages/seating.php">
                <span class="module-name">Seating</span>
                <span class="module-icon"><i class="fa fa-th"></i></span>
            </a>
            <a class="module-card" href="pages/events.php">
                <span class="module-name">Events</span>
                <span class="module-icon"><i class="fa fa-calendar-o"></i></span>
            </a>
            <a class="module-card" href="pages/assignments.php">
                <span class="module-name">Assignments</span>
                <span class="module-icon"><i class="fa fa-tasks"></i></span>
            </a>
        </div>

    </div>

    <script src="js/jquery-2.0.2.min.js"></script>

</body>
</html>
