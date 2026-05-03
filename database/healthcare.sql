-- ============================================
-- AI DRIVEN HEALTHCARE APPLICATION
-- Database Schema
-- ============================================

CREATE DATABASE IF NOT EXISTS healthcare_db;
USE healthcare_db;

-- Users Table (Patients + Doctors + Admin)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('patient','doctor','admin') DEFAULT 'patient',
    phone VARCHAR(15),
    gender ENUM('Male','Female','Other'),
    age INT,
    address TEXT,
    profile_pic VARCHAR(255) DEFAULT 'default.png',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Doctors Table (extends users)
CREATE TABLE doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    specialization VARCHAR(100),
    qualification VARCHAR(100),
    experience INT,
    available_days VARCHAR(100),
    fee DECIMAL(10,2),
    hospital VARCHAR(150),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Patient Medical Records
CREATE TABLE patient_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    blood_group VARCHAR(5),
    weight FLOAT,
    height FLOAT,
    allergies TEXT,
    chronic_diseases TEXT,
    current_medications TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Symptom Predictions
CREATE TABLE predictions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    symptoms TEXT NOT NULL,
    predicted_disease VARCHAR(100),
    confidence FLOAT,
    precautions TEXT,
    medications TEXT,
    diet TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Appointments
CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    reason TEXT,
    status ENUM('pending','confirmed','cancelled','completed') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id),
    FOREIGN KEY (doctor_id) REFERENCES users(id)
);

-- Medical Reports
CREATE TABLE reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    report_type VARCHAR(100),
    file_path VARCHAR(255),
    ai_analysis TEXT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- SAMPLE DATA
-- ============================================

-- Admin user (password: admin123)
INSERT INTO users (name, email, password, role, phone, gender, age) VALUES
('Admin User', 'admin@healthcare.com', '$2y$10$WQttL7M9xoyOvxHBHHWEpOEySMnflMaWZDX8wza6mzl0vyTQwOzGK', 'admin', '9999999999', 'Male', 30);

-- Doctor users (password: doctor123)
INSERT INTO users (name, email, password, role, phone, gender, age) VALUES
('Dr. Priya Sharma', 'priya@healthcare.com', '$2y$10$Mtx68z9LfRb/3I3BYLr7n.Ypdo2LKCFVZxIJy86uQGrTk92UXsw2q', 'doctor', '9876543210', 'Female', 38),
('Dr. Rahul Mehta', 'rahul@healthcare.com', '$2y$10$Mtx68z9LfRb/3I3BYLr7n.Ypdo2LKCFVZxIJy86uQGrTk92UXsw2q', 'doctor', '9876543211', 'Male', 45),
('Dr. Anita Patel', 'anita@healthcare.com', '$2y$10$Mtx68z9LfRb/3I3BYLr7n.Ypdo2LKCFVZxIJy86uQGrTk92UXsw2q', 'doctor', '9876543212', 'Female', 41),
('Dr. Vikram Singh', 'vikram@healthcare.com', '$2y$10$Mtx68z9LfRb/3I3BYLr7n.Ypdo2LKCFVZxIJy86uQGrTk92UXsw2q', 'doctor', '9876543213', 'Male', 50);

-- Doctor profiles
INSERT INTO doctors (user_id, specialization, qualification, experience, available_days, fee, hospital) VALUES
(2, 'Cardiologist', 'MD Cardiology', 12, 'Mon,Wed,Fri', 800.00, 'City Heart Hospital'),
(3, 'Neurologist', 'MD Neurology', 18, 'Tue,Thu,Sat', 1000.00, 'Brain & Spine Clinic'),
(4, 'Dermatologist', 'MD Dermatology', 10, 'Mon,Tue,Wed', 600.00, 'Skin Care Center'),
(5, 'Orthopedic', 'MS Orthopedics', 15, 'Wed,Thu,Fri', 900.00, 'Bone & Joint Hospital');

-- Patient user (password: patient123)
INSERT INTO users (name, email, password, role, phone, gender, age) VALUES
('Ravi Kumar', 'ravi@gmail.com', '$2y$10$.XdCAQS8m6aLTHufeIkmM.biQ5q42wU8snZVx3RM/1aGIsFeCH9kG', 'patient', '9012345678', 'Male', 28);

INSERT INTO patient_records (patient_id, blood_group, weight, height, allergies, chronic_diseases) VALUES
(6, 'O+', 70.5, 175.0, 'Penicillin', 'None');
