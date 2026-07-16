<?php
session_start();
if (isset($_SESSION['user_id'])) { header('Location: home.php'); exit(); }
require_once 'api/db.php';
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $dept  = trim($_POST['department'] ?? '');
    $sem   = intval($_POST['semester'] ?? 1);
    $dob   = $_POST['dob'] ?? '';
    $addr  = trim($_POST['address'] ?? '');
    $uname = trim($_POST['preferred_username'] ?? '');
    if ($name && $email && $uname) {
        $stmt = $pdo->prepare('INSERT INTO student_registrations (name, email, phone, department, semester, dob, address, preferred_username) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$name, $email, $phone, $dept, $sem, $dob, $addr, $uname]);
        $msg = 'success';
    } else { $msg = 'error'; }
}
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>My Amrita - Register</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <link rel="shortcut icon" href="images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after { box-sizing:border-box; }
        body { margin:0; font-family:'Inter','Segoe UI',sans-serif; background:linear-gradient(135deg,#0f0c29 0%,#302b63 50%,#24243e 100%); min-height:100vh; display:flex; align-items:center; justify-content:center; }
        .reg-wrapper { width:100%; max-width:520px; padding:20px; }
        .reg-card { background:rgba(255,255,255,0.07); backdrop-filter:blur(20px); border:1px solid rgba(255,255,255,0.12); border-radius:24px; padding:40px; box-shadow:0 8px 32px rgba(0,0,0,0.3); }
        .reg-card h1 { color:#fff; font-size:24px; text-align:center; margin:0 0 8px; }
        .reg-card p.subtitle { color:rgba(255,255,255,0.5); font-size:13px; text-align:center; margin-bottom:28px; }
        .form-row { display:flex; gap:12px; margin-bottom:14px; }
        .form-group { flex:1; }
        .form-group label { display:block; color:rgba(255,255,255,0.7); font-size:12px; font-weight:500; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.3px; }
        .form-input { width:100%; padding:12px 14px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1); border-radius:12px; color:#fff; font-size:14px; font-family:'Inter',sans-serif; outline:none; transition:all 0.3s; }
        .form-input:focus { border-color:rgba(164,18,63,0.6); background:rgba(255,255,255,0.1); }
        .form-input::placeholder { color:rgba(255,255,255,0.3); }
        select.form-input { appearance:none; cursor:pointer; }
        select.form-input option { background:#24243e; color:#fff; }
        .reg-btn { width:100%; padding:14px; background:linear-gradient(135deg,#a4123f,#d4264f); color:#fff; border:none; border-radius:12px; font-size:15px; font-weight:600; cursor:pointer; transition:all 0.3s; margin-top:8px; font-family:'Inter',sans-serif; }
        .reg-btn:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(164,18,63,0.4); }
        .back-link { display:block; text-align:center; margin-top:16px; color:rgba(255,255,255,0.5); font-size:13px; text-decoration:none; }
        .back-link:hover { color:#fff; }
        .msg-s { background:rgba(39,174,96,0.15); border:1px solid rgba(39,174,96,0.3); border-radius:12px; padding:14px; color:#6dd49a; font-size:13px; margin-bottom:20px; text-align:center; }
        .msg-e { background:rgba(220,53,69,0.15); border:1px solid rgba(220,53,69,0.3); border-radius:12px; padding:14px; color:#ff6b7a; font-size:13px; margin-bottom:20px; text-align:center; }
    </style>
</head>
<body>
    <div class="reg-wrapper">
        <div class="reg-card">
            <h1><i class="fa fa-pencil-square-o" style="color:#d4264f;"></i> Student Registration</h1>
            <p class="subtitle">Submit your details. Admin will review and approve your account.</p>

            <?php if ($msg === 'success'): ?>
                <div class="msg-s"><i class="fa fa-check-circle"></i> Registration submitted! You'll receive access once admin approves your application.</div>
            <?php elseif ($msg === 'error'): ?>
                <div class="msg-e"><i class="fa fa-times-circle"></i> Please fill all required fields (Name, Email, Username).</div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-row">
                    <div class="form-group"><label>Full Name *</label><input type="text" class="form-input" name="name" placeholder="Your full name" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Email *</label><input type="email" class="form-input" name="email" placeholder="you@email.com" required></div>
                    <div class="form-group"><label>Phone</label><input type="text" class="form-input" name="phone" placeholder="Mobile number"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Department</label>
                        <select class="form-input" name="department">
                            <option value="Computer Science & Engineering">CSE</option>
                            <option value="Electronics & Communication">ECE</option>
                            <option value="Mechanical Engineering">ME</option>
                            <option value="Civil Engineering">CE</option>
                            <option value="Electrical Engineering">EE</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Semester</label><select class="form-input" name="semester"><?php for($i=1;$i<=8;$i++) echo "<option value='$i'>$i</option>"; ?></select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Date of Birth</label><input type="date" class="form-input" name="dob"></div>
                    <div class="form-group"><label>Preferred Username *</label><input type="text" class="form-input" name="preferred_username" placeholder="Desired login username" required></div>
                </div>
                <div class="form-group" style="margin-bottom:14px;"><label>Address</label><input type="text" class="form-input" name="address" placeholder="Your address"></div>
                <button type="submit" class="reg-btn"><i class="fa fa-paper-plane"></i> Submit Registration</button>
            </form>
            <a href="login.php" class="back-link"><i class="fa fa-arrow-left"></i> Back to Login</a>
        </div>
    </div>
</body>
</html>
