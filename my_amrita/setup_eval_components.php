<?php
require_once 'api/db.php';

// 1. Create Tables
$pdo->exec("
CREATE TABLE IF NOT EXISTS course_evaluation_components (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20),
    component_name VARCHAR(100),
    max_marks DECIMAL(5,2),
    weightage DECIMAL(5,2)
);

CREATE TABLE IF NOT EXISTS student_component_marks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    component_id INT,
    scored_marks DECIMAL(5,2),
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (component_id) REFERENCES course_evaluation_components(id) ON DELETE CASCADE
);
");

// 2. Clear previous seeded components to avoid duplicates
$pdo->exec("DELETE FROM course_evaluation_components");

// 3. Seed data
$components = [
    // Compiler Design (23CSE314)
    ['23CSE314', 'Quiz 1', 10, 10],
    ['23CSE314', 'Mid Term', 50, 20],
    ['23CSE314', 'Lab Evaluation 1', 10, 10],
    ['23CSE314', 'Quiz 2', 10, 10],
    ['23CSE314', 'Project', 20, 20],

    // Distributed Systems (23CSE312)
    ['23CSE312', 'Quiz 1', 10, 10],
    ['23CSE312', 'Lab Evaluation 1', 10, 10],
    ['23CSE312', 'Mid Term', 50, 20],
    ['23CSE312', 'Lab Evaluation 2', 10, 10],
    ['23CSE312', 'Project', 20, 20],

    // Foundations of Cyber Security (23CSE313)
    ['23CSE313', 'Quiz 1', 15, 15],
    ['23CSE313', 'Quiz 2', 15, 15],
    ['23CSE313', 'Quiz 3', 20, 20],
    ['23CSE313', 'Mid Term', 50, 20],

    // Cloud Computing (23CSE363)
    ['23CSE363', 'Quiz 1', 20, 10],
    ['23CSE363', 'Lab Evaluation 1', 10, 10],
    ['23CSE363', 'Mid Term', 50, 20],
    ['23CSE363', 'Quiz 2', 10, 10],
    ['23CSE363', 'Lab Evaluation 2', 20, 20],

    // Software Engineering (23CSE311)
    ['23CSE311', 'Quiz 1', 15, 10],
    ['23CSE311', 'Quiz 2', 15, 10],
    ['23CSE311', 'Assignment 1', 20, 15],
    ['23CSE311', 'Assignment 2', 20, 15],
    ['23CSE311', 'Mid Term', 50, 20],

    // Life Skills for Engineers IV (23LSE311)
    ['23LSE311', 'Online Quiz 1', 6, 6],
    ['23LSE311', 'Online Quiz 2', 4, 4],
    ['23LSE311', 'Quiz 1', 4, 4],
    ['23LSE311', 'Quiz 2', 6, 6],
    ['23LSE311', 'Assignment 1', 15, 15],
    ['23LSE311', 'Assignment 2', 15, 15],
];

$stmt = $pdo->prepare("INSERT INTO course_evaluation_components (course_code, component_name, max_marks, weightage) VALUES (?, ?, ?, ?)");
foreach ($components as $c) {
    $stmt->execute($c);
}

echo "Components setup successfully!";
?>
