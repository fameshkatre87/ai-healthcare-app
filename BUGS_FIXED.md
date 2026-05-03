# 🐛 Bug Fix Report — HealthAI Project
**Fixed by:** Code Review & Debug Pass  
**Total Bugs Found & Fixed:** 12

---

## 🔴 Critical — SQL Injection Vulnerabilities

### BUG 1 — `profile.php`: Raw queries with real_escape_string
**Problem:** `real_escape_string()` is not a safe substitute for prepared statements.  
**Risk:** SQL Injection if input contains special characters/encodings.  
**Fix:** Replaced ALL 4 raw queries with `prepare()` + `bind_param()`.

### BUG 2 — `reports.php`: Raw INSERT query
**Problem:** Report type and text were inserted using `real_escape_string` + raw query.  
**Fix:** Full prepared statement for INSERT and SELECT.

### BUG 3 — `appointment.php`: Raw SELECT for my appointments
**Problem:** `$uid` interpolated directly in query string.  
**Fix:** Prepared statement with `bind_param("i", $uid)`.

### BUG 4 — `admin.php`: Status update SQL injection
**Problem:** `$status` from POST input was escaped but still inserted raw.  
**Risk:** Attacker could set arbitrary status values.  
**Fix:** Whitelist validation (`pending/confirmed/cancelled/completed`) + prepared statement.

### BUG 5 — `doctor.php`: Status + notes SQL injection
**Problem:** Same pattern as admin — `$status` and `$notes` from POST, raw query.  
**Fix:** Whitelist for status + prepared statement binding both fields.

### BUG 6 — `doctor_suggest.php`: Search/spec injection via GET params
**Problem:** `$search` and `$spec` from GET were inserted using `addslashes()` in dynamic SQL.  
**Risk:** `addslashes()` does NOT properly escape SQL — classic injection vector.  
**Fix:** Full parameterized query builder with dynamic `bind_param`.

### BUG 7 — `register.php`: Raw `insert_id` in query
**Problem:** `$db->insert_id` used directly in `$db->query(...)` string.  
**Fix:** Prepared statement with int-cast `insert_id`.

---

## 🟠 Logic Errors

### BUG 8 — `ml_connect.php`: Wrong default HTTP method
**Problem:** `callMLAPI($endpoint, $data=[], $method='POST')` — default was POST.  
`getAllSymptoms()` called it without passing 'GET', meaning GET requests silently became POST requests.  
**Fix:** Changed default to `'GET'`; all callers now explicitly pass their method.

### BUG 9 — `ml_connect.php`: HTTP 400 errors were blocked
**Problem:** Code returned an error for any non-200 HTTP response.  
Flask returns 400 with structured JSON (unknown symptoms + suggestions) — that data was being thrown away.  
**Fix:** Only block HTTP 500+; let 400 responses return their JSON to the caller.

### BUG 10 — `app.py`: Wrong validation order
**Problem:** Symptom validity was checked BEFORE checking if count < 2.  
If a user entered only 1 valid symptom, they'd get a confusing "unknown symptom" error instead of "please add more symptoms".  
**Fix:** Count check runs first, then individual validation.

### BUG 11 — `app.py`: Crash on missing model files
**Problem:** `joblib.load()` with no error handling — Python throws a raw `FileNotFoundError` traceback with no explanation.  
**Fix:** Wrapped in `try/except FileNotFoundError` with a clear instruction message.

---

## 🟡 Minor Bugs

### BUG 12 — `doctor.php`: Wrong avatar letter
**Problem:** `substr($doctor['name'], 3, 1)` — index 3 skips past "Dr." but lands on a space or wrong letter.  
**Fix:** `substr($doctor['name'], 0, 1)` — always takes the first character correctly.

---

## ✅ Files Changed Summary

| File | Changes |
|------|---------|
| `php-app/config/db.php` | Hide DB errors from client, log server-side |
| `php-app/api/ml_connect.php` | Fixed default method, fixed HTTP error handling |
| `php-app/register.php` | Prepared statement for patient_records insert |
| `php-app/dashboard/patient.php` | Added `(int)` cast to `$uid` |
| `php-app/dashboard/admin.php` | Prepared statement + whitelist for status update |
| `php-app/dashboard/doctor.php` | Prepared statements, avatar fix, status whitelist |
| `php-app/modules/profile.php` | All queries converted to prepared statements |
| `php-app/modules/reports.php` | Prepared statements + missing HTML closing tags fixed |
| `php-app/modules/appointment.php` | Prepared statements + unclosed `<optgroup>` fix |
| `php-app/modules/doctor_suggest.php` | Replaced `addslashes()` with parameterized query |
| `ml-api/app.py` | Fixed validation order, added model load error handling |

---

## 🚀 How to Run (Quick Reference)

1. Import `database/healthcare.sql` in phpMyAdmin  
2. Copy `php-app/` to `htdocs/healthcare/`  
3. Run: `cd ml-api && pip install -r requirements.txt`  
4. Run once: `python model/train_model.py`  
5. Start API: `python app.py`  
6. Open: `http://localhost/healthcare/`  

**Demo password for all accounts:** `Test@1234`  
(Run the UPDATE query in README.md → phpMyAdmin SQL tab)
