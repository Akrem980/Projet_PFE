<?php
session_start();
session_destroy();
header("Location: HealthCare.html");
exit();
?>
