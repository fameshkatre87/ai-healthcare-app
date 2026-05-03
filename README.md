<div align="center">

<img src="https://img.shields.io/badge/PHP-8.x-777BB4?style=for-the-badge&logo=php&logoColor=white"/>
<img src="https://img.shields.io/badge/Python-3.x-3776AB?style=for-the-badge&logo=python&logoColor=white"/>
<img src="https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white"/>
<img src="https://img.shields.io/badge/Flask-3.0-000000?style=for-the-badge&logo=flask&logoColor=white"/>
<img src="https://img.shields.io/badge/Scikit--Learn-1.3-F7931E?style=for-the-badge&logo=scikit-learn&logoColor=white"/>

# 🏥 AI Driven Healthcare Application

**An intelligent healthcare platform that predicts diseases using Machine Learning, recommends doctors, and manages patient appointments — built with PHP, MySQL, and a Python ML API.**

[Features](#-features) • [Demo](#-demo-credentials) • [Tech Stack](#-tech-stack) • [Setup](#-setup-guide) • [API Docs](#-api-documentation) • [Screenshots](#-project-structure)

</div>

---

## 🎯 Project Overview

This full-stack healthcare application leverages **Artificial Intelligence** to assist patients in identifying potential diseases based on symptoms. The system integrates a **Python Flask ML API** (Random Forest Classifier trained on 41 diseases & 132 symptoms) with a **PHP + MySQL** web application — enabling real-time AI predictions accessible through a clean, modern UI.

> 📌 **MCA Final Year Project** — Rishika Narayan Reddy & Sanika Lokhande

---

## ✨ Features

| Feature | Description |
|---|---|
| 🔬 **AI Disease Prediction** | Enter symptoms → Get instant ML-powered disease diagnosis with confidence % |
| 🏆 **Top 3 Predictions** | Shows top 3 probable diseases with progress bar visualization |
| 💊 **Medical Recommendations** | Auto-suggests precautions, medications & diet based on prediction |
| 👨‍⚕️ **Smart Doctor Matching** | Recommends specialist doctors based on predicted disease |
| 📅 **Appointment Booking** | Full appointment CRUD — book, confirm, cancel, complete |
| 📊 **AI Report Analysis** | Paste lab report values → AI extracts findings & suggestions |
| 👥 **Role-Based Access** | Separate portals for Patient, Doctor, and Admin |
| 🔐 **Secure Auth** | Password hashing with PHP `password_hash()` + session management |
| 🩺 **Health Profile** | BMI calculator, blood group, allergies, chronic disease tracking |
| 🖥️ **Admin Panel** | Manage users, doctors, appointments, view AI prediction stats |

---

## 🛠️ Tech Stack

### Frontend
- **HTML5, CSS3, JavaScript** — Responsive UI
- **Sora + DM Sans** — Google Fonts typography
- **Custom Design System** — Dark sidebar, glass cards, smooth animations

### Backend
- **PHP 8.x** — Server-side logic, session management, CRUD operations
- **MySQL** — Relational database (5 tables, normalized schema)
- **cURL** — PHP → Python API bridge

### Machine Learning API
- **Python 3.x + Flask** — REST API server
- **Scikit-learn** — Random Forest Classifier
- **Pandas + NumPy** — Data processing
- **Joblib** — Model serialization

### Tools & Environment
- **XAMPP** — Local development (Apache + MySQL)
- **phpMyAdmin** — Database management
- **Git + GitHub** — Version control

---

## 🤖 ML Model Details

```
Algorithm     : Random Forest Classifier (100 estimators)
Training Data : 41 diseases × 60 samples = 2,460 records
Features      : 132 symptoms (binary encoded)
Accuracy      : 100% on test set
Output        : Disease name + Confidence % + Top 3 predictions
```

**Diseases Covered (41):** Malaria, Dengue, Diabetes, Typhoid, Hypertension, Heart Attack, Common Cold, Pneumonia, Tuberculosis, Jaundice, Hepatitis A/B/C/D/E, Fungal Infection, Acne, Arthritis, Migraine, Varicose Veins, and more.

---

## 👥 User Roles

```
┌─────────────┬──────────────────────────────────────────────────┐
│ Role        │ Capabilities                                     │
├─────────────┼──────────────────────────────────────────────────┤
│ 🧑 Patient  │ Symptom check, Book appointments, Upload reports │
│             │ View predictions history, Update health profile  │
├─────────────┼──────────────────────────────────────────────────┤
│ 👨‍⚕️ Doctor  │ View today's patients, Manage appointments       │
│             │ Add consultation notes, Update status            │
├─────────────┼──────────────────────────────────────────────────┤
│ 🔧 Admin    │ Manage all users & doctors, View all appointments│
│             │ Monitor AI predictions, Full CRUD access         │
└─────────────┴──────────────────────────────────────────────────┘
```

---

## 🔑 Demo Credentials

| Role | Email | Password |
|---|---|---|
| 🧑 Patient | ravi@gmail.com | patient123 |
| 👨‍⚕️ Doctor | priya@healthcare.com | doctor123 |
| 🔧 Admin | admin@healthcare.com | admin123 |

---

## ⚙️ Setup Guide

### Prerequisites
- XAMPP (PHP 8.x + MySQL)
- Python 3.8+
- Git

### 1️⃣ Clone the Repository

```bash
git clone https://github.com/fameshkatre87/ai-healthcare-app.git
cd ai-healthcare-app
```

### 2️⃣ Database Setup

```sql
-- 1. Open phpMyAdmin → http://localhost/phpmyadmin
-- 2. Create database
CREATE DATABASE healthcare_db;
-- 3. Import file: database/healthcare.sql
```

### 3️⃣ PHP App Setup

```bash
# Copy project to XAMPP
cp -r . C:/xampp/htdocs/healthcare/

# Edit DB credentials if needed
# File: config/db.php
```

### 4️⃣ Python ML API Setup

```bash
cd ml-api

# Install dependencies
pip install -r requirements.txt

# Train the ML model (run once)
python model/train_model.py

# Start Flask API server
python app.py
# → Running on http://localhost:5000
```

### 5️⃣ Launch Application

```
http://localhost/healthcare/
```

> ⚠️ Keep Python Flask server running while using the app!

---

## 🔗 API Documentation

**Base URL:** `http://localhost:5000`

### Endpoints

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/health` | API health check |
| `GET` | `/symptoms` | Returns all 132 symptoms |
| `POST` | `/predict` | Predict disease from symptoms |
| `POST` | `/analyze_report` | Analyze medical report text |

### Example — Disease Prediction

**Request:**
```json
POST /predict
{
  "symptoms": ["high_fever", "chills", "sweating", "headache", "muscle_pain"]
}
```

**Response:**
```json
{
  "predicted_disease": "Malaria",
  "confidence": 89.5,
  "top_predictions": [
    { "disease": "Malaria",  "confidence": 89.5 },
    { "disease": "Dengue",   "confidence": 7.2  },
    { "disease": "Typhoid",  "confidence": 3.3  }
  ],
  "precautions":  ["Use mosquito nets", "Apply repellent", ...],
  "medications":  ["Chloroquine", "Artemisinin therapy", ...],
  "diet":         ["Coconut water", "Light digestible food", ...]
}
```

---

## 📁 Project Structure

```
ai-healthcare-app/
│
├── 📂 config/
│   └── db.php                  # Database connection + session helpers
│
├── 📂 api/
│   └── ml_connect.php          # PHP → Python Flask bridge (cURL)
│
├── 📂 dashboard/
│   ├── patient.php             # Patient dashboard
│   ├── doctor.php              # Doctor dashboard
│   ├── admin.php               # Admin panel
│   ├── admin_users.php         # User management
│   ├── admin_doctors.php       # Doctor management
│   ├── admin_appointments.php  # Appointment management
│   ├── admin_predictions.php   # AI predictions log
│   └── doctor_profile.php      # Doctor profile editor
│
├── 📂 modules/
│   ├── symptom_checker.php     # ⭐ Core AI feature
│   ├── doctor_suggest.php      # Doctor recommendation
│   ├── appointment.php         # Booking system
│   ├── reports.php             # Report upload + AI analysis
│   └── profile.php             # Patient health profile
│
├── 📂 database/
│   └── healthcare.sql          # Complete DB schema + seed data
│
├── 📂 ml-api/                  # Python Flask ML API
│   ├── app.py                  # Flask server (4 endpoints)
│   ├── requirements.txt
│   └── model/
│       ├── train_model.py      # Model training script
│       ├── symptoms_list.json  # 132 symptoms
│       └── disease_info.json   # Disease metadata
│
├── 📂 assets/css/
│   └── style.css               # Design system (800+ lines)
│
├── index.php                   # Landing page
├── login.php                   # Authentication
├── register.php                # Patient registration
├── logout.php                  # Session destroy
└── README.md
```

---

## 🗄️ Database Schema

```
users              → id, name, email, password, role, phone, gender, age
doctors            → id, user_id, specialization, qualification, experience, fee
patient_records    → id, patient_id, blood_group, weight, height, allergies
appointments       → id, patient_id, doctor_id, date, time, status, notes
predictions        → id, patient_id, symptoms, predicted_disease, confidence
reports            → id, patient_id, report_type, file_path, ai_analysis
```

---

## 🔧 Troubleshooting

| Error | Solution |
|---|---|
| `mysqli connection refused` | Start MySQL in XAMPP Control Panel |
| `ML API connection failed` | Run `python app.py` in ml-api folder |
| `Model file not found` | Run `python model/train_model.py` first |
| `404 Not Found` | Check files are in `htdocs/healthcare/` |
| `Blank white page` | Start Apache in XAMPP Control Panel |

---

## 📄 License

This project is for **educational purposes** as part of an MCA academic project.

---

<div align="center">

**Built with ❤️ by Rishika Narayan Reddy & Sanika Lokhande**

*MCA — Sem II Project | 2024-25*

⭐ Star this repo if you found it helpful!

</div>