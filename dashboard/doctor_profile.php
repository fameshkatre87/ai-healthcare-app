<?php
require_once '../config/db.php';
requireRole('doctor');

$db      = getDB();
$uid     = (int)$_SESSION['user_id'];
$success = '';
$error   = '';

// ── UPDATE profile ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $age      = (int)($_POST['age'] ?? 0);
    $gender   = $_POST['gender'] ?? '';
    $spec     = trim($_POST['specialization'] ?? '');
    $qual     = trim($_POST['qualification'] ?? '');
    $exp      = (int)($_POST['experience'] ?? 0);
    $days     = trim($_POST['available_days'] ?? '');
    $fee      = (float)($_POST['fee'] ?? 0);
    $hospital = trim($_POST['hospital'] ?? '');

    if (!$name || !$spec) {
        $error = 'Name and specialization are required.';
    } else {
        $db->begin_transaction();
        try {
            $stmt = $db->prepare("UPDATE users SET name=?,phone=?,age=?,gender=? WHERE id=?");
            $stmt->bind_param("ssisi", $name, $phone, $age, $gender, $uid);
            $stmt->execute();
            $_SESSION['name'] = $name;

            $stmt2 = $db->prepare("UPDATE doctors SET specialization=?,qualification=?,experience=?,available_days=?,fee=?,hospital=? WHERE user_id=?");
            $stmt2->bind_param("ssisdsi", $spec, $qual, $exp, $days, $fee, $hospital, $uid);
            $stmt2->execute();

            // Password change
            if (!empty($_POST['new_password'])) {
                if ($_POST['new_password'] !== ($_POST['confirm_password'] ?? '')) {
                    throw new Exception('New passwords do not match.');
                }
                $stmtPw = $db->prepare("SELECT password FROM users WHERE id=?");
                $stmtPw->bind_param("i", $uid);
                $stmtPw->execute();
                $row = $stmtPw->get_result()->fetch_assoc();
                if (!password_verify($_POST['current_password'] ?? '', $row['password'])) {
                    throw new Exception('Current password is incorrect.');
                }
                $hashed = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $stmtUpPw = $db->prepare("UPDATE users SET password=? WHERE id=?");
                $stmtUpPw->bind_param("si", $hashed, $uid);
                $stmtUpPw->execute();
            }

            $db->commit();
            $success = 'Profile updated successfully!';
        } catch (Exception $e) {
            $db->rollback();
            $error = $e->getMessage();
        }
    }
}

$stmt = $db->prepare("
    SELECT u.*, d.specialization, d.qualification, d.experience, d.available_days, d.fee, d.hospital
    FROM users u JOIN doctors d ON d.user_id=u.id WHERE u.id=?
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();

$specializations = ['Cardiologist','Neurologist','Dermatologist','Orthopedic','Gastroenterologist',
    'Endocrinologist','Pulmonologist','Psychiatrist','Gynecologist','Pediatrician',
    'Ophthalmologist','ENT Specialist','General Physician'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile — HealthAI Doctor</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="sidebar-logo"><div class="sidebar-logo-icon">❤️</div><div><h2>Health<span>AI</span></h2><p>Doctor Portal</p></div></div>
    <nav class="sidebar-nav">
      <div class="nav-section">Main</div>
      <a href="doctor.php" class="nav-item"><span class="nav-icon">🏠</span> Dashboard</a>
      <a href="#today" class="nav-item"><span class="nav-icon">📅</span> Today's Patients</a>
      <a href="#all" class="nav-item"><span class="nav-icon">📋</span> All Appointments</a>
      <div class="nav-section">Account</div>
      <a href="doctor_profile.php" class="nav-item active"><span class="nav-icon">👤</span> My Profile</a>
      <a href="../logout.php" class="nav-item"><span class="nav-icon">🚪</span> Logout</a>
    </nav>
    <div class="sidebar-user">
      <div class="avatar"><?= strtoupper(substr($doctor['name'],0,1)) ?></div>
      <div class="user-info"><p><?= htmlspecialchars($doctor['name']) ?></p><span><?= htmlspecialchars($doctor['specialization']) ?></span></div>
    </div>
  </aside>

  <div class="main-content">
    <div class="topbar"><h1>👤 My Profile</h1></div>
    <div class="page-content">

      <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if ($error):   ?><div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

      <form method="POST">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">

        <!-- Personal Info -->
        <div class="card">
          <h3 class="card-title">👤 Personal Information</h3>
          <div class="form-group">
            <label class="form-label">Full Name *</label>
            <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($doctor['name']) ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Email (read-only)</label>
            <input type="email" class="form-control" disabled value="<?= htmlspecialchars($doctor['email']) ?>" style="background:#f8fafc;color:#94a3b8;">
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div class="form-group">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($doctor['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Age</label>
              <input type="number" name="age" class="form-control" min="25" max="80" value="<?= (int)($doctor['age'] ?? 0) ?>">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Gender</label>
            <select name="gender" class="form-control">
              <option value="">Select...</option>
              <?php foreach (['Male','Female','Other'] as $g): ?>
              <option value="<?= $g ?>" <?= ($doctor['gender'] ?? '')===$g?'selected':'' ?>><?= $g ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Professional Info -->
        <div class="card">
          <h3 class="card-title">🏥 Professional Information</h3>
          <div class="form-group">
            <label class="form-label">Specialization *</label>
            <select name="specialization" class="form-control" required>
              <?php foreach ($specializations as $sp): ?>
              <option value="<?= $sp ?>" <?= $doctor['specialization']===$sp?'selected':'' ?>><?= $sp ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Qualification</label>
            <input type="text" name="qualification" class="form-control" placeholder="e.g. MD Cardiology" value="<?= htmlspecialchars($doctor['qualification'] ?? '') ?>">
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div class="form-group">
              <label class="form-label">Experience (years)</label>
              <input type="number" name="experience" class="form-control" min="0" value="<?= (int)($doctor['experience'] ?? 0) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Consultation Fee (₹)</label>
              <input type="number" name="fee" class="form-control" min="0" step="50" value="<?= (float)($doctor['fee'] ?? 0) ?>">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Hospital / Clinic</label>
            <input type="text" name="hospital" class="form-control" value="<?= htmlspecialchars($doctor['hospital'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Available Days <small style="font-weight:400;">(comma separated)</small></label>
            <input type="text" name="available_days" class="form-control" placeholder="e.g. Mon,Wed,Fri" value="<?= htmlspecialchars($doctor['available_days'] ?? '') ?>">
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;" id="dayPills">
              <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day): ?>
              <?php $active = str_contains($doctor['available_days'] ?? '', $day); ?>
              <button type="button" onclick="toggleDay('<?= $day ?>')"
                id="day_<?= $day ?>"
                class="btn btn-sm <?= $active ? 'btn-primary' : 'btn-outline' ?>"
                style="padding:5px 12px;font-size:12px;"><?= $day ?></button>
              <?php endforeach; ?>
            </div>
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
<script>
function toggleDay(day) {
  const btn = document.getElementById('day_' + day);
  const input = document.querySelector('input[name="available_days"]');
  let days = input.value.split(',').map(d => d.trim()).filter(Boolean);
  if (days.includes(day)) {
    days = days.filter(d => d !== day);
    btn.className = btn.className.replace('btn-primary', 'btn-outline');
  } else {
    days.push(day);
    btn.className = btn.className.replace('btn-outline', 'btn-primary');
  }
  input.value = days.join(',');
}
</script>
</body>
</html>
