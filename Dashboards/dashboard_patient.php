<?php 
session_start();
require_once '../config.php';;

$user_id = $_SESSION['user_id'];
$query = "SELECT first_name, last_name FROM patients WHERE user_id = '$user_id'";
$result = mysqli_query($conn, $query);
$patient = mysqli_fetch_assoc($result);

$displayName = $patient ? $patient['first_name'] . " " . $patient['last_name'] : $_SESSION['user_email'];
?>
<!DOCTYPE html>
<html>
<head>
<title>Patient Dashboard</title>
</head>

<body>

<h1>Patient Dashboard</h1>

<p>Welcome, <?php echo htmlspecialchars($displayName); ?></p>

<a href="logout.php">Logout</a>

</body>
</html>
