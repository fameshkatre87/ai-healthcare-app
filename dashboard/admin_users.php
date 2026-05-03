<?php
require_once '../config/db.php';
requireRole('admin');

$db      = getDB();
$success = '';
$error   = '';

// ── DELETE user ──────────────────────────────────────────
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    // Prevent deleting yourself or other admins
    $chk = $db->prepare("SELECT role FROM users WHERE id=?");
    $chk->bind_param("i", $did);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    if ($row && $row['role'] !== 'admin') {
        $stmt = $db->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $did);
        $stmt->execute();
        $success = 'User deleted successfully.';
    } else {
        $error = 'Cannot delete admin accounts.';
    }
}

// ── ADD new patient ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone    = trim($_POST['phone'] ?? '');
    $gender   = $_POST['gender'] ?? '';
    $age      = (int)($_POST['age'] ?? 0);

    if (!$name || !$email || !$password) {
        $error = 'Name, email and password are required.';
    } else {
        $chk = $db->prepare("SELECT id FROM users WHERE email=?");
        $chk->bind_param("s", $email);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error = 'Email already registered.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt   = $db->prepare("INSERT INTO users (name,email,password,role,phone,gender,age) VALUES (?,?,?,'patient',?,?,?)");
            $stmt->bind_param("sssssi", $name, $email, $hashed, $phone, $gender, $age);
            if ($stmt->execute()) {
                $newId = (int)$db->insert_id;
                $stmtRec = $db->prepare("INSERT INTO patient_records (patient_id) VALUES (?)");
                $stmtRec->bind_param("i", $newId);
                $stmtRec->execute();
                $success = "Patient '$name' added successfully.";
            } else {
                $error = 'Failed to add patient.';
            }
        }
    }
}

// ── EDIT patient ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $uid    = (int)$_POST['uid'];
    $name   = trim($_POST['name'] ?? '');
    $phone  = trim($_POST['phone'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $age    = (int)($_POST['age'] ?? 0);

    if (!$name) {
        $error = 'Name is required.';
    } else {
        $stmt = $db->prepare("UPDATE users SET name=?,phone=?,gender=?,age=? WHERE id=? AND role='patient'");
        $stmt->bind_param("sssii", $name, $phone, $gender, $age, $uid);
        $stmt->execute();

        // Update password if provided
        if (!empty($_POST['new_password'])) {
            $hashed = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $stmtPw = $db->prepare("UPDATE users SET password=? WHERE id=?");
            $stmtPw->bind_param("si", $hashed, $uid);
            $stmtPw->execute();
        }
        $success = 'Patient updated successfully.';
    }
}

// ── Fetch edit target ────────────────────────────────────
$editUser = null;
if (isset($_GET['edit'])) {
    $eid  = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM users WHERE id=? AND role='patient'");
    $stmt->bind_param("i", $eid);
    $stmt->execute();
    $editUser = $stmt->get_result()->fetch_assoc();
}

// ── Search + list all patients ───────────────────────────
$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $stmt = $db->prepare("
        SELECT u.*, pr.blood_group
        FROM users u
        LEFT JOIN patient_records pr ON pr.patient_id=u.id
        WHERE u.role='patient' AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)
        ORDER BY u.created_at DESC
    ");
    $like = '%' . $search . '%';
    $stmt->bind_param("sss", $like, $like, $like);
} else {
    $stmt = $db->prepare("
        SELECT u.*, pr.blood_group
        FROM users u
        LEFT JOIN patient_records pr ON pr.patient_id=u.id
        WHERE u.role='patient'
        ORDER BY u.created_at DESC
    ");
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users — HealthAI Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
  .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;}
  .modal-overlay.open{display:flex;}
  .modal{background:#fff;border-radius:16px;padding:28px;width:100%;max-width:500px;max-height:90vh;overflow-y:auto;}
  .modal h3{font-size:20px;font-weight:700;margin-bottom:20px;}
  .modal-close{float:right;background:none;border:none;font-size:22px;cursor:pointer;color:#64748b;}
  .two-col-form{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
</style>
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="sidebar-logo"><div class="sidebar-logo-icon">❤️</div><div><h2>Health<span>AI</span></h2><p>Admin Panel</p></div></div>
    <nav class="sidebar-nav">
      <div class="nav-section">Management</div>
      <a href="admin.php" class="nav-item"><span class="nav-icon">📊</span> Dashboard</a>
      <a href="admin_users.php" class="nav-item active"><span class="nav-icon">👥</span> Manage Users</a>
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
      <h1>👥 Manage Users (Patients)</h1>
      <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('open')">+ Add Patient</button>
    </div>
    <div class="page-content">

      <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if ($error):   ?><div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

      <!-- Search bar -->
      <form method="GET" style="display:flex;gap:10px;margin-bottom:20px;">
        <input type="text" name="q" class="form-control" placeholder="Search by name, email or phone..." value="<?= htmlspecialchars($search) ?>" style="max-width:360px;">
        <button type="submit" class="btn btn-primary">🔍 Search</button>
        <?php if ($search): ?><a href="admin_users.php" class="btn btn-outline">✕ Clear</a><?php endif; ?>
      </form>

      <!-- Edit form inline -->
      <?php if ($editUser): ?>
      <div class="card" style="margin-bottom:20px;border:2px solid #0ea5e9;">
        <h3 class="card-title">✏️ Edit Patient: <?= htmlspecialchars($editUser['name']) ?></h3>
        <form method="POST">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="uid" value="<?= $editUser['id'] ?>">
          <div class="two-col-form">
            <div class="form-group">
              <label class="form-label">Full Name *</label>
              <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($editUser['name']) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Email (read-only)</label>
              <input type="email" class="form-control" disabled value="<?= htmlspecialchars($editUser['email']) ?>" style="background:#f8fafc;color:#94a3b8;">
            </div>
            <div class="form-group">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($editUser['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Age</label>
              <input type="number" name="age" class="form-control" min="1" max="120" value="<?= (int)($editUser['age'] ?? 0) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Gender</label>
              <select name="gender" class="form-control">
                <option value="">Select...</option>
                <?php foreach (['Male','Female','Other'] as $g): ?>
                <option value="<?= $g ?>" <?= ($editUser['gender'] ?? '')===$g?'selected':'' ?>><?= $g ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">New Password <small style="font-weight:400;">(leave blank to keep)</small></label>
              <input type="password" name="new_password" class="form-control" placeholder="Set new password">
            </div>
          </div>
          <div style="display:flex;gap:10px;margin-top:6px;">
            <button type="submit" class="btn btn-primary">💾 Save Changes</button>
            <a href="admin_users.php" class="btn btn-outline">Cancel</a>
          </div>
        </form>
      </div>
      <?php endif; ?>

      <!-- Users table -->
      <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
          <h3 class="card-title" style="margin:0;">All Patients (<?= count($users) ?>)</h3>
        </div>
        <?php if (empty($users)): ?>
          <p style="text-align:center;color:#64748b;padding:32px;">No patients found<?= $search ? " for \"$search\"" : '' ?>.</p>
        <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Age / Gender</th>
                <th>Blood</th>
                <th>Joined</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $i => $u): ?>
            <tr>
              <td style="color:#94a3b8;font-size:12px;"><?= $i+1 ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:10px;">
                  <div style="width:34px;height:34px;border-radius:50%;background:#e0f2fe;color:#0284c7;
                    display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;flex-shrink:0;">
                    <?= strtoupper(substr($u['name'],0,1)) ?>
                  </div>
                  <strong><?= htmlspecialchars($u['name']) ?></strong>
                </div>
              </td>
              <td style="font-size:13px;color:#64748b;"><?= htmlspecialchars($u['email']) ?></td>
              <td style="font-size:13px;"><?= htmlspecialchars($u['phone'] ?? '—') ?></td>
              <td style="font-size:13px;"><?= ($u['age'] ?? '—') ?> / <?= htmlspecialchars($u['gender'] ?? '—') ?></td>
              <td>
                <?php if ($u['blood_group']): ?>
                  <span class="badge badge-danger"><?= htmlspecialchars($u['blood_group']) ?></span>
                <?php else: ?>
                  <span style="color:#94a3b8;font-size:12px;">—</span>
                <?php endif; ?>
              </td>
              <td style="font-size:12px;color:#94a3b8;"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
              <td>
                <div style="display:flex;gap:6px;">
                  <a href="admin_users.php?edit=<?= $u['id'] ?>" class="btn btn-outline btn-sm">✏️ Edit</a>
                  <a href="admin_users.php?delete=<?= $u['id'] ?>"
                     class="btn btn-danger btn-sm"
                     onclick="return confirm('Delete <?= htmlspecialchars(addslashes($u['name'])) ?>? This cannot be undone.')">🗑️</a>
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

<!-- ADD Patient Modal -->
<div class="modal-overlay" id="addModal">
  <div class="modal">
    <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('open')">×</button>
    <h3>➕ Add New Patient</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="two-col-form">
        <div class="form-group" style="grid-column:1/-1;">
          <label class="form-label">Full Name *</label>
          <input type="text" name="name" class="form-control" required placeholder="Patient full name">
        </div>
        <div class="form-group" style="grid-column:1/-1;">
          <label class="form-label">Email *</label>
          <input type="email" name="email" class="form-control" required placeholder="patient@email.com">
        </div>
        <div class="form-group">
          <label class="form-label">Password *</label>
          <input type="password" name="password" class="form-control" required placeholder="Min 6 chars">
        </div>
        <div class="form-group">
          <label class="form-label">Phone</label>
          <input type="text" name="phone" class="form-control" placeholder="10-digit number">
        </div>
        <div class="form-group">
          <label class="form-label">Age</label>
          <input type="number" name="age" class="form-control" min="1" max="120" placeholder="Age">
        </div>
        <div class="form-group">
          <label class="form-label">Gender</label>
          <select name="gender" class="form-control">
            <option value="">Select...</option>
            <option>Male</option><option>Female</option><option>Other</option>
          </select>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-block" style="margin-top:8px;">✅ Add Patient</button>
    </form>
  </div>
</div>

</body>
</html>
