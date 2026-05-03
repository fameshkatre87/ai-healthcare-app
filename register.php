<?php
require_once 'config/db.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $phone    = trim($_POST['phone'] ?? '');
    $gender   = $_POST['gender'] ?? '';
    $age      = (int)($_POST['age'] ?? 0);

    if (!$name || !$email || !$password || !$gender || !$age) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $db   = getDB();
        $check = $db->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'Email already registered. Please login.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt   = $db->prepare("INSERT INTO users (name,email,password,role,phone,gender,age) VALUES (?,?,?,'patient',?,?,?)");
            $stmt->bind_param("sssssi", $name, $email, $hashed, $phone, $gender, $age);
            if ($stmt->execute()) {
                $newId = (int)$db->insert_id;
                // BUG FIX: Use prepared statement for insert_id (best practice)
                $stmtRec = $db->prepare("INSERT INTO patient_records (patient_id) VALUES (?)");
                $stmtRec->bind_param("i", $newId);
                $stmtRec->execute();
                $success = 'Account created successfully! You can now <a href="login.php">login</a>.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register — HealthAI</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-left">
    <div style="text-align:center;color:#fff;max-width:380px;">
      <div style="font-size:60px;margin-bottom:20px;">🏥</div>
      <h1 style="font-size:40px;font-weight:800;font-family:'Instrument Serif',serif;font-style:italic;line-height:1.2;margin-bottom:16px;">Join HealthAI Today</h1>
      <p style="opacity:.7;line-height:1.7;font-size:16px;">Create your account and start managing your health intelligently with AI-powered insights.</p>
    </div>
  </div>

  <div class="auth-right" style="overflow-y:auto;">
    <div class="auth-form" style="max-width:480px;">
      <h2>Create Account</h2>
      <p>Fill in your details to register</p>

      <?php if ($error): ?>
        <div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success">✅ <?= $success ?></div>
      <?php endif; ?>

      <form method="POST">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div class="form-group" style="grid-column:1/-1;">
            <label class="form-label">Full Name *</label>
            <input type="text" name="name" class="form-control" placeholder="Your full name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
          </div>
          <div class="form-group" style="grid-column:1/-1;">
            <label class="form-label">Email Address *</label>
            <input type="email" name="email" class="form-control" placeholder="you@example.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Password *</label>
            <input type="password" name="password" class="form-control" placeholder="Min 6 chars" required>
          </div>
          <div class="form-group">
            <label class="form-label">Confirm Password *</label>
            <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
          </div>
          <div class="form-group">
            <label class="form-label">Phone Number</label>
            <input type="text" name="phone" class="form-control" placeholder="10-digit number" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Age *</label>
            <input type="number" name="age" class="form-control" placeholder="Your age" min="1" max="120" required value="<?= htmlspecialchars($_POST['age'] ?? '') ?>">
          </div>
          <div class="form-group" style="grid-column:1/-1;">
            <label class="form-label">Gender *</label>
            <select name="gender" class="form-control" required>
              <option value="">Select gender</option>
              <option value="Male" <?= ($_POST['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
              <option value="Female" <?= ($_POST['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
              <option value="Other" <?= ($_POST['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
            </select>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg">Create Account →</button>
      </form>
      <p style="text-align:center;margin-top:20px;font-size:14px;color:#64748b;">
        Already have an account? <a href="login.php" style="color:#0ea5e9;font-weight:600;">Login here</a>
      </p>
    </div>
  </div>
</div>
</body>
</html>
