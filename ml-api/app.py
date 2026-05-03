"""
AI Healthcare - Python Flask ML API
Endpoints:
  POST /predict  -> Disease prediction from symptoms
  GET  /symptoms -> Get all symptoms list
  GET  /health   -> API health check
"""

from flask import Flask, request, jsonify
from flask_cors import CORS
import joblib
import json
import numpy as np
import os

app = Flask(__name__)
CORS(app)  # Allow PHP to call this API

# ── Load model artifacts ──────────────────────────────────
BASE = os.path.dirname(__file__)

# BUG FIX: Wrap model loading in try/except — gives clear error instead of crash
try:
    model   = joblib.load(os.path.join(BASE, 'model/disease_model.pkl'))
    encoder = joblib.load(os.path.join(BASE, 'model/label_encoder.pkl'))
except FileNotFoundError:
    raise SystemExit(
        "\n❌ Model files not found!\n"
        "   Run this first: python model/train_model.py\n"
    )

with open(os.path.join(BASE, 'model/symptoms_list.json')) as f:
    SYMPTOMS_LIST = json.load(f)

with open(os.path.join(BASE, 'model/disease_info.json')) as f:
    DISEASE_INFO = json.load(f)

DEFAULT_INFO = {
    'precautions': ['Consult a doctor immediately', 'Rest properly',
                    'Stay hydrated', 'Take prescribed medications'],
    'medications': ['Consult doctor for proper medication'],
    'diet': ['Balanced diet', 'Stay hydrated', 'Avoid junk food']
}

# ── Routes ────────────────────────────────────────────────

@app.route('/health', methods=['GET'])
def health():
    return jsonify({'status': 'ok', 'message': 'Healthcare ML API is running'})


@app.route('/symptoms', methods=['GET'])
def get_symptoms():
    """Return all 132 symptoms (for front-end dropdowns)"""
    readable = [s.replace('_', ' ').title() for s in SYMPTOMS_LIST]
    return jsonify({
        'symptoms': SYMPTOMS_LIST,
        'readable': readable,
        'count': len(SYMPTOMS_LIST)
    })


@app.route('/predict', methods=['POST'])
def predict():
    """
    Predict disease from symptoms.
    Body (JSON): { "symptoms": ["fever", "headache", "cough"] }
    """
    import pandas as pd

    # Common symptom aliases users might type → canonical name
    ALIASES = {
        'fever':              'high_fever',
        'temperature':        'high_fever',
        'cold':               'continuous_sneezing',
        'runny_nose':         'runny_nose',
        'stomach_ache':       'stomach_pain',
        'stomach_upset':      'stomach_pain',
        'loose_motion':       'diarrhoea',
        'loose_motions':      'diarrhoea',
        'loose_stool':        'diarrhoea',
        'body_pain':          'muscle_pain',
        'body_ache':          'muscle_pain',
        'sore_throat':        'throat_irritation',
        'throat_pain':        'throat_irritation',
        'eye_redness':        'redness_of_eyes',
        'red_eyes':           'redness_of_eyes',
        'short_of_breath':    'breathlessness',
        'shortness_of_breath':'breathlessness',
        'difficulty_breathing':'breathlessness',
        'low_appetite':       'loss_of_appetite',
        'no_appetite':        'loss_of_appetite',
        'dark_yellow_urine':  'dark_urine',
        'yellow_skin':        'yellowish_skin',
        'yellow_eyes':        'yellowing_of_eyes',
        'fast_heartbeat':     'fast_heart_rate',
        'palpitation':        'palpitations',
        'tiredness':          'fatigue',
        'tired':              'fatigue',
        'weakness':           'fatigue',
        'low_energy':         'fatigue',
        'joint_ache':         'joint_pain',
        'back_ache':          'back_pain',
        'skin_itching':       'itching',
        'rash':               'skin_rash',
        'stomach_bloating':   'swelling_of_stomach',
        'bloating':           'passage_of_gases',
        'gas':                'passage_of_gases',
        'sugar_craving':      'excessive_hunger',
        'frequent_urination': 'polyuria',
        'thirst':             'dehydration',
        'weight_increase':    'weight_gain',
    }

    data = request.get_json(silent=True)
    if not data or 'symptoms' not in data:
        return jsonify({'error': 'Please provide symptoms list'}), 400

    raw_symptoms = [s.lower().strip().replace(' ', '_') for s in data['symptoms']]

    # Apply aliases
    user_symptoms = [ALIASES.get(s, s) for s in raw_symptoms]

    # BUG FIX: Check minimum count BEFORE validating individual symptoms
    # (prevents confusing "unknown symptom" error when user only typed 1 symptom)
    if len(user_symptoms) < 2:
        return jsonify({'error': 'Please provide at least 2 symptoms'}), 400

    # Validate — collect truly invalid ones
    invalid = [s for s in user_symptoms if s not in SYMPTOMS_LIST]
    if invalid:
        # Try partial match suggestion
        suggestions = {}
        for inv in invalid:
            matches = [x for x in SYMPTOMS_LIST if len(inv) >= 4 and inv[:4] in x][:3]
            suggestions[inv] = matches
        return jsonify({
            'error': f'Unknown symptoms: {invalid}',
            'suggestions': suggestions
        }), 400

    # Build feature DataFrame (fixes sklearn feature name warning)
    feature_dict = {s: [1 if s in user_symptoms else 0] for s in SYMPTOMS_LIST}
    features_df  = pd.DataFrame(feature_dict)

    # Predict
    pred_encoded  = model.predict(features_df)[0]
    probabilities = model.predict_proba(features_df)[0]
    confidence    = float(np.max(probabilities)) * 100
    disease       = encoder.inverse_transform([pred_encoded])[0]

    # Top 3 predictions
    top3_idx = np.argsort(probabilities)[::-1][:3]
    top3 = [
        {
            'disease':    encoder.inverse_transform([i])[0],
            'confidence': round(float(probabilities[i]) * 100, 2)
        }
        for i in top3_idx
    ]

    info = DISEASE_INFO.get(disease, DEFAULT_INFO)

    return jsonify({
        'predicted_disease': disease,
        'confidence':        round(confidence, 2),
        'top_predictions':   top3,
        'precautions':       info.get('precautions', DEFAULT_INFO['precautions']),
        'medications':       info.get('medications', DEFAULT_INFO['medications']),
        'diet':              info.get('diet',         DEFAULT_INFO['diet']),
        'symptoms_analyzed': user_symptoms
    })


@app.route('/analyze_report', methods=['POST'])
def analyze_report():
    """
    Simulated report analysis (text-based)
    Body (JSON): { "report_text": "...", "report_type": "blood" }
    """
    data = request.get_json(silent=True)
    if not data:
        return jsonify({'error': 'No data provided'}), 400

    report_text = data.get('report_text', '').lower()
    report_type = data.get('report_type', 'general')

    findings = []
    suggestions = []

    # Basic keyword analysis
    if 'hemoglobin' in report_text or 'hb' in report_text:
        findings.append('Hemoglobin levels detected in report')
        suggestions.append('Monitor iron levels and consider iron-rich diet')

    if 'glucose' in report_text or 'sugar' in report_text:
        findings.append('Blood glucose values found')
        suggestions.append('Monitor blood sugar levels regularly')

    if 'cholesterol' in report_text:
        findings.append('Cholesterol values present')
        suggestions.append('Consider low-fat diet and regular exercise')

    if 'creatinine' in report_text:
        findings.append('Kidney function markers detected')
        suggestions.append('Stay well hydrated and monitor kidney health')

    if not findings:
        findings = ['Report received and analyzed']
        suggestions = ['Consult your doctor to interpret the results']

    return jsonify({
        'report_type': report_type,
        'findings': findings,
        'suggestions': suggestions,
        'recommendation': 'Please consult a qualified doctor for detailed interpretation'
    })


if __name__ == '__main__':
    print("Starting Healthcare ML API on port 5000...")
    app.run(host='0.0.0.0', port=5000, debug=True)
