<?php
require_once '../config/db.php';
requireRole('admin');

$db = getDB();

// ── DELETE report ─────────────────────────────────────────
if (isset($_GET['delete'])) {
    $rid  = (int)$_GET['delete'];
    $stmt = $db->prepare("DELETE FROM reports WHERE id=?");
    $stmt->bind_param("i", $rid);
    $stmt->execute();
    header('Location: admin_reports.php?msg=deleted');
    exit();
}

// ── Filters ───────────────────────────────────────────────
$filterType = trim($_GET['type'] ?? '');
$search     = trim($_GET['q'] ?? '');

$sql    = "SELECT r.*, u.name AS patient_name, u.email AS patient_email FROM reports r JOIN users u ON r.patient_id=u.id WHERE 1=1";
$params = [];
$types  = '';

if ($filterType !== '') {
    $sql    .= " AND r.report_type=?";
    $params[] = $filterType;
    $types   .= 's';
}
if ($search !== '') {
    $sql    .= " AND (u.name LIKE ? OR r.report_type LIKE ?)";
    $like    = '%' . $search . '%';
    $params  = array_merge($params, [$like, $like]);
    $types  .= 'ss';
}
$sql .= " ORDER BY r.uploaded_at DESC";

$stmt = $db->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$totalReports = $db->query("SELECT COUNT(*) c FROM reports")->fetch_assoc()['c'];
$reportTypes  = $db->query("SELECT DISTINCT report_type FROM reports ORDER BY report_type")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>All Reports — HealthAI Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
  .report-box{background:#f8fafc;border-radius:10px;padding:12px;margin-top:10px;font-size:13px;color:#475569;max-height:100px;overflow:hidden;position:relative;}
  .report-box.expanded{max-height:none;}
  .expand-btn{font-size:12px;color:#0ea5e9;cursor:pointer;background:none;border:none;padding:0;font-family:inherit;}
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
      <a href="admin_appointments.php" class="nav-item"><span class="nav-icon">📅</span> Appointments</a>
      <a href="admin_reports.php" class="nav-item active"><span class="nav-icon">📋</span> All Reports</a>
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
    <div class="topbar"><h1>📋 All Patient Reports (<?= $totalReports ?>)</h1></div>
    <div class="page-content">

      <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success">✅ Report deleted.</div>
      <?php endif; ?>

      <!-- Filters -->
      <form method="GET" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:flex-end;">
        <div>
          <label class="form-label">Search Patient</label>
          <input type="text" name="q" class="form-control" placeholder="Patient name..." value="<?= htmlspecialchars($search) ?>" style="width:220px;">
        </div>
        <div>
          <label class="form-label">Report Type</label>
          <select name="type" class="form-control" style="width:160px;">
            <option value="">All Types</option>
            <?php foreach ($reportTypes as $rt): ?>
            <option value="<?= htmlspecialchars($rt['report_type']) ?>" <?= $filterType===$rt['report_type']?'selected':'' ?>><?= ucfirst(htmlspecialchars($rt['report_type'])) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary">🔍 Filter</button>
        <a href="admin_reports.php" class="btn btn-outline">✕ Reset</a>
      </form>

      <?php if (empty($reports)): ?>
        <div class="card" style="text-align:center;padding:48px;">
          <div style="font-size:48px;margin-bottom:12px;">📋</div>
          <p style="color:#64748b;">No reports found.</p>
        </div>
      <?php else: ?>
      <div class="card">
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Patient</th>
                <th>Type</th>
                <th>Report Content</th>
                <th>AI Analysis</th>
                <th>Date</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($reports as $i => $r): ?>
            <tr>
              <td style="color:#94a3b8;font-size:12px;"><?= $i+1 ?></td>
              <td>
                <strong><?= htmlspecialchars($r['patient_name']) ?></strong><br>
                <small style="color:#64748b;"><?= htmlspecialchars($r['patient_email']) ?></small>
              </td>
              <td><span class="badge badge-info"><?= strtoupper(htmlspecialchars($r['report_type'] ?? 'general')) ?></span></td>
              <td style="max-width:200px;">
                <div style="font-size:13px;color:#475569;background:#f8fafc;padding:8px;border-radius:7px;max-height:60px;overflow:hidden;">
                  <?= nl2br(htmlspecialchars(substr($r['file_path'] ?? '', 0, 120))) ?>
                  <?= strlen($r['file_path'] ?? '') > 120 ? '…' : '' ?>
                </div>
              </td>
              <td style="max-width:220px;">
                <?php if ($r['ai_analysis']): ?>
                <div style="font-size:12px;color:#075985;background:#e0f2fe;padding:8px;border-radius:7px;max-height:60px;overflow:hidden;">
                  <?= htmlspecialchars(substr($r['ai_analysis'], 0, 150)) ?>
                  <?= strlen($r['ai_analysis']) > 150 ? '…' : '' ?>
                </div>
                <?php else: ?>
                <span style="color:#94a3b8;font-size:12px;">No analysis</span>
                <?php endif; ?>
              </td>
              <td style="font-size:12px;color:#94a3b8;"><?= date('d M Y', strtotime($r['uploaded_at'])) ?></td>
              <td>
                <a href="admin_reports.php?delete=<?= $r['id'] ?>"
                   class="btn btn-danger btn-sm"
                   onclick="return confirm('Delete this report?')">🗑️</a>
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
