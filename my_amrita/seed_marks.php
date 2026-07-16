<?php
require_once __DIR__ . '/api/db.php';

echo "Starting dynamic component marks seed...\n";

// Fetch all courses that have dynamic components defined
$stmt = $pdo->prepare("SELECT DISTINCT course_code FROM course_evaluation_components");
$stmt->execute();
$dynamic_courses = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "Found " . count($dynamic_courses) . " courses with dynamic components.\n";

if (empty($dynamic_courses)) {
    die("No dynamic courses found. Ensure setup_eval_components.php has been run.\n");
}

$students_updated = 0;
$marks_updated = 0;

// For each dynamic course, find all students enrolled
foreach ($dynamic_courses as $course) {
    // Get components for this course
    $stmt_comps = $pdo->prepare("SELECT * FROM course_evaluation_components WHERE course_code = ?");
    $stmt_comps->execute([$course]);
    $comps = $stmt_comps->fetchAll(PDO::FETCH_ASSOC);

    // Get all students enrolled in this course from marks table
    $stmt_students = $pdo->prepare("SELECT student_id, external FROM marks WHERE course_code = ?");
    $stmt_students->execute([$course]);
    $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Processing $course (" . count($comps) . " components, " . count($students) . " students)\n";

    foreach ($students as $s) {
        $sid = $s['student_id'];
        $total_internal = 0;

        foreach ($comps as $c) {
            $cid = $c['id'];
            $max = floatval($c['max_marks']);
            $weight = floatval($c['weightage']);
            
            // Generate realistic random score (60% to 95% of max_marks)
            $random_pct = mt_rand(600, 950) / 1000;
            $scored = round($max * $random_pct, 1);
            
            // Insert or Update the component mark
            $stmt_upsert = $pdo->prepare("INSERT INTO student_component_marks (student_id, component_id, scored_marks) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE scored_marks = VALUES(scored_marks)");
            $stmt_upsert->execute([$sid, $cid, $scored]);
            
            // Calculate weightage contribution
            $contrib = ($max > 0) ? ($scored / $max) * $weight : 0;
            $total_internal += $contrib;
            $marks_updated++;
        }

        // Update the master marks table with the new internal score and calculate total and grade
        $external = floatval($s['external']);
        $total = $total_internal + $external;
        
        // Calculate Grade (Amrita Grading System typical bounds)
        // Assume O > 90, A+ > 85, A > 80, B+ > 70, B > 60, C > 50, P > 40, F < 40
        // Wait, total might be out of 100 if external is out of 50 and internal is out of 50. Let's assume % based on 100.
        $grade = 'F';
        if ($total >= 90) $grade = 'O';
        elseif ($total >= 85) $grade = 'A+';
        elseif ($total >= 80) $grade = 'A';
        elseif ($total >= 70) $grade = 'B+';
        elseif ($total >= 60) $grade = 'B';
        elseif ($total >= 50) $grade = 'C';
        elseif ($total >= 40) $grade = 'P';
        
        $stmt_update_mark = $pdo->prepare("UPDATE marks SET internal = ?, total = ?, grade = ? WHERE student_id = ? AND course_code = ?");
        $stmt_update_mark->execute([$total_internal, $total, $grade, $sid, $course]);
        $students_updated++;
    }
}

echo "\nSeeding Complete!\n";
echo "Processed $students_updated student-course combinations.\n";
echo "Updated $marks_updated individual component marks.\n";
?>
