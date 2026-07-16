<?php
session_start();

// If already logged in, redirect based on role
if (isset($_SESSION['user_id'])) {
    $r = $_SESSION['role'] ?? 'student';
    if ($r === 'admin')           header('Location: admin/home.php');
    elseif ($r === 'teacher')     header('Location: teacher/home.php');
    elseif ($r === 'warden')      header('Location: warden/home.php');
    elseif ($r === 'chief_warden') header('Location: chief_warden/home.php');
    else                          header('Location: home.php');
    exit();
}

// Handle login form submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'api/db.php';

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        $stmt = $pdo->prepare('SELECT u.*, s.enrollment_no, s.semester FROM users u LEFT JOIN students s ON u.linked_student_id = s.id WHERE u.username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['role']          = $user['role'];
            $_SESSION['user_name']     = $user['name'];
            $_SESSION['student_id']    = $user['linked_student_id'];
            $_SESSION['student_name']  = $user['name'];
            $_SESSION['enrollment_no'] = $user['enrollment_no'] ?? '';
            $_SESSION['username']      = $username;
            $_SESSION['semester']      = $user['semester'] ?? 4;

            if ($user['role'] === 'admin')           header('Location: admin/home.php');
            elseif ($user['role'] === 'teacher')     header('Location: teacher/home.php');
            elseif ($user['role'] === 'warden')      header('Location: warden/home.php');
            elseif ($user['role'] === 'chief_warden') header('Location: chief_warden/home.php');
            else                                     header('Location: home.php');
            exit();
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en-us">

<head>
    <meta charset="utf-8">
    <title>My Amrita - Login</title>
    <meta name="description" content="My Amrita Student Portal Login">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <!-- CSS Links -->
    <link rel="stylesheet" type="text/css" media="screen" href="css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" media="screen" href="css/font-awesome.min.css">

    <!-- FAVICONS -->
    <link rel="shortcut icon" href="images/am.png" type="image/x-icon">
    <link rel="icon" href="images/am.png" type="image/x-icon">

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0; padding: 0; min-height: 100vh;
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
            display: flex; align-items: center; justify-content: center;
            overflow: hidden; position: relative;
        }

        body::before, body::after {
            content: ''; position: absolute; border-radius: 50%;
            filter: blur(80px); opacity: 0.4; animation: float 8s ease-in-out infinite;
        }

        body::before {
            width: 400px; height: 400px;
            background: radial-gradient(circle, #a4123f, transparent 70%);
            top: -100px; right: -100px;
        }

        body::after {
            width: 350px; height: 350px;
            background: radial-gradient(circle, #f5a623, transparent 70%);
            bottom: -80px; left: -80px; animation-delay: -4s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-30px) scale(1.05); }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes shimmer {
            0% { background-position: -200% center; }
            100% { background-position: 200% center; }
        }

        .login-wrapper {
            position: relative; z-index: 10;
            width: 100%; max-width: 440px; padding: 20px;
            animation: fadeInUp 0.8s ease-out;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.07);
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 24px; padding: 48px 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }

        .login-header { text-align: center; margin-bottom: 36px; }

        .login-logo {
            width: 80px; height: 80px; border-radius: 20px;
            object-fit: contain; margin-bottom: 16px;
            background: rgba(255, 255, 255, 0.1); padding: 12px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
        }

        .login-header h1 {
            color: #ffffff; font-size: 28px; font-weight: 700;
            margin: 0 0 6px 0; letter-spacing: -0.5px;
        }

        .login-header p {
            color: rgba(255, 255, 255, 0.5); font-size: 14px;
            margin: 0; font-weight: 400;
        }

        .form-group { margin-bottom: 22px; position: relative; }

        .form-group label {
            display: block; color: rgba(255, 255, 255, 0.7);
            font-size: 13px; font-weight: 500; margin-bottom: 8px;
            letter-spacing: 0.3px; text-transform: uppercase;
        }

        .input-wrapper { position: relative; }

        .input-wrapper i {
            position: absolute; left: 16px; top: 50%;
            transform: translateY(-50%); color: rgba(255, 255, 255, 0.35);
            font-size: 16px; transition: color 0.3s ease;
        }

        .form-input {
            width: 100%; padding: 14px 16px 14px 46px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 14px; color: #ffffff; font-size: 15px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease; outline: none;
        }

        .form-input::placeholder { color: rgba(255, 255, 255, 0.3); }

        .form-input:focus {
            border-color: rgba(164, 18, 63, 0.6);
            background: rgba(255, 255, 255, 0.1);
            box-shadow: 0 0 0 4px rgba(164, 18, 63, 0.15);
        }

        .input-wrapper:focus-within i { color: rgba(255, 255, 255, 0.7); }

        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear {
            display: none;
        }

        .password-toggle {
            position: absolute; right: 16px; top: 50%;
            transform: translateY(-50%); background: none;
            border: none; color: rgba(255, 255, 255, 0.35);
            cursor: pointer; font-size: 16px; padding: 4px;
            transition: color 0.3s ease;
        }

        .password-toggle:hover { color: rgba(255, 255, 255, 0.7); }

        .login-btn {
            width: 100%; padding: 16px;
            background: linear-gradient(135deg, #a4123f, #d4264f);
            color: #ffffff; border: none; border-radius: 14px;
            font-size: 16px; font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer; transition: all 0.3s ease;
            margin-top: 8px; position: relative;
            overflow: hidden; letter-spacing: 0.5px;
        }

        .login-btn::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.15), transparent);
            background-size: 200% 100%;
            animation: shimmer 3s ease-in-out infinite;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(164, 18, 63, 0.4);
        }

        .login-btn:active { transform: translateY(0); }

        .error-message {
            background: rgba(220, 53, 69, 0.15);
            border: 1px solid rgba(220, 53, 69, 0.3);
            border-radius: 12px; padding: 12px 16px;
            color: #ff6b7a; font-size: 13px; margin-bottom: 20px;
            display: none; align-items: center; gap: 10px;
            animation: fadeInUp 0.3s ease-out;
        }

        .error-message.show { display: flex; }

        .login-footer {
            text-align: center; margin-top: 28px; padding-top: 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .login-footer p { color: rgba(255, 255, 255, 0.4); font-size: 13px; margin: 0; }

        /* Quick-login role pills */
        .role-pills {
            display: flex; gap: 8px; justify-content: center;
            margin-top: 20px; flex-wrap: wrap;
        }
        .role-pill {
            padding: 7px 14px; border-radius: 20px; font-size: 11px;
            font-weight: 600; cursor: pointer; transition: all 0.25s;
            border: 1px solid rgba(255,255,255,0.15);
            color: rgba(255,255,255,0.6); background: rgba(255,255,255,0.05);
            font-family: 'Inter', sans-serif; text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .role-pill:hover {
            border-color: rgba(164,18,63,0.6); color: #fff;
            background: rgba(164,18,63,0.2);
        }
        .role-pill i { margin-right: 4px; }

        .register-link {
            display: block; text-align: center; margin-top: 16px;
            color: rgba(255,255,255,0.5); font-size: 13px; text-decoration: none;
            transition: color 0.2s;
        }
        .register-link:hover { color: #fff; }

        .particles {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            pointer-events: none; z-index: 1;
        }

        .particle {
            position: absolute; width: 3px; height: 3px;
            background: rgba(255, 255, 255, 0.15); border-radius: 50%;
            animation: particleFloat linear infinite;
        }

        @keyframes particleFloat {
            0% { transform: translateY(100vh) scale(0); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-10vh) scale(1); opacity: 0; }
        }

        @media (max-width: 480px) {
            .login-card { padding: 36px 28px; border-radius: 20px; }
            .login-header h1 { font-size: 24px; }
        }
    </style>
</head>

<body>

    <div class="particles" id="particles"></div>

    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <img src="images/am.png" alt="My Amrita" class="login-logo">
                <h1>My Amrita</h1>
                <p>Sign in to access e-governance services</p>
            </div>

            <div class="error-message <?php echo $error ? 'show' : ''; ?>" id="errorMsg">
                <i class="fa fa-exclamation-circle"></i>
                <span id="errorText"><?php echo htmlspecialchars($error ?: 'Invalid username or password'); ?></span>
            </div>

            <form method="POST" action="login.php" id="loginForm">
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-wrapper">
                        <input type="text" class="form-input" id="username" name="username"
                            placeholder="Enter your username" autocomplete="username" required
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                        <i class="fa fa-user"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <input type="password" class="form-input" id="password" name="password"
                            placeholder="Enter your password" autocomplete="current-password" required
                            oninput="document.getElementById('togglePassword').style.display = this.value.length > 0 ? 'block' : 'none';">
                        <i class="fa fa-lock"></i>
                        <button type="button" class="password-toggle" id="togglePassword"
                            onclick="togglePasswordVisibility()" style="display:none;">
                            <i class="fa fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="login-btn" id="loginBtn">
                    <span class="btn-text">Sign In</span>
                </button>
            </form>

            <!-- Quick Login Role Pills -->
            <div class="role-pills">
                <button class="role-pill" onclick="quickLogin('bl.en.u4cse23102@bl.students.amrita.edu')"><i class="fa fa-graduation-cap"></i> Student</button>
                <button class="role-pill" onclick="quickLogin('bp_peeta@blr.amrita.edu')"><i class="fa fa-book"></i> Teacher</button>
                <button class="role-pill" onclick="quickLogin('admin')"><i class="fa fa-shield"></i> Admin</button>
                <button class="role-pill" onclick="quickLogin('a_gouri@blr.amrita.edu')"><i class="fa fa-building"></i> Warden</button>
                <button class="role-pill" onclick="quickLogin('g_latha@blr.amrita.edu')"><i class="fa fa-star"></i> Chief Warden</button>
            </div>

            <a href="register.php" class="register-link"><i class="fa fa-pencil-square-o"></i> Register as New Student</a>

            <div class="login-footer">
                <p>Amrita Vishwa Vidyapeetham &copy; 2026</p>
            </div>
        </div>
    </div>

    <script src="js/jquery-2.0.2.min.js"></script>

    <script>
        // Generate floating particles
        (function createParticles() {
            var container = document.getElementById('particles');
            for (var i = 0; i < 30; i++) {
                var particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDuration = (Math.random() * 10 + 8) + 's';
                particle.style.animationDelay = (Math.random() * 10) + 's';
                particle.style.width = (Math.random() * 3 + 1) + 'px';
                particle.style.height = particle.style.width;
                container.appendChild(particle);
            }
        })();

        function togglePasswordVisibility() {
            var passwordInput = document.getElementById('password');
            var toggleIcon = document.querySelector('#togglePassword i');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'fa fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'fa fa-eye';
            }
        }

        function quickLogin(user) {
            document.getElementById('username').value = user;
            document.getElementById('password').value = '';
            document.getElementById('password').focus();
        }
    </script>

</body>
</html>
