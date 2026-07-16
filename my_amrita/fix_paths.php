<?php
require_once 'api/db.php';
$pdo->exec("UPDATE notes SET file_path = REPLACE(file_path, '/uploads/notes/', '../uploads/notes/') WHERE file_path LIKE '/uploads/notes/%'");
$pdo->exec("UPDATE assignments SET file_path = REPLACE(file_path, '/uploads/assignments/', '../uploads/assignments/') WHERE file_path LIKE '/uploads/assignments/%'");
echo "DB Paths Fixed";
