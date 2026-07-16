<?php
require_once __DIR__ . '/api/db.php';

echo "Starting Attendance Backfill...\n";

// Fetch all attendance summaries
$stmt = $pdo->query("SELECT student_id, course_code, course_name, total_classes, attended FROM attendance");
$summaries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_inserted = 0;

foreach ($summaries as $s) {
    $sid = $s['student_id'];
    $ccode = $s['course_code'];
    $cname = $s['course_name'];
    
    $actual_absent = max(0, $s['total_classes'] - $s['attended']);
    
    // Check how many are currently recorded
    $stmt_rec = $pdo->prepare("SELECT COUNT(*) FROM attendance_records WHERE student_id = ? AND course_code = ? AND status = 'Absent'");
    $stmt_rec->execute([$sid, $ccode]);
    $recorded_absent = $stmt_rec->fetchColumn();
    
    $diff = $actual_absent - $recorded_absent;
    
    if ($diff > 0) {
        // We need to insert $diff dummy records
        for ($i = 0; $i < $diff; $i++) {
            // Generate a random date in the past 3 months
            $days_ago = mt_rand(1, 90);
            $date = date('Y-m-d', strtotime("-$days_ago days"));
            $period = mt_rand(1, 6);
            
            $stmt_ins = $pdo->prepare("INSERT INTO attendance_records (student_id, course_code, date, period_number, status) VALUES (?, ?, ?, ?, 'Absent')");
            $stmt_ins->execute([$sid, $ccode, $date, $period]);
            $total_inserted++;
        }
    }
}

echo "Backfill complete. Inserted $total_inserted missing absent records.\n";
?>
