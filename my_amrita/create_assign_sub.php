<?php
require 'api/db.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS assignment_submissions (
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
    )");
    echo "Table assignment_submissions created.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
