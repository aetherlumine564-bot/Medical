<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requirePermission('roles.delete');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/roles/index.php');
verifyCsrf();

$id = (int)($_POST['id'] ?? 0);
$db = getDB();

$role = $db->prepare('SELECT * FROM roles WHERE id = ?');
$role->execute([$id]);
$role = $role->fetch();

if (!$role) {
    setFlash('error','Rol no encontrado.');
    redirect('admin/roles/index.php');
}

// Proteger roles del sistema
if (in_array($role['slug'], ['admin','doctor','patient'])) {
    setFlash('error','No puedes eliminar los roles del sistema.');
    redirect('admin/roles/index.php');
}

// Verificar si hay usuarios con este rol
$count = $db->prepare('SELECT COUNT(*) FROM users WHERE role_id = ?');
$count->execute([$id]);
if ($count->fetchColumn() > 0) {
    setFlash('error','No puedes eliminar un rol que tiene usuarios asignados.');
    redirect('admin/roles/index.php');
}

$db->prepare('DELETE FROM roles WHERE id = ?')->execute([$id]);
setFlash('success', "Rol «{$role['name']}» eliminado correctamente.");
redirect('admin/roles/index.php');
