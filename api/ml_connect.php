<?php
// ============================================================
// PHP → Python ML API Bridge
// ============================================================
require_once __DIR__ . '/../config/db.php';

function callMLAPI($endpoint, $data = [], $method = 'GET') {
    $url = ML_API_URL . $endpoint;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    // BUG FIX: Default was 'POST' but GET calls were broken — function signature fixed
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['error' => 'ML API connection failed. Make sure Python server is running on port 5000.'];
    }
    // BUG FIX: Accept both 200 and 400 (400 returns structured error JSON we want to show)
    if ($httpCode >= 500) {
        return ['error' => 'ML API server error: HTTP ' . $httpCode];
    }

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Invalid JSON response from ML API'];
    }
    return $decoded;
}

// Predict disease
function predictDisease($symptoms) {
    return callMLAPI('/predict', ['symptoms' => $symptoms], 'POST');
}

// Get all symptoms
function getAllSymptoms() {
    return callMLAPI('/symptoms', [], 'GET');
}

// Analyze report
function analyzeReport($reportText, $reportType) {
    return callMLAPI('/analyze_report', [
        'report_text' => $reportText,
        'report_type' => $reportType
    ], 'POST');
}
?>
