<?php
require_once '../config/db.php';
requireRole('admin');

$db = getDB();

// ── DELETE prediction ─────────────────────────────────────
if (isset($_GET['delete'])) {
    $rid  = (int)$_GET['delete'];
    $stmt = $db->prepare("DELETE FROM predictions WHERE id=?");
    $stmt->bind_param("i", $rid);
    $stmt->execute();
    header('Location: admin_predictions.php?msg=deleted');
    exit();
}

// ── Filters ───────────────────────────────────────────────
$search     = trim($_GET['q'] ?? '');
$filterDis  = trim($_GET['disease'] ?? '');

$sql    = "SELECT p.*, u.name AS patient_name FROM predictions p JOIN users u ON p.patient_id=u.id WHERE 1=1";
$params = [];
$types  = '';

if ($search !== '') {
    $sql    .= " AND (u.name LIKE ? OR p.symptoms LIKE ?)";
    $like    = '%' . $search . '%';
    $params  = [$like, $like];
    $types  .= 'ss';
}
if ($filterDis !== '') {
    $sql    .= " AND p.predicted_disease=?";
    $params[] = $filterDis;
    $types   .= 's';
}
$sql .= " ORDER BY p.created_at DESC";

$stmt = $db->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$predictions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$totalPreds = $db->query("SELECT COUNT(*) c FROM predictions")->fetch_assoc()['c'];
$diseases   = $db->query("SELECT DISTINCT predicted_disease FROM predictions ORDER BY predicted_disease")->fetch_all(MYSQLI_ASSOC);

// Top disease stats
$topStats = $db->query("SELECT predicted_disease, COUNT(*) cnt, AVG(confidence) avg_conf FROM predictions GROUP BY predicted_disease ORDER BY cnt DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI Predictions — HealthAI Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
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
      <a href="admin_appointments.php" class="nav-item"><span class="nav-icon">📅</span> Appointments</a>
      <a href="admin_reports.php" class="nav-item"><span class="nav-icon">📋</span> All Reports</a>
      <a href="admin_predictions.php" class="nav-item active"><span class="nav-icon">🔬</span> AI Predictions</a>
      <div class="nav-section">Account</div>
      <a href="../logout.php" class="nav-item"><span class="nav-icon">🚪</span> Logout</a>
    </nav>
    <div class="sidebar-user">
      <div class="avatar">A</div>
      <div class="user-info"><p><?= htmlspecialchars($_SESSION['name']) ?></p><span>Administrator</span></div>
    </div>
  </aside>

  <div class="main-content">
    <div class="topbar"><h1>🔬 All AI Predictions (<?= $totalPreds ?>)</h1></div>
    <div class="page-content">

      <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success">✅ Prediction record deleted.</div>
      <?php endif; ?>

      <!-- Top 5 disease quick filter cards -->
      <?php if (!empty($topStats)): ?>
      <div style="display:flex;gap:12px;margin-bottom:22px;flex-wrap:wrap;">
        <?php foreach ($topStats as $ts): ?>
        <a href="admin_predictions.php?disease=<?= urlencode($ts['predicted_disease']) ?>"
           style="background:#fff;border:1px solid <?= $filterDis===$ts['predicted_disease']?'#0ea5e9':'#e2e8f0' ?>;
           border-radius:12px;padding:12px 18px;text-decoration:none;transition:all .2s;
           <?= $filterDis===$ts['predicted_disease']?'background:#e0f2fe;':''; ?>">
          <div style="font-weight:700;font-size:14px;color:#1e293b;"><?= htmlspecialchars($ts['predicted_disease']) ?></div>
          <div style="font-size:12px;color:#64748b;margin-top:2px;"><?= $ts['cnt'] ?> cases · <?= round($ts['avg_conf']) ?>% avg</div>
        </a>
        <?php endforeach; ?>
        <?php if ($filterDis): ?>
        <a href="admin_predictions.php" class="btn btn-outline btn-sm" style="align-self:center;">✕ Clear</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Filters -->
      <form method="GET" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:flex-end;">
        <?php if ($filterDis): ?><input type="hidden" name="disease" value="<?= htmlspecialchars($filterDis) ?>"><?php endif; ?>
        <div>
          <label class="form-label">Search</label>
          <input type="text" name="q" class="form-control" placeholder="Patient name or symptom..." value="<?= htmlspecialchars($search) ?>" style="width:280px;">
        </div>
        <div>
          <label class="form-label">Disease</label>
          <select name="disease" class="form-control" style="width:200px;">
            <option value="">All Diseases</option>
            <?php foreach ($diseases as $d): ?>
            <option value="<?= htmlspecialchars($d['predicted_disease']) ?>" <?= $filterDis===$d['predicted_disease']?'selected':'' ?>><?= htmlspecialchars($d['predicted_disease']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary">🔍 Filter</button>
        <a href="admin_predictions.php" class="btn btn-outline">✕ Reset</a>
      </form>

      <?php if (empty($predictions)): ?>
        <div class="card" style="text-align:center;padding:48px;">
          <div style="font-size:48px;margin-bottom:12px;">🔬</div>
          <p style="color:#64748b;">No predictions found.</p>
        </div>
      <?php else: ?>
      <div class="card">
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Patient</th>
                <th>Predicted Disease</th>
                <th>Confidence</th>
                <th>Symptoms</th>
                <th>Date</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($predictions as $i => $p): ?>
            <tr>
              <td style="color:#94a3b8;font-size:12px;"><?= $i+1 ?></td>
              <td><strong><?= htmlspecialchars($p['patient_name']) ?></strong></td>
              <td>
                <strong style="color:#0ea5e9;"><?= htmlspecialchars($p['predicted_disease']) ?></strong>
              </td>
              <td>
                <span class="badge badge-<?= $p['confidence']>80?'success':($p['confidence']>60?'warning':'danger') ?>">
                  <?= round($p['confidence']) ?>%
                </span>
              </td>
              <td style="font-size:12px;color:#64748b;max-width:200px;">
                <?= htmlspecialchars(substr($p['symptoms'] ?? '', 0, 80)) ?>
                <?= strlen($p['symptoms'] ?? '') > 80 ? '…' : '' ?>
              </td>
              <td style="font-size:12px;color:#94a3b8;"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
              <td>
                <a href="admin_predictions.php?delete=<?= $p['id'] ?>"
                   class="btn btn-danger btn-sm"
                   onclick="return confirm('Delete this prediction record?')">🗑️</a>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
