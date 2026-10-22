<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requirePermission('patients.delete');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/patients/index.php');
verifyCsrf();
$id = (int)($_POST['id'] ?? 0);
$db = getDB();
$p  = $db->prepare('SELECT p.*, u.name FROM patients p JOIN users u ON u.id = p.user_id WHERE p.id = ?');
$p->execute([$id]);
$p = $p->fetch();
if (!$p) { setFlash('error','Paciente no encontrado.'); redirect('admin/patients/index.php'); }
$db->prepare('DELETE FROM users WHERE id = ?')->execute([$p['user_id']]);
setFlash('success', "Paciente «{$p['name']}» eliminado.");
redirect('admin/patients/index.php');
