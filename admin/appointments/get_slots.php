<?php
/**
 * API: devuelve los slots de tiempo disponibles de un doctor en una fecha
 */
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requireLogin();
header('Content-Type: application/json');

$doctorId = (int)($_GET['doctor_id'] ?? 0);
$date     = $_GET['date'] ?? '';

if (!$doctorId || !$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode([]);
    exit;
}

$db = getDB();

// Día de la semana: 0=Dom ... 6=Sab
$dayOfWeek = (int)date('w', strtotime($date));

// Obtener horario del doctor para ese día
$schedule = $db->prepare(
    'SELECT * FROM schedules WHERE doctor_id=? AND day_of_week=? AND is_active=1'
);
$schedule->execute([$doctorId, $dayOfWeek]);
$schedule = $schedule->fetch();

if (!$schedule) {
    echo json_encode([]);
    exit;
}

// Generar slots
$slots       = [];
$start       = strtotime($date . ' ' . $schedule['start_time']);
$end         = strtotime($date . ' ' . $schedule['end_time']);
$slotMinutes = (int)$schedule['slot_duration'];

// Citas ya ocupadas
$booked = $db->prepare(
    "SELECT appointment_time FROM appointments
     WHERE doctor_id=? AND appointment_date=? AND status NOT IN ('cancelada')"
);
$booked->execute([$doctorId, $date]);
$bookedTimes = $booked->fetchAll(PDO::FETCH_COLUMN);

for ($t = $start; $t < $end; $t += $slotMinutes * 60) {
    $time      = date('H:i:s', $t);
    $timeLabel = date('H:i', $t);
    $slots[]   = [
        'time'      => $timeLabel,
        'available' => !in_array($time, $bookedTimes),
    ];
}

echo json_encode($slots);
