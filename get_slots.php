<?php
session_start();
require_once '../config.php';
checkAuth('patient');

header('Content-Type: application/json');

$doctor_id = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 0;
$date      = isset($_GET['date'])      ? $_GET['date'] : '';

if (!$doctor_id || !$date) {
    echo json_encode(['slots' => []]);
    exit;
}

// Validate date not in the past
if ($date < date('Y-m-d')) {
    echo json_encode(['slots' => [], 'error' => 'Date in the past']);
    exit;
}

// Get already booked slots for this doctor on this date
$booked_res = mysqli_query($conn,
    "SELECT start_time FROM appointments
     WHERE doctor_id='$doctor_id' AND appointment_date='$date' AND status != 'cancelled'");
$booked = [];
while ($row = mysqli_fetch_assoc($booked_res)) {
    $booked[] = $row['start_time'];
}

// Generate 30-min slots: 08:00 – 18:00
$slots = [];
$start_hour = 8;
$end_hour   = 18;
$current = new DateTime($date . ' ' . sprintf('%02d:00:00', $start_hour));
$end     = new DateTime($date . ' ' . sprintf('%02d:00:00', $end_hour));

while ($current < $end) {
    $slot_start = $current->format('H:i:s');
    $current->modify('+30 minutes');
    $slot_end = $current->format('H:i:s');

    $taken = in_array($slot_start, $booked);

    $slots[] = [
        'start' => substr($slot_start, 0, 5),
        'end'   => substr($slot_end,   0, 5),
        'taken' => $taken
    ];
}

echo json_encode(['slots' => $slots]);
?>
