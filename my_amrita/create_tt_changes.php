<?php
require 'api/db.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS timetable_changes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        change_date DATE NOT NULL,
        course_code VARCHAR(20) NOT NULL,
        period_number INT NOT NULL,
        change_type ENUM('Cancelled', 'Extra Class', 'Rescheduled', 'Faculty Absent', 'Room Changed') NOT NULL,
        remarks TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "timetable_changes created successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
