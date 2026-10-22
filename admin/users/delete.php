<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requirePermission('users.delete');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/users/index.php');
verifyCsrf();

$id = (int)($_POST['id'] ?? 0);
if ($id === (int)$_SESSION['user_id']) {
    setFlash('error','No puedes eliminar tu propia cuenta.');
    redirect('admin/users/index.php');
}

$db = getDB();
$u  = $db->prepare('SELECT * FROM users WHERE id = ?');
$u->execute([$id]);
$u = $u->fetch();
if (!$u) { setFlash('error','Usuario no encontrado.'); redirect('admin/users/index.php'); }

$db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
setFlash('success', "Usuario «{$u['name']}» eliminado.");
redirect('admin/users/index.php');
