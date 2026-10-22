<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requireRole('patient');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('patient/appointments/index.php');
verifyCsrf();

$db     = getDB();
$userId = $_SESSION['user_id'];
$id     = (int)($_POST['id'] ?? 0);

// Verificar que la cita pertenece al paciente
$stmtP = $db->prepare('SELECT id FROM patients WHERE user_id=?');
$stmtP->execute([$userId]);
$patient = $stmtP->fetch();
if (!$patient) redirect('patient/appointments/index.php');

$stmt = $db->prepare("SELECT id FROM appointments WHERE id=? AND patient_id=? AND status IN ('pendiente','confirmada')");
$stmt->execute([$id, $patient['id']]);
if (!$stmt->fetch()) {
    setFlash('error','No puedes cancelar esta cita.');
    redirect('patient/appointments/index.php');
}

$db->prepare("UPDATE appointments SET status='cancelada', updated_at=NOW() WHERE id=?")->execute([$id]);
setFlash('success','Cita cancelada correctamente.');
redirect('patient/appointments/index.php');
