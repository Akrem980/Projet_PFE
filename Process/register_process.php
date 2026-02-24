<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "medical_appointment_db";

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("Connexion échouée : " . mysqli_connect_error());
}

function redirectWithMessage($message, $type = 'error', $page = '../Web_Pages/Healthcare.html') {
    header("Location: $page?message=" . urlencode($message) . "&type=" . $type);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (!empty($_POST['email']) && !empty($_POST['password']) && !empty($_POST['role']) && !empty($_POST['phone'])) {

        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirmPassword'];
        $role = mysqli_real_escape_string($conn, $_POST['role']);

        if ($password !== $confirmPassword) {
            redirectWithMessage("Passwords do not match.");
        }

        $roles_valides = array('admin', 'doctor', 'patient');
        if (!in_array($role, $roles_valides)) {
            redirectWithMessage("Rôle invalide.");
        }

        $check = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
        
        if (mysqli_num_rows($check) > 0) {
            redirectWithMessage("Cet email existe déjà.");
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $sql = "INSERT INTO users (email, phone, password, role, is_active, created_at)
                    VALUES ('$email', '$phone', '$hashed_password', '$role', 1, NOW())";
            
            $insert = mysqli_query($conn, $sql);
            
            if ($insert) {
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['email'] = $email;
                $_SESSION['role'] = $role;

                if ($role == "admin") {
                    header("Location: ../Dashboards/dashboard_admin.php");
                } elseif ($role == "doctor") {
                    header("Location: ../Dashboards/dashboard_doctor.php");
                } else {
                    header("Location: ../Dashboards/dashboard_patient.php");
                }
                exit();
            } else {
                die("Erreur SQL: " . mysqli_error($conn));
            }
        }
    } else {
        redirectWithMessage("Veuillez remplir tous les champs.");
    }
}

mysqli_close($conn);
?>