<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';

requirePermission('appointments.edit');
verifyCsrf();

$db = getDB();

$id = $_POST['id'] ?? null;

if ($id) {
    $stmt = $db->prepare("UPDATE appointments SET status = 'completada' WHERE id = ?");
    $stmt->execute([$id]);
}

// 🔁 Redirige de vuelta
header("Location: index.php");
exit;