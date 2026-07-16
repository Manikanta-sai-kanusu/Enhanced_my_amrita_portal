<?php
session_start();
if (isset($_SESSION['user_id'])) {
    $r = $_SESSION['role'] ?? 'student';
    if ($r === 'admin')           header('Location: admin/home.php');
    elseif ($r === 'teacher')     header('Location: teacher/home.php');
    elseif ($r === 'warden')      header('Location: warden/home.php');
    elseif ($r === 'chief_warden') header('Location: chief_warden/home.php');
    else                          header('Location: home.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Amrita - E-Governance Services</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <style>
        body, html {
            margin: 0; padding: 0; height: 100%;
            font-family: 'Inter', sans-serif;
            background-color: #f7f7f7;
            /* A subtle background pattern/color to mimic the sketches */
            background-image: radial-gradient(#e0e0e0 1px, transparent 1px);
            background-size: 20px 20px;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
        }

        .center-container {
            text-align: center;
            z-index: 10;
            padding: 40px;
            width: 100%;
        }

        .logo-container {
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 8px;
        }

        .logo-my {
            font-family: 'Brush Script MT', cursive, sans-serif;
            font-size: 54px;
            color: #f39c12; /* Orange */
            margin-right: -5px;
            z-index: 2;
            position: relative;
        }

        .logo-amrita {
            background-color: #a4123f; /* Maroon */
            color: white;
            font-size: 42px;
            font-weight: 400;
            padding: 8px 16px 8px 12px;
            letter-spacing: 2px;
            position: relative;
            z-index: 1;
            /* Small slant on left side to match logo slightly if needed */
        }

        .tagline {
            font-size: 13px;
            color: #333;
            letter-spacing: 0.5px;
            margin-bottom: 24px;
            text-transform: uppercase;
        }

        .divider {
            height: 1px;
            background-color: #a4123f;
            width: 150px;
            margin: 0 auto 30px auto;
        }

        .login-btn {
            display: inline-flex; align-items: center; justify-content: center;
            text-decoration: none;
            color: #a4123f;
            font-size: 24px;
            font-weight: 400;
            transition: transform 0.2s;
        }

        .login-btn:hover {
            transform: scale(1.05);
        }

        .login-icon {
            display: inline-flex;
            align-items: center; justify-content: center;
            width: 32px; height: 32px;
            background-color: #a67c00; /* brownish gold */
            color: white;
            border-radius: 50%;
            margin-left: 10px;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <div class="center-container">
        <div class="logo-container">
            <div class="logo-my">my</div>
            <div class="logo-amrita">AMRITA</div>
        </div>
        <div class="tagline">YOUR WINDOW TO E-GOVERNANCE SERVICES</div>
        <div class="divider"></div>
        <a href="login.php" class="login-btn">
            LOGIN <div class="login-icon"><i class="fa fa-chevron-right"></i></div>
        </a>
    </div>
</body>
</html>
