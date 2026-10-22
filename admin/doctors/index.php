<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requirePermission('doctors.view');

$db = getDB();
$doctors = $db->query(
    "SELECT d.*, u.name, u.email, u.phone, u.status, s.name AS specialty
     FROM doctors d
     JOIN users u ON u.id = d.user_id
     JOIN specialties s ON s.id = d.specialty_id
     ORDER BY d.created_at DESC"
)->fetchAll();

$pageTitle   = 'Doctores';
$breadcrumbs = [['label'=>'Doctores']];
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-user-doctor" style="color:#7C3AED;"></i> Doctores</h1>
    <p class="page-subtitle">Gestión del cuerpo médico</p>
  </div>
  <?php if (hasPermission('doctors.create')): ?>
  <div class="page-actions">
    <a href="create.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nuevo doctor</a>
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
            <th>Doctor</th>
            <th>Especialidad</th>
            <th>Licencia</th>
            <th>Consultorio</th>
            <th>Tarifa</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($doctors as $d): ?>
          <tr>
            <td><?= $d['id'] ?></td>
            <td>
              <div class="table-user-cell">
                <div class="table-avatar" style="background:#7C3AED;"><?= avatarInitials($d['name']) ?></div>
                <div>
                  <div class="table-user-name"><?= e($d['name']) ?></div>
                  <div class="table-user-email"><?= e($d['email']) ?></div>
                </div>
              </div>
            </td>
            <td><span class="badge badge-purple"><?= e($d['specialty']) ?></span></td>
            <td><code style="font-size:.8rem;"><?= e($d['license_number']) ?></code></td>
            <td><?= e($d['office'] ?? '—') ?></td>
            <td style="font-weight:600;">S/ <?= number_format($d['consultation_fee'],2) ?></td>
            <td><?= $d['status'] ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-danger">Inactivo</span>' ?></td>
            <td>
              <div class="table-actions">
                <?php if (hasPermission('doctors.edit')): ?>
                <a href="edit.php?id=<?= $d['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="Editar">
                  <i class="fa-solid fa-pen"></i>
                </a>
                <?php endif; ?>
                <!-- Toggle activo con confirmación -->
                <form method="POST" action="toggle.php" onsubmit="return false;" style="display:inline;">
                  <?= csrfField() ?>
                  <input type="hidden" name="id" value="<?= $d['id'] ?>">
                  <input type="hidden" name="status" value="<?= $d['status'] ? 0 : 1 ?>">
                  <button type="button" class="btn btn-<?= $d['status'] ? 'warning' : 'success' ?> btn-sm btn-icon"
                    title="<?= $d['status'] ? 'Deshabilitar' : 'Habilitar' ?>"
                    onclick="confirmAction(this.closest('form'), '<?= $d['status'] ? 'Deshabilitar doctor' : 'Habilitar doctor' ?>', '<?= $d['status'] ? '¿Deseas deshabilitar a «'  . addslashes(e($d['name'])) . '»?' : '¿Deseas habilitar a «' . addslashes(e($d['name'])) . '»?' ?>', '<?= $d['status'] ? 'warning' : 'question' ?>')">
                    <i class="fa-solid fa-<?= $d['status'] ? 'ban' : 'check' ?>"></i>
                  </button>
                </form>
                <?php if (hasPermission('doctors.delete')): ?>
                <form method="POST" action="delete.php" onsubmit="return false;">
                  <?= csrfField() ?>
                  <input type="hidden" name="id" value="<?= $d['id'] ?>">
                  <button type="button" class="btn btn-danger btn-sm btn-icon" title="Eliminar"
                    onclick="confirmDelete(this.closest('form'), 'Se eliminará al doctor «<?= e($d['name']) ?>»')">
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
