<?php
/**
 * MedCRM – Elimina el instalador desde el navegador
 * Solo accesible si el sistema ya está instalado
 */
if (!file_exists(__DIR__ . '/.installed')) {
    header('Location: install.php'); exit;
}

$deleted = [];
$errors  = [];

$files = ['install.php', 'delete_installer.php'];
foreach ($files as $f) {
    $path = __DIR__ . '/' . $f;
    if (file_exists($path)) {
        if (@unlink($path)) {
            $deleted[] = $f;
        } else {
            $errors[] = "No se pudo eliminar: {$f} (verifica permisos)";
        }
    }
}

// Si se eliminó todo, redirigir al sistema
if (empty($errors)) {
    header('Location: index.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Eliminar instalador – MedCRM</title>
<style>
body{font-family:Inter,sans-serif;background:#F0F4F8;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;}
.box{background:#fff;border-radius:16px;padding:2.5rem;max-width:460px;box-shadow:0 4px 24px rgba(0,0,0,.1);}
h2{color:#1A56DB;margin-bottom:1rem;}
.err{background:#FDE8E8;color:#9B1C1C;padding:.85rem 1rem;border-radius:8px;font-size:.87rem;margin:.4rem 0;border-left:4px solid #E02424;}
.ok {background:#DEF7EC;color:#065F46;padding:.85rem 1rem;border-radius:8px;font-size:.87rem;margin:.4rem 0;border-left:4px solid #0E9F6E;}
a{display:inline-block;margin-top:1.5rem;background:#1A56DB;color:#fff;padding:.6rem 1.3rem;border-radius:9px;text-decoration:none;font-weight:600;}
code{background:#F3F4F6;padding:.15rem .4rem;border-radius:4px;}
</style>
</head>
<body>
<div class="box">
  <h2>🗑️ Eliminar instalador</h2>
  <?php foreach ($deleted as $f): ?>
  <div class="ok">✅ Eliminado: <code><?= htmlspecialchars($f) ?></code></div>
  <?php endforeach; ?>
  <?php foreach ($errors as $e): ?>
  <div class="err">❌ <?= htmlspecialchars($e) ?></div>
  <p style="font-size:.82rem;color:#6B7280;margin-top:.5rem;">
    Elimínalo manualmente: <code>htdocs/medical-crm/install.php</code>
  </p>
  <?php endforeach; ?>
  <a href="index.php">→ Ir al sistema</a>
</div>
</body>
</html>
