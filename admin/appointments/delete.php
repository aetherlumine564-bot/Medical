<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requirePermission('appointments.delete');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/appointments/index.php');
verifyCsrf();
$id = (int)($_POST['id'] ?? 0);
$db = getDB();
// Cancelar (no eliminar físicamente)
$db->prepare("UPDATE appointments SET status='cancelada', updated_at=NOW() WHERE id=?")->execute([$id]);
setFlash('success','Cita cancelada correctamente.');
redirect('admin/appointments/index.php');
