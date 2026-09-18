<?php
require_once __DIR__ . '/../config/db.php';
$r = $conn->query('DESCRIBE products');
while($row = $r->fetch_assoc()) {
    echo $row['Field'].' ('.$row['Type'].')\n';
}
