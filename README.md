# EduPulse — Academic Intelligence & Teaching Effectiveness

A PHP + MySQL university analytics classroom prototype for BAT 405. This project illustrates a **proposed**, not existing, analytics integration of Student/Faculty Portal data and Student Evaluation of Teaching Effectiveness (SETE).

## Features
- Five KPI cards: course pass rate, students at risk, teaching effectiveness, SETE response rate, grade completion
- Three charts: semester trends, departmental SETE scores, and at-risk distribution
- Academic year, semester and department filters
- Warnings, management suggestions and aggregate CSV export
- Program Chair and Faculty local logins
- MySQL sample data installer, responsive design

## Run on XAMPP (Windows)

1. Clone to `C:\xampp\htdocs\edupulse`.
2. Start Apache and MySQL in XAMPP.
3. Run `C:\xampp\php\php.exe setup.php` in the project folder.
4. Visit `http://localhost/edupulse/login.php`.

Local login (local only): `chair@edupulse.local`, password `ChangeMe123!`; faculty login `faculty@edupulse.local` with the same password.

Requirements: PHP 8.1+ with PDO MySQL, MySQL/MariaDB, Apache. Chart.js and fonts are fetched from CDNs.

Database settings use `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` environment variables or local XAMPP defaults in `config/database.php`.

## Proposed KPI targets

| KPI | Target |
| --- | --- |
| Course pass rate (passed/attempted course units) | ≥85% |
| At-risk academic records (grade >3 or attendance <75%) | ≤15% |
| SETE weighted mean | ≥4/5 |
| SETE response rate | ≥70% |
| Grade completion | ≥95% |

These are classroom assumptions, **not** official university thresholds. Data is fully synthetic. The system provides descriptive/diagnostic analytics, not a trained predictive model. SETE is only one of several measures of teaching effectiveness and should never solely determine personnel actions.

## Case study alignment

- Part I: Separate student/faculty portal and SETE source models, with proposed analytics integration.
- Part II: Five measurable KPIs with management thresholds.
- Part III: One-page Program Chair dashboard, three visualizations, filters, warnings.
- Part IV: Rule-based student support flags; proposed predictive modeling requires separate documented workflow and evaluation metrics.
- Part V: Functional/integration, usability, privacy and security testing recommended prior to real deployment.

**For educational example only. Not production-ready.** Change/remove local credentials before deployment, and conduct security review before using sensitive data.

## Predictive analytics extension

The **ML Risk Explorer** is available from the dashboard or at `predict.php`. It uses a logistic regression model trained on the external [UCI higher-education dropout dataset](https://archive.ics.uci.edu/dataset/697/predict+students+dropout+and+academic+success) (CC BY 4.0). Training uses first-semester academic features and an 80/20 stratified train/test split; evaluation reports ROC-AUC, recall, precision, and accuracy.

Run `python -m pip install -r ml/requirements.txt` and `python ml/train.py` to produce `model/dropout_logistic.json` and `model/metrics.json`, or use the [GitHub Actions training workflow](https://github.com/clinton1510/EduPulse/actions/workflows/train-model.yml). See `ml/README.md` for methodology and limitations.

This external model is a separate research example, **not** a model validated on EduPulse's synthetic PHP database or on Philippine university students. PHP runs inference directly from a trained JSON model; it does not train the model itself.
