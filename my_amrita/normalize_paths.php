<?php
require 'c:/xampp/htdocs/my_amrita/api/db.php';
$tables = ['downloads', 'documents', 'assignments', 'notes', 'awards', 'file_attachments'];
foreach ($tables as $t) {
    try {
        $pdo->exec("UPDATE $t SET file_path = REPLACE(file_path, '../uploads/', '/uploads/') WHERE file_path LIKE '../uploads/%'");
    } catch (Exception $e) { }
}
echo 'Done';
