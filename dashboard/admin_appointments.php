<?php
require_once '../config/db.php';
requireRole('admin');

$db      = getDB();
$success = '';
$error   = '';

// ── DELETE appointment ────────────────────────────────────
if (isset($_GET['delete'])) {
    $did  = (int)$_GET['delete'];
    $stmt = $db->prepare("DELETE FROM appointments WHERE id=?");
    $stmt->bind_param("i", $did);
    $stmt->execute();
    if ($db->affected_rows > 0) {
        $success = 'Appointment deleted successfully.';
    } else {
        $error = 'Appointment not found.';
    }
}

// ── UPDATE status + notes ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $aid     = (int)$_POST['appt_id'];
    $allowed = ['pending','confirmed','cancelled','completed'];
    $status  = in_array($_POST['status'], $allowed) ? $_POST['status'] : 'pending';
    $notes   = trim($_POST['notes'] ?? '');
    $stmt    = $db->prepare("UPDATE appointments SET status=?, notes=? WHERE id=?");
    $stmt->bind_param("ssi", $status, $notes, $aid);
    $stmt->execute();
    $success = 'Appointment updated.';
}

// ── ADD new appointment (admin can create on behalf) ──────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $patient_id = (int)$_POST['patient_id'];
    $doctor_id  = (int)$_POST['doctor_id'];
    $date       = $_POST['appointment_date'] ?? '';
    $time       = $_POST['appointment_time'] ?? '';
    $reason     = trim($_POST['reason'] ?? '');
    $status     = $_POST['status'] ?? 'pending';

    if (!$patient_id || !$doctor_id || !$date || !$time) {
        $error = 'Patient, doctor, date and time are required.';
    } else {
        // Check slot conflict
        $chk = $db->prepare("SELECT id FROM appointments WHERE doctor_id=? AND appointment_date=? AND appointment_time=? AND status != 'cancelled'");
        $chk->bind_param("iss", $doctor_id, $date, $time);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error = 'This time slot is already booked for that doctor.';
        } else {
            $stmt = $db->prepare("INSERT INTO appointments (patient_id,doctor_id,appointment_date,appointment_time,reason,status) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("iissss", $patient_id, $doctor_id, $date, $time, $reason, $status);
            if ($stmt->execute()) {
                $success = 'Appointment created successfully.';
            } else {
                $error = 'Failed to create appointment.';
            }
        }
    }
}

// ── Filters ───────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? '';
$filterDate   = $_GET['date'] ?? '';
$search       = trim($_GET['q'] ?? '');

$sql    = "
    SELECT a.*, p.name AS patient_name, p.phone AS patient_phone,
           d.name AS doctor_name, doc.specialization
    FROM appointments a
    JOIN users p ON a.patient_id = p.id
    JOIN users d ON a.doctor_id  = d.id
    JOIN doctors doc ON doc.user_id = d.id
    WHERE 1=1
";
$params = [];
$types  = '';

if ($filterStatus !== '') {
    $sql    .= " AND a.status=?";
    $params[] = $filterStatus;
    $types   .= 's';
}
if ($filterDate !== '') {
    $sql    .= " AND a.appointment_date=?";
    $params[] = $filterDate;
    $types   .= 's';
}
if ($search !== '') {
    $sql    .= " AND (p.name LIKE ? OR d.name LIKE ? OR doc.specialization LIKE ?)";
    $like    = '%' . $search . '%';
    $params  = array_merge($params, [$like, $like, $like]);
    $types  .= 'sss';
}
$sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmt = $db->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Stats for top cards — status values are hardcoded strings, not user input (safe)
$stats = [];
foreach (['pending','confirmed','cancelled','completed'] as $st) {
    $stmtStat = $db->prepare("SELECT COUNT(*) c FROM appointments WHERE status=?");
    $stmtStat->bind_param("s", $st);
    $stmtStat->execute();
    $stats[$st] = $stmtStat->get_result()->fetch_assoc()['c'];
}

// Patients and doctors for add form
$patients = $db->query("SELECT id, name FROM users WHERE role='patient' ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$doctors  = $db->query("SELECT u.id, u.name, d.specialization FROM users u JOIN doctors d ON d.user_id=u.id ORDER BY u.name")->fetch_all(MYSQLI_ASSOC);

$times = ['09:00','09:30','10:00','10:30','11:00','11:30','12:00','14:00','14:30','15:00','15:30','16:00','16:30','17:00'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Appointments — HealthAI Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
  .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;}
  .modal-overlay.open{display:flex;}
  .modal{background:#fff;border-radius:16px;padding:28px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;}
  .modal h3{font-size:20px;font-weight:700;margin-bottom:20px;}
  .modal-close{float:right;background:none;border:none;font-size:22px;cursor:pointer;color:#64748b;}
  .g2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
  .inline-form{display:flex;gap:6px;align-items:center;flex-wrap:wrap;}
  .inline-select{padding:5px 10px;border-radius:8px;border:2px solid #e2e8f0;
    font-size:12px;font-family:inherit;color:#1e293b;cursor:pointer;}
  .inline-select:focus{outline:none;border-color:#0ea5e9;}
  tr.row-pending td{border-left:3px solid #f59e0b;}
  tr.row-confirmed td{border-left:3px solid #10b981;}
  tr.row-cancelled td{border-left:3px solid #ef4444;opacity:.7;}
  tr.row-completed td{border-left:3px solid #0ea5e9;}
  .notes-input{padding:4px 8px;border:2px solid #e2e8f0;border-radius:7px;font-size:12px;
    font-family:inherit;width:160px;}
  .notes-input:focus{outline:none;border-color:#0ea5e9;}
</style>
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="sidebar-logo"><div class="sidebar-logo-icon">❤️</div><div><h2>Health<span>AI</span></h2><p>Admin Panel</p></div></div>
    <nav class="sidebar-nav">
      <div class="nav-section">Management</div>
      <a href="admin.php" class="nav-item"><span class="nav-icon">📊</span> Dashboard</a>
      <a href="admin_users.php" class="nav-item"><span class="nav-icon">👥</span> Manage Users</a>
      <a href="admin_doctors.php" class="nav-item"><span class="nav-icon">👨‍⚕️</span> Manage Doctors</a>
      <a href="admin_appointments.php" class="nav-item active"><span class="nav-icon">📅</span> Appointments</a>
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
      <h1>📅 Manage Appointments</h1>
      <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('open')">+ New Appointment</button>
    </div>
    <div class="page-content">

      <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if ($error):   ?><div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

      <!-- Stat cards -->
      <div class="stats-grid" style="margin-bottom:22px;">
        <div class="stat-card">
          <div class="stat-icon orange">⏳</div>
          <div><div class="stat-value"><?= $stats['pending'] ?></div><div class="stat-label">Pending</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green">✅</div>
          <div><div class="stat-value"><?= $stats['confirmed'] ?></div><div class="stat-label">Confirmed</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon blue">🏁</div>
          <div><div class="stat-value"><?= $stats['completed'] ?></div><div class="stat-label">Completed</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon red">❌</div>
          <div><div class="stat-value"><?= $stats['cancelled'] ?></div><div class="stat-label">Cancelled</div></div>
        </div>
      </div>

      <!-- Filters -->
      <form method="GET" style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:20px;align-items:flex-end;">
        <div>
          <label class="form-label">Search</label>
          <input type="text" name="q" class="form-control" placeholder="Patient, doctor, specialization..." value="<?= htmlspecialchars($search) ?>" style="width:250px;">
        </div>
        <div>
          <label class="form-label">Status</label>
          <select name="status" class="form-control" style="width:140px;">
            <option value="">All Status</option>
            <?php foreach (['pending','confirmed','completed','cancelled'] as $st): ?>
            <option value="<?= $st ?>" <?= $filterStatus===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">Date</label>
          <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filterDate) ?>">
        </div>
        <button type="submit" class="btn btn-primary">🔍 Filter</button>
        <a href="admin_appointments.php" class="btn btn-outline">✕ Reset</a>
      </form>

      <!-- Appointments table -->
      <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
          <h3 class="card-title" style="margin:0;">
            All Appointments (<?= count($appointments) ?>)
            <?php if ($filterStatus || $filterDate || $search): ?>
              <span style="font-size:13px;font-weight:400;color:#64748b;">— filtered</span>
            <?php endif; ?>
          </h3>
        </div>
        <?php if (empty($appointments)): ?>
          <p style="text-align:center;color:#64748b;padding:40px;">No appointments found matching your filters.</p>
        <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>#ID</th>
                <th>Patient</th>
                <th>Doctor</th>
                <th>Date & Time</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Notes</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($appointments as $a): ?>
            <tr class="row-<?= $a['status'] ?>">
              <td style="color:#94a3b8;font-size:12px;">#<?= $a['id'] ?></td>
              <td>
                <strong style="font-size:14px;"><?= htmlspecialchars($a['patient_name']) ?></strong><br>
                <small style="color:#64748b;"><?= htmlspecialchars($a['patient_phone'] ?? '') ?></small>
              </td>
              <td>
                <strong style="font-size:14px;"><?= htmlspecialchars($a['doctor_name']) ?></strong><br>
                <span class="badge badge-info" style="font-size:11px;"><?= htmlspecialchars($a['specialization']) ?></span>
              </td>
              <td>
                <strong><?= date('d M Y', strtotime($a['appointment_date'])) ?></strong><br>
                <small style="color:#64748b;"><?= date('h:i A', strtotime($a['appointment_time'])) ?></small><br>
                <small style="color:#94a3b8;"><?= date('d M Y', strtotime($a['created_at'])) ?> booked</small>
              </td>
              <td style="font-size:13px;max-width:150px;color:#475569;">
                <?= htmlspecialchars(substr($a['reason'] ?? '—', 0, 60)) ?>
                <?= strlen($a['reason'] ?? '') > 60 ? '...' : '' ?>
              </td>
              <td>
                <?php
                $bdg = ['pending'=>'warning','confirmed'=>'success','cancelled'=>'danger','completed'=>'info'];
                ?>
                <span class="badge badge-<?= $bdg[$a['status']] ?? 'info' ?>"><?= ucfirst($a['status']) ?></span>
              </td>
              <td style="font-size:12px;color:#64748b;max-width:140px;">
                <?= htmlspecialchars(substr($a['notes'] ?? '', 0, 60)) ?>
                <?= strlen($a['notes'] ?? '') > 60 ? '...' : '' ?>
              </td>
              <td>
                <form method="POST" style="display:flex;flex-direction:column;gap:5px;min-width:190px;">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="appt_id" value="<?= $a['id'] ?>">
                  <div style="display:flex;gap:5px;">
                    <select name="status" class="inline-select">
                      <?php foreach (['pending','confirmed','completed','cancelled'] as $st): ?>
                      <option value="<?= $st ?>" <?= $a['status']===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">✓</button>
                  </div>
                  <input type="text" name="notes" class="notes-input" placeholder="Add notes..." value="<?= htmlspecialchars($a['notes'] ?? '') ?>">
                </form>
                <a href="admin_appointments.php?delete=<?= $a['id'] ?><?= $filterStatus ? "&status=$filterStatus" : '' ?><?= $filterDate ? "&date=$filterDate" : '' ?>"
                   class="btn btn-danger btn-sm"
                   style="margin-top:5px;"
                   onclick="return confirm('Permanently delete this appointment?')">🗑️ Delete</a>
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

<!-- ADD Appointment Modal -->
<div class="modal-overlay" id="addModal">
  <div class="modal">
    <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('open')">×</button>
    <h3>➕ Create New Appointment</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="g2">
        <div class="form-group" style="grid-column:1/-1;">
          <label class="form-label">Patient *</label>
          <select name="patient_id" class="form-control" required>
            <option value="">Select patient...</option>
            <?php foreach ($patients as $p): ?>
            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="grid-column:1/-1;">
          <label class="form-label">Doctor *</label>
          <select name="doctor_id" class="form-control" required>
            <option value="">Select doctor...</option>
            <?php foreach ($doctors as $d): ?>
            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?> — <?= htmlspecialchars($d['specialization']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Date *</label>
          <input type="date" name="appointment_date" class="form-control" required min="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Time Slot *</label>
          <select name="appointment_time" class="form-control" required>
            <option value="">Choose time...</option>
            <?php foreach ($times as $t): ?>
            <option value="<?= $t ?>"><?= date('h:i A', strtotime($t)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-control">
            <option value="pending">Pending</option>
            <option value="confirmed">Confirmed</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Reason</label>
          <textarea name="reason" class="form-control" rows="2" placeholder="Reason or symptoms"></textarea>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-block" style="margin-top:8px;">📅 Create Appointment</button>
    </form>
  </div>
</div>

</body>
</html>
