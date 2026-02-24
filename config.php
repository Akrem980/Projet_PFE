<?php

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'medical_appointment_db');


$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if (!$conn) {
    die("ERROR: Could not connect. " . mysqli_connect_error());
}


mysqli_set_charset($conn, "utf8");

function redirectWithMessage($message, $type = 'error', $page = '../Web_Pages/HealthCare.html') {
    header("Location: $page?message=" . urlencode($message) . "&type=" . $type);
    exit();
}
?>