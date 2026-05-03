<?php
// Auto-redirect logged-in users to their dashboard
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'patient';
    if ($role === 'admin')       header('Location: dashboard/admin.php');
    elseif ($role === 'doctor')  header('Location: dashboard/doctor.php');
    else                         header('Location: dashboard/patient.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI Healthcare - Home</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
  body { background: #0f172a; color: #fff; font-family: 'Plus Jakarta Sans', sans-serif; }
  nav { display: flex; align-items: center; justify-content: space-between; padding: 20px 60px; border-bottom: 1px solid rgba(255,255,255,.08); position: sticky; top: 0; background: rgba(15,23,42,.95); backdrop-filter: blur(12px); z-index: 100; }
  .nav-logo { font-size: 22px; font-weight: 800; }
  .nav-logo span { color: #0ea5e9; }
  .nav-links { display: flex; gap: 32px; align-items: center; }
  .nav-links a { color: rgba(255,255,255,.65); font-size: 14px; font-weight: 500; transition: color .2s; }
  .nav-links a:hover { color: #fff; }
  .hero { padding: 120px 60px 80px; text-align: center; max-width: 900px; margin: 0 auto; }
  .hero-badge { display: inline-flex; align-items: center; gap: 8px; background: rgba(14,165,233,.15); border: 1px solid rgba(14,165,233,.3); padding: 6px 16px; border-radius: 99px; font-size: 13px; color: #0ea5e9; font-weight: 600; margin-bottom: 32px; }
  .hero h1 { font-size: 64px; font-weight: 800; line-height: 1.1; margin-bottom: 24px; }
  .hero h1 em { font-family: 'Instrument Serif', serif; font-style: italic; color: #0ea5e9; }
  .hero p { font-size: 18px; color: rgba(255,255,255,.6); max-width: 560px; margin: 0 auto 40px; line-height: 1.7; }
  .hero-cta { display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; }
  .features { padding: 80px 60px; display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px; max-width: 1200px; margin: 0 auto; }
  .feat-card { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); border-radius: 16px; padding: 28px; transition: all .3s; }
  .feat-card:hover { background: rgba(255,255,255,.07); transform: translateY(-4px); }
  .feat-icon { font-size: 32px; margin-bottom: 16px; }
  .feat-card h3 { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
  .feat-card p { color: rgba(255,255,255,.55); font-size: 14px; line-height: 1.65; }
  footer { text-align: center; padding: 40px; color: rgba(255,255,255,.3); font-size: 13px; border-top: 1px solid rgba(255,255,255,.08); }
</style>
</head>
<body>
<nav>
  <div class="nav-logo">❤️ Health<span>AI</span></div>
  <div class="nav-links">
    <a href="login.php">Login</a>
    <a href="register.php">Register</a>
    <a href="login.php" class="btn btn-primary btn-sm">Get Started →</a>
  </div>
</nav>

<div class="hero">
  <div class="hero-badge">🤖 Powered by Machine Learning</div>
  <h1>Your Smart<br><em>Healthcare</em><br>Companion</h1>
  <p>AI-powered disease prediction, doctor recommendations, and appointment booking — all in one platform for better healthcare outcomes.</p>
  <div class="hero-cta">
    <a href="register.php" class="btn btn-primary btn-lg">Start Free →</a>
    <a href="login.php" class="btn btn-outline btn-lg" style="color:#fff;border-color:rgba(255,255,255,.3);">Login</a>
  </div>
</div>

<div class="features">
  <div class="feat-card">
    <div class="feat-icon">🔬</div>
    <h3>AI Disease Prediction</h3>
    <p>Enter your symptoms and get instant AI-powered disease predictions with confidence scores and recommendations.</p>
  </div>
  <div class="feat-card">
    <div class="feat-icon">👨‍⚕️</div>
    <h3>Doctor Recommendations</h3>
    <p>Based on predicted illness, get matched with the right specialist doctors available near you.</p>
  </div>
  <div class="feat-card">
    <div class="feat-icon">📅</div>
    <h3>Online Appointment Booking</h3>
    <p>Book appointments with verified doctors instantly from the comfort of your home.</p>
  </div>
  <div class="feat-card">
    <div class="feat-icon">📊</div>
    <h3>Medical Report Analysis</h3>
    <p>Upload your lab reports and get AI-powered analysis with suggestions and insights.</p>
  </div>
  <div class="feat-card">
    <div class="feat-icon">🗂️</div>
    <h3>Patient Records</h3>
    <p>Securely store and access your complete medical history, medications, and records anytime.</p>
  </div>
  <div class="feat-card">
    <div class="feat-icon">🔐</div>
    <h3>Secure & Private</h3>
    <p>Your health data is encrypted and protected. We follow strict data privacy standards.</p>
  </div>
</div>

<footer>
  <p>© 2024 HealthAI — AI Driven Healthcare Application | MCA Project by Rishika Narayan Reddy & Sanika Lokhande</p>
</footer>
</body>
</html>
