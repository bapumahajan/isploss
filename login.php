<?php
// login.php
session_name('oss_portal');
session_start();
require_once 'includes/db.php'; // Ensure this path is correct
require_once 'includes/audit.php'; // Ensure this path is correct

// Constants
$timeout_duration = 900; // 15 minutes in seconds
$max_attempts_ip = 5;
$lockout_duration_ip = 300; // 5 minutes lockout for IP in seconds
$max_attempts_account = 3;
$lockout_duration_account = 300; // 5 minutes lockout for account in seconds

// Session Timeout
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    if (isset($_SESSION['username'])) {
        log_activity($pdo, $_SESSION['username'], 'session_timeout', null, $_SESSION['user_id'] ?? null, "Session timed out");
    }
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

// Redirect if already logged in
if (isset($_SESSION['username'])) {
    log_activity($pdo, $_SESSION['username'], 'login_redirect', 'users', $_SESSION['user_id'] ?? null, "Already logged in user redirected to dashboard");
    header('Location: dashboard.php');
    exit;
}

// Get client IP
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown IP';

// Cleanup old IP-based attempts
$pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND attempt_time < NOW() - INTERVAL ? SECOND")->execute([$client_ip, $lockout_duration_ip]);

// Count IP-based attempts in the lockout window
$stmt_ip_attempts = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempt_time >= NOW() - INTERVAL ? SECOND");
$stmt_ip_attempts->execute([$client_ip, $lockout_duration_ip]);
$attempts_ip = (int)$stmt_ip_attempts->fetchColumn();
$locked_out_ip = $attempts_ip >= $max_attempts_ip;

$message = $_SESSION['message'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['message'], $_SESSION['error']);

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$locked_out_ip) {
    // Check CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        log_activity($pdo, null, 'login_csrf_fail', null, null, "Login blocked due to invalid CSRF token from IP {$client_ip}");
        die('Invalid CSRF token');
        // Consider logging this
    }

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Basic validation
    if ($username === '' || $password === '') {
        $error = "Username and password are required.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Check account lockout
            if ($user['lockout_time'] !== null && strtotime($user['lockout_time']) > time()) {
                $error = "Account temporarily locked. Please try again after " . date('H:i:s', strtotime($user['lockout_time'])) . ".";
                log_activity($pdo, $username, 'login_account_locked', 'users', $user['id'], "Login attempt blocked for locked out user '{$username}' from IP {$client_ip}");
            } elseif (password_verify($password, $user['password'])) {
                if ((int)$user['is_active'] === 1) {
                    session_regenerate_id(true);
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['message'] = "Welcome, {$user['username']}!";

                    // Reset failed attempts and lockout time on successful login
                    $pdo->prepare("UPDATE users SET failed_attempts = 0, lockout_time = NULL WHERE id = ?")->execute([$user['id']]);

                    // Clear IP-based failed attempts
                    $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?")->execute([$client_ip]);

                    log_activity($pdo, $user['username'], 'login', 'users', $user['id'], "User '{$user['username']}' successfully logged in from IP {$client_ip}");

                    header('Location: ' . ($user['role'] === 'viewer' ? 'view_customer_data.php' : 'dashboard.php'));
                    exit();
                } else {
                    $error = "Your account is inactive.";
                    log_activity($pdo, $username, 'login_inactive', 'users', $user['id'], "Inactive user '{$username}' attempted login from IP {$client_ip}");
                    // Increment failed attempt for the account
                    $pdo->prepare("UPDATE users SET failed_attempts = failed_attempts + 1 WHERE id = ?")->execute([$user['id']]);
                    // Check for account lockout
                    $stmt_failed = $pdo->prepare("SELECT failed_attempts FROM users WHERE id = ?");
                    $stmt_failed->execute([$user['id']]);
                    $failed_attempts = (int)$stmt_failed->fetchColumn();
                    if ($failed_attempts >= $max_attempts_account) {
                        $lockout_time = date('Y-m-d H:i:s', time() + $lockout_duration_account);
                        $pdo->prepare("UPDATE users SET lockout_time = ? WHERE id = ?")->execute([$lockout_time, $user['id']]);
                        $error .= " Your account is now temporarily locked due to too many failed attempts.";
                        log_activity($pdo, $username, 'login_account_locked', 'users', $user['id'], "Account '{$username}' locked out due to too many failed attempts from IP {$client_ip}");
                    }
                }
            } else {
                $error = "Invalid username or password.";
                log_activity($pdo, $username, 'login_failed', 'users', $user['id'], "Invalid password for user '{$username}' from IP {$client_ip}");
                // Increment failed attempt for the account
                $pdo->prepare("UPDATE users SET failed_attempts = failed_attempts + 1 WHERE id = ?")->execute([$user['id']]);
                // Check for account lockout
                $stmt_failed = $pdo->prepare("SELECT failed_attempts FROM users WHERE id = ?");
                $stmt_failed->execute([$user['id']]);
                $failed_attempts = (int)$stmt_failed->fetchColumn();
                if ($failed_attempts >= $max_attempts_account) {
                    $lockout_time = date('Y-m-d H:i:s', time() + $lockout_duration_account);
                    $pdo->prepare("UPDATE users SET lockout_time = ? WHERE id = ?")->execute([$lockout_time, $user['id']]);
                    $error .= " Your account is now temporarily locked due to too many failed attempts.";
                    log_activity($pdo, $username, 'login_account_locked', 'users', $user['id'], "Account '{$username}' locked out due to too many failed attempts from IP {$client_ip}");
                }
            }
        } else {
            $error = "Invalid username or password.";
            log_activity($pdo, $username, 'login_failed', 'users', null, "Unknown username '{$username}' attempted login from IP {$client_ip}");
        }

        // Log failed IP-based attempt
        $pdo->prepare("INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, NOW())")->execute([$client_ip]);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | ISPL CUSTOMER Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css">
    <style>
        body { background: #f5f7fa; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .login-card { max-width: 400px; width: 100%; padding: 30px; background: white; box-shadow: 0 0 15px rgba(0,0,0,0.1); border-radius: 12px; }
        .login-title { font-size: 1.5rem; font-weight: 600; text-align: center; margin-bottom: 25px; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="login-title">ISPL CUSTOMER PORTAL</div>

    <?php if ($message): ?>
        <div class="alert alert-success text-center"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger text-center"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($locked_out_ip): ?>
        <div class="alert alert-warning text-center">Too many failed login attempts from your IP. Please try again after <?= round($lockout_duration_ip / 60) ?> minutes.</div>
    <?php else: ?>
        <form method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" class="form-control" name="username" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" class="form-control" name="password" required autocomplete="off">
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
    <?php endif; ?>

    <div class="d-flex justify-content-between mt-3">
        <a href="signup.php" class="btn btn-link">Sign Up</a>
    </div>
</div>
</body>
</html>