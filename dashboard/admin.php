<?php
require_once '../config/db.php';
requireRole('admin');

$db = getDB();

// Update appointment status — BUG FIX: use prepared statement + whitelist status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appt_id'])) {
    $aid    = (int)$_POST['appt_id'];
    $allowed = ['pending','confirmed','cancelled','completed'];
    $status  = in_array($_POST['status'], $allowed) ? $_POST['status'] : 'pending';
    $stmt    = $db->prepare("UPDATE appointments SET status=? WHERE id=?");
    $stmt->bind_param("si", $status, $aid);
    $stmt->execute();
    header('Location: admin.php');
    exit();
}

// Stats
$total_users    = $db->query("SELECT COUNT(*) c FROM users WHERE role='patient'")->fetch_assoc()['c'];
$total_doctors  = $db->query("SELECT COUNT(*) c FROM users WHERE role='doctor'")->fetch_assoc()['c'];
$total_appts    = $db->query("SELECT COUNT(*) c FROM appointments")->fetch_assoc()['c'];
$total_preds    = $db->query("SELECT COUNT(*) c FROM predictions")->fetch_assoc()['c'];
$pending_appts  = $db->query("SELECT COUNT(*) c FROM appointments WHERE status='pending'")->fetch_assoc()['c'];

// Toggle user active status
if (isset($_GET['toggle'])) {
    // Simple delete for demo — in production use an 'active' column
    $tid = (int)$_GET['toggle'];
    header("Location: admin.php");
    exit();
}

// Recent users
$users = $db->query("SELECT * FROM users WHERE role='patient' ORDER BY created_at DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);

// All appointments recent
$allAppts = $db->query("
    SELECT a.*, p.name patient_name, d.name doctor_name, doc.specialization
    FROM appointments a
    JOIN users p ON a.patient_id=p.id
    JOIN users d ON a.doctor_id=d.id
    JOIN doctors doc ON doc.user_id=d.id
    ORDER BY a.created_at DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Top predicted diseases
$topDiseases = $db->query("
    SELECT predicted_disease, COUNT(*) cnt, AVG(confidence) avg_conf
    FROM predictions
    GROUP BY predicted_disease ORDER BY cnt DESC LIMIT 6
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — HealthAI</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="sidebar-logo"><div class="sidebar-logo-icon">❤️</div><div><h2>Health<span>AI</span></h2><p>Admin Panel</p></div></div>
    <nav class="sidebar-nav">
      <div class="nav-section">Management</div>
      <a href="admin.php" class="nav-item active"><span class="nav-icon">📊</span> Dashboard</a>
      <a href="admin_users.php" class="nav-item"><span class="nav-icon">👥</span> Manage Users</a>
      <a href="admin_doctors.php" class="nav-item"><span class="nav-icon">👨‍⚕️</span> Manage Doctors</a>
      <a href="admin_appointments.php" class="nav-item"><span class="nav-icon">📅</span> Appointments</a>
      <a href="admin_reports.php" class="nav-item"><span class="nav-icon">📋</span> All Reports</a>
      <a href="admin_predictions.php" class="nav-item"><span class="nav-icon">🔬</span> AI Predictions</a>
      <div class="nav-section">Account</div>
      <a href="../logout.php" class="nav-item"><span class="nav-icon">🚪</span> Logout</a>
    </nav>
    <div class="sidebar-user">
      <div class="avatar">A</div>
      <div class="user-info"><p><?= htmlspecialchars($_SESSION['name']) ?></p><span>Administrator</span></div>
    </div>
  </aside>

  <div class="main-content">
    <div class="topbar">
      <h1>📊 Admin Dashboard</h1>
      <span style="font-size:13px;color:#64748b;">Welcome, <?= htmlspecialchars($_SESSION['name']) ?></span>
    </div>
    <div class="page-content">

      <!-- Stats -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon blue">👥</div>
          <div><div class="stat-value"><?= $total_users ?></div><div class="stat-label">Total Patients</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green">👨‍⚕️</div>
          <div><div class="stat-value"><?= $total_doctors ?></div><div class="stat-label">Doctors</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon orange">📅</div>
          <div><div class="stat-value"><?= $total_appts ?></div><div class="stat-label">Appointments</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon red">🔬</div>
          <div><div class="stat-value"><?= $total_preds ?></div><div class="stat-label">AI Predictions</div></div>
        </div>
      </div>

      <?php if ($pending_appts > 0): ?>
      <div class="alert alert-warning">
        ⚠️ <strong><?= $pending_appts ?> pending appointment(s)</strong> need confirmation.
      </div>
      <?php endif; ?>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">
        <!-- Top Diseases -->
        <div class="card">
          <h3 class="card-title">🔬 Most Predicted Diseases</h3>
          <?php if (empty($topDiseases)): ?>
            <p style="color:#64748b;font-size:14px;">No predictions yet.</p>
          <?php else: ?>
            <?php foreach ($topDiseases as $td): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f1f5f9;">
              <span style="font-size:14px;font-weight:500;"><?= htmlspecialchars($td['predicted_disease']) ?></span>
              <div style="display:flex;gap:8px;align-items:center;">
                <span class="badge badge-info"><?= $td['cnt'] ?>x</span>
                <span style="font-size:12px;color:#64748b;"><?= round($td['avg_conf']) ?>% avg</span>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Recent Patients -->
        <div class="card">
          <h3 class="card-title">👥 Recent Patients</h3>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Name</th><th>Age</th><th>Joined</th></tr></thead>
              <tbody>
              <?php foreach ($users as $u): ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars($u['name']) ?></strong><br>
                  <small style="color:#64748b;"><?= htmlspecialchars($u['email']) ?></small>
                </td>
                <td><?= $u['age'] ?? '—' ?></td>
                <td style="font-size:12px;color:#94a3b8;"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- All Appointments -->
      <div class="card">
        <h3 class="card-title">📅 Recent Appointments</h3>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Patient</th><th>Doctor</th><th>Date</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($allAppts as $a): ?>
            <tr>
              <td><strong><?= htmlspecialchars($a['patient_name']) ?></strong></td>
              <td><?= htmlspecialchars($a['doctor_name']) ?><br><small style="color:#64748b;"><?= htmlspecialchars($a['specialization']) ?></small></td>
              <td><?= date('d M Y', strtotime($a['appointment_date'])) ?><br><small><?= date('h:i A', strtotime($a['appointment_time'])) ?></small></td>
              <td><span class="badge badge-<?= ['pending'=>'warning','confirmed'=>'success','cancelled'=>'danger','completed'=>'info'][$a['status']] ?? 'info' ?>"><?= ucfirst($a['status']) ?></span></td>
              <td>
                <form method="POST" style="display:flex;gap:6px;">
                  <input type="hidden" name="appt_id" value="<?= $a['id'] ?>">
                  <select name="status" class="form-control" style="padding:4px 8px;font-size:12px;width:auto;">
                    <?php foreach (['pending','confirmed','completed','cancelled'] as $st): ?>
                    <option value="<?= $st ?>" <?= $a['status']===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="btn btn-primary btn-sm">✓</button>
                </form>
              </td>
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
