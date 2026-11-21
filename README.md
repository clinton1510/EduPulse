# EduPulse — CICS Academic Intelligence & Teaching Effectiveness

EduPulse is an existing PHP/MySQL BAT 405 case-study application, extended into a public, read-only College of Informatics and Computing Sciences workspace. It is an academic project in the Batangas State University context, **not an officially deployed or endorsed university system**.

## Run locally with XAMPP

Requirements: PHP 8.1+, PDO MySQL, MySQL/MariaDB, Apache; PHP DOM/SimpleXML, fileinfo, iconv, and zlib for workbook imports and exports. No application framework, Composer package, Python runtime, or Node server is required. Charts, icons, fonts, and the guided tour work offline.

1. Put this repository in `C:\xampp\htdocs\EduPulse`.
2. Start Apache and MySQL in XAMPP.
3. In PowerShell run:

```powershell
cd C:\xampp\htdocs\EduPulse
& C:\xampp\php\php.exe setup.php
```

4. Open `http://localhost/EduPulse/`.

Visitor flow: **Landing page → Enter Program Chair Dashboard → Guided tour → CICS workspace**. No login is required. Old `login.php` bookmarks redirect to the landing page. Existing users remain in the database but are not used to authorize administration.

Database configuration remains in `config/database.php`, using `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS`, with local XAMPP defaults. Keep credentials outside source control when configuring a server.

## Existing installations and migration

Back up the database using your normal MySQL backup process, then run:

```powershell
& C:\xampp\php\php.exe migrate.php
```

`setup.php` also runs the same migration. Both commands are CLI-only, repeatable, and do not delete legacy rows. Migration execution has already been completed on the current local database.

`sql/cics.sql` adds relational tables: `programs`, `academic_periods`, `subjects`, `curriculum_subjects`, `students`, `enrollments`, `subject_grades`, `dataset_imports`, and `schema_migrations`. Existing `academic_records` and `sete_summary` gain nullable foreign-key `program_id` and a default-off `public_synthetic` flag.

Exact legacy mappings are **Computer Science → BSCS** and **Information Technology → BSIT**. Business Administration is preserved and left unmapped. Original academic and SETE data remain archived outside the public dataset, including records with compatible mappings; migration does not assume existing data is safe for publication. Faculty and user records are preserved. No faculty names or individual student grades are shown publicly.

The migration inserts a separate, explicitly synthetic CICS cohort with 1,296 academic records, 2,592 subject grades, 216 SETE criterion summaries, 16 illustrative shared subjects, four year levels, and six academic periods. BSIT, BSCS, and BSIS are configurable initial categories, **not verified campus offerings**. Subject codes beginning `SYN-` identify illustrative courses and must not be cited as official curricula. Students represent independent period-specific cohorts, not longitudinal student histories. The second tracked migration reconciles synthetic period averages against completed subject grades. Failed data seeding rolls back; MariaDB DDL itself is not transactional, so repeatable schema checks handle interruption.

## Routes and interface

- `index.php`: white/deep-red landing page, actual query-backed dashboard preview, eight feature cards, purpose, workflow, and academic context.
- `dashboard.php`: original five KPI formulas and query-backed SVG charts in the public workspace.
- `dashboard.php?module=programs`: program comparison, population, year-level distribution, assigned subjects, trends, and warnings.
- `?module=subjects`: searchable/sortable subject table, selectable subject detail, pass/fail/pending counts, grade distribution.
- `?module=students`: aggregate student outcomes and year-level populations, without student identities.
- `?module=teaching`: weighted SETE comparison, criteria, semester scores, participation, and support suggestions.
- `?module=trends`: semester pass rates, annual comparison, and response trends.
- `?module=warnings`: period-average academic flags, historical risk percentages, and separate subject-level flags.
- `?module=insights`: signals with supporting program aggregates and suggested next steps.
- `?module=reports`: six report types in CSV, native Excel XLSX, downloadable text-based PDF, and print view.
- `?module=datasets`: protected dataset administration; public access displays configuration/access guidance only.
- `?module=guide`: formulas, scope explanations, and tour restart.
- `?module=settings`: browser-local table density, reduced effects, and tour reset.
- `predict.php`: preserved trained external logistic regression model with input validation, risk gauge, holdout metrics, provenance, and limitations.

Every sidebar route preserves filter scope where applicable. Desktop navigation collapses; mobile uses a drawer with keyboard handling. The automatic ten-step accessible modal tour highlights actual overview elements, supports previous/next/skip/close/finish, and remembers completion in localStorage. Restarting from another module navigates to the overview while retaining scope.

## Consistent filters and KPI definitions

Filters: academic year, semester, CICS program, year level, and subject. Subject choices respond to program, semester, and year-level selections. Apply and Reset controls are explicit. Server-side validation normalizes invalid filter values; all query values use prepared statements. `lib/analytics.php` is shared by the workspace, landing preview, and exports.

| KPI | Formula | Proposed target |
| --- | --- | --- |
| Course Pass Rate | 100 × passed units / attempted units | ≥85% |
| Students at Risk | 100 × records with average grade >3 OR attendance <75% / selected records | ≤15% |
| Teaching Effectiveness | total SETE score / total criterion responses | ≥4/5 |
| SETE Response Rate | 100 × Clarity responses / Clarity invitations | ≥70% |
| Grade Completion | 100 × completed academic records / selected academic records | ≥95% |

A period record is complete when all of its subject grades are complete. Subject filtering changes the academic calculation to the selected subject's units, grade, attendance, and completion. Subject passing/failing percentages use all enrolled records as denominator; pending grades are shown separately. Pending-only period averages are stored as zero for the legacy non-null column; these are excluded from grade-based risk by the same >3 rule and can still be flagged by attendance. Completion remains independently visible.

SETE participation uses one criterion to avoid multiplying respondents across criteria. SETE is attributed only to synthetic program-period evaluations. **Subject and year-level filters produce unavailable SETE KPIs**, since this dataset cannot support those attributions. Missing values are not presented as zero or as failing KPIs. Historical charts compare all periods with the selected program, level, and subject; academic-year charts aggregate both semesters. Current cards and tables use the selected year and semester. These distinctions are labeled in the interface and exports.

Targets are classroom proposals, not official university policy. Flags and recommended support are suggestions; SETE must never be the sole personnel measure.

## Reports

`export.php` retains CSV support and adds `format=xlsx`, `format=pdf`, and `format=print`. `type` accepts `academic`, `program`, `subject`, `warning`, `teaching`, or `participation`. All formats include scope, generation time in Asia/Manila, calculated KPIs, and synthetic-data disclosure. Program-specific reports follow the selected program; leaving the program filter blank compares all programs. CSV cells are protected against spreadsheet formula interpretation. XLSX cells are explicit strings. PDF reports are downloadable multi-page documents with searchable text; the print view supports a browser's own print/save-as-PDF feature.

## Protected dataset administration

Public visitors cannot import, modify, or access import history. Imports are disabled by default. To enable administration, an authorized server administrator must set a high-entropy `EDUPULSE_ADMIN_KEY` of at least 32 characters in the PHP server environment, restart the server as required, then unlock Dataset Management with that key. The key is not committed, passed in a URL, or stored in browser storage. Administration expires after 30 minutes and can be locked explicitly. Invalid attempts have a session-level cooldown; use server-level access controls/rate limiting for a shared deployment and HTTPS outside localhost. The shared key is intended for a controlled academic installation; it is not a multi-user role/audit system.

All state changes require CSRF tokens and server-side authorization. Uploads are limited to 2 MB, 2,000 rows, CSV or `.xlsx`, and exact template columns:

```text
student_code,program,academic_year,semester,year_level,subject_code,grade,attendance_rate,completed
```

Student codes must be pseudonymous codes without names. Programs, periods, and curriculum assignments must already exist. Grades must be 1–5 for completed rows; pending grades must be blank and `completed=0`. Attendance must be 0–100. Imports reject missing required values, duplicates, invalid ranges, unassigned subjects, inconsistent cohorts, formulas, unsupported file types, and oversized workbooks. Legacy binary `.xls` is not supported; save it as `.xlsx` or CSV first.

The workflow validates and previews before a separate confirmation. It revalidates at confirmation and writes within a database transaction; it never overwrites existing student codes. Files are processed in memory, not saved under the public web root. An import history is recorded after successful saves. Only explicitly synthetic imports enter public analytics. Authorized institutional imports are stored privately and are excluded from **every public query and export**; a private institutional analytics interface and institution-approved access policies are not implemented. An institution must authorize data use and verify real curricula before any operational deployment. No import changes the trained model or ML training data.

Apache `.htaccess` disables directory listings and blocks direct HTTP access to internal source/support directories. On another web server, configure equivalent directory protection. Do not serve this repository with a generic static server.

## Machine learning preservation

`model/dropout_logistic.json`, `model/metrics.json`, `ml/train.py`, and the existing GitHub training workflow are retained. Coefficients and reported evaluation numbers are unchanged. The standardized logistic regression runs inference in PHP; it uses the UCI higher-education dropout dataset from Portugal and has not been trained on CICS data or validated for Batangas State University. It is separate from rule-based academic warnings. See `ml/README.md` for training methodology. Python is needed only to retrain through the existing workflow.

## Verification

With Apache and MySQL running:

```powershell
node tests/smoke.cjs
& C:\xampp\php\php.exe tests/backend.php
node tests/admin.cjs
```

- `smoke.cjs`: landing, public five-KPI dashboard, all sidebar routes, CICS-only scope, program/year/semester/level/subject filters, scoped report agreement, CSV/XLSX/PDF/print, ML and invalid inputs, CSRF, and protected imports.
- `backend.php`: creates a uniquely named `edupulse_verify_*` database, checks migration preservation/idempotency, program mappings, subject/KPI reconciliation, XLSX round trip, import validation, transaction rollback, private institutional exclusion, and unchanged model coefficients. It retains the verification database for inspection, never modifies the main database, and writes the database name to ignored `tests/artifacts/backend-state.json`.
- `admin.cjs`: uses that isolated database and a temporary local PHP server on port 8107 with a test-only generated key. Tests unlock, bad keys, Excel/CSV upload preview and confirmation, institutional privacy, file validation, CSRF, import history, and locking. Run `backend.php` before each admin test to obtain a fresh database.
- `browser.cjs`: Chrome DevTools-based desktop/mobile checks. Start a **separate headless Chrome test profile** with remote debugging port 9223, then run `node tests/browser.cjs`. Checks automatic/restartable ten-step tour, highlighting/focus, table search/sort, interconnected subjects, collapse/drawer, saved settings, screenshots, overflow, and JavaScript errors.

Node is a test runner only; the app remains PHP/MySQL. Test screenshots are saved under ignored `tests/artifacts/`. Syntax checks cover all PHP and JavaScript files. The current implementation passed public HTTP, backend, authorized import, and desktop/mobile browser checks.

## Files changed and added

Modified: `index.php`, `login.php`, `logout.php`, `predict.php`, `export.php`, `setup.php`, `config/app.php`, `assets/style.css`, `assets/dashboard.js`, `assets/interactions.js`, `tests/smoke.cjs`, and this README.

Added: `dashboard.php`, `migrate.php`, `sql/cics.sql`, shared `lib/analytics.php`, `lib/ui.php`, `lib/migration.php`, `lib/formats.php`, `lib/reports.php`, `lib/datasets.php`, module views under `views/`, `assets/workspace.js`, protected `dataset-import.php`/`dataset-template.php`, backend/admin/browser test suites, `.htaccess`, and `.gitignore`.

Further institutional work: verified campus offerings and curricula, approved source data, validated student longitudinal identifiers, subject-level SETE attribution, university-approved thresholds, production identity/role controls, and local validation of the external ML model. No claims of official offerings, deployment, or institutional validation are made.
