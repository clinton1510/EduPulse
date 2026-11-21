CREATE TABLE IF NOT EXISTS users (
 id INT AUTO_INCREMENT PRIMARY KEY,
 full_name VARCHAR(120) NOT NULL,
 email VARCHAR(150) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('chair','faculty') NOT NULL,
 department VARCHAR(80) DEFAULT NULL,
 faculty_id INT DEFAULT NULL
);
CREATE TABLE IF NOT EXISTS academic_records (
 id INT AUTO_INCREMENT PRIMARY KEY,
 student_code VARCHAR(30) NOT NULL,
 academic_year VARCHAR(12) NOT NULL,
 semester VARCHAR(20) NOT NULL,
 department VARCHAR(80) NOT NULL,
 units_attempted INT NOT NULL,
 units_passed INT NOT NULL,
 avg_grade DECIMAL(4,2) NOT NULL,
 attendance_rate DECIMAL(5,2) NOT NULL,
 grade_completed TINYINT(1) NOT NULL,
 INDEX idx_period (academic_year,semester,department),
 INDEX idx_student (student_code)
);
CREATE TABLE IF NOT EXISTS faculty (
 id INT AUTO_INCREMENT PRIMARY KEY,
 display_name VARCHAR(100) NOT NULL,
 department VARCHAR(80) NOT NULL
);
CREATE TABLE IF NOT EXISTS sete_summary (
 id INT AUTO_INCREMENT PRIMARY KEY,
 faculty_id INT NOT NULL,
 academic_year VARCHAR(12) NOT NULL,
 semester VARCHAR(20) NOT NULL,
 department VARCHAR(80) NOT NULL,
 criterion VARCHAR(50) NOT NULL,
 score_total DECIMAL(10,2) NOT NULL,
 response_count INT NOT NULL,
 invitations INT NOT NULL,
 INDEX idx_evaluation (academic_year,semester,department),
 CONSTRAINT fk_faculty FOREIGN KEY (faculty_id) REFERENCES faculty(id)
);
