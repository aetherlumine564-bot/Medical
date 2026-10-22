<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requirePermission('patients.view');

$db = getDB();
$patients = $db->query(
    "SELECT p.*, u.name, u.email, u.phone, u.status,
            TIMESTAMPDIFF(YEAR, p.birth_date, CURDATE()) AS age
     FROM patients p
     JOIN users u ON u.id = p.user_id
     ORDER BY p.created_at DESC"
)->fetchAll();

$pageTitle   = 'Pacientes';
$breadcrumbs = [['label'=>'Pacientes']];
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-user-injured text-accent"></i> Pacientes</h1>
    <p class="page-subtitle">Registro y gestión de pacientes</p>
  </div>
  <?php if (hasPermission('patients.create')): ?>
  <div class="page-actions">
    <a href="create.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nuevo paciente</a>
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
            <th>Paciente</th>
            <th>DNI</th>
            <th>Edad</th>
            <th>Género</th>
            <th>Grupo Sanguíneo</th>
            <th>Teléfono</th>
            <th>Estado</th>
            <th>Registrado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($patients as $p): ?>
          <tr>
            <td><?= $p['id'] ?></td>
            <td>
              <div class="table-user-cell">
                <div class="table-avatar" style="background:var(--accent);"><?= avatarInitials($p['name']) ?></div>
                <div>
                  <div class="table-user-name"><?= e($p['name']) ?></div>
                  <div class="table-user-email"><?= e($p['email']) ?></div>
                </div>
              </div>
            </td>
            <td><?= e($p['dni'] ?? '—') ?></td>
            <td><?= $p['age'] ? $p['age'] . ' años' : '—' ?></td>
            <td><?= $p['gender'] === 'M' ? '<span class="badge badge-info">Masculino</span>' : ($p['gender'] === 'F' ? '<span class="badge badge-purple">Femenino</span>' : '—') ?></td>
            <td><?= $p['blood_type'] ? '<span class="badge badge-danger">'.$p['blood_type'].'</span>' : '—' ?></td>
            <td><?= e($p['phone'] ?? '—') ?></td>
            <td><?= statusBadge($p['status']) ?></td>
            <td><?= formatDate($p['created_at']) ?></td>
            <td>
              <div class="table-actions">
                <a href="view.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="Ver detalle">
                  <i class="fa-solid fa-eye"></i>
                </a>
                <?php if (hasPermission('patients.edit')): ?>
                <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="Editar">
                  <i class="fa-solid fa-pen"></i>
                </a>
                <?php endif; ?>
                <?php if (hasPermission('patients.delete')): ?>
                <form method="POST" action="delete.php" onsubmit="return false;">
                  <?= csrfField() ?>
                  <input type="hidden" name="id" value="<?= $p['id'] ?>">
                  <button type="button" class="btn btn-danger btn-sm btn-icon" title="Eliminar"
                    onclick="confirmDelete(this.closest('form'), 'Se eliminará al paciente «<?= e($p['name']) ?>» y toda su información.')">
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
