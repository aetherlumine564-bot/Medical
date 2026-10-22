<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole('doctor');

$db     = getDB();
$userId = $_SESSION['user_id'];

$stmtD = $db->prepare('SELECT id FROM doctors WHERE user_id=?');
$stmtD->execute([$userId]);
$doctor = $stmtD->fetch();
if (!$doctor) { setFlash('error','Perfil de doctor no encontrado.'); logout(); }
$doctorId = $doctor['id'];

$currentStatus = $_GET['status'] ?? '';

$stmtA = $db->prepare(
    "SELECT a.*, pu.name AS patient_name, pu.phone AS patient_phone
     FROM appointments a
     JOIN patients p ON p.id = a.patient_id
     JOIN users pu ON pu.id = p.user_id
     WHERE a.doctor_id = ?
     ORDER BY a.appointment_date DESC, a.appointment_time DESC"
);
$stmtA->execute([$doctorId]);
$all = $stmtA->fetchAll();

if ($currentStatus) {
    $appointments = array_values(array_filter($all, function($a) use ($currentStatus) {
        return $a['status'] === $currentStatus;
    }));
} else {
    $appointments = $all;
}

$pageTitle   = 'Mis Citas';
$breadcrumbs = [['label'=>'Mis citas']];
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-calendar-check text-primary"></i> Mis Citas</h1>
    <p class="page-subtitle">Gestión de todas tus citas médicas</p>
  </div>
</div>

<!-- Filtros -->
<div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.25rem;">
  <?php foreach (['' => 'Todas','pendiente'=>'Pendientes','confirmada'=>'Confirmadas','en_curso'=>'En curso','completada'=>'Completadas'] as $val => $lbl): ?>
  <a href="?status=<?= $val ?>" class="btn <?= $currentStatus === $val ? 'btn-primary':'btn-secondary' ?> btn-sm"><?= $lbl ?></a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-body">
    <?php if (empty($appointments)): ?>
    <div class="empty-state">
      <div class="empty-icon"><i class="fa-regular fa-calendar"></i></div>
      <h3>Sin citas <?= $currentStatus ? 'con estado «' . $currentStatus . '»' : '' ?></h3>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table" id="mainTable">
        <thead><tr><th>Fecha</th><th>Hora</th><th>Paciente</th><th>Teléfono</th><th>Motivo</th><th>Estado</th><th>Acción</th></tr></thead>
        <tbody>
          <?php foreach ($appointments as $a): ?>
          <tr>
            <td><?= formatDate($a['appointment_date']) ?></td>
            <td><strong><?= substr($a['appointment_time'],0,5) ?></strong></td>
            <td>
              <div class="table-user-cell">
                <div class="table-avatar" style="background:var(--accent);"><?= avatarInitials($a['patient_name']) ?></div>
                <span class="table-user-name"><?= e($a['patient_name']) ?></span>
              </div>
            </td>
            <td><?= e($a['patient_phone'] ?? '—') ?></td>
            <td style="max-width:160px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;"><?= e($a['reason'] ?? '—') ?></td>
            <td><?= appointmentBadge($a['status']) ?></td>
            <td>
              <!-- Cambiar estado -->
              <form method="POST" action="<?= APP_URL ?>/admin/appointments/edit.php?id=<?= $a['id'] ?>" style="display:flex;gap:.35rem;align-items:center;">
                <?= csrfField() ?>
                <input type="hidden" name="patient_id"       value="<?= $a['patient_id'] ?>">
                <input type="hidden" name="doctor_id"        value="<?= $a['doctor_id'] ?>">
                <input type="hidden" name="appointment_date" value="<?= $a['appointment_date'] ?>">
                <input type="hidden" name="appointment_time" value="<?= $a['appointment_time'] ?>">
                <input type="hidden" name="reason"           value="<?= e($a['reason']??'') ?>">
                <input type="hidden" name="notes"            value="<?= e($a['notes']??'') ?>">
                <select name="status" class="form-control" style="padding:.25rem .5rem;font-size:.78rem;height:auto;">
                  <?php foreach(['pendiente','confirmada','en_curso','completada','no_asistio'] as $s): ?>
                  <option value="<?= $s ?>" <?= $a['status']===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm btn-icon" title="Guardar estado"><i class="fa-solid fa-check"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
