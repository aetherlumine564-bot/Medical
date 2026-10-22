<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requirePermission('doctors.delete');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/doctors/index.php');
verifyCsrf();
$id = (int)($_POST['id'] ?? 0);
$db = getDB();
$d  = $db->prepare('SELECT d.*, u.name FROM doctors d JOIN users u ON u.id = d.user_id WHERE d.id = ?');
$d->execute([$id]);
$d = $d->fetch();
if (!$d) { setFlash('error','Doctor no encontrado.'); redirect('admin/doctors/index.php'); }
$db->prepare('DELETE FROM users WHERE id = ?')->execute([$d['user_id']]);
setFlash('success', "Doctor «{$d['name']}» eliminado.");
redirect('admin/doctors/index.php');
