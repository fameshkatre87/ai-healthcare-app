# 🏥 AI Driven Healthcare Application

---

## 🛠️ Tech Stack
| Layer | Technology |
|---|---|
| Frontend | HTML, CSS, JavaScript |
| Backend | PHP 8.x |
| Database | MySQL (XAMPP) |
| ML API | Python 3.x + Flask |
| ML Model | Scikit-learn (Random Forest) |

---

## 📁 Project Structure
```
healthcare-app/
├── database/
│   └── healthcare.sql          ← Import this first
│
├── ml-api/                     ← Python Flask API
│   ├── app.py                  ← Main Flask server
│   ├── requirements.txt
│   └── model/
│       ├── train_model.py      ← Run this once
│       ├── disease_model.pkl   ← Auto-generated
│       ├── label_encoder.pkl   ← Auto-generated
│       ├── symptoms_list.json  ← Auto-generated
│       └── disease_info.json   ← Auto-generated
│
└── php-app/                    ← Put inside htdocs/
    ├── index.php               ← Home page
    ├── login.php
    ├── register.php
    ├── logout.php
    ├── config/
    │   └── db.php              ← Edit DB credentials here
    ├── api/
    │   └── ml_connect.php      ← PHP → Python bridge
    ├── assets/
    │   └── css/style.css
    ├── dashboard/
    │   ├── patient.php
    │   ├── doctor.php
    │   └── admin.php
    └── modules/
        ├── symptom_checker.php ← Core AI feature
        ├── doctor_suggest.php
        ├── appointment.php
        ├── reports.php
        └── profile.php
```

---

## ⚙️ Setup Instructions

### Step 1 — Install XAMPP
- Download from https://www.apachefriends.org/
- Start **Apache** and **MySQL** in XAMPP Control Panel

### Step 2 — Database Setup
1. Open **phpMyAdmin** → http://localhost/phpmyadmin
2. Create new database: `healthcare_db`
3. Click **Import** → Select `database/healthcare.sql`
4. Click **Go**

### Step 3 — PHP App Setup
1. Copy `php-app/` folder to `C:\xampp\htdocs\healthcare\`
2. Edit `config/db.php` if needed (default: root / no password)
3. Open browser: http://localhost/healthcare/

### Step 4 — Python ML API Setup
Open **Command Prompt** or **Terminal**:

```bash
# Go to ml-api folder
cd path/to/healthcare-app/ml-api

# Install dependencies
pip install -r requirements.txt

# Train the model (run once)
python model/train_model.py

# Start Flask server (keep this running!)
python app.py
```

The ML API will start on: **http://localhost:5000**

> ⚠️ **Important:** Keep the Python server running while using the app!

---

## 🔑 Demo Login Credentials

| Role | Email | Password |
|---|---|---|
| Patient | ravi@gmail.com | patient123 |
| Doctor | priya@healthcare.com | doctor123 |
| Admin | admin@healthcare.com | admin123 |

> **Note:** The demo passwords above use `password_verify()`. The SQL file stores hashed passwords using `password()` function. For testing, register a new account or update passwords in phpMyAdmin using PHP's `password_hash()`.

### Fix Demo Passwords (run in phpMyAdmin SQL tab):
```sql
-- Run this after importing the SQL file
UPDATE users SET password = '$2y$10$TKh8H1.PfY0boCbskqoTce5RCF9b3RJFqW.Db3hHDhvTJGW.6Hpqq' WHERE email IN ('ravi@gmail.com', 'priya@healthcare.com', 'rahul@healthcare.com', 'anita@healthcare.com', 'vikram@healthcare.com', 'admin@healthcare.com');
-- This sets password to: Test@1234
```
Then use password: **Test@1234** for all demo accounts.

---

## 🤖 AI Features

### Disease Prediction
- **Model:** Random Forest Classifier
- **Diseases:** 41 diseases
- **Symptoms:** 132 symptoms
- **Accuracy:** ~100% on training data
- **Output:** Disease name, confidence %, top 3 predictions, precautions, medications, diet

### Report Analysis
- Keyword-based AI analysis of medical reports
- Detects: Blood values, Glucose, Cholesterol, Kidney markers
- Provides findings and suggestions

---

## 🔗 API Endpoints (Python Flask)

| Method | Endpoint | Description |
|---|---|---|
| GET | /health | Check if API is running |
| GET | /symptoms | Get all 132 symptoms |
| POST | /predict | Predict disease from symptoms |
| POST | /analyze_report | Analyze medical report text |

### Example /predict Request:
```json
POST http://localhost:5000/predict
{
  "symptoms": ["high_fever", "headache", "chills", "sweating", "muscle_pain"]
}
```

### Example /predict Response:
```json
{
  "predicted_disease": "Malaria",
  "confidence": 95.5,
  "top_predictions": [...],
  "precautions": ["Use mosquito nets", ...],
  "medications": ["Chloroquine", ...],
  "diet": ["Coconut water", ...]
}
```

---

## 📌 Modules Summary

| Module | File | Description |
|---|---|---|
| Home | index.php | Landing page |
| Login | login.php | User authentication |
| Register | register.php | New patient registration |
| Patient Dashboard | dashboard/patient.php | Patient home |
| Doctor Dashboard | dashboard/doctor.php | Doctor home |
| Admin Dashboard | dashboard/admin.php | Admin panel |
| Symptom Checker | modules/symptom_checker.php | AI disease prediction |
| Find Doctors | modules/doctor_suggest.php | Doctor recommendations |
| Appointments | modules/appointment.php | Book/manage appointments |
| Reports | modules/reports.php | Upload & AI analysis |
| Profile | modules/profile.php | Patient profile & health info |
| Logout | logout.php | Session destroy |

---

## ⚠️ Troubleshooting

**"ML API connection failed"**
→ Make sure Python Flask server is running: `python app.py`

**Database connection error**
→ Check XAMPP MySQL is running. Edit `config/db.php` credentials.

**Blank page in PHP**
→ Check XAMPP Apache is running. Files should be in `htdocs/` folder.

**Model file not found**
→ Run `python model/train_model.py` inside `ml-api/` folder first.
