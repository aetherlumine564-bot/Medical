<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requirePermission('roles.view');

$db    = getDB();
$roles = $db->query(
    "SELECT r.*, COUNT(DISTINCT u.id) AS user_count,
            COUNT(DISTINCT rp.permission_id) AS perm_count
     FROM roles r
     LEFT JOIN users u  ON u.role_id = r.id
     LEFT JOIN role_permissions rp ON rp.role_id = r.id
     GROUP BY r.id
     ORDER BY r.id"
)->fetchAll();

$pageTitle   = 'Roles';
$breadcrumbs = [['label'=>'Roles']];
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-shield-halved text-primary"></i> Roles</h1>
    <p class="page-subtitle">Gestión de roles y permisos del sistema</p>
  </div>
  <?php if (hasPermission('roles.create')): ?>
  <div class="page-actions">
    <a href="create.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nuevo rol</a>
  </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table" id="mainTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Rol</th>
            <th>Slug</th>
            <th>Descripción</th>
            <th>Permisos</th>
            <th>Usuarios</th>
            <th>Creado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($roles as $role): ?>
          <tr>
            <td><?= $role['id'] ?></td>
            <td>
              <span style="display:inline-flex;align-items:center;gap:.5rem;">
                <span style="width:10px;height:10px;border-radius:50%;background:<?= e($role['color']) ?>;display:inline-block;"></span>
                <strong><?= e($role['name']) ?></strong>
              </span>
            </td>
            <td><code style="background:var(--primary-light);color:var(--primary);padding:.15rem .5rem;border-radius:5px;font-size:.8rem;"><?= e($role['slug']) ?></code></td>
            <td style="max-width:220px;white-space:normal;"><?= e($role['description'] ?? '—') ?></td>
            <td>
              <span class="badge badge-info"><?= $role['perm_count'] ?> permisos</span>
            </td>
            <td>
              <span class="badge badge-secondary"><?= $role['user_count'] ?> usuarios</span>
            </td>
            <td><?= formatDate($role['created_at']) ?></td>
            <td>
              <div class="table-actions">
                <a href="edit.php?id=<?= $role['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="Editar">
                  <i class="fa-solid fa-pen"></i>
                </a>
                <?php if (hasPermission('roles.delete') && !in_array($role['slug'], ['admin','doctor','patient'])): ?>
                <form method="POST" action="delete.php" onsubmit="return false;">
                  <?= csrfField() ?>
                  <input type="hidden" name="id" value="<?= $role['id'] ?>">
                  <button type="button" class="btn btn-danger btn-sm btn-icon" title="Eliminar"
                    onclick="confirmDelete(this.closest('form'), 'Se eliminará el rol «<?= e($role['name']) ?>»')">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
