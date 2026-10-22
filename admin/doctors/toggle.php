<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requirePermission('doctors.edit');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/doctors/index.php');
verifyCsrf();
$id     = (int)($_POST['id'] ?? 0);
$status = (int)($_POST['status'] ?? 0);
$db     = getDB();
$db->prepare('UPDATE doctors SET status=?, updated_at=NOW() WHERE id=?')->execute([$status, $id]);
setFlash('success', $status ? 'Doctor habilitado.' : 'Doctor deshabilitado.');
redirect('admin/doctors/index.php');
