<?php
require_once '../config/db.php';
requireLogin();
if ($_SESSION['role'] !== 'patient') { header('Location: ../login.php'); exit(); }

$db  = getDB();
$uid = (int)$_SESSION['user_id']; // BUG FIX: cast to int — defensive best practice

// Stats
$appt_count   = $db->query("SELECT COUNT(*) c FROM appointments WHERE patient_id=$uid")->fetch_assoc()['c'];
$pred_count   = $db->query("SELECT COUNT(*) c FROM predictions WHERE patient_id=$uid")->fetch_assoc()['c'];
$report_count = $db->query("SELECT COUNT(*) c FROM reports WHERE patient_id=$uid")->fetch_assoc()['c'];

// Recent appointments — $uid is cast to int above, safe to interpolate
$appts = $db->query("
  SELECT a.*, u.name AS doctor_name, d.specialization
  FROM appointments a
  JOIN users u ON a.doctor_id = u.id
  JOIN doctors d ON d.user_id = u.id
  WHERE a.patient_id = $uid
  ORDER BY a.created_at DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Recent predictions
$preds = $db->query("
  SELECT * FROM predictions WHERE patient_id=$uid
  ORDER BY created_at DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Medical record
$record = $db->query("SELECT * FROM patient_records WHERE patient_id=$uid")->fetch_assoc();
$user   = $db->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patient Dashboard — HealthAI</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
  <!-- Sidebar -->
  <aside class="sidebar">
        <div class="sidebar-logo">
      <div class="sidebar-logo-icon">❤️</div>
      <div><h2>Health<span>AI</span></h2><p>Patient Portal</p></div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section">Main</div>
      <a href="patient.php" class="nav-item active"><span class="nav-icon">🏠</span> Dashboard</a>
      <a href="../modules/symptom_checker.php" class="nav-item"><span class="nav-icon">🔬</span> Symptom Checker</a>
      <a href="../modules/doctor_suggest.php" class="nav-item"><span class="nav-icon">👨‍⚕️</span> Find Doctors</a>
      <a href="../modules/appointment.php" class="nav-item"><span class="nav-icon">📅</span> Appointments</a>
      <div class="nav-section">Health</div>
      <a href="../modules/reports.php" class="nav-item"><span class="nav-icon">📊</span> My Reports</a>
      <a href="../modules/profile.php" class="nav-item"><span class="nav-icon">👤</span> My Profile</a>
      <div class="nav-section">Account</div>
      <a href="../logout.php" class="nav-item"><span class="nav-icon">🚪</span> Logout</a>
    </nav>
    <div class="sidebar-user">
      <div class="avatar"><?= strtoupper(substr($_SESSION['name'],0,1)) ?></div>
      <div class="user-info">
        <p><?= htmlspecialchars($_SESSION['name']) ?></p>
        <span>Patient</span>
      </div>
    </div>
  </aside>

  <!-- Main -->
  <div class="main-content">
    <div class="topbar">
      <h1>👋 Welcome, <?= htmlspecialchars(explode(' ', $_SESSION['name'])[0]) ?>!</h1>
      <a href="../modules/symptom_checker.php" class="btn btn-primary btn-sm">🔬 Check Symptoms</a>
    </div>

    <div class="page-content">
      <!-- Stats -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon blue">📅</div>
          <div><div class="stat-value"><?= $appt_count ?></div><div class="stat-label">Appointments</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green">🔬</div>
          <div><div class="stat-value"><?= $pred_count ?></div><div class="stat-label">Predictions Done</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon orange">📊</div>
          <div><div class="stat-value"><?= $report_count ?></div><div class="stat-label">Reports</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon red">🩸</div>
          <div><div class="stat-value"><?= htmlspecialchars($record['blood_group'] ?? 'N/A') ?></div><div class="stat-label">Blood Group</div></div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;flex-wrap:wrap;">
        <!-- Recent Appointments -->
        <div class="card">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
            <h3 class="card-title" style="margin:0;">Recent Appointments</h3>
            <a href="../modules/appointment.php" class="btn btn-outline btn-sm">View All</a>
          </div>
          <?php if (empty($appts)): ?>
            <p style="color:#64748b;font-size:14px;text-align:center;padding:24px;">No appointments yet. <a href="../modules/doctor_suggest.php" style="color:#0ea5e9;">Book one →</a></p>
          <?php else: ?>
            <div class="table-wrap">
              <table>
                <tr><th>Doctor</th><th>Date</th><th>Status</th></tr>
                <?php foreach ($appts as $a): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($a['doctor_name']) ?></strong><br><small style="color:#64748b;"><?= htmlspecialchars($a['specialization']) ?></small></td>
                  <td><?= date('d M Y', strtotime($a['appointment_date'])) ?><br><small><?= $a['appointment_time'] ?></small></td>
                  <td><span class="badge badge-<?= $a['status']==='confirmed'?'success':($a['status']==='cancelled'?'danger':'warning') ?>"><?= ucfirst($a['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- Recent Predictions -->
        <div class="card">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
            <h3 class="card-title" style="margin:0;">AI Predictions</h3>
            <a href="../modules/symptom_checker.php" class="btn btn-primary btn-sm">New Check</a>
          </div>
          <?php if (empty($preds)): ?>
            <p style="color:#64748b;font-size:14px;text-align:center;padding:24px;">No predictions yet. <a href="../modules/symptom_checker.php" style="color:#0ea5e9;">Check symptoms →</a></p>
          <?php else: ?>
            <div class="table-wrap">
              <table>
                <tr><th>Disease</th><th>Confidence</th><th>Date</th></tr>
                <?php foreach ($preds as $p): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($p['predicted_disease']) ?></strong></td>
                  <td><span class="badge badge-<?= $p['confidence']>70?'success':'warning' ?>"><?= round($p['confidence']) ?>%</span></td>
                  <td style="font-size:12px;color:#64748b;"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="card" style="margin-top:24px;">
        <h3 class="card-title">Quick Actions</h3>
        <div style="display:flex;gap:12px;flex-wrap:wrap;">
          <a href="../modules/symptom_checker.php" class="btn btn-primary">🔬 Check Symptoms</a>
          <a href="../modules/doctor_suggest.php" class="btn btn-success">👨‍⚕️ Find Doctor</a>
          <a href="../modules/appointment.php" class="btn btn-outline">📅 Book Appointment</a>
          <a href="../modules/reports.php" class="btn btn-outline">📊 Upload Report</a>
          <a href="../modules/profile.php" class="btn btn-outline">👤 Update Profile</a>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
