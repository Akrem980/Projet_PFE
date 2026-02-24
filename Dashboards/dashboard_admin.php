<?php 
session_start();
require_once '../config.php';;

$user_id = $_SESSION['user_id'];
$query = "SELECT first_name, last_name FROM users WHERE user_id = '$user_id'";
$result = mysqli_query($conn, $query);
$admin = mysqli_fetch_assoc($result);

$displayName = $admin ? $admin['first_name'] . " " . $admin['last_name'] : $_SESSION['user_email'];
?>
<!DOCTYPE html>
<html>
<head>
<title>Admin Dashboard</title>
</head>

<body>

<h1>Admin Dashboard</h1>

<p>Welcome, <?php echo htmlspecialchars($displayName); ?></p>

<a href="logout.php">Logout</a>

</body>
</html>
