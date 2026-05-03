<?php
require_once '../config/db.php';
require_once '../api/ml_connect.php';
requireLogin();

$db         = getDB();
$uid        = $_SESSION['user_id'];
$prediction = null;
$error      = '';

// Handle prediction POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['symptoms'])) {
    $symptoms = json_decode($_POST['symptoms'], true);
    if (!$symptoms || count($symptoms) < 2) {
        $error = 'Please select at least 2 symptoms.';
    } else {
        $result = predictDisease($symptoms);
        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            $prediction = $result;
            // Save prediction to DB using prepared statement
            $stmt = $db->prepare(
                "INSERT INTO predictions (patient_id,symptoms,predicted_disease,confidence,precautions,medications,diet)
                 VALUES (?,?,?,?,?,?,?)"
            );
            $syms = implode(', ', $symptoms);
            $prec = implode(' | ', $result['precautions'] ?? []);
            $meds = implode(' | ', $result['medications'] ?? []);
            $diet = implode(' | ', $result['diet'] ?? []);
            $conf = round($result['confidence'], 2);
            $stmt->bind_param("issdsss",
                $uid,
                $syms,
                $result['predicted_disease'],
                $conf,
                $prec,
                $meds,
                $diet
            );
            $stmt->execute();
        }
    }
}

// Get symptoms list from ML API
$symptomsData = getAllSymptoms();
$allSymptoms  = $symptomsData['symptoms'] ?? [];
$readable     = $symptomsData['readable'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Symptom Checker — HealthAI</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
  .symptom-search { position: relative; }
  .symptom-dropdown { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 2px solid #0ea5e9; border-top: none; border-radius: 0 0 10px 10px; max-height: 220px; overflow-y: auto; z-index: 100; box-shadow: 0 8px 24px rgba(0,0,0,.1); }
  .symptom-option { padding: 10px 14px; font-size: 13px; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: background .15s; }
  .symptom-option:hover { background: #e0f2fe; }
  .symptom-option.selected { background: #dcfce7; }
  .top3-card { background: rgba(255,255,255,.1); border-radius: 10px; padding: 12px 16px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; }
  .progress-bar { height: 6px; border-radius: 99px; background: rgba(255,255,255,.2); margin-top: 4px; overflow: hidden; }
  .progress-fill { height: 100%; background: #fff; border-radius: 99px; transition: width .6s ease; }
</style>
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
      <a href="../dashboard/patient.php" class="nav-item"><span class="nav-icon">🏠</span> Dashboard</a>
      <a href="symptom_checker.php" class="nav-item active"><span class="nav-icon">🔬</span> Symptom Checker</a>
      <a href="doctor_suggest.php" class="nav-item"><span class="nav-icon">👨‍⚕️</span> Find Doctors</a>
      <a href="appointment.php" class="nav-item"><span class="nav-icon">📅</span> Appointments</a>
      <div class="nav-section">Health</div>
      <a href="reports.php" class="nav-item"><span class="nav-icon">📊</span> My Reports</a>
      <a href="profile.php" class="nav-item"><span class="nav-icon">👤</span> My Profile</a>
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
      <h1>🔬 AI Symptom Checker</h1>
      <a href="../dashboard/patient.php" class="btn btn-outline btn-sm">← Dashboard</a>
    </div>

    <div class="page-content">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:28px;align-items:start;flex-wrap:wrap;">
        <!-- Symptom Input -->
        <div>
          <div class="card">
            <h3 class="card-title">Select Your Symptoms</h3>
            <p style="color:#64748b;font-size:13px;margin-bottom:20px;">Search and add symptoms you are experiencing. Add at least 2 for accurate prediction.</p>

            <?php if ($error): ?>
              <div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Selected symptoms display -->
            <div class="form-group">
              <label class="form-label">Selected Symptoms</label>
              <div class="symptom-tags" id="selected-tags">
                <span style="color:#94a3b8;font-size:13px;padding:4px;">Click below to add symptoms...</span>
              </div>
            </div>

            <!-- Search box -->
            <div class="form-group symptom-search">
              <label class="form-label">Search Symptoms</label>
              <input type="text" id="symptomSearch" class="form-control" placeholder="Type symptom name..." autocomplete="off">
              <div class="symptom-dropdown" id="symptomDropdown" style="display:none;"></div>
            </div>

            <!-- Common symptoms quick-add -->
            <div class="form-group">
              <label class="form-label">Common Symptoms</label>
              <div style="display:flex;flex-wrap:wrap;gap:6px;">
                <?php
                $common = ['fever','headache','cough','fatigue','nausea','vomiting','chest_pain','dizziness','breathlessness','joint_pain','skin_rash','abdominal_pain'];
                foreach ($common as $s):
                  $label = ucwords(str_replace('_', ' ', $s));
                ?>
                <button type="button" class="btn btn-outline btn-sm quick-add" data-sym="<?= $s ?>" data-label="<?= $label ?>"><?= $label ?></button>
                <?php endforeach; ?>
              </div>
            </div>

            <form method="POST" id="predForm">
              <input type="hidden" name="symptoms" id="symptomsInput">
              <button type="submit" class="btn btn-primary btn-block btn-lg" onclick="return submitForm()">
                🤖 Predict Disease
              </button>
            </form>
          </div>
        </div>

        <!-- Prediction Result -->
        <div>
          <?php if ($prediction): ?>
          <div class="prediction-result">
            <p style="font-size:13px;opacity:.75;margin-bottom:4px;">AI Predicted Disease</p>
            <h2><?= htmlspecialchars($prediction['predicted_disease']) ?></h2>
            <p class="confidence">Confidence: <?= round($prediction['confidence']) ?>%</p>

            <!-- Top 3 predictions -->
            <div style="margin-top:20px;">
              <p style="font-size:12px;opacity:.7;margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px;">Top Predictions</p>
              <?php foreach ($prediction['top_predictions'] as $p): ?>
              <div class="top3-card">
                <div>
                  <div style="font-weight:600;font-size:14px;"><?= htmlspecialchars($p['disease']) ?></div>
                  <div class="progress-bar" style="width:150px;">
                    <div class="progress-fill" style="width:<?= round($p['confidence']) ?>%"></div>
                  </div>
                </div>
                <div style="font-weight:700;font-size:18px;"><?= round($p['confidence']) ?>%</div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Info cards -->
          <div class="card" style="margin-top:20px;">
            <h4 style="margin-bottom:12px;font-size:15px;font-weight:700;">⚠️ Precautions</h4>
            <?php foreach ($prediction['precautions'] as $p): ?>
              <div style="display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid #f1f5f9;font-size:14px;">
                <span style="color:#f59e0b;font-size:16px;">•</span> <?= htmlspecialchars($p) ?>
              </div>
            <?php endforeach; ?>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px;">
            <div class="card">
              <h4 style="margin-bottom:12px;font-size:15px;font-weight:700;">💊 Medications</h4>
              <?php foreach ($prediction['medications'] as $m): ?>
                <div style="font-size:13px;padding:5px 0;border-bottom:1px solid #f1f5f9;">• <?= htmlspecialchars($m) ?></div>
              <?php endforeach; ?>
              <p style="font-size:11px;color:#94a3b8;margin-top:8px;">⚠️ Always consult a doctor before taking medication.</p>
            </div>
            <div class="card">
              <h4 style="margin-bottom:12px;font-size:15px;font-weight:700;">🥗 Diet</h4>
              <?php foreach ($prediction['diet'] as $d): ?>
                <div style="font-size:13px;padding:5px 0;border-bottom:1px solid #f1f5f9;">• <?= htmlspecialchars($d) ?></div>
              <?php endforeach; ?>
            </div>
          </div>

          <div style="margin-top:16px;display:flex;gap:12px;flex-wrap:wrap;">
            <a href="doctor_suggest.php?disease=<?= urlencode($prediction['predicted_disease']) ?>" class="btn btn-success">👨‍⚕️ Find a Doctor</a>
            <a href="appointment.php" class="btn btn-outline">📅 Book Appointment</a>
          </div>

          <?php else: ?>
          <!-- Placeholder -->
          <div class="card" style="text-align:center;padding:48px 24px;">
            <div style="font-size:64px;margin-bottom:16px;">🤖</div>
            <h3 style="font-size:20px;font-weight:700;margin-bottom:8px;">AI Prediction</h3>
            <p style="color:#64748b;font-size:14px;line-height:1.7;">Select your symptoms from the left panel and click "Predict Disease" to get an AI-powered diagnosis.</p>
            <div class="alert alert-info" style="margin-top:20px;text-align:left;">
              <strong>How it works:</strong><br>
              Our AI model is trained on 41 diseases and 132 symptoms using Random Forest algorithm.
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const allSymptoms = <?= json_encode(array_combine($allSymptoms, $readable)) ?>;
let selected = [];

const searchInput  = document.getElementById('symptomSearch');
const dropdown     = document.getElementById('symptomDropdown');
const tagsDiv      = document.getElementById('selected-tags');
const symptomsInput = document.getElementById('symptomsInput');

searchInput.addEventListener('input', function() {
  const q = this.value.toLowerCase();
  if (!q) { dropdown.style.display = 'none'; return; }
  const matches = Object.entries(allSymptoms).filter(([k,v]) => v.toLowerCase().includes(q)).slice(0,12);
  if (!matches.length) { dropdown.style.display = 'none'; return; }
  dropdown.innerHTML = matches.map(([k,v]) =>
    `<div class="symptom-option ${selected.includes(k)?'selected':''}" onclick="toggleSymptom('${k}','${v}')">${v} ${selected.includes(k)?'✓':''}</div>`
  ).join('');
  dropdown.style.display = 'block';
});

document.addEventListener('click', e => {
  if (!e.target.closest('.symptom-search')) dropdown.style.display = 'none';
});

function toggleSymptom(key, label) {
  if (selected.includes(key)) {
    selected = selected.filter(s => s !== key);
  } else {
    selected.push(key);
  }
  renderTags();
  searchInput.dispatchEvent(new Event('input'));
}

document.querySelectorAll('.quick-add').forEach(btn => {
  btn.addEventListener('click', () => {
    const sym = btn.dataset.sym;
    const lbl = btn.dataset.label;
    if (!selected.includes(sym)) selected.push(sym);
    renderTags();
  });
});

function renderTags() {
  if (!selected.length) {
    tagsDiv.innerHTML = '<span style="color:#94a3b8;font-size:13px;padding:4px;">Click below to add symptoms...</span>';
    return;
  }
  tagsDiv.innerHTML = selected.map(s => {
    const label = allSymptoms[s] || s;
    return `<span class="symptom-tag">${label} <button onclick="removeSymptom('${s}')" title="Remove">×</button></span>`;
  }).join('');
}

function removeSymptom(key) {
  selected = selected.filter(s => s !== key);
  renderTags();
}

function submitForm() {
  if (selected.length < 2) {
    alert('Please select at least 2 symptoms.');
    return false;
  }
  symptomsInput.value = JSON.stringify(selected);
  return true;
}
</script>
</body>
</html>
