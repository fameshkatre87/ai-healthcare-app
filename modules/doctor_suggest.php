<?php
require_once '../config/db.php';
requireLogin();

$db      = getDB();
$disease = $_GET['disease'] ?? '';
$search  = trim($_GET['search'] ?? '');
$spec    = trim($_GET['spec'] ?? '');

// Disease → specialization mapping
$diseaseSpecMap = [
    'Heart attack' => 'Cardiologist', 'Hypertension' => 'Cardiologist',
    'Paralysis (brain hemorrhage)' => 'Neurologist', 'Migraine' => 'Neurologist',
    'Acne' => 'Dermatologist', 'Psoriasis' => 'Dermatologist', 'Fungal infection' => 'Dermatologist',
    'Arthritis' => 'Orthopedic', 'Osteoarthritis' => 'Orthopedic',
    'Diabetes' => 'Endocrinologist', 'Hypothyroidism' => 'Endocrinologist',
    'Bronchial Asthma' => 'Pulmonologist', 'Tuberculosis' => 'Pulmonologist',
    'Jaundice' => 'Gastroenterologist', 'GERD' => 'Gastroenterologist',
];

// BUG FIX: Was building SQL with addslashes() — replaced with proper parameterized query
$sql    = "SELECT u.id, u.name, u.email, u.phone, u.gender,
                  d.specialization, d.qualification, d.experience,
                  d.available_days, d.fee, d.hospital
           FROM users u
           JOIN doctors d ON d.user_id = u.id
           WHERE 1=1";
$params = [];
$types  = '';

if ($search !== '') {
    $sql    .= " AND u.name LIKE ?";
    $params[] = '%' . $search . '%';
    $types   .= 's';
}

// Auto-suggest specialization from disease if no explicit spec filter
$suggestedSpec = ($disease && isset($diseaseSpecMap[$disease])) ? $diseaseSpecMap[$disease] : '';
$activeSpec    = $spec !== '' ? $spec : $suggestedSpec;

if ($activeSpec !== '') {
    $sql    .= " AND d.specialization = ?";
    $params[] = $activeSpec;
    $types   .= 's';
}

$sql .= " ORDER BY d.experience DESC";

$stmt = $db->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$doctors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// All specializations
$specs = $db->query("SELECT DISTINCT specialization FROM doctors ORDER BY specialization")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Find Doctors — HealthAI</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
  .doctor-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; display: flex; gap: 20px; align-items: flex-start; transition: box-shadow .2s; }
  .doctor-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.08); }
  .doctor-avatar { width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg,#0ea5e9,#0284c7); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 800; flex-shrink: 0; }
  .doctor-info { flex: 1; }
  .doctor-info h3 { font-size: 17px; font-weight: 700; margin-bottom: 4px; }
  .doctor-meta { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 8px; }
  .doctor-meta span { font-size: 13px; color: #64748b; display: flex; align-items: center; gap: 5px; }
  .doctor-actions { display: flex; flex-direction: column; gap: 8px; align-items: flex-end; }
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
      <a href="doctor_suggest.php" class="nav-item active"><span class="nav-icon">👨‍⚕️</span> Find Doctors</a>
      <a href="appointment.php" class="nav-item"><span class="nav-icon">📅</span> Appointments</a>
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
    <div class="topbar"><h1>👨‍⚕️ Find Doctors</h1></div>
    <div class="page-content">

      <?php if ($disease): ?>
      <div class="alert alert-info" style="margin-bottom:20px;">
        🔬 Showing doctors for predicted disease: <strong><?= htmlspecialchars($disease) ?></strong>
        <?php if ($suggestedSpec): ?> — Recommended: <strong><?= $suggestedSpec ?></strong><?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Filters -->
      <div class="card" style="margin-bottom:24px;">
        <form method="GET" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap;">
          <?php if ($disease): ?><input type="hidden" name="disease" value="<?= htmlspecialchars($disease) ?>"><?php endif; ?>
          <div style="flex:1;min-width:200px;">
            <label class="form-label">Search by Name</label>
            <input type="text" name="search" class="form-control" placeholder="Doctor name..." value="<?= htmlspecialchars($search) ?>">
          </div>
          <div style="min-width:180px;">
            <label class="form-label">Specialization</label>
            <select name="spec" class="form-control">
              <option value="">All Specializations</option>
              <?php foreach ($specs as $s): ?>
              <option value="<?= htmlspecialchars($s['specialization']) ?>" <?= $spec===$s['specialization']?'selected':'' ?>><?= htmlspecialchars($s['specialization']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-primary">🔍 Search</button>
          <a href="doctor_suggest.php" class="btn btn-outline">Clear</a>
        </form>
      </div>

      <!-- Results -->
      <p style="color:#64748b;font-size:14px;margin-bottom:16px;">Found <?= count($doctors) ?> doctor(s)</p>

      <?php if (empty($doctors)): ?>
        <div class="card" style="text-align:center;padding:48px;">
          <div style="font-size:48px;margin-bottom:16px;">🔍</div>
          <h3>No doctors found</h3>
          <p style="color:#64748b;margin-top:8px;"><a href="doctor_suggest.php" style="color:#0ea5e9;">Clear filters</a> to see all doctors.</p>
        </div>
      <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:16px;">
          <?php foreach ($doctors as $doc): ?>
          <div class="doctor-card">
            <div class="doctor-avatar"><?= strtoupper(substr($doc["name"],0,1)) ?></div>
            <div class="doctor-info">
              <h3><?= htmlspecialchars($doc['name']) ?></h3>
              <span class="badge badge-info"><?= htmlspecialchars($doc['specialization']) ?></span>
              <div class="doctor-meta">
                <span>🎓 <?= htmlspecialchars($doc['qualification']) ?></span>
                <span>⏱️ <?= $doc['experience'] ?> yrs experience</span>
                <span>🏥 <?= htmlspecialchars($doc['hospital']) ?></span>
                <span>📅 <?= htmlspecialchars($doc['available_days']) ?></span>
                <span>💰 ₹<?= number_format($doc['fee'],0) ?> / visit</span>
              </div>
            </div>
            <div class="doctor-actions">
              <a href="appointment.php?doctor_id=<?= $doc['id'] ?>" class="btn btn-primary btn-sm">📅 Book</a>
              <span style="font-size:12px;color:#94a3b8;">📞 <?= htmlspecialchars($doc['phone'] ?? 'N/A') ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
