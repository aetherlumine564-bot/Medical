<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requirePermission('roles.create');

$db     = getDB();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name  = trim($_POST['name']  ?? '');
    $slug  = trim($_POST['slug']  ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $color = $_POST['color'] ?? '#1A56DB';
    $perms = $_POST['permissions'] ?? [];

    $errors = validateRequired(['name' => 'Nombre', 'slug' => 'Slug'], $_POST);

    // Slug único
    if (empty($errors['slug'])) {
        $slug = strtolower(preg_replace('/[^a-z0-9_-]/','', $slug));
        $exists = $db->prepare('SELECT id FROM roles WHERE slug = ?');
        $exists->execute([$slug]);
        if ($exists->fetch()) $errors['slug'] = 'El slug ya está en uso.';
    }

    if (empty($errors)) {
        $db->prepare('INSERT INTO roles (name, slug, description, color) VALUES (?,?,?,?)')
           ->execute([$name, $slug, $desc, $color]);
        $roleId = $db->lastInsertId();

        // Asignar permisos seleccionados
        if (!empty($perms)) {
            $stmt = $db->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?,?)');
            foreach ($perms as $pid) {
                $stmt->execute([$roleId, (int)$pid]);
            }
        }

        setFlash('success', "Rol «{$name}» creado correctamente.");
        redirect('admin/roles/index.php');
    }
}

// Obtener todos los permisos agrupados por módulo
$allPerms = $db->query('SELECT * FROM permissions ORDER BY module, name')->fetchAll();
$grouped  = [];
foreach ($allPerms as $p) $grouped[$p['module']][] = $p;

$pageTitle   = 'Nuevo Rol';
$breadcrumbs = [['label'=>'Roles','url'=>'admin/roles/index.php'],['label'=>'Nuevo rol']];
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Nuevo Rol</h1>
    <p class="page-subtitle">Define nombre, slug y permisos</p>
  </div>
</div>

<form method="POST">
  <?= csrfField() ?>

  <div style="display:grid;grid-template-columns:1fr 360px;gap:1.25rem;">

    <!-- Información del rol -->
    <div class="card">
      <div class="card-header"><span class="card-title">Información del rol</span></div>
      <div class="card-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Nombre <span class="required">*</span></label>
            <input type="text" name="name" class="form-control <?= isset($errors['name'])? 'is-invalid':'' ?>"
                   value="<?= e($_POST['name']??'') ?>" placeholder="Ej: Recepcionista"
                   oninput="autoSlug(this)">
            <?php if(isset($errors['name'])): ?><span class="form-error"><?= e($errors['name']) ?></span><?php endif; ?>
          </div>

          <div class="form-group">
            <label>Slug <span class="required">*</span></label>
            <div class="input-icon-wrap">
              <i class="fa fa-code input-icon"></i>
              <input type="text" name="slug" id="slugField" class="form-control <?= isset($errors['slug'])? 'is-invalid':'' ?>"
                     value="<?= e($_POST['slug']??'') ?>" placeholder="recepcionista">
            </div>
            <?php if(isset($errors['slug'])): ?><span class="form-error"><?= e($errors['slug']) ?></span><?php endif; ?>
            <span class="form-hint">Solo letras minúsculas, números y guiones</span>
          </div>

          <div class="form-group full-width">
            <label>Descripción</label>
            <textarea name="description" class="form-control" rows="3" placeholder="Descripción del rol..."><?= e($_POST['description']??'') ?></textarea>
          </div>

          <div class="form-group">
            <label>Color identificador</label>
            <div style="display:flex;align-items:center;gap:.75rem;">
              <input type="color" name="color" value="<?= e($_POST['color']??'#1A56DB') ?>"
                     style="width:44px;height:38px;border:1px solid var(--border-color);border-radius:8px;cursor:pointer;">
              <span style="font-size:.82rem;color:var(--text-muted);">Color para identificar este rol en el sistema</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Acciones -->
    <div style="display:flex;flex-direction:column;gap:1rem;">
      <div class="card">
        <div class="card-header"><span class="card-title">Guardar</span></div>
        <div class="card-body">
          <button type="submit" class="btn btn-primary w-full" style="justify-content:center;">
            <i class="fa-solid fa-floppy-disk"></i> Crear rol
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
          <div style="font-size:.8rem;font-weight:700;color:var(--primary);margin-bottom:.75rem;display:flex;align-items:center;gap:.4rem;">
            <i class="fa-solid fa-layer-group"></i> <?= e($module) ?>
          </div>
          <?php foreach ($perms as $p): ?>
          <label class="form-check" style="margin-bottom:.45rem;cursor:pointer;">
            <input type="checkbox" name="permissions[]" value="<?= $p['id'] ?>"
                   <?= in_array($p['id'], $_POST['permissions']??[]) ? 'checked' : '' ?>>
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
function autoSlug(input) {
  const slug = input.value.toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
    .replace(/[^a-z0-9\s-]/g,'').replace(/\s+/g,'-');
  document.getElementById('slugField').value = slug;
}
function toggleAllPerms(btn) {
  const checkboxes = document.querySelectorAll('input[name="permissions[]"]');
  const allChecked = [...checkboxes].every(c => c.checked);
  checkboxes.forEach(c => c.checked = !allChecked);
  btn.textContent = allChecked ? 'Seleccionar todos' : 'Deseleccionar todos';
}
</script>

<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
