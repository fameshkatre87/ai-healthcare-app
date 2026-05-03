<?php
require_once '../config/db.php';
require_once '../api/ml_connect.php';
requireLogin();

$db      = getDB();
$uid     = (int)$_SESSION['user_id'];
$success = '';
$error   = '';

// Handle report upload + AI analysis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_type = $_POST['report_type'] ?? '';
    $report_text = trim($_POST['report_text'] ?? '');

    if (!$report_type || !$report_text) {
        $error = 'Please fill in report type and description.';
    } else {
        // Call ML API for analysis
        $analysis = analyzeReport($report_text, $report_type);
        $ai_text  = '';
        if (!isset($analysis['error'])) {
            $findings    = implode(' | ', $analysis['findings'] ?? []);
            $suggestions = implode(' | ', $analysis['suggestions'] ?? []);
            $ai_text     = "Findings: $findings || Suggestions: $suggestions || " . ($analysis['recommendation'] ?? '');
        } else {
            $ai_text = 'Manual review required. ' . $analysis['error'];
        }

        // BUG FIX: Was using raw $db->query() with real_escape_string — replaced with prepared statement
        $stmt = $db->prepare("INSERT INTO reports (patient_id, report_type, file_path, ai_analysis) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $uid, $report_type, $report_text, $ai_text);
        if ($stmt->execute()) {
            $success = 'Report uploaded and analyzed successfully!';
        } else {
            $error = 'Failed to save report. Please try again.';
        }
    }
}

// BUG FIX: Was using raw $uid directly in query string (SQL injection risk) — use prepared statement
$stmt = $db->prepare("SELECT * FROM reports WHERE patient_id = ? ORDER BY uploaded_at DESC");
$stmt->bind_param("i", $uid);
$stmt->execute();
$reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports — HealthAI</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
  .report-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:20px; margin-bottom:16px; }
  .report-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; }
  .analysis-section { background:#f8fafc; border-radius:10px; padding:14px; margin-top:12px; }
  .analysis-item { display:flex; gap:8px; font-size:13px; padding:5px 0; border-bottom:1px solid #e2e8f0; }
  .analysis-item:last-child { border:none; }
</style>
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
      <a href="appointment.php" class="nav-item"><span class="nav-icon">📅</span> Appointments</a>
      <div class="nav-section">Health</div>
      <a href="reports.php" class="nav-item active"><span class="nav-icon">📊</span> My Reports</a>
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
    <div class="topbar"><h1>📊 Medical Reports</h1></div>
    <div class="page-content">

      <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

      <div style="display:grid;grid-template-columns:1fr 1.5fr;gap:28px;align-items:start;">
        <!-- Upload Form -->
        <div class="card">
          <h3 class="card-title">📤 Upload / Add Report</h3>
          <form method="POST">
            <div class="form-group">
              <label class="form-label">Report Type *</label>
              <select name="report_type" class="form-control" required>
                <option value="">Select type...</option>
                <option value="blood" <?= ($_POST['report_type']??'')==='blood'?'selected':'' ?>>Blood Test</option>
                <option value="urine" <?= ($_POST['report_type']??'')==='urine'?'selected':'' ?>>Urine Test</option>
                <option value="xray"  <?= ($_POST['report_type']??'')==='xray'?'selected':'' ?>>X-Ray</option>
                <option value="mri"   <?= ($_POST['report_type']??'')==='mri'?'selected':'' ?>>MRI/CT</option>
                <option value="ecg"   <?= ($_POST['report_type']??'')==='ecg'?'selected':'' ?>>ECG</option>
                <option value="general" <?= ($_POST['report_type']??'')==='general'?'selected':'' ?>>General</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Report Description / Values *</label>
              <textarea name="report_text" class="form-control" rows="6"
                placeholder="Paste report text or describe values e.g. Hemoglobin: 11g/dL, Glucose: 120mg/dL, Cholesterol: 180mg/dL" required><?= htmlspecialchars($_POST['report_text'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-block">🤖 Analyze Report</button>
          </form>
        </div>

        <!-- Reports List -->
        <div>
          <h3 style="font-weight:700;margin-bottom:16px;">My Reports (<?= count($reports) ?>)</h3>
          <?php if (empty($reports)): ?>
            <div class="card" style="text-align:center;padding:40px;">
              <div style="font-size:48px;margin-bottom:12px;">📋</div>
              <p style="color:#64748b;">No reports yet. Add your first report above.</p>
            </div>
          <?php else: ?>
            <?php foreach ($reports as $r): ?>
            <div class="report-card">
              <div class="report-header">
                <div>
                  <span class="badge badge-info"><?= htmlspecialchars(strtoupper($r['report_type'])) ?></span>
                  <div style="font-size:12px;color:#94a3b8;margin-top:4px;">
                    <?= date('d M Y, h:i A', strtotime($r['uploaded_at'])) ?>
                  </div>
                </div>
              </div>
              <div style="font-size:13px;color:#475569;background:#f8fafc;padding:10px;border-radius:8px;max-height:80px;overflow:hidden;">
                <?= nl2br(htmlspecialchars(substr($r['file_path'], 0, 200))) ?>
                <?= strlen($r['file_path']) > 200 ? '...' : '' ?>
              </div>
              <?php if ($r['ai_analysis']): ?>
              <div class="analysis-section">
                <div style="font-weight:600;font-size:13px;margin-bottom:8px;">🤖 AI Analysis</div>
                <?php
                // Parse stored analysis string
                $parts = explode(' || ', $r['ai_analysis']);
                foreach ($parts as $part):
                    if (trim($part)):
                ?>
                <div class="analysis-item">
                  <span style="color:#0ea5e9;">•</span>
                  <span><?= htmlspecialchars($part) ?></span>
                </div>
                <?php endif; endforeach; ?>
              </div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
