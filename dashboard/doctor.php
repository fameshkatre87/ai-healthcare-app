<?php
require_once '../config/db.php';
requireRole('doctor');

$db  = getDB();
$uid = (int)$_SESSION['user_id']; // cast for safety

// Doctor profile
$doctor = $db->query("
    SELECT u.*, d.specialization, d.qualification, d.experience, d.available_days, d.fee, d.hospital
    FROM users u JOIN doctors d ON d.user_id=u.id WHERE u.id=$uid
")->fetch_assoc();

// Update appointment status — BUG FIX: whitelist status + prepared statement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appt_id'])) {
    $aid     = (int)$_POST['appt_id'];
    $allowed = ['pending','confirmed','cancelled','completed'];
    $status  = in_array($_POST['status'], $allowed) ? $_POST['status'] : 'pending';
    $notes   = trim($_POST['notes'] ?? '');
    $stmt    = $db->prepare("UPDATE appointments SET status=?, notes=? WHERE id=? AND doctor_id=?");
    $stmt->bind_param("ssii", $status, $notes, $aid, $uid);
    $stmt->execute();
    header('Location: doctor.php?msg=updated');
    exit();
}

// Stats — BUG FIX: $uid is cast to int at top, safe for direct interpolation in these read-only queries
// (uid comes from $_SESSION set at login, not user input — these are safe)
$total_appts     = $db->query("SELECT COUNT(*) c FROM appointments WHERE doctor_id=$uid")->fetch_assoc()['c'];
$pending_appts   = $db->query("SELECT COUNT(*) c FROM appointments WHERE doctor_id=$uid AND status='pending'")->fetch_assoc()['c'];
$confirmed_appts = $db->query("SELECT COUNT(*) c FROM appointments WHERE doctor_id=$uid AND status='confirmed'")->fetch_assoc()['c'];
$completed_appts = $db->query("SELECT COUNT(*) c FROM appointments WHERE doctor_id=$uid AND status='completed'")->fetch_assoc()['c'];

// Today's appointments
$today = date('Y-m-d');
$stmtToday = $db->prepare("
    SELECT a.*, u.name AS patient_name, u.age, u.gender, u.phone,
           pr.blood_group, pr.allergies, pr.chronic_diseases
    FROM appointments a
    JOIN users u ON a.patient_id=u.id
    LEFT JOIN patient_records pr ON pr.patient_id=u.id
    WHERE a.doctor_id=? AND a.appointment_date=?
    ORDER BY a.appointment_time ASC
");
$stmtToday->bind_param("is", $uid, $today);
$stmtToday->execute();
$todayAppts = $stmtToday->get_result()->fetch_all(MYSQLI_ASSOC);

// All appointments
$stmtAll = $db->prepare("
    SELECT a.*, u.name AS patient_name, u.age, u.gender, u.phone
    FROM appointments a
    JOIN users u ON a.patient_id=u.id
    WHERE a.doctor_id=?
    ORDER BY a.appointment_date DESC, a.appointment_time ASC
    LIMIT 15
");
$stmtAll->bind_param("i", $uid);
$stmtAll->execute();
$allAppts = $stmtAll->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Doctor Dashboard — HealthAI</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="sidebar-logo"><div class="sidebar-logo-icon">❤️</div><div><h2>Health<span>AI</span></h2><p>Doctor Portal</p></div></div>
    <nav class="sidebar-nav">
      <div class="nav-section">Main</div>
      <a href="doctor.php" class="nav-item active"><span class="nav-icon">🏠</span> Dashboard</a>
      <a href="#today" class="nav-item"><span class="nav-icon">📅</span> Today's Patients</a>
      <a href="#all" class="nav-item"><span class="nav-icon">📋</span> All Appointments</a>
      <div class="nav-section">Account</div>
      <a href="doctor_profile.php" class="nav-item"><span class="nav-icon">👤</span> My Profile</a>
      <a href="../logout.php" class="nav-item"><span class="nav-icon">🚪</span> Logout</a>
    </nav>
    <div class="sidebar-user">
      <div class="avatar"><?= strtoupper(substr($doctor['name'],0,1)) ?></div>
      <div class="user-info">
        <p><?= htmlspecialchars($doctor['name']) ?></p>
        <span><?= htmlspecialchars($doctor['specialization']) ?></span>
      </div>
    </div>
  </aside>

  <div class="main-content">
    <div class="topbar">
      <h1>👨‍⚕️ Doctor Dashboard</h1>
      <span style="font-size:13px;color:#64748b;">📅 Today: <?= date('l, d M Y') ?></span>
    </div>
    <div class="page-content">

      <?php if (isset($_GET['msg'])): ?>
      <div class="alert alert-success">✅ Appointment status updated.</div>
      <?php endif; ?>

      <!-- Doctor Info Card -->
      <div class="card" style="margin-bottom:24px;display:flex;gap:24px;align-items:center;flex-wrap:wrap;">
        <div style="width:70px;height:70px;border-radius:50%;background:linear-gradient(135deg,#0ea5e9,#0284c7);color:#fff;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:800;flex-shrink:0;">
          <?= strtoupper(substr($doctor['name'],0,1)) ?>
        </div>
        <div style="flex:1;">
          <h2 style="font-size:22px;font-weight:800;"><?= htmlspecialchars($doctor['name']) ?></h2>
          <div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:6px;">
            <span style="font-size:13px;color:#64748b;">🏥 <?= htmlspecialchars($doctor['specialization']) ?></span>
            <span style="font-size:13px;color:#64748b;">🎓 <?= htmlspecialchars($doctor['qualification']) ?></span>
            <span style="font-size:13px;color:#64748b;">⏱️ <?= $doctor['experience'] ?> yrs experience</span>
            <span style="font-size:13px;color:#64748b;">📅 <?= htmlspecialchars($doctor['available_days']) ?></span>
            <span style="font-size:13px;color:#64748b;">💰 ₹<?= number_format($doctor['fee'],0) ?> / visit</span>
          </div>
        </div>
      </div>

      <!-- Stats -->
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon blue">📅</div><div><div class="stat-value"><?= $total_appts ?></div><div class="stat-label">Total Appointments</div></div></div>
        <div class="stat-card"><div class="stat-icon orange">⏳</div><div><div class="stat-value"><?= $pending_appts ?></div><div class="stat-label">Pending</div></div></div>
        <div class="stat-card"><div class="stat-icon green">✅</div><div><div class="stat-value"><?= $confirmed_appts ?></div><div class="stat-label">Confirmed</div></div></div>
        <div class="stat-card"><div class="stat-icon blue">🏁</div><div><div class="stat-value"><?= $completed_appts ?></div><div class="stat-label">Completed</div></div></div>
      </div>

      <!-- Today's patients -->
      <div class="card" id="today" style="margin-bottom:24px;">
        <h3 class="card-title">📅 Today's Patients (<?= count($todayAppts) ?>)</h3>
        <?php if (empty($todayAppts)): ?>
          <p style="color:#64748b;font-size:14px;text-align:center;padding:24px;">No appointments for today.</p>
        <?php else: ?>
          <div style="display:flex;flex-direction:column;gap:14px;">
          <?php foreach ($todayAppts as $a): ?>
          <div style="border:1px solid #e2e8f0;border-radius:12px;padding:16px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
              <div>
                <h4 style="font-size:16px;font-weight:700;"><?= htmlspecialchars($a['patient_name']) ?></h4>
                <div style="display:flex;gap:12px;margin-top:4px;flex-wrap:wrap;">
                  <span style="font-size:13px;color:#64748b;">🕐 <?= date('h:i A', strtotime($a['appointment_time'])) ?></span>
                  <?php if ($a['age']): ?><span style="font-size:13px;color:#64748b;">Age: <?= $a['age'] ?></span><?php endif; ?>
                  <?php if ($a['gender']): ?><span style="font-size:13px;color:#64748b;"><?= htmlspecialchars($a['gender']) ?></span><?php endif; ?>
                  <?php if ($a['blood_group']): ?><span style="font-size:13px;color:#64748b;">🩸 <?= htmlspecialchars($a['blood_group']) ?></span><?php endif; ?>
                  <?php if ($a['phone']): ?><span style="font-size:13px;color:#64748b;">📞 <?= htmlspecialchars($a['phone']) ?></span><?php endif; ?>
                </div>
                <?php if ($a['reason']): ?>
                <div style="margin-top:6px;font-size:13px;color:#475569;"><strong>Reason:</strong> <?= htmlspecialchars($a['reason']) ?></div>
                <?php endif; ?>
                <?php if ($a['allergies']): ?>
                <div style="margin-top:4px;font-size:12px;color:#ef4444;"><strong>⚠️ Allergies:</strong> <?= htmlspecialchars($a['allergies']) ?></div>
                <?php endif; ?>
                <?php if ($a['chronic_diseases']): ?>
                <div style="font-size:12px;color:#f59e0b;"><strong>Chronic:</strong> <?= htmlspecialchars($a['chronic_diseases']) ?></div>
                <?php endif; ?>
              </div>
              <form method="POST" style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
                <input type="hidden" name="appt_id" value="<?= $a['id'] ?>">
                <select name="status" class="form-control" style="width:130px;padding:6px 10px;font-size:13px;">
                  <?php foreach (['pending','confirmed','completed','cancelled'] as $st): ?>
                  <option value="<?= $st ?>" <?= $a['status']===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
                  <?php endforeach; ?>
                </select>
                <input type="text" name="notes" class="form-control" style="width:160px;font-size:13px;" placeholder="Add notes..." value="<?= htmlspecialchars($a['notes'] ?? '') ?>">
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- All appointments -->
      <div class="card" id="all">
        <h3 class="card-title">📋 All Appointments</h3>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Patient</th><th>Date & Time</th><th>Reason</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($allAppts as $a): ?>
            <tr>
              <td><strong><?= htmlspecialchars($a['patient_name']) ?></strong><br><small style="color:#64748b;"><?= $a['age'] ? 'Age '.$a['age'] : '' ?> <?= htmlspecialchars($a['gender'] ?? '') ?></small></td>
              <td><?= date('d M Y', strtotime($a['appointment_date'])) ?><br><small><?= date('h:i A', strtotime($a['appointment_time'])) ?></small></td>
              <td style="font-size:13px;color:#64748b;max-width:180px;"><?= htmlspecialchars($a['reason'] ?: '—') ?></td>
              <td><span class="badge badge-<?= ['pending'=>'warning','confirmed'=>'success','cancelled'=>'danger','completed'=>'info'][$a['status']] ?? 'info' ?>"><?= ucfirst($a['status']) ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
