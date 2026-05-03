<?php
require_once '../config/db.php';
requireRole('admin');

$db      = getDB();
$success = '';
$error   = '';

// ── DELETE doctor ─────────────────────────────────────────
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    // Deleting from users cascades to doctors table
    $stmt = $db->prepare("DELETE FROM users WHERE id=? AND role='doctor'");
    $stmt->bind_param("i", $did);
    $stmt->execute();
    if ($db->affected_rows > 0) {
        $success = 'Doctor deleted successfully.';
    } else {
        $error = 'Doctor not found.';
    }
}

// ── ADD new doctor ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone    = trim($_POST['phone'] ?? '');
    $gender   = $_POST['gender'] ?? '';
    $age      = (int)($_POST['age'] ?? 0);
    $spec     = trim($_POST['specialization'] ?? '');
    $qual     = trim($_POST['qualification'] ?? '');
    $exp      = (int)($_POST['experience'] ?? 0);
    $days     = trim($_POST['available_days'] ?? '');
    $fee      = (float)($_POST['fee'] ?? 0);
    $hospital = trim($_POST['hospital'] ?? '');

    if (!$name || !$email || !$password || !$spec) {
        $error = 'Name, email, password and specialization are required.';
    } else {
        $chk = $db->prepare("SELECT id FROM users WHERE email=?");
        $chk->bind_param("s", $email);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error = 'Email already registered.';
        } else {
            $db->begin_transaction();
            try {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt   = $db->prepare("INSERT INTO users (name,email,password,role,phone,gender,age) VALUES (?,?,?,'doctor',?,?,?)");
                $stmt->bind_param("sssssi", $name, $email, $hashed, $phone, $gender, $age);
                $stmt->execute();
                $newUid = (int)$db->insert_id;

                $stmt2 = $db->prepare("INSERT INTO doctors (user_id,specialization,qualification,experience,available_days,fee,hospital) VALUES (?,?,?,?,?,?,?)");
                // types: i=user_id, s=spec, s=qual, i=exp, s=days, d=fee, s=hospital
                $stmt2->bind_param("issisds", $newUid, $spec, $qual, $exp, $days, $fee, $hospital);
                $stmt2->execute();

                $db->commit();
                $success = "Dr. $name added successfully.";
            } catch (Exception $e) {
                $db->rollback();
                $error = 'Failed to add doctor: ' . $e->getMessage();
            }
        }
    }
}

// ── EDIT doctor ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $uid      = (int)$_POST['uid'];
    $name     = trim($_POST['name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $gender   = $_POST['gender'] ?? '';
    $age      = (int)($_POST['age'] ?? 0);
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
            $stmt = $db->prepare("UPDATE users SET name=?,phone=?,gender=?,age=? WHERE id=? AND role='doctor'");
            $stmt->bind_param("sssii", $name, $phone, $gender, $age, $uid);
            $stmt->execute();

            $stmt2 = $db->prepare("UPDATE doctors SET specialization=?,qualification=?,experience=?,available_days=?,fee=?,hospital=? WHERE user_id=?");
            $stmt2->bind_param("ssisdsi", $spec, $qual, $exp, $days, $fee, $hospital, $uid);
            $stmt2->execute();

            if (!empty($_POST['new_password'])) {
                $hashed = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $stmtPw = $db->prepare("UPDATE users SET password=? WHERE id=?");
                $stmtPw->bind_param("si", $hashed, $uid);
                $stmtPw->execute();
            }

            $db->commit();
            $success = 'Doctor updated successfully.';
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Update failed: ' . $e->getMessage();
        }
    }
}

// ── Fetch edit target ─────────────────────────────────────
$editDoc = null;
if (isset($_GET['edit'])) {
    $eid  = (int)$_GET['edit'];
    $stmt = $db->prepare("
        SELECT u.*, d.specialization, d.qualification, d.experience,
               d.available_days, d.fee, d.hospital
        FROM users u JOIN doctors d ON d.user_id=u.id
        WHERE u.id=? AND u.role='doctor'
    ");
    $stmt->bind_param("i", $eid);
    $stmt->execute();
    $editDoc = $stmt->get_result()->fetch_assoc();
}

// ── List all doctors ──────────────────────────────────────
$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $stmt = $db->prepare("
        SELECT u.id, u.name, u.email, u.phone, u.gender, u.age, u.created_at,
               d.specialization, d.qualification, d.experience, d.fee, d.hospital, d.available_days
        FROM users u JOIN doctors d ON d.user_id=u.id
        WHERE u.role='doctor' AND (u.name LIKE ? OR d.specialization LIKE ? OR d.hospital LIKE ?)
        ORDER BY d.specialization, u.name
    ");
    $like = '%' . $search . '%';
    $stmt->bind_param("sss", $like, $like, $like);
} else {
    $stmt = $db->prepare("
        SELECT u.id, u.name, u.email, u.phone, u.gender, u.age, u.created_at,
               d.specialization, d.qualification, d.experience, d.fee, d.hospital, d.available_days
        FROM users u JOIN doctors d ON d.user_id=u.id
        WHERE u.role='doctor'
        ORDER BY d.specialization, u.name
    ");
}
$stmt->execute();
$doctors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$specializations = ['Cardiologist','Neurologist','Dermatologist','Orthopedic','Gastroenterologist',
    'Endocrinologist','Pulmonologist','Psychiatrist','Gynecologist','Pediatrician',
    'Ophthalmologist','ENT Specialist','General Physician'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Doctors — HealthAI Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
  .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;}
  .modal-overlay.open{display:flex;}
  .modal{background:#fff;border-radius:16px;padding:28px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto;}
  .modal h3{font-size:20px;font-weight:700;margin-bottom:20px;}
  .modal-close{float:right;background:none;border:none;font-size:22px;cursor:pointer;color:#64748b;}
  .g2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
  .g3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;}
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
      <a href="admin_doctors.php" class="nav-item active"><span class="nav-icon">👨‍⚕️</span> Manage Doctors</a>
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
      <h1>👨‍⚕️ Manage Doctors</h1>
      <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('open')">+ Add Doctor</button>
    </div>
    <div class="page-content">

      <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if ($error):   ?><div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

      <!-- Search -->
      <form method="GET" style="display:flex;gap:10px;margin-bottom:20px;">
        <input type="text" name="q" class="form-control" placeholder="Search by name, specialization or hospital..." value="<?= htmlspecialchars($search) ?>" style="max-width:400px;">
        <button type="submit" class="btn btn-primary">🔍 Search</button>
        <?php if ($search): ?><a href="admin_doctors.php" class="btn btn-outline">✕ Clear</a><?php endif; ?>
      </form>

      <!-- Inline edit form -->
      <?php if ($editDoc): ?>
      <div class="card" style="margin-bottom:20px;border:2px solid #0ea5e9;">
        <h3 class="card-title">✏️ Edit Doctor: <?= htmlspecialchars($editDoc['name']) ?></h3>
        <form method="POST">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="uid" value="<?= $editDoc['id'] ?>">
          <div class="g2">
            <div class="form-group">
              <label class="form-label">Full Name *</label>
              <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($editDoc['name']) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Email (read-only)</label>
              <input type="email" class="form-control" disabled value="<?= htmlspecialchars($editDoc['email']) ?>" style="background:#f8fafc;color:#94a3b8;">
            </div>
            <div class="form-group">
              <label class="form-label">Specialization *</label>
              <select name="specialization" class="form-control" required>
                <?php foreach ($specializations as $sp): ?>
                <option value="<?= $sp ?>" <?= $editDoc['specialization']===$sp?'selected':'' ?>><?= $sp ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Qualification</label>
              <input type="text" name="qualification" class="form-control" value="<?= htmlspecialchars($editDoc['qualification'] ?? '') ?>" placeholder="e.g. MD Cardiology">
            </div>
            <div class="form-group">
              <label class="form-label">Experience (years)</label>
              <input type="number" name="experience" class="form-control" min="0" value="<?= (int)($editDoc['experience'] ?? 0) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Consultation Fee (₹)</label>
              <input type="number" name="fee" class="form-control" min="0" step="50" value="<?= (float)($editDoc['fee'] ?? 0) ?>">
            </div>
            <div class="form-group" style="grid-column:1/-1;">
              <label class="form-label">Hospital / Clinic</label>
              <input type="text" name="hospital" class="form-control" value="<?= htmlspecialchars($editDoc['hospital'] ?? '') ?>" placeholder="Hospital name">
            </div>
            <div class="form-group">
              <label class="form-label">Available Days</label>
              <input type="text" name="available_days" class="form-control" value="<?= htmlspecialchars($editDoc['available_days'] ?? '') ?>" placeholder="e.g. Mon,Wed,Fri">
            </div>
            <div class="form-group">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($editDoc['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Age</label>
              <input type="number" name="age" class="form-control" value="<?= (int)($editDoc['age'] ?? 0) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Gender</label>
              <select name="gender" class="form-control">
                <option value="">Select...</option>
                <?php foreach (['Male','Female','Other'] as $g): ?>
                <option value="<?= $g ?>" <?= ($editDoc['gender'] ?? '')===$g?'selected':'' ?>><?= $g ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">New Password <small style="font-weight:400;">(leave blank to keep)</small></label>
              <input type="password" name="new_password" class="form-control" placeholder="Set new password">
            </div>
          </div>
          <div style="display:flex;gap:10px;margin-top:8px;">
            <button type="submit" class="btn btn-primary">💾 Save Changes</button>
            <a href="admin_doctors.php" class="btn btn-outline">Cancel</a>
          </div>
        </form>
      </div>
      <?php endif; ?>

      <!-- Doctors table -->
      <div class="card">
        <h3 class="card-title" style="margin-bottom:14px;">All Doctors (<?= count($doctors) ?>)</h3>
        <?php if (empty($doctors)): ?>
          <p style="text-align:center;color:#64748b;padding:32px;">No doctors found.</p>
        <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Doctor</th>
                <th>Specialization</th>
                <th>Hospital</th>
                <th>Experience</th>
                <th>Fee</th>
                <th>Available Days</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($doctors as $i => $d): ?>
            <tr>
              <td style="color:#94a3b8;font-size:12px;"><?= $i+1 ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:10px;">
                  <div style="width:34px;height:34px;border-radius:50%;background:#dcfce7;color:#166534;
                    display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;flex-shrink:0;">
                    <?= strtoupper(substr($d['name'],0,1)) ?>
                  </div>
                  <div>
                    <div style="font-weight:600;font-size:14px;"><?= htmlspecialchars($d['name']) ?></div>
                    <div style="font-size:12px;color:#64748b;"><?= htmlspecialchars($d['email']) ?></div>
                  </div>
                </div>
              </td>
              <td><span class="badge badge-info"><?= htmlspecialchars($d['specialization']) ?></span><br><small style="color:#64748b;"><?= htmlspecialchars($d['qualification'] ?? '') ?></small></td>
              <td style="font-size:13px;">🏥 <?= htmlspecialchars($d['hospital'] ?? '—') ?></td>
              <td style="font-size:13px;"><?= (int)($d['experience'] ?? 0) ?> yrs</td>
              <td style="font-size:13px;font-weight:600;color:#10b981;">₹<?= number_format((float)($d['fee'] ?? 0), 0) ?></td>
              <td style="font-size:12px;color:#64748b;"><?= htmlspecialchars($d['available_days'] ?? '—') ?></td>
              <td>
                <div style="display:flex;gap:6px;">
                  <a href="admin_doctors.php?edit=<?= $d['id'] ?>" class="btn btn-outline btn-sm">✏️ Edit</a>
                  <a href="admin_doctors.php?delete=<?= $d['id'] ?>"
                     class="btn btn-danger btn-sm"
                     onclick="return confirm('Delete Dr. <?= htmlspecialchars(addslashes($d['name'])) ?>? All their appointments will also be deleted.')">🗑️</a>
                </div>
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

<!-- ADD Doctor Modal -->
<div class="modal-overlay" id="addModal">
  <div class="modal">
    <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('open')">×</button>
    <h3>➕ Add New Doctor</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="g2">
        <div class="form-group" style="grid-column:1/-1;">
          <label class="form-label">Full Name * <small style="font-weight:400;">(e.g. Dr. Priya Sharma)</small></label>
          <input type="text" name="name" class="form-control" required placeholder="Dr. Full Name">
        </div>
        <div class="form-group">
          <label class="form-label">Email *</label>
          <input type="email" name="email" class="form-control" required placeholder="doctor@hospital.com">
        </div>
        <div class="form-group">
          <label class="form-label">Password *</label>
          <input type="password" name="password" class="form-control" required placeholder="Min 6 chars">
        </div>
        <div class="form-group">
          <label class="form-label">Specialization *</label>
          <select name="specialization" class="form-control" required>
            <option value="">Select...</option>
            <?php foreach ($specializations as $sp): ?>
            <option><?= $sp ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Qualification</label>
          <input type="text" name="qualification" class="form-control" placeholder="e.g. MD Cardiology">
        </div>
        <div class="form-group">
          <label class="form-label">Experience (years)</label>
          <input type="number" name="experience" class="form-control" min="0" value="0">
        </div>
        <div class="form-group">
          <label class="form-label">Fee (₹)</label>
          <input type="number" name="fee" class="form-control" min="0" step="50" value="500">
        </div>
        <div class="form-group" style="grid-column:1/-1;">
          <label class="form-label">Hospital / Clinic</label>
          <input type="text" name="hospital" class="form-control" placeholder="Hospital name">
        </div>
        <div class="form-group">
          <label class="form-label">Available Days</label>
          <input type="text" name="available_days" class="form-control" placeholder="e.g. Mon,Wed,Fri">
        </div>
        <div class="form-group">
          <label class="form-label">Phone</label>
          <input type="text" name="phone" class="form-control" placeholder="10-digit number">
        </div>
        <div class="form-group">
          <label class="form-label">Age</label>
          <input type="number" name="age" class="form-control" min="25" max="80" value="35">
        </div>
        <div class="form-group">
          <label class="form-label">Gender</label>
          <select name="gender" class="form-control">
            <option value="">Select...</option>
            <option>Male</option><option>Female</option><option>Other</option>
          </select>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-block" style="margin-top:8px;">✅ Add Doctor</button>
    </form>
  </div>
</div>

</body>
</html>
