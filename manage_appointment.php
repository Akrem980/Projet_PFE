<?php
session_start();
require_once '../config.php';
checkAuth('doctor');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $appt_id = intval($_POST['appointment_id']);
    $action  = mysqli_real_escape_string($conn, $_POST['action']);
    $user_id = $_SESSION['user_id'];

    $valid_actions = ['scheduled', 'cancelled', 'completed'];
    if (!in_array($action, $valid_actions)) {
        redirectWithMessage("Action invalide.", 'error', '../Dashboards/dashboard_doctor.php');
    }

    // Verify this appointment belongs to this doctor
    $doc_stmt = mysqli_prepare($conn, "SELECT id FROM doctors WHERE user_id = ?");
    mysqli_stmt_bind_param($doc_stmt, "i", $user_id);
    mysqli_stmt_execute($doc_stmt);
    $doc_res = mysqli_stmt_get_result($doc_stmt);
    $doctor  = mysqli_fetch_assoc($doc_res);

    if (!$doctor) {
        redirectWithMessage("Médecin introuvable.", 'error', '../Dashboards/dashboard_doctor.php');
    }

    $upd = mysqli_prepare($conn,
        "UPDATE appointments SET status=? WHERE id=? AND doctor_id=?");
    mysqli_stmt_bind_param($upd, "sii", $action, $appt_id, $doctor['id']);

    if (mysqli_stmt_execute($upd)) {
        redirectWithMessage("Statut du rendez-vous mis à jour.", 'success', '../Dashboards/dashboard_doctor.php');
    } else {
        redirectWithMessage("Erreur lors de la mise à jour.", 'error', '../Dashboards/dashboard_doctor.php');
    }
} else {
    header("Location: ../Dashboards/dashboard_doctor.php");
    exit();
}
mysqli_close($conn);
?>
