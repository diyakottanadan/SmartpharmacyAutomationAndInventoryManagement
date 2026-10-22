
<?php
session_start();
include("db_connect.php");

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = "";
$message = "";
$developmentOtp = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';

    if (!$csrf || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        $error = "Invalid request. Please try again.";
    } else {
        $role = $_POST['role'] ?? '';
        $username = trim($_POST['username'] ?? '');

        if (!in_array($role, ['admin', 'pharmacist'], true)) {
            $error = "Please select a valid account type.";
        } elseif ($username === '' || strlen($username) > 25) {
            $error = "Please enter a valid username.";
        } else {
            $sql = $role === 'admin'
                ? "SELECT Username FROM admin WHERE Username = ? LIMIT 1"
                : "SELECT Username FROM pharmacist WHERE Username = ? LIMIT 1";

            $stmt = mysqli_prepare($conn, $sql);
            $exists = false;

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "s", $username);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $exists = $result && mysqli_num_rows($result) === 1;
                mysqli_stmt_close($stmt);
            }

            // Clear any previous recovery attempt.
            unset($_SESSION['recovery']);

            if ($exists) {
                $otp = (string) random_int(100000, 999999);

                $_SESSION['recovery'] = [
                    'role' => $role,
                    'username' => $username,
                    'otp_hash' => password_hash($otp, PASSWORD_DEFAULT),
                    'expires' => time() + 300,
                    'attempts' => 0,
                    'verified' => false
                ];

                // Development-only: show OTP on the local page.
                // Never enable this behavior on a public website.
                $developmentOtp = $otp;
                $message = "OTP generated. For local testing only, use the code shown below.";
            } else {
                $error = "Account not found. Please check the account type and username.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Forgot Password - Smart Pharmacy</title>
<style>
body{font-family:Arial,sans-serif;background:#f2f2f2;margin:0}
.box{max-width:400px;width:90%;margin:60px auto;background:white;padding:25px;border-radius:10px;box-shadow:0 0 10px #ccc}
h2{text-align:center}
input,select,button{box-sizing:border-box;width:100%;padding:11px;margin:9px 0;border:1px solid #ccc;border-radius:5px}
button{background:green;color:white;border:0;cursor:pointer}
.error{background:#f8d7da;color:#842029;padding:10px;border-radius:5px}
.success{background:#d1e7dd;color:#0f5132;padding:10px;border-radius:5px}
.otp{font-size:28px;font-weight:bold;letter-spacing:6px;text-align:center;background:#fff3cd;padding:15px}
a{color:#1769aa}
</style>
</head>
<body>
<div class="box">
<h2>Forgot Password</h2>
<p>Select your account type and enter your username.</p>

<?php if ($error !== ''): ?>
<div class="error"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($message !== ''): ?>
<div class="success"><?= e($message) ?></div>
<?php endif; ?>

<form method="post">
<input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

<label>Account Type</label>
<select name="role" required>
<option value="">-- Select Account --</option>
<option value="admin">Admin</option>
<option value="pharmacist">Pharmacist</option>
</select>

<label>Username</label>
<input type="text" name="username" maxlength="25" required autocomplete="username">

<button type="submit">Generate OTP</button>
</form>

<?php if ($developmentOtp !== ''): ?>
<h3>Development OTP</h3>
<p>This code is displayed only for local project testing.</p>
<div class="otp"><?= e($developmentOtp) ?></div>
<p><a href="verify_otp.php">Continue to OTP verification</a></p>
<?php endif; ?>

<p><a href="login.php">Back to Login</a></p>
</div>
</body>
</html>