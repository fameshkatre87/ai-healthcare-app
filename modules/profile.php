<?php
require_once '../config/db.php';
requireLogin();

$db      = getDB();
$uid     = (int)$_SESSION['user_id'];
$success = '';
$error   = '';

// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $age     = (int)($_POST['age'] ?? 0);
    $gender  = $_POST['gender'] ?? '';
    $address = trim($_POST['address'] ?? '');

    // Medical record fields
    $blood   = $_POST['blood_group'] ?? '';
    $weight  = (float)($_POST['weight'] ?? 0);
    $height  = (float)($_POST['height'] ?? 0);
    $allergy = trim($_POST['allergies'] ?? '');
    $chronic = trim($_POST['chronic_diseases'] ?? '');
    $meds    = trim($_POST['current_medications'] ?? '');

    if ($name) {
        // BUG FIX: Was using real_escape_string + raw query (SQL injection risk) — use prepared statements
        $stmt = $db->prepare("UPDATE users SET name=?, phone=?, age=?, gender=?, address=? WHERE id=?");
        $stmt->bind_param("ssissi", $name, $phone, $age, $gender, $address, $uid);
        $stmt->execute();
        $_SESSION['name'] = $name;

        $existing = $db->prepare("SELECT id FROM patient_records WHERE patient_id=?");
        $existing->bind_param("i", $uid);
        $existing->execute();
        $hasRecord = $existing->get_result()->num_rows > 0;

        if ($hasRecord) {
            $stmt2 = $db->prepare("UPDATE patient_records SET blood_group=?,weight=?,height=?,allergies=?,chronic_diseases=?,current_medications=? WHERE patient_id=?");
            $stmt2->bind_param("sddsssi", $blood, $weight, $height, $allergy, $chronic, $meds, $uid);
            $stmt2->execute();
        } else {
            $stmt2 = $db->prepare("INSERT INTO patient_records (patient_id,blood_group,weight,height,allergies,chronic_diseases,current_medications) VALUES (?,?,?,?,?,?,?)");
            $stmt2->bind_param("isddsss", $uid, $blood, $weight, $height, $allergy, $chronic, $meds);
            $stmt2->execute();
        }

        // Change password
        if (!empty($_POST['new_password'])) {
            if ($_POST['new_password'] !== ($_POST['confirm_password'] ?? '')) {
                $error = 'New passwords do not match.';
            } else {
                $stmtPw = $db->prepare("SELECT password FROM users WHERE id=?");
                $stmtPw->bind_param("i", $uid);
                $stmtPw->execute();
                $userRow = $stmtPw->get_result()->fetch_assoc();
                if (password_verify($_POST['current_password'] ?? '', $userRow['password'])) {
                    $hashed = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                    $stmtUpPw = $db->prepare("UPDATE users SET password=? WHERE id=?");
                    $stmtUpPw->bind_param("si", $hashed, $uid);
                    $stmtUpPw->execute();
                    $success = 'Profile and password updated!';
                } else {
                    $error = 'Current password is incorrect.';
                }
            }
        }
        if (!$error) $success = $success ?: 'Profile updated successfully!';
    } else {
        $error = 'Name is required.';
    }
}

$stmtU = $db->prepare("SELECT * FROM users WHERE id=?");
$stmtU->bind_param("i", $uid);
$stmtU->execute();
$user = $stmtU->get_result()->fetch_assoc();

$stmtR = $db->prepare("SELECT * FROM patient_records WHERE patient_id=?");
$stmtR->bind_param("i", $uid);
$stmtR->execute();
$record = $stmtR->get_result()->fetch_assoc() ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile — HealthAI</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="sidebar-logo"><div class="sidebar-logo-icon">❤️</div><div><h2>Health<span>AI</span></h2><p>Patient Portal</p></div></div>
    <nav class="sidebar-nav">
      <div class="nav-section">Main</div>
      <a href="../dashboard/patient.php" class="nav-item"><span class="nav-icon">🏠</span> Dashboard</a>
      <a href="symptom_checker.php" class="nav-item"><span class="nav-icon">🔬</span> Symptom Checker</a>
      <a href="doctor_suggest.php" class="nav-item"><span class="nav-icon">👨‍⚕️</span> Find Doctors</a>
      <a href="appointment.php" class="nav-item"><span class="nav-icon">📅</span> Appointments</a>
      <div class="nav-section">Health</div>
      <a href="reports.php" class="nav-item"><span class="nav-icon">📊</span> My Reports</a>
      <a href="profile.php" class="nav-item active"><span class="nav-icon">👤</span> My Profile</a>
      <div class="nav-section">Account</div>
      <a href="../logout.php" class="nav-item"><span class="nav-icon">🚪</span> Logout</a>
    </nav>
    <div class="sidebar-user">
      <div class="avatar"><?= strtoupper(substr($user['name'],0,1)) ?></div>
      <div class="user-info"><p><?= htmlspecialchars($user['name']) ?></p><span>Patient</span></div>
    </div>
  </aside>

  <div class="main-content">
    <div class="topbar"><h1>👤 My Profile</h1></div>
    <div class="page-content">

      <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

      <form method="POST">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">

        <!-- Personal Info -->
        <div class="card">
          <h3 class="card-title">👤 Personal Information</h3>
          <div class="form-group">
            <label class="form-label">Full Name</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled style="background:#f8fafc;color:#94a3b8;">
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div class="form-group">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Age</label>
              <input type="number" name="age" class="form-control" value="<?= (int)($user['age'] ?? 0) ?>" min="1" max="120">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Gender</label>
            <select name="gender" class="form-control">
              <option value="">Select...</option>
              <?php foreach (['Male','Female','Other'] as $g): ?>
              <option value="<?= $g ?>" <?= ($user['gender'] ?? '')===$g?'selected':'' ?>><?= $g ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Address</label>
            <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
          </div>
        </div>

        <!-- Medical Info -->
        <div class="card">
          <h3 class="card-title">🏥 Medical Information</h3>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
            <div class="form-group">
              <label class="form-label">Blood Group</label>
              <select name="blood_group" class="form-control">
                <option value="">Select...</option>
                <?php foreach (['A+','A-','B+','B-','O+','O-','AB+','AB-'] as $bg): ?>
                <option value="<?= $bg ?>" <?= ($record['blood_group'] ?? '')===$bg?'selected':'' ?>><?= $bg ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Weight (kg)</label>
              <input type="number" name="weight" class="form-control" step="0.1" value="<?= htmlspecialchars($record['weight'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Height (cm)</label>
              <input type="number" name="height" class="form-control" step="0.1" value="<?= htmlspecialchars($record['height'] ?? '') ?>">
            </div>
          </div>
          <?php
          $bmi    = '';
          $bmiCat = '';
          if (!empty($record['weight']) && !empty($record['height']) && $record['height'] > 0) {
              $h   = $record['height'] / 100;
              $bmi = round($record['weight'] / ($h * $h), 1);
              $bmiCat = $bmi < 18.5 ? 'Underweight' : ($bmi < 25 ? 'Normal' : ($bmi < 30 ? 'Overweight' : 'Obese'));
          }
          ?>
          <?php if ($bmi): ?>
          <div class="alert alert-info" style="margin-bottom:16px;">
            📊 Your BMI: <strong><?= $bmi ?></strong> — <?= $bmiCat ?>
          </div>
          <?php endif; ?>
          <div class="form-group">
            <label class="form-label">Allergies</label>
            <textarea name="allergies" class="form-control" rows="2" placeholder="e.g. Penicillin, Dust, Peanuts"><?= htmlspecialchars($record['allergies'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Chronic Diseases</label>
            <textarea name="chronic_diseases" class="form-control" rows="2" placeholder="e.g. Diabetes, Hypertension"><?= htmlspecialchars($record['chronic_diseases'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Current Medications</label>
            <textarea name="current_medications" class="form-control" rows="2" placeholder="e.g. Metformin 500mg"><?= htmlspecialchars($record['current_medications'] ?? '') ?></textarea>
          </div>
        </div>

        <!-- Change Password -->
        <div class="card" style="grid-column:1/-1;">
          <h3 class="card-title">🔐 Change Password <span style="font-size:13px;font-weight:400;color:#64748b;">(leave blank to keep current)</span></h3>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
            <div class="form-group">
              <label class="form-label">Current Password</label>
              <input type="password" name="current_password" class="form-control" placeholder="Current password">
            </div>
            <div class="form-group">
              <label class="form-label">New Password</label>
              <input type="password" name="new_password" class="form-control" placeholder="New password">
            </div>
            <div class="form-group">
              <label class="form-label">Confirm New Password</label>
              <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password">
            </div>
          </div>
          <button type="submit" class="btn btn-primary">💾 Save All Changes</button>
        </div>
      </div>
      </form>
    </div>
  </div>
</div>
</body>
</html>
