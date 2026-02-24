<?php 
session_start();
require_once '../config.php';;

$user_id = $_SESSION['user_id'];
$query = "SELECT first_name, last_name FROM doctors WHERE user_id = '$user_id'";
$result = mysqli_query($conn, $query);
$doctor = mysqli_fetch_assoc($result);

$displayName = $doctor ? $doctor['first_name'] . " " . $doctor['last_name'] : $_SESSION['user_email'];
?>
<!DOCTYPE html>
<html>
<head>
<title>Doctor Dashboard</title>
</head>

<body>

<h1>Doctor Dashboard</h1>

<p>Welcome, <?php echo htmlspecialchars($displayName); ?></p>

<a href="logout.php">Logout</a>

</body>
</html>
