<?php
$files = [
    "admin/manage_documents.php",
    "admin/manage_files.php",
    "teacher/add_awards.php",
    "teacher/add_notes.php",
    "teacher/upload_assignments.php",
    "teacher/grade_assignment.php",
    "teacher/view_issues.php",
    "pages/assignments.php",
    "pages/attendance.php",
    "pages/awards.php",
    "pages/documents.php",
    "pages/fee.php",
    "pages/leave.php",
    "pages/marks.php",
    "pages/notes.php",
    "pages/payments.php",
    "pages/refund.php"
];

$baseDir = "c:/xampp/htdocs/my_amrita/";

foreach ($files as $f) {
    $path = $baseDir . $f;
    if (file_exists($path)) {
        $content = file_get_contents($path);
        
        // Use double quotes for replacement so that we can escape properly
        $content = preg_replace('/href="<\?php echo htmlspecialchars\(\\$([a-zA-Z0-9_]+)\[\'file_path\'\]\); \?>"/i', 'href="..<?php echo htmlspecialchars(\$$1[\'file_path\']); ?>"', $content);
        
        $content = str_replace("\$file_path = '../uploads/", "\$file_path = '/uploads/", $content);
        $content = str_replace("href=\"..<?php echo htmlspecialchars(\$disp_path); ?>\"", "href=\"..<?php echo htmlspecialchars(\$disp_path); ?>\"", $content); // No-op, just to check
        
        file_put_contents($path, $content);
    }
}
echo "Code fixed.";
