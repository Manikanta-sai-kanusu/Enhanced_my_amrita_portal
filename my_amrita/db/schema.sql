-- ============================================================
-- My Amrita Student Portal – Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS my_amrita;
USE my_amrita;

-- ============================================================
-- 1. Students (core user table)
-- ============================================================
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_no VARCHAR(30) NOT NULL UNIQUE,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(15),
    department VARCHAR(100),
    semester INT DEFAULT 1,
    dob DATE,
    address TEXT,
    photo_url VARCHAR(255) DEFAULT NULL,
    bank_account VARCHAR(30) DEFAULT NULL,
    ifsc_code VARCHAR(20) DEFAULT NULL,
    bank_name VARCHAR(100) DEFAULT NULL,
    hostel_room VARCHAR(30) DEFAULT NULL,
    hostel_block VARCHAR(30) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 1b. Users (role-based login)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('student','admin','teacher','warden','chief_warden') DEFAULT 'student',
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(15) DEFAULT NULL,
    department VARCHAR(100) DEFAULT NULL,
    linked_student_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (linked_student_id) REFERENCES students(id) ON DELETE SET NULL
);

-- ============================================================
-- 2. Payments
-- ============================================================
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_type VARCHAR(50),
    status ENUM('Paid','Pending','Failed') DEFAULT 'Pending',
    transaction_id VARCHAR(50),
    payment_date DATE,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- ============================================================
-- 2b. Payment Issues / Reporting
-- ============================================================
CREATE TABLE IF NOT EXISTS payment_issues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    payment_id INT DEFAULT NULL,
    issue_type VARCHAR(100),
    description TEXT,
    status ENUM('Open','In Progress','Resolved') DEFAULT 'Open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL
);

-- ============================================================
-- 3. Gate Passes (3-level approval)
-- ============================================================
CREATE TABLE IF NOT EXISTS gate_passes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    reason TEXT,
    urgency ENUM('Normal','Urgent','Emergency') DEFAULT 'Normal',
    from_date DATETIME,
    to_date DATETIME,
    status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    approved_by VARCHAR(100),
    level1_status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    level1_by VARCHAR(100) DEFAULT NULL,
    level1_at TIMESTAMP NULL DEFAULT NULL,
    level2_status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    level2_by VARCHAR(100) DEFAULT NULL,
    level2_at TIMESTAMP NULL DEFAULT NULL,
    level3_status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    level3_by VARCHAR(100) DEFAULT NULL,
    level3_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- ============================================================
-- 4. Services & Complaints (merged)
-- ============================================================
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    service_type VARCHAR(100),
    category ENUM('Service','Complaint') DEFAULT 'Service',
    description TEXT,
    status ENUM('Open','In Progress','Closed','Resolved') DEFAULT 'Open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject VARCHAR(200),
    description TEXT,
    status ENUM('Open','In Progress','Resolved') DEFAULT 'Open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- ============================================================
-- 6. Downloads (admin-managed files)
-- ============================================================
CREATE TABLE IF NOT EXISTS downloads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    file_path VARCHAR(255),
    category VARCHAR(100),
    uploaded_by VARCHAR(100) DEFAULT 'Admin',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 7. Documents (student uploads + admin uploads)
-- ============================================================
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    doc_type VARCHAR(100),
    file_name VARCHAR(200),
    file_path VARCHAR(255),
    uploaded_by VARCHAR(50) DEFAULT 'student',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- ============================================================
-- 8. Incidents
-- ============================================================
CREATE TABLE IF NOT EXISTS incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    incident_type VARCHAR(100),
    description TEXT,
    incident_date DATE,
    status ENUM('Reported','Investigating','Resolved') DEFAULT 'Reported',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- ============================================================
-- 9. Attendance
-- ============================================================
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_code VARCHAR(20),
    course_name VARCHAR(100),
    total_classes INT DEFAULT 0,
    attended INT DEFAULT 0,
    percentage DECIMAL(5,2) DEFAULT 0.00,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- ============================================================
-- 10. Leaves
-- ============================================================
CREATE TABLE IF NOT EXISTS leaves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    leave_type VARCHAR(50),
    from_date DATE,
    to_date DATE,
    reason TEXT,
    status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- ============================================================
-- 11. Awards
-- ============================================================
CREATE TABLE IF NOT EXISTS awards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    award_name VARCHAR(200),
    description TEXT,
    award_date DATE,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- ============================================================
-- 12. Timetable (with faculty name)
-- ============================================================
CREATE TABLE IF NOT EXISTS timetable (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    day_name VARCHAR(15),
    time_slot VARCHAR(30),
    course_code VARCHAR(20),
    course_name VARCHAR(100),
    room VARCHAR(30),
    faculty_name VARCHAR(100) DEFAULT NULL,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- ============================================================
-- 13. Supplementary
-- ============================================================
CREATE TABLE IF NOT EXISTS supplementary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_code VARCHAR(20),
    course_name VARCHAR(100),
    exam_date DATE,
    status ENUM('Registered','Appeared','Result Declared') DEFAULT 'Registered',
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- ============================================================
-- 14. Marks
-- ============================================================
CREATE TABLE IF NOT EXISTS marks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_code VARCHAR(20),
    course_name VARCHAR(100),
    internal DECIMAL(5,2) DEFAULT 0,
    external DECIMAL(5,2) DEFAULT 0,
    total DECIMAL(5,2) DEFAULT 0,
    grade VARCHAR(5),
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- ============================================================
-- 15. Refunds
-- ============================================================
CREATE TABLE IF NOT EXISTS refunds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    amount DECIMAL(10,2),
    reason TEXT,
    status ENUM('Pending','Approved','Processed','Rejected') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- ============================================================
-- 16. Admit Cards (with file_path)
-- ============================================================
CREATE TABLE IF NOT EXISTS admit_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    exam_name VARCHAR(200),
    exam_date DATE,
    hall_ticket_no VARCHAR(50),
    file_path VARCHAR(255) DEFAULT NULL,
    status ENUM('Available','Downloaded','Expired') DEFAULT 'Available',
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- ============================================================
-- 17. Course Feedback (structured rubric)
-- ============================================================
CREATE TABLE IF NOT EXISTS course_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_code VARCHAR(20),
    course_name VARCHAR(100),
    rating INT CHECK (rating BETWEEN 1 AND 5),
    content_rating INT DEFAULT NULL CHECK (content_rating BETWEEN 1 AND 5),
    delivery_rating INT DEFAULT NULL CHECK (delivery_rating BETWEEN 1 AND 5),
    assessment_rating INT DEFAULT NULL CHECK (assessment_rating BETWEEN 1 AND 5),
    resource_rating INT DEFAULT NULL CHECK (resource_rating BETWEEN 1 AND 5),
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- ============================================================
-- 18. TLP Feedback (structured rubric)
-- ============================================================
CREATE TABLE IF NOT EXISTS tlp_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    faculty_name VARCHAR(100),
    course_code VARCHAR(20),
    rating INT CHECK (rating BETWEEN 1 AND 5),
    knowledge_rating INT DEFAULT NULL CHECK (knowledge_rating BETWEEN 1 AND 5),
    communication_rating INT DEFAULT NULL CHECK (communication_rating BETWEEN 1 AND 5),
    helpfulness_rating INT DEFAULT NULL CHECK (helpfulness_rating BETWEEN 1 AND 5),
    punctuality_rating INT DEFAULT NULL CHECK (punctuality_rating BETWEEN 1 AND 5),
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- ============================================================
-- 19. Courses (master list)
-- ============================================================
CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20) NOT NULL UNIQUE,
    course_name VARCHAR(100) NOT NULL,
    credits INT DEFAULT 3,
    grade_point DECIMAL(3,1) DEFAULT 0.0,
    semester INT DEFAULT 1
);

-- ============================================================
-- 20. Attendance Issues
-- ============================================================
CREATE TABLE IF NOT EXISTS attendance_issues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_code VARCHAR(20),
    course_name VARCHAR(100),
    issue_date DATE,
    description TEXT,
    proof_file VARCHAR(255) DEFAULT NULL,
    status ENUM('Submitted','Under Review','Resolved','Rejected') DEFAULT 'Submitted',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- ============================================================
-- 21. Marks Issues
-- ============================================================
CREATE TABLE IF NOT EXISTS marks_issues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_code VARCHAR(20),
    course_name VARCHAR(100),
    exam_type VARCHAR(50),
    description TEXT,
    proof_file VARCHAR(255) DEFAULT NULL,
    status ENUM('Submitted','Under Review','Resolved','Rejected') DEFAULT 'Submitted',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- ============================================================
-- 22. Notes (teacher-uploaded course notes)
-- ============================================================
CREATE TABLE IF NOT EXISTS notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uploaded_by_user_id INT DEFAULT NULL,
    student_id INT DEFAULT NULL,
    course_code VARCHAR(20),
    course_name VARCHAR(100),
    title VARCHAR(200),
    file_name VARCHAR(200),
    file_path VARCHAR(255),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- ============================================================
-- 23. Internal Marks
-- ============================================================
CREATE TABLE IF NOT EXISTS internal_marks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_code VARCHAR(20),
    course_name VARCHAR(100),
    assignment1 DECIMAL(5,2) DEFAULT 0,
    assignment2 DECIMAL(5,2) DEFAULT 0,
    quiz1 DECIMAL(5,2) DEFAULT 0,
    quiz2 DECIMAL(5,2) DEFAULT 0,
    midterm DECIMAL(5,2) DEFAULT 0,
    max_assignment DECIMAL(5,2) DEFAULT 10,
    max_quiz DECIMAL(5,2) DEFAULT 10,
    max_midterm DECIMAL(5,2) DEFAULT 20,
    total_internal DECIMAL(5,2) DEFAULT 0,
    percentage DECIMAL(5,2) DEFAULT 0,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- ============================================================
-- 24. Faculty Advisors
-- ============================================================
CREATE TABLE IF NOT EXISTS faculty_advisors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    faculty_name VARCHAR(100),
    designation VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(20),
    office_room VARCHAR(50),
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- ============================================================
-- 25. Gate Pass Cancellations
-- ============================================================
CREATE TABLE IF NOT EXISTS gatepass_cancellations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gatepass_id INT NOT NULL,
    student_id INT NOT NULL,
    reason TEXT,
    cancelled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (gatepass_id) REFERENCES gate_passes(id),
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- ============================================================
-- 26. Medical Leaves
-- ============================================================
CREATE TABLE IF NOT EXISTS medical_leaves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    from_date DATE,
    to_date DATE,
    condition_desc VARCHAR(200),
    doctor_name VARCHAR(100),
    hospital VARCHAR(200),
    medical_cert_file VARCHAR(255) DEFAULT NULL,
    status ENUM('Submitted','Under Review','Verified','Approved','Rejected') DEFAULT 'Submitted',
    reviewed_by VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- ============================================================
-- 27. Timetable Changes
-- ============================================================
CREATE TABLE IF NOT EXISTS timetable_changes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_code VARCHAR(20),
    course_name VARCHAR(100),
    change_type ENUM('Room Change','Time Change','Cancelled','Extra Class') DEFAULT 'Room Change',
    old_value VARCHAR(100),
    new_value VARCHAR(100),
    effective_date DATE,
    day_name VARCHAR(15),
    notified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- ============================================================
-- 28. Seating Arrangements (with room_number)
-- ============================================================
CREATE TABLE IF NOT EXISTS seating_arrangements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    exam_name VARCHAR(200),
    course_code VARCHAR(20),
    exam_date DATE,
    hall_name VARCHAR(100),
    seat_number VARCHAR(20),
    room_number VARCHAR(30) DEFAULT NULL,
    floor VARCHAR(20),
    block VARCHAR(20),
    file_path VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- ============================================================
-- 29. Attendance Alerts
-- ============================================================
CREATE TABLE IF NOT EXISTS attendance_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_code VARCHAR(20),
    course_name VARCHAR(100),
    current_percentage DECIMAL(5,2),
    alert_type ENUM('Warning','Critical') DEFAULT 'Warning',
    message TEXT,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- ============================================================
-- 30. Grace Marks
-- ============================================================
CREATE TABLE IF NOT EXISTS grace_marks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    award_or_publication VARCHAR(200),
    category ENUM('Award','Paper Publication','Hackathon','Sports','Cultural') DEFAULT 'Award',
    marks_awarded DECIMAL(5,2) DEFAULT 0,
    applied_to_course VARCHAR(20),
    applied_to_course_name VARCHAR(100),
    old_total DECIMAL(5,2) DEFAULT 0,
    new_total DECIMAL(5,2) DEFAULT 0,
    old_grade VARCHAR(5),
    new_grade VARCHAR(5),
    sgpa_before DECIMAL(4,2) DEFAULT 0,
    sgpa_after DECIMAL(4,2) DEFAULT 0,
    status ENUM('Pending','Applied','Rejected') DEFAULT 'Pending',
    applied_date DATE,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- ============================================================
-- 31. Academic Calendar
-- ============================================================
CREATE TABLE IF NOT EXISTS academic_calendar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_title VARCHAR(200) NOT NULL,
    event_date DATE NOT NULL,
    end_date DATE DEFAULT NULL,
    event_type ENUM('Academic','Examination','Holiday','Deadline','Result') DEFAULT 'Academic',
    description TEXT,
    semester INT DEFAULT 0
);

-- ============================================================
-- 32. Fee Notifications
-- ============================================================
CREATE TABLE IF NOT EXISTS fee_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    title VARCHAR(200),
    fee_type VARCHAR(100),
    old_amount DECIMAL(10,2) DEFAULT NULL,
    new_amount DECIMAL(10,2) DEFAULT NULL,
    deadline DATE,
    description TEXT,
    action_required TEXT DEFAULT NULL,
    status ENUM('Upcoming','Active','Overdue','Completed') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- ============================================================
-- 33. Events (with google form link)
-- ============================================================
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_name VARCHAR(200) NOT NULL,
    event_type ENUM('Cultural','Technical','Sports','Workshop','Seminar','Other') DEFAULT 'Other',
    event_date DATE NOT NULL,
    start_time TIME,
    end_time TIME,
    venue VARCHAR(200),
    description TEXT,
    organizer VARCHAR(100),
    registration_link VARCHAR(255) DEFAULT NULL,
    google_form_link VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 34. Teacher–Course mapping
-- ============================================================
CREATE TABLE IF NOT EXISTS teacher_courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_code VARCHAR(20) NOT NULL,
    course_name VARCHAR(100),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_teacher_course (user_id, course_code)
);

-- ============================================================
-- 35. Teacher–Advisee mapping
-- ============================================================
CREATE TABLE IF NOT EXISTS teacher_advisees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    student_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_teacher_student (user_id, student_id)
);

-- ============================================================
-- 36. Notifications
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT,
    type ENUM('marks','attendance','gatepass','medical_leave','award','timetable','seating','notes','general') DEFAULT 'general',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- ============================================================
-- 37. Student Registrations (self-register, admin approves)
-- ============================================================
CREATE TABLE IF NOT EXISTS student_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(15),
    department VARCHAR(100),
    semester INT DEFAULT 1,
    dob DATE,
    address TEXT,
    preferred_username VARCHAR(50),
    status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    reject_reason TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 38. Hostel Attendance
-- ============================================================
CREATE TABLE IF NOT EXISTS hostel_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('Present','Absent','Late','On Leave') DEFAULT 'Present',
    marked_by VARCHAR(100) DEFAULT NULL,
    remarks TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- ============================================================
-- 39. Student ID Cards
-- ============================================================
CREATE TABLE IF NOT EXISTS student_id_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL UNIQUE,
    card_status ENUM('Active','Blocked','Lost','Expired') DEFAULT 'Active',
    block_reason TEXT DEFAULT NULL,
    blocked_since DATE DEFAULT NULL,
    blocked_days INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- ============================================================
-- 40. Counselling Requests
-- ============================================================
CREATE TABLE IF NOT EXISTS counselling_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    preferred_date DATE NOT NULL,
    time_slot VARCHAR(30),
    reason_category ENUM('Academic','Personal','Career','Mental Health','Other') DEFAULT 'Other',
    description TEXT,
    status ENUM('Pending','Scheduled','Completed','Cancelled') DEFAULT 'Pending',
    counsellor_name VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- ============================================================
-- 41. File Attachments (generic, for leaves, attendance, marks, admitcards)
-- ============================================================
CREATE TABLE IF NOT EXISTS file_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ref_type VARCHAR(50) NOT NULL,
    ref_id INT NOT NULL,
    file_name VARCHAR(200),
    file_path VARCHAR(255),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 42. Assignments
-- ============================================================
CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20),
    course_name VARCHAR(100),
    title VARCHAR(200) NOT NULL,
    description TEXT,
    due_date DATE,
    assigned_by VARCHAR(100),
    file_path VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 43. Notes Doubts
-- ============================================================
CREATE TABLE IF NOT EXISTS notes_doubts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    note_id INT NOT NULL,
    student_id INT NOT NULL,
    course_code VARCHAR(20),
    issue_text TEXT,
    response TEXT,
    status ENUM('Open', 'Resolved') DEFAULT 'Open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- ============================================================
-- 44. Assignment Submissions
-- ============================================================
CREATE TABLE IF NOT EXISTS assignment_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    file_name VARCHAR(200),
    file_path VARCHAR(255),
    status ENUM('Submitted', 'Graded') DEFAULT 'Submitted',
    marks_awarded DECIMAL(5,2),
    feedback TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- ============================================================
-- 45. Timetable Changes (Daily)
-- ============================================================
CREATE TABLE IF NOT EXISTS timetable_changes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    change_date DATE NOT NULL,
    course_code VARCHAR(20) NOT NULL,
    period_number INT NOT NULL,
    change_type ENUM('Cancelled', 'Extra Class', 'Rescheduled', 'Faculty Absent', 'Room Changed') NOT NULL,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Student users
INSERT INTO students (enrollment_no, username, password, name, email, phone, department, semester, dob, address, bank_account, ifsc_code, bank_name, hostel_room, hostel_block)
VALUES ('BL.EN.U4CSE23102', 'rahul', '$2y$10$2x/5QxVdEf1E5xujFqUfn.ufMz9/I1VqJKbWYIgbjDk8W8QxO5R2m', 'AILURI RAHUL REDDY', 'rahul@am.amrita.edu', '9876543210', 'Computer Science & Engineering', 4, '2005-06-15', 'Amritapuri Campus, Kerala', '1234567890123456', 'SBIN0001234', 'State Bank of India', 'A-205', 'Block A');

INSERT INTO students (enrollment_no, username, password, name, email, phone, department, semester, dob, address, hostel_room, hostel_block)
VALUES ('BL.EN.U4CSE23000', 'demo', '$2y$10$/1EI2QMYE00oqctFnkfBDO.E5zWKI2SomP2LEvc3U7EpjMUI3sssq', 'Demo User', 'demo@am.amrita.edu', '9000000000', 'Computer Science & Engineering', 1, '2000-01-01', 'Demo Address', 'B-101', 'Block B');

-- Users (role-based login)
-- Passwords: rahul=password123, demo=student, admin=admin, teacher=teacher, warden=warden, chiefwarden=chiefwarden
INSERT INTO users (username, password, role, name, email, linked_student_id) VALUES
('rahul',   '$2y$10$2x/5QxVdEf1E5xujFqUfn.ufMz9/I1VqJKbWYIgbjDk8W8QxO5R2m', 'student', 'AILURI RAHUL REDDY', 'rahul@am.amrita.edu', 1),
('demo',    '$2y$10$/1EI2QMYE00oqctFnkfBDO.E5zWKI2SomP2LEvc3U7EpjMUI3sssq', 'student', 'Demo User', 'demo@am.amrita.edu', 2),
('admin',   '$2y$10$kQ7SN/nMKAT7o/ox3ZycCe6mHJE5pKq02oZbeX5TBIPr3UhxxipJ2', 'admin',   'Administrator', 'admin@am.amrita.edu', NULL),
('teacher', '$2y$10$HDXRSmCuTQTbXr/CgzTZ2u3lF.6QUuGF5WNdbGEI25yfKX8u7cvpq', 'teacher', 'Dr. Ramesh Iyer', 'ramesh@am.amrita.edu', NULL),
('warden',  '$2y$10$HDXRSmCuTQTbXr/CgzTZ2u3lF.6QUuGF5WNdbGEI25yfKX8u7cvpq', 'warden',  'Mr. Vijay Menon', 'vijay.menon@am.amrita.edu', NULL),
('chiefwarden', '$2y$10$HDXRSmCuTQTbXr/CgzTZ2u3lF.6QUuGF5WNdbGEI25yfKX8u7cvpq', 'chief_warden', 'Dr. Anand Sharma', 'anand.sharma@am.amrita.edu', NULL);

-- Payments
INSERT INTO payments (student_id, amount, payment_type, status, transaction_id, payment_date) VALUES
(1, 150000.00, 'Tuition Fee', 'Paid', 'TXN20260101001', '2026-01-05'),
(1, 25000.00, 'Hostel Fee', 'Paid', 'TXN20260101002', '2026-01-05'),
(1, 5000.00, 'Exam Fee', 'Pending', NULL, '2026-03-15'),
(1, 3000.00, 'Library Fee', 'Paid', 'TXN20260201003', '2026-02-10');

-- Payment Issues
INSERT INTO payment_issues (student_id, payment_id, issue_type, description, status) VALUES
(1, 3, 'Payment Failed', 'Attempted to pay exam fee online but transaction failed. Amount debited from bank.', 'Open');

-- Gate Passes (with 3-level approval)
INSERT INTO gate_passes (student_id, reason, urgency, from_date, to_date, status, approved_by, level1_status, level1_by, level2_status, level2_by, level3_status, level3_by) VALUES
(1, 'Family visit – Diwali break', 'Normal', '2025-11-01 08:00:00', '2025-11-05 18:00:00', 'Approved', 'Dr. Suresh Kumar', 'Approved', 'Dr. Suresh Kumar', 'Approved', 'Mr. Vijay Menon', 'Approved', 'Dr. Anand Sharma'),
(1, 'Medical appointment', 'Urgent', '2026-02-20 09:00:00', '2026-02-20 17:00:00', 'Approved', 'Dr. Suresh Kumar', 'Approved', 'Dr. Suresh Kumar', 'Approved', 'Mr. Vijay Menon', 'Approved', 'Dr. Anand Sharma'),
(1, 'Weekend outing', 'Normal', '2026-03-22 08:00:00', '2026-03-23 20:00:00', 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL);

-- Services (merged with complaints)
INSERT INTO services (student_id, service_type, category, description, status) VALUES
(1, 'Transcript Request', 'Service', 'Need official transcript for internship application', 'In Progress'),
(1, 'ID Card Replacement', 'Service', 'Lost student ID card', 'Closed'),
(1, 'Wi-Fi Access Issue', 'Service', 'Cannot connect to campus Wi-Fi in Block-C', 'Open'),
(1, 'Hostel Water Issue', 'Complaint', 'No hot water in Block-C hostel for 3 days', 'In Progress'),
(1, 'Library AC not working', 'Complaint', 'Air conditioning in 2nd floor reading hall is not working', 'Resolved');

-- Downloads
INSERT INTO downloads (title, file_path, category, uploaded_by) VALUES
('Academic Calendar 2025-26', '/downloads/academic_calendar_2025_26.pdf', 'Academic', 'Admin'),
('Exam Timetable – Semester 4', '/downloads/exam_timetable_sem4.pdf', 'Examination', 'Admin'),
('Hostel Rules & Regulations', '/downloads/hostel_rules.pdf', 'Hostel', 'Admin'),
('Scholarship Application Form', '/downloads/scholarship_form.pdf', 'Finance', 'Admin'),
('Anti-Ragging Policy', '/downloads/anti_ragging_policy.pdf', 'General', 'Admin');

-- Documents
INSERT INTO documents (student_id, doc_type, file_name, file_path) VALUES
(1, 'Aadhar Card', 'aadhar_rahul.pdf', '/uploads/docs/aadhar_rahul.pdf'),
(1, '10th Marksheet', 'ssc_marksheet.pdf', '/uploads/docs/ssc_marksheet.pdf'),
(1, '12th Marksheet', 'hsc_marksheet.pdf', '/uploads/docs/hsc_marksheet.pdf');

-- Incidents
INSERT INTO incidents (student_id, incident_type, description, incident_date, status) VALUES
(1, 'Lost Property', 'Lost wallet near main canteen', '2026-02-15', 'Resolved'),
(1, 'Infrastructure Issue', 'Broken chair in classroom A-204', '2026-03-10', 'Reported');

-- Attendance
INSERT INTO attendance (student_id, course_code, course_name, total_classes, attended, percentage) VALUES
(1, '22CSE311', 'Data Structures & Algorithms', 45, 40, 88.89),
(1, '22CSE312', 'Database Management Systems', 42, 38, 90.48),
(1, '22CSE313', 'Operating Systems', 40, 33, 82.50),
(1, '22MAT201', 'Probability & Statistics', 38, 35, 92.11),
(1, '22CSE314', 'Computer Networks', 44, 41, 93.18),
(1, '22CSE315', 'Software Engineering', 36, 30, 83.33);

-- Leaves
INSERT INTO leaves (student_id, leave_type, from_date, to_date, reason, status) VALUES
(1, 'Medical', '2026-02-10', '2026-02-12', 'Fever and cold', 'Approved'),
(1, 'Personal', '2026-01-20', '2026-01-21', 'Family function', 'Approved'),
(1, 'Medical', '2026-03-18', '2026-03-19', 'Dental appointment', 'Pending');

-- Awards
INSERT INTO awards (student_id, award_name, description, award_date) VALUES
(1, 'Best Project Award', 'Won best project in CS department hackathon 2025', '2025-12-15'),
(1, 'Academic Excellence', 'Scored highest CGPA in Semester 3', '2025-07-20'),
(1, 'Sports Achievement', '1st place in inter-college coding marathon', '2026-01-10');

-- Timetable (with faculty names)
INSERT INTO timetable (student_id, day_name, time_slot, course_code, course_name, room, faculty_name) VALUES
(1, 'Monday',    '09:00-10:00', '22CSE311', 'Data Structures & Algorithms', 'A-201', 'Dr. Ramesh Iyer'),
(1, 'Monday',    '10:00-11:00', '22CSE312', 'Database Management Systems', 'A-202', 'Dr. Ramesh Iyer'),
(1, 'Monday',    '11:15-12:15', '22MAT201', 'Probability & Statistics', 'B-101', 'Prof. Meera Nair'),
(1, 'Monday',    '14:00-15:00', '22CSE314', 'Computer Networks', 'A-203', 'Dr. Anil Kumar'),
(1, 'Tuesday',   '09:00-10:00', '22CSE313', 'Operating Systems', 'A-204', 'Prof. Lakshmi Nair'),
(1, 'Tuesday',   '10:00-11:00', '22CSE315', 'Software Engineering', 'A-205', 'Dr. Priya Das'),
(1, 'Tuesday',   '11:15-12:15', '22CSE311', 'Data Structures & Algorithms', 'Lab-1', 'Dr. Ramesh Iyer'),
(1, 'Tuesday',   '14:00-16:00', '22CSE312', 'DBMS Lab', 'Lab-2', 'Dr. Ramesh Iyer'),
(1, 'Wednesday', '09:00-10:00', '22MAT201', 'Probability & Statistics', 'B-101', 'Prof. Meera Nair'),
(1, 'Wednesday', '10:00-11:00', '22CSE314', 'Computer Networks', 'A-203', 'Dr. Anil Kumar'),
(1, 'Wednesday', '11:15-12:15', '22CSE315', 'Software Engineering', 'A-205', 'Dr. Priya Das'),
(1, 'Wednesday', '14:00-16:00', '22CSE313', 'OS Lab', 'Lab-3', 'Prof. Lakshmi Nair'),
(1, 'Thursday',  '09:00-10:00', '22CSE311', 'Data Structures & Algorithms', 'A-201', 'Dr. Ramesh Iyer'),
(1, 'Thursday',  '10:00-11:00', '22CSE312', 'Database Management Systems', 'A-202', 'Dr. Ramesh Iyer'),
(1, 'Thursday',  '11:15-12:15', '22CSE314', 'Computer Networks', 'A-203', 'Dr. Anil Kumar'),
(1, 'Thursday',  '14:00-16:00', '22CSE311', 'DSA Lab', 'Lab-1', 'Dr. Ramesh Iyer'),
(1, 'Friday',    '09:00-10:00', '22MAT201', 'Probability & Statistics', 'B-101', 'Prof. Meera Nair'),
(1, 'Friday',    '10:00-11:00', '22CSE313', 'Operating Systems', 'A-204', 'Prof. Lakshmi Nair'),
(1, 'Friday',    '11:15-12:15', '22CSE315', 'Software Engineering', 'A-205', 'Dr. Priya Das');

-- Supplementary
INSERT INTO supplementary (student_id, course_code, course_name, exam_date, status) VALUES
(1, '22PHY101', 'Engineering Physics', '2026-04-10', 'Registered'),
(1, '22ENG101', 'Technical English', '2026-04-12', 'Registered');

-- Marks
INSERT INTO marks (student_id, course_code, course_name, internal, external, total, grade) VALUES
(1, '22CSE311', 'Data Structures & Algorithms', 38.00, 52.00, 90.00, 'O'),
(1, '22CSE312', 'Database Management Systems', 35.00, 48.00, 83.00, 'A+'),
(1, '22CSE313', 'Operating Systems', 30.00, 42.00, 72.00, 'A'),
(1, '22MAT201', 'Probability & Statistics', 40.00, 55.00, 95.00, 'O'),
(1, '22CSE314', 'Computer Networks', 33.00, 45.00, 78.00, 'A'),
(1, '22CSE315', 'Software Engineering', 36.00, 50.00, 86.00, 'A+');

-- Refunds
INSERT INTO refunds (student_id, amount, reason, status) VALUES
(1, 2500.00, 'Excess exam fee paid for Semester 3', 'Processed'),
(1, 1000.00, 'Library deposit refund', 'Pending');

-- Admit Cards (with file_path)
INSERT INTO admit_cards (student_id, exam_name, exam_date, hall_ticket_no, file_path, status) VALUES
(1, 'End Semester Exam – Sem 4', '2026-04-15', 'HT-CSE-2026-0102', '/downloads/admit_card_sem4.pdf', 'Available'),
(1, 'Supplementary Exam – Physics', '2026-04-10', 'HT-SUP-2026-0045', '/downloads/admit_card_sup_phy.pdf', 'Available');

-- Course Feedback (structured)
INSERT INTO course_feedback (student_id, course_code, course_name, rating, content_rating, delivery_rating, assessment_rating, resource_rating, comments) VALUES
(1, '22CSE311', 'Data Structures & Algorithms', 5, 5, 5, 4, 5, 'Excellent teaching methodology'),
(1, '22CSE312', 'Database Management Systems', 4, 4, 4, 3, 4, 'Good course content, labs could be improved');

-- TLP Feedback (structured)
INSERT INTO tlp_feedback (student_id, faculty_name, course_code, rating, knowledge_rating, communication_rating, helpfulness_rating, punctuality_rating, comments) VALUES
(1, 'Dr. Ramesh Iyer', '22CSE311', 5, 5, 5, 5, 4, 'Very engaging lectures'),
(1, 'Prof. Lakshmi Nair', '22CSE312', 4, 5, 4, 4, 3, 'Knowledgeable but pace is fast');

-- Courses
INSERT INTO courses (course_code, course_name, credits, grade_point, semester) VALUES
('22CSE311', 'Data Structures & Algorithms', 4, 10.0, 4),
('22CSE312', 'Database Management Systems', 4, 9.0, 4),
('22CSE313', 'Operating Systems', 3, 8.0, 4),
('22MAT201', 'Probability & Statistics', 3, 10.0, 4),
('22CSE314', 'Computer Networks', 3, 8.0, 4),
('22CSE315', 'Software Engineering', 3, 9.0, 4);

-- Attendance Issues
INSERT INTO attendance_issues (student_id, course_code, course_name, issue_date, description, proof_file, status) VALUES
(1, '22CSE313', 'Operating Systems', '2026-03-05', 'Was present in class on 5th March but marked absent.', '/uploads/proofs/att_proof_os_mar5.pdf', 'Under Review'),
(1, '22CSE315', 'Software Engineering', '2026-02-20', 'Attended online session but attendance not recorded.', '/uploads/proofs/att_proof_se_feb20.png', 'Resolved'),
(1, '22MAT201', 'Probability & Statistics', '2026-03-12', 'Marked absent due to late entry.', '/uploads/proofs/att_proof_mat_mar12.pdf', 'Submitted');

-- Marks Issues
INSERT INTO marks_issues (student_id, course_code, course_name, exam_type, description, proof_file, status) VALUES
(1, '22CSE312', 'Database Management Systems', 'Mid-Term', 'Question 3b was correctly answered but given 0 marks.', '/uploads/proofs/marks_dbms_midterm.pdf', 'Under Review'),
(1, '22CSE314', 'Computer Networks', 'Assignment 2', 'Assignment was submitted on time but marked as late.', '/uploads/proofs/marks_cn_assign2.png', 'Resolved');

-- Notes (teacher-uploaded)
INSERT INTO notes (uploaded_by_user_id, student_id, course_code, course_name, title, file_name, file_path) VALUES
(4, 1, '22CSE311', 'Data Structures & Algorithms', 'Binary Trees & BST Notes', 'dsa_trees_notes.pdf', '/uploads/notes/dsa_trees_notes.pdf'),
(4, 1, '22CSE311', 'Data Structures & Algorithms', 'Graph Algorithms Summary', 'dsa_graphs.pdf', '/uploads/notes/dsa_graphs.pdf'),
(4, 1, '22CSE312', 'Database Management Systems', 'Normalization Complete Notes', 'dbms_normalization.pdf', '/uploads/notes/dbms_normalization.pdf'),
(NULL, 1, '22CSE313', 'Operating Systems', 'Process Scheduling Algorithms', 'os_scheduling.pdf', '/uploads/notes/os_scheduling.pdf'),
(NULL, 1, '22MAT201', 'Probability & Statistics', 'Probability Distributions Formulas', 'prob_distributions.pdf', '/uploads/notes/prob_distributions.pdf');

-- Internal Marks
INSERT INTO internal_marks (student_id, course_code, course_name, assignment1, assignment2, quiz1, quiz2, midterm, max_assignment, max_quiz, max_midterm, total_internal, percentage) VALUES
(1, '22CSE311', 'Data Structures & Algorithms', 9.0, 8.5, 8.0, 9.0, 18.0, 10, 10, 20, 38.0, 76.0),
(1, '22CSE312', 'Database Management Systems', 8.0, 7.5, 7.0, 8.0, 16.0, 10, 10, 20, 35.0, 70.0),
(1, '22CSE313', 'Operating Systems', 7.0, 6.5, 6.0, 7.0, 14.0, 10, 10, 20, 30.0, 60.0),
(1, '22MAT201', 'Probability & Statistics', 10.0, 9.5, 9.0, 9.5, 19.0, 10, 10, 20, 40.0, 80.0),
(1, '22CSE314', 'Computer Networks', 8.0, 7.0, 7.5, 7.0, 15.0, 10, 10, 20, 33.0, 66.0),
(1, '22CSE315', 'Software Engineering', 9.0, 8.0, 8.5, 8.0, 17.0, 10, 10, 20, 36.0, 72.0);

-- Faculty Advisor
INSERT INTO faculty_advisors (student_id, faculty_name, designation, email, phone, office_room) VALUES
(1, 'Dr. Suresh Kumar', 'Associate Professor & Faculty Advisor', 'suresh.kumar@am.amrita.edu', '+91 9876543210', 'Room A-105, CSE Block');

-- Gate Pass Cancellations
INSERT INTO gatepass_cancellations (gatepass_id, student_id, reason) VALUES
(3, 1, 'Plans changed due to unexpected lab exam scheduled on the same day.');

-- Medical Leaves
INSERT INTO medical_leaves (student_id, from_date, to_date, condition_desc, doctor_name, hospital, medical_cert_file, status, reviewed_by) VALUES
(1, '2026-02-10', '2026-02-12', 'Viral Fever & Cold', 'Dr. Anitha Menon', 'Amrita Hospital, Kochi', '/uploads/medical/med_cert_feb10.pdf', 'Approved', 'Dr. Suresh Kumar'),
(1, '2026-03-18', '2026-03-19', 'Dental Surgery', 'Dr. Rahul Nair', 'KIMS Dental Clinic', '/uploads/medical/med_cert_mar18.pdf', 'Under Review', NULL),
(1, '2026-01-05', '2026-01-06', 'Food Poisoning', 'Dr. Priya Das', 'Campus Health Centre', NULL, 'Verified', 'Dr. Suresh Kumar');

-- Timetable Changes
INSERT INTO timetable_changes (student_id, course_code, course_name, change_type, old_value, new_value, effective_date, day_name) VALUES
(1, '22CSE311', 'Data Structures & Algorithms', 'Room Change', 'A-201', 'A-301', '2026-03-17', 'Monday'),
(1, '22CSE312', 'Database Management Systems', 'Time Change', '10:00-11:00', '11:15-12:15', '2026-03-18', 'Tuesday'),
(1, '22CSE313', 'Operating Systems', 'Extra Class', '—', 'Saturday 10:00-12:00 (Lab-3)', '2026-03-22', 'Saturday'),
(1, '22MAT201', 'Probability & Statistics', 'Cancelled', 'Wednesday 09:00-10:00', 'Cancelled (Faculty leave)', '2026-03-19', 'Wednesday');

-- Seating Arrangements (with room_number)
INSERT INTO seating_arrangements (student_id, exam_name, course_code, exam_date, hall_name, seat_number, room_number, floor, block) VALUES
(1, 'End Semester Exam – DSA', '22CSE311', '2026-04-15', 'Examination Hall 1', 'A-42', 'Room 201', '2nd Floor', 'Block A'),
(1, 'End Semester Exam – DBMS', '22CSE312', '2026-04-17', 'Examination Hall 2', 'B-18', 'Room 105', '1st Floor', 'Block B'),
(1, 'End Semester Exam – OS', '22CSE313', '2026-04-19', 'Examination Hall 1', 'A-35', 'Room 203', '2nd Floor', 'Block A'),
(1, 'End Semester Exam – Prob & Stats', '22MAT201', '2026-04-21', 'Examination Hall 3', 'C-07', 'Room 001', 'Ground Floor', 'Block C'),
(1, 'End Semester Exam – CN', '22CSE314', '2026-04-23', 'Examination Hall 2', 'B-22', 'Room 108', '1st Floor', 'Block B'),
(1, 'End Semester Exam – SE', '22CSE315', '2026-04-25', 'Examination Hall 1', 'A-50', 'Room 205', '2nd Floor', 'Block A');

-- Attendance Alerts
INSERT INTO attendance_alerts (student_id, course_code, course_name, current_percentage, alert_type, message) VALUES
(1, '22CSE313', 'Operating Systems', 82.50, 'Warning', 'Your attendance in Operating Systems is 82.5%. It is approaching the minimum 75% threshold.'),
(1, '22CSE315', 'Software Engineering', 83.33, 'Warning', 'Your attendance in Software Engineering is 83.3%. Maintain regular attendance.');

-- Grace Marks
INSERT INTO grace_marks (student_id, award_or_publication, category, marks_awarded, applied_to_course, applied_to_course_name, old_total, new_total, old_grade, new_grade, sgpa_before, sgpa_after, status, applied_date) VALUES
(1, 'Best Project Award – CS Hackathon 2025', 'Hackathon', 3.00, '22CSE313', 'Operating Systems', 72.00, 75.00, 'A', 'A', 8.75, 8.90, 'Applied', '2026-01-20'),
(1, 'Paper: ML-based Attendance Prediction System (IEEE)', 'Paper Publication', 5.00, '22CSE314', 'Computer Networks', 78.00, 83.00, 'A', 'A+', 8.75, 9.05, 'Applied', '2026-02-15'),
(1, '1st Place – Inter-college Coding Marathon', 'Award', 2.00, '22CSE315', 'Software Engineering', 86.00, 88.00, 'A+', 'A+', 9.05, 9.10, 'Pending', '2026-03-10');

-- Academic Calendar
INSERT INTO academic_calendar (event_title, event_date, end_date, event_type, description, semester) VALUES
('Semester 4 Begins', '2026-01-06', NULL, 'Academic', 'Start of Even Semester 2025-26', 4),
('Republic Day', '2026-01-26', NULL, 'Holiday', 'National Holiday', 4),
('Mid-Semester Exam Registration Deadline', '2026-02-10', NULL, 'Deadline', 'Last date to register for mid-semester examinations', 4),
('Mid-Semester Examinations', '2026-02-16', '2026-02-22', 'Examination', 'Mid-semester exams for all courses', 4),
('Holi Holiday', '2026-03-14', NULL, 'Holiday', 'Festival Holiday', 4),
('Course Feedback Submission Deadline', '2026-03-20', NULL, 'Deadline', 'Last date to submit course feedback forms', 4),
('Last Working Day', '2026-03-28', NULL, 'Academic', 'Last day of regular classes', 4),
('Study Holidays', '2026-03-30', '2026-04-12', 'Academic', 'Preparation leave before end-semester exams', 4),
('End Semester Examinations', '2026-04-13', '2026-04-28', 'Examination', 'End-semester final examinations', 4),
('Results Declaration', '2026-05-15', NULL, 'Result', 'Semester 4 results announced', 4),
('Summer Vacation Begins', '2026-05-01', '2026-06-30', 'Holiday', 'Summer break', 4);

-- Fee Notifications
INSERT INTO fee_notifications (student_id, title, fee_type, old_amount, new_amount, deadline, description, action_required, status) VALUES
(1, 'Tuition Fee Revision – Semester 5', 'Tuition Fee', 150000.00, 160000.00, '2026-06-15', 'Tuition fee has been revised for the academic year 2026-27.', 'Please review the updated fee structure.', 'Upcoming'),
(1, 'Exam Fee – End Semester', 'Exam Fee', NULL, 5000.00, '2026-03-25', 'End semester examination fee for Semester 4.', 'Pay ₹5,000 via the Payments portal before 25th March.', 'Active'),
(1, 'Hostel Fee – Even Semester', 'Hostel Fee', 25000.00, 27000.00, '2026-01-15', 'Hostel fee for Even Semester 2025-26 has been updated.', NULL, 'Completed'),
(1, 'Library Fine', 'Fine', NULL, 200.00, '2026-03-15', 'Overdue library fine for 2 books returned late.', 'Pay the fine at the library counter or online.', 'Overdue');

-- Events (with google form links)
INSERT INTO events (event_name, event_type, event_date, start_time, end_time, venue, description, organizer, registration_link, google_form_link) VALUES
('Amritathon 2026 – Coding Marathon', 'Technical', '2026-03-25', '09:00:00', '18:00:00', 'Computer Lab Block-A', '12-hour coding marathon open to all departments.', 'CSE Department', 'https://events.amrita.edu/amritathon2026', 'https://docs.google.com/forms/d/e/example1/viewform'),
('Anokha Tech Fest', 'Technical', '2026-04-05', '10:00:00', '17:00:00', 'Main Auditorium & Open Grounds', 'Annual tech fest featuring robotics, AI demos, startup pitches.', 'Student Council', 'https://anokha.amrita.edu', 'https://docs.google.com/forms/d/e/example2/viewform'),
('Cultural Night – Sangamam', 'Cultural', '2026-03-28', '18:00:00', '22:00:00', 'Open Air Theatre', 'Annual cultural night with music, dance, and drama.', 'Cultural Committee', NULL, NULL),
('Inter-Department Cricket Tournament', 'Sports', '2026-03-22', '07:00:00', '12:00:00', 'Sports Ground', 'Cricket tournament between all engineering departments.', 'Sports Committee', NULL, NULL),
('Workshop: Introduction to Cloud Computing', 'Workshop', '2026-03-20', '14:00:00', '17:00:00', 'Seminar Hall B-201', 'Hands-on workshop on AWS and Azure.', 'ACM Student Chapter', 'https://events.amrita.edu/cloud-workshop', 'https://docs.google.com/forms/d/e/example3/viewform'),
('Guest Lecture: AI in Healthcare', 'Seminar', '2026-04-02', '11:00:00', '13:00:00', 'Lecture Hall A-101', 'Guest lecture by Dr. Priya Sharma from IIT Madras.', 'CSE Department', NULL, NULL),
('Yoga & Meditation Camp', 'Cultural', '2026-03-21', '06:00:00', '08:00:00', 'Yoga Hall', 'Morning yoga and meditation session.', 'Amrita Yuva Dharma Dhara', NULL, NULL);

-- Teacher–Course mapping
INSERT INTO teacher_courses (user_id, course_code, course_name) VALUES
(4, '22CSE311', 'Data Structures & Algorithms'),
(4, '22CSE312', 'Database Management Systems');

-- Teacher–Advisee mapping
INSERT INTO teacher_advisees (user_id, student_id) VALUES
(4, 1),
(4, 2);

-- Notifications
INSERT INTO notifications (student_id, title, message, type) VALUES
(1, 'Gate Pass Approved', 'Your gate pass for "Family visit – Diwali break" has been approved.', 'gatepass'),
(1, 'Medical Leave Approved', 'Your medical leave (10 Feb – 12 Feb) for Viral Fever has been approved.', 'medical_leave'),
(1, 'Attendance Updated', 'Your attendance for Data Structures & Algorithms (22CSE311) has been updated.', 'attendance'),
(1, 'Marks Updated', 'Your marks for Database Management Systems (22CSE312) have been updated.', 'marks');

-- Hostel Attendance
INSERT INTO hostel_attendance (student_id, attendance_date, status, marked_by) VALUES
(1, '2026-03-25', 'Present', 'Mr. Vijay Menon'),
(1, '2026-03-26', 'Present', 'Mr. Vijay Menon'),
(1, '2026-03-27', 'Late', 'Mr. Vijay Menon'),
(2, '2026-03-25', 'Present', 'Mr. Vijay Menon'),
(2, '2026-03-26', 'Absent', 'Mr. Vijay Menon'),
(2, '2026-03-27', 'Present', 'Mr. Vijay Menon');

-- Student ID Cards
INSERT INTO student_id_cards (student_id, card_status) VALUES
(1, 'Active'),
(2, 'Active');

-- Counselling Requests
INSERT INTO counselling_requests (student_id, preferred_date, time_slot, reason_category, description, status, counsellor_name) VALUES
(1, '2026-04-01', '10:00-11:00', 'Academic', 'Need guidance on choosing electives for next semester.', 'Scheduled', 'Dr. Kavitha Menon');

-- Assignments
INSERT INTO assignments (course_code, course_name, title, description, due_date, assigned_by) VALUES
('22CSE311', 'Data Structures & Algorithms', 'Assignment 3: AVL Trees Implementation', 'Implement AVL tree with insert, delete, and search operations in C++.', '2026-04-05', 'Dr. Ramesh Iyer'),
('22CSE312', 'Database Management Systems', 'Assignment 2: SQL Queries', 'Write complex SQL queries for the given ER diagram.', '2026-04-02', 'Dr. Ramesh Iyer'),
('22CSE313', 'Operating Systems', 'Lab 5: Process Synchronization', 'Implement producer-consumer problem using semaphores.', '2026-03-30', 'Prof. Lakshmi Nair');

-- Sample Student Registration (pending)
INSERT INTO student_registrations (name, email, phone, department, semester, dob, address, preferred_username, status) VALUES
('Arun Krishnan', 'arun.k@gmail.com', '9876000123', 'Computer Science & Engineering', 1, '2007-03-15', 'Trivandrum, Kerala', 'arun_k', 'Pending');
