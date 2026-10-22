<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requirePermission('roles.edit');

$db     = getDB();
$id     = (int)($_GET['id'] ?? 0);
$errors = [];

// 🔥 CONSULTA SEGURA
$stmt = $db->prepare('SELECT * FROM roles WHERE id = ?');
$stmt->execute([$id]);
$role = $stmt->fetch(PDO::FETCH_ASSOC);

// 🔥 BLINDAJE TOTAL
if (!is_array($role)) {
    $role = [];
}

if (empty($role)) { 
    setFlash('error','Rol no encontrado.'); 
    redirect('admin/roles/index.php'); 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name  = trim($_POST['name']  ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $color = $_POST['color'] ?? '#1A56DB';
    $perms = $_POST['permissions'] ?? [];

    $errors = validateRequired(['name' => 'Nombre'], $_POST);

    if (empty($errors)) {
        $db->prepare('UPDATE roles SET name=?, description=?, color=?, updated_at=NOW() WHERE id=?')
           ->execute([$name, $desc, $color, $id]);

        // Reemplazar permisos
        $db->prepare('DELETE FROM role_permissions WHERE role_id=?')->execute([$id]);

        if (!empty($perms)) {
            $stmtPerm = $db->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?,?)');
            foreach ($perms as $pid) {
                $stmtPerm->execute([$id, (int)$pid]);
            }
        }

        setFlash('success', "Rol «{$name}» actualizado correctamente.");
        redirect('admin/roles/index.php');
    }
}

// Permisos actuales del rol
$stmtPerms = $db->prepare(
    'SELECT permission_id FROM role_permissions WHERE role_id = ?'
);
$stmtPerms->execute([$id]);
$currentPerms = $stmtPerms->fetchAll(PDO::FETCH_COLUMN);

// 🔥 FETCH SEGURO
$allPerms = $db->query('SELECT * FROM permissions ORDER BY module, name')
               ->fetchAll(PDO::FETCH_ASSOC);

$grouped  = [];
foreach ($allPerms as $p) {
    $grouped[$p['module']][] = $p;
}

$pageTitle   = 'Editar Rol';
$breadcrumbs = [['label'=>'Roles','url'=>'admin/roles/index.php'],['label'=>'Editar rol']];
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      Editar Rol: 
      <span style="color:var(--primary);"><?= e($role['name'] ?? '') ?></span>
    </h1>
  </div>
</div>

<form method="POST">
  <?= csrfField() ?>

  <div style="display:grid;grid-template-columns:1fr 360px;gap:1.25rem;">
    <div class="card">
      <div class="card-header"><span class="card-title">Información del rol</span></div>
      <div class="card-body">
        <div class="form-grid">
          
          <div class="form-group">
            <label>Nombre <span class="required">*</span></label>
            <input type="text" name="name" class="form-control <?= isset($errors['name'])? 'is-invalid':'' ?>"
                   value="<?= e($_POST['name'] ?? ($role['name'] ?? '')) ?>">
            <?php if(isset($errors['name'])): ?>
              <span class="form-error"><?= e($errors['name']) ?></span>
            <?php endif; ?>
          </div>

          <div class="form-group">
            <label>Slug</label>
            <input type="text" class="form-control" value="<?= e($role['slug'] ?? '') ?>" disabled>
            <span class="form-hint">El slug no puede cambiarse</span>
          </div>

          <div class="form-group full-width">
            <label>Descripción</label>
            <textarea name="description" class="form-control" rows="3"><?= e($_POST['description'] ?? ($role['description'] ?? '')) ?></textarea>
          </div>

          <div class="form-group">
            <label>Color identificador</label>
            <input type="color" name="color" value="<?= e($_POST['color'] ?? ($role['color'] ?? '#1A56DB')) ?>"
                   style="width:44px;height:38px;border:1px solid var(--border-color);border-radius:8px;cursor:pointer;">
          </div>

        </div>
      </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:1rem;">
      <div class="card">
        <div class="card-header"><span class="card-title">Guardar</span></div>
        <div class="card-body">
          <button type="submit" class="btn btn-primary w-full" style="justify-content:center;">
            <i class="fa-solid fa-floppy-disk"></i> Guardar cambios
          </button>
          <a href="index.php" class="btn btn-secondary w-full mt-1" style="justify-content:center;">
            <i class="fa-solid fa-xmark"></i> Cancelar
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Permisos -->
  <div class="card" style="margin-top:1.25rem;">
    <div class="card-header">
      <span class="card-title">Permisos</span>
      <button type="button" class="btn btn-secondary btn-sm" onclick="toggleAllPerms(this)">
        Seleccionar todos
      </button>
    </div>

    <div class="card-body">
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1.5rem;">
        
        <?php foreach ($grouped as $module => $perms): ?>
        <div>
          <div style="font-size:.8rem;font-weight:700;color:var(--primary);margin-bottom:.75rem;">
            <i class="fa-solid fa-layer-group"></i> <?= e($module) ?>
          </div>

          <?php foreach ($perms as $p): ?>
          <label class="form-check" style="margin-bottom:.45rem;">
            <input type="checkbox" name="permissions[]" value="<?= $p['id'] ?>"
                   <?= in_array($p['id'], $_POST['permissions'] ?? $currentPerms) ? 'checked' : '' ?>>
            <span style="font-size:.84rem;"><?= e($p['name']) ?></span>
          </label>
          <?php endforeach; ?>

        </div>
        <?php endforeach; ?>

      </div>
    </div>
  </div>
</form>

<script>
function toggleAllPerms(btn) {
  const checkboxes = document.querySelectorAll('input[name="permissions[]"]');
  const allChecked = [...checkboxes].every(c => c.checked);
  checkboxes.forEach(c => c.checked = !allChecked);
  btn.textContent = allChecked ? 'Seleccionar todos' : 'Deseleccionar todos';
}
</script>

<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>