<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requirePermission('schedules.manage');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/schedules/index.php');
verifyCsrf();
$id = (int)($_POST['id'] ?? 0);
$db = getDB();
$s  = $db->prepare('SELECT doctor_id FROM schedules WHERE id=?');
$s->execute([$id]);
$s = $s->fetch();
$db->prepare('DELETE FROM schedules WHERE id=?')->execute([$id]);
setFlash('success','Horario eliminado.');
redirect('admin/schedules/index.php'.($s?'?doctor_id='.$s['doctor_id']:''));
