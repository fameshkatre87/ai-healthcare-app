<?php
require_once '../config/db.php';
requireLogin();

$db      = getDB();
$uid     = (int)$_SESSION['user_id'];
$success = '';
$error   = '';

// BUG FIX: Cancel must use prepared statement — was raw $uid in URL vulnerable to IDOR
if (isset($_GET['cancel'])) {
    $aid  = (int)$_GET['cancel'];
    $stmt = $db->prepare("UPDATE appointments SET status='cancelled' WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $aid, $uid);
    $stmt->execute();
    header('Location: appointment.php?msg=cancelled');
    exit();
}

// Book appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctor_id = (int)($_POST['doctor_id'] ?? 0);
    $date      = $_POST['appointment_date'] ?? '';
    $time      = $_POST['appointment_time'] ?? '';
    $reason    = trim($_POST['reason'] ?? '');

    if (!$doctor_id || !$date || !$time) {
        $error = 'Please fill all required fields.';
    } elseif (strtotime($date) < strtotime('today')) {
        $error = 'Please select a future date.';
    } else {
        // Check existing slot — BUG FIX: was already prepared, kept as-is
        $check = $db->prepare("SELECT id FROM appointments WHERE doctor_id=? AND appointment_date=? AND appointment_time=? AND status != 'cancelled'");
        $check->bind_param("iss", $doctor_id, $date, $time);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'This time slot is already booked. Please choose another.';
        } else {
            $stmt = $db->prepare("INSERT INTO appointments (patient_id,doctor_id,appointment_date,appointment_time,reason,status) VALUES (?,?,?,?,?,'pending')");
            $stmt->bind_param("iisss", $uid, $doctor_id, $date, $time, $reason);
            if ($stmt->execute()) {
                $success = 'Appointment booked successfully! Doctor will confirm shortly.';
            } else {
                $error = 'Booking failed. Please try again.';
            }
        }
    }
}

// All doctors for dropdown
$doctors = $db->query("
    SELECT u.id, u.name, d.specialization, d.available_days, d.fee
    FROM users u JOIN doctors d ON d.user_id=u.id
    ORDER BY d.specialization, u.name
")->fetch_all(MYSQLI_ASSOC);

// BUG FIX: My appointments — was using raw $uid in query
$stmtA = $db->prepare("
    SELECT a.*, u.name AS doctor_name, d.specialization, d.hospital
    FROM appointments a
    JOIN users u ON a.doctor_id=u.id
    JOIN doctors d ON d.user_id=u.id
    WHERE a.patient_id=?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmtA->bind_param("i", $uid);
$stmtA->execute();
$myAppts = $stmtA->get_result()->fetch_all(MYSQLI_ASSOC);

$preDoc = (int)($_GET['doctor_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Appointments — HealthAI</title>
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
      <a href="appointment.php" class="nav-item active"><span class="nav-icon">📅</span> Appointments</a>
      <div class="nav-section">Health</div>
      <a href="reports.php" class="nav-item"><span class="nav-icon">📊</span> My Reports</a>
      <a href="profile.php" class="nav-item"><span class="nav-icon">👤</span> My Profile</a>
      <div class="nav-section">Account</div>
      <a href="../logout.php" class="nav-item"><span class="nav-icon">🚪</span> Logout</a>
    </nav>
    <div class="sidebar-user">
      <div class="avatar"><?= strtoupper(substr($_SESSION['name'],0,1)) ?></div>
      <div class="user-info"><p><?= htmlspecialchars($_SESSION['name']) ?></p><span>Patient</span></div>
    </div>
  </aside>

  <div class="main-content">
    <div class="topbar"><h1>📅 Appointments</h1></div>
    <div class="page-content">

      <?php if (isset($_GET['msg']) && $_GET['msg']==='cancelled'): ?>
        <div class="alert alert-warning">✅ Appointment cancelled.</div>
      <?php endif; ?>
      <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

      <div style="display:grid;grid-template-columns:1fr 1.6fr;gap:28px;align-items:start;">
        <!-- Booking Form -->
        <div class="card">
          <h3 class="card-title">📋 Book New Appointment</h3>
          <form method="POST">
            <div class="form-group">
              <label class="form-label">Select Doctor *</label>
              <select name="doctor_id" class="form-control" required>
                <option value="">Choose a doctor...</option>
                <?php
                $lastSpec = '';
                foreach ($doctors as $d):
                  if ($d['specialization'] !== $lastSpec) {
                      if ($lastSpec !== '') echo "</optgroup>";
                      echo "<optgroup label=\"" . htmlspecialchars($d['specialization']) . "\">";
                      $lastSpec = $d['specialization'];
                  }
                ?>
                <option value="<?= $d['id'] ?>" <?= $preDoc===$d['id']?'selected':'' ?>>
                  <?= htmlspecialchars($d['name']) ?> — ₹<?= number_format($d['fee'],0) ?>
                </option>
                <?php endforeach; ?>
                <?php if ($lastSpec !== '') echo "</optgroup>"; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Date *</label>
              <input type="date" name="appointment_date" class="form-control" required
                     min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                     value="<?= htmlspecialchars($_POST['appointment_date'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Time Slot *</label>
              <select name="appointment_time" class="form-control" required>
                <option value="">Choose time...</option>
                <?php
                $times = ['09:00','09:30','10:00','10:30','11:00','11:30','12:00','14:00','14:30','15:00','15:30','16:00','16:30','17:00'];
                foreach ($times as $t) {
                    $label = date('h:i A', strtotime($t));
                    $sel   = ($_POST['appointment_time'] ?? '') === $t ? 'selected' : '';
                    echo "<option value=\"" . htmlspecialchars($t) . "\" $sel>$label</option>";
                }
                ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Reason / Symptoms</label>
              <textarea name="reason" class="form-control" rows="3" placeholder="Briefly describe your problem..."><?= htmlspecialchars($_POST['reason'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-block">📅 Book Appointment</button>
          </form>
        </div>

        <!-- My Appointments -->
        <div class="card">
          <h3 class="card-title">My Appointments</h3>
          <?php if (empty($myAppts)): ?>
            <p style="text-align:center;color:#64748b;padding:32px 0;">No appointments found.</p>
          <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Doctor</th><th>Date & Time</th><th>Status</th><th>Action</th></tr></thead>
              <tbody>
              <?php foreach ($myAppts as $a): ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars($a['doctor_name']) ?></strong><br>
                  <small style="color:#64748b;"><?= htmlspecialchars($a['specialization']) ?></small><br>
                  <small style="color:#94a3b8;">🏥 <?= htmlspecialchars($a['hospital']) ?></small>
                </td>
                <td>
                  <?= date('d M Y', strtotime($a['appointment_date'])) ?><br>
                  <small><?= date('h:i A', strtotime($a['appointment_time'])) ?></small>
                </td>
                <td>
                  <?php
                  $badgeMap = ['pending'=>'warning','confirmed'=>'success','cancelled'=>'danger','completed'=>'info'];
                  $badge = $badgeMap[$a['status']] ?? 'info';
                  ?>
                  <span class="badge badge-<?= $badge ?>"><?= ucfirst(htmlspecialchars($a['status'])) ?></span>
                </td>
                <td>
                  <?php if (in_array($a['status'], ['pending','confirmed']) && strtotime($a['appointment_date']) >= strtotime('today')): ?>
                    <a href="appointment.php?cancel=<?= (int)$a['id'] ?>"
                       class="btn btn-danger btn-sm"
                       onclick="return confirm('Cancel this appointment?')">Cancel</a>
                  <?php else: ?>
                    <span style="color:#94a3b8;font-size:12px;">—</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
