<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requireRole('patient');

$db     = getDB();
$userId = $_SESSION['user_id'];

$stmtP = $db->prepare('SELECT id FROM patients WHERE user_id=?');
$stmtP->execute([$userId]);
$patient = $stmtP->fetch();
$patientId = $patient['id'] ?? 0;

$stmtA = $db->prepare(
    "SELECT a.*, du.name AS doctor_name, s.name AS specialty
     FROM appointments a
     JOIN doctors d ON d.id = a.doctor_id
     JOIN users du ON du.id = d.user_id
     JOIN specialties s ON s.id = d.specialty_id
     WHERE a.patient_id = ?
     ORDER BY a.appointment_date DESC, a.appointment_time DESC"
);
$stmtA->execute([$patientId]);
$appointments = $stmtA->fetchAll();

$pageTitle   = 'Mis Citas';
$breadcrumbs = [['label'=>'Mis citas']];
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-list-check text-primary"></i> Mis Citas</h1>
    <p class="page-subtitle">Historial de todas tus citas médicas</p>
  </div>
  <div class="page-actions">
    <a href="create.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Reservar cita</a>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <?php if (empty($appointments)): ?>
    <div class="empty-state">
      <div class="empty-icon"><i class="fa-regular fa-calendar-xmark"></i></div>
      <h3>No tienes citas registradas</h3>
      <p>¡Reserva tu primera cita médica!</p>
      <a href="create.php" class="btn btn-primary mt-2">Reservar ahora</a>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table" id="mainTable">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Hora</th>
            <th>Doctor</th>
            <th>Especialidad</th>
            <th>Motivo</th>
            <th>Estado</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($appointments as $a): ?>
          <tr>
            <td><?= formatDate($a['appointment_date']) ?></td>
            <td><strong><?= substr($a['appointment_time'],0,5) ?></strong></td>
            <td>
              <div class="table-user-cell">
                <div class="table-avatar" style="background:#7C3AED;"><?= avatarInitials($a['doctor_name']) ?></div>
                <span class="table-user-name"><?= e($a['doctor_name']) ?></span>
              </div>
            </td>
            <td><span class="badge badge-purple"><?= e($a['specialty']) ?></span></td>
            <td style="max-width:160px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;"><?= e($a['reason'] ?? '—') ?></td>
            <td><?= appointmentBadge($a['status']) ?></td>
            <td>
              <?php if (in_array($a['status'],['pendiente','confirmada'])): ?>
              <form method="POST" action="cancel.php" onsubmit="return false;" style="display:inline;">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                <button type="button" class="btn btn-danger btn-sm"
                  onclick="confirmAction(this.closest('form'),'Cancelar cita','¿Deseas cancelar esta cita? Esta acción no se puede deshacer.','warning')">
                  <i class="fa-solid fa-ban"></i> Cancelar
                </button>
              </form>
              <?php else: ?>
              <span class="text-muted" style="font-size:.8rem;">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
