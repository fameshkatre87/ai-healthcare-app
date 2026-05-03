<?php
require_once 'config/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];

            // Redirect by role
            if ($user['role'] === 'admin')  header('Location: dashboard/admin.php');
            elseif ($user['role'] === 'doctor') header('Location: dashboard/doctor.php');
            else header('Location: dashboard/patient.php');
            exit();
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — HealthAI</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-page">
  <!-- Left Panel -->
  <div class="auth-left">
    <div class="auth-left-content">
      <div style="font-size:48px;margin-bottom:16px;">❤️</div>
      <h1>Healthcare made smarter with AI</h1>
      <p>Predict diseases, book doctors, manage your health — all in one place.</p>
      <div class="auth-features">
        <div class="auth-feature"><span>🔬</span><span>AI Disease Prediction</span></div>
        <div class="auth-feature"><span>👨‍⚕️</span><span>Doctor Recommendations</span></div>
        <div class="auth-feature"><span>📅</span><span>Appointment Booking</span></div>
        <div class="auth-feature"><span>📊</span><span>Report Analysis</span></div>
      </div>
    </div>
  </div>

  <!-- Right Panel -->
  <div class="auth-right">
    <div class="auth-form">
      <h2>Welcome back</h2>
      <p>Login to your HealthAI account</p>

      <?php if ($error): ?>
        <div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-control" placeholder="you@example.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" placeholder="Enter password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:8px;">Login →</button>
      </form>

      <div class="divider">or</div>
      <p style="text-align:center;font-size:14px;color:#64748b;">
        Don't have an account? <a href="register.php" style="color:#0ea5e9;font-weight:600;">Register here</a>
      </p>

      <div class="alert alert-info" style="margin-top:24px;font-size:12px;">
        <div><strong>Demo Logins:</strong></div>
        <div>Patient: ravi@gmail.com / patient123</div>
        <div>Doctor: priya@healthcare.com / doctor123</div>
        <div>Admin: admin@healthcare.com / admin123</div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
