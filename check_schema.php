// Save this as check_schema.php at root ng project
<?php
include 'config/database.php';
$result = $conn->query("DESCRIBE request_forms");
while($row = $result->fetch_assoc()) {
    print_r($row);
    echo "<br>";
}
?>