<?php
session_start();

$host = "localhost";
$user = "root";
$password = "";
$database = "medical_appointment_db";

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("Connexion échouée : " . mysqli_connect_error());
}


function redirectWithMessage($message, $type = 'error', $page = '../Web_Pages/HealthCare.html') {
    header("Location: $page?message=" . urlencode($message) . "&type=" . $type);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {


    if (empty($_POST['login']) || empty($_POST['password'])) {
        redirectWithMessage("Veuillez remplir tous les champs.");
    }

    $login = mysqli_real_escape_string($conn, $_POST['login']);
    $password = $_POST['password'];

    $query = mysqli_query($conn, "SELECT * FROM users WHERE email='$login' or phone='$login'");

    if (!$query) {
        redirectWithMessage("Erreur technique. Veuillez réessayer.");
    }

    if (mysqli_num_rows($query) == 0) {
        redirectWithMessage("Email non trouvé.");
    }

    $user = mysqli_fetch_assoc($query);

    if ($user['is_active'] != 1) {
        redirectWithMessage("Votre compte est désactivé.");
    }

    if (!password_verify($password, $user['password'])) {
        redirectWithMessage("Mot de passe incorrect.");
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_login'] = $user['login'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['logged_in'] = true;


    switch ($user['role']) {
        case 'admin':
            header("Location: ../Dashboards/dashboard_admin.php");
            break;
        case 'doctor':
            header("Location: ../Dashboards/dashboard_doctor.php");
            break;
        case 'patient':
            header("Location: ../Dashboards/dashboard_patient.php");
            break;
        default:
            redirectWithMessage("Rôle utilisateur inconnu.");
    }

    exit();

} else {

    header("Location: ../Web_Pages/HealthCare.html");
    exit();
}

mysqli_close($conn);
?>