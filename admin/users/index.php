<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requirePermission('users.view');

$db    = getDB();
$users = $db->query(
    "SELECT u.*, r.name AS role_name, r.slug AS role_slug, r.color AS role_color
     FROM users u JOIN roles r ON r.id = u.role_id
     ORDER BY u.created_at DESC"
)->fetchAll();

$pageTitle   = 'Usuarios';
$breadcrumbs = [['label'=>'Usuarios']];
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-users text-primary"></i> Usuarios</h1>
    <p class="page-subtitle">Gestión de cuentas de usuario del sistema</p>
  </div>
  <?php if (hasPermission('users.create')): ?>
  <div class="page-actions">
    <a href="create.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nuevo usuario</a>
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
            <th>Usuario</th>
            <th>Teléfono</th>
            <th>Rol</th>
            <th>Estado</th>
            <th>Último acceso</th>
            <th>Registrado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td><?= $u['id'] ?></td>
            <td>
              <div class="table-user-cell">
                <div class="table-avatar" style="background:<?= e($u['role_color']) ?>;">
                  <?= avatarInitials($u['name']) ?>
                </div>
                <div>
                  <div class="table-user-name"><?= e($u['name']) ?></div>
                  <div class="table-user-email"><?= e($u['email']) ?></div>
                </div>
              </div>
            </td>
            <td><?= e($u['phone'] ?? '—') ?></td>
            <td>
              <span class="badge" style="background:<?= e($u['role_color']) ?>22;color:<?= e($u['role_color']) ?>;">
                <?= e($u['role_name']) ?>
              </span>
            </td>
            <td><?= statusBadge($u['status']) ?></td>
            <td><?= $u['last_login'] ? timeAgo($u['last_login']) : '<span class="text-muted">Nunca</span>' ?></td>
            <td><?= formatDate($u['created_at']) ?></td>
            <td>
              <div class="table-actions">
                <?php if (hasPermission('users.edit')): ?>
                <a href="edit.php?id=<?= $u['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="Editar">
                  <i class="fa-solid fa-pen"></i>
                </a>
                <?php endif; ?>
                <?php if (hasPermission('users.delete') && $u['id'] != $_SESSION['user_id']): ?>
                <form method="POST" action="delete.php" onsubmit="return false;">
                  <?= csrfField() ?>
                  <input type="hidden" name="id" value="<?= $u['id'] ?>">
                  <button type="button" class="btn btn-danger btn-sm btn-icon" title="Eliminar"
                    onclick="confirmDelete(this.closest('form'), 'Se eliminará el usuario «<?= e($u['name']) ?>»')">
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
