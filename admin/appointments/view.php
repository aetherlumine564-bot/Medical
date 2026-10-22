<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requirePermission('appointments.view');

$db  = getDB();
$id  = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare(
    "SELECT a.*,
            pu.name AS patient_name, pu.email AS patient_email, pu.phone AS patient_phone,
            du.name AS doctor_name,  du.email AS doctor_email,
            s.name  AS specialty,
            p.blood_type, p.allergies, p.dni,
            d.office, d.license_number, d.consultation_fee
     FROM appointments a
     JOIN patients p    ON p.id = a.patient_id
     JOIN users pu      ON pu.id = p.user_id
     JOIN doctors d     ON d.id = a.doctor_id
     JOIN users du      ON du.id = d.user_id
     JOIN specialties s ON s.id = d.specialty_id
     WHERE a.id = ?"
);
$stmt->execute([$id]);
$appt = $stmt->fetch();
if (!$appt) { setFlash('error','Cita no encontrada.'); redirect('admin/appointments/index.php'); }

$pageTitle   = 'Detalle de Cita';
$breadcrumbs = [['label'=>'Citas','url'=>'admin/appointments/index.php'],['label'=>'Detalle #'.$id]];
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Cita #<?= $id ?></h1>
    <p class="page-subtitle"><?= formatDate($appt['appointment_date'], 'd \d\e F, Y') ?> a las <?= substr($appt['appointment_time'],0,5) ?></p>
  </div>
  <div class="page-actions">
    <?php if (hasPermission('appointments.edit')): ?>
    <a href="edit.php?id=<?= $id ?>" class="btn btn-primary"><i class="fa-solid fa-pen"></i> Editar</a>
    <?php endif; ?>
    <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Volver</a>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">

  <!-- Paciente -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fa-solid fa-user-injured text-accent"></i> Paciente</span></div>
    <div class="card-body">
      <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem;">
        <div class="table-avatar" style="width:52px;height:52px;font-size:1rem;background:var(--accent);"><?= avatarInitials($appt['patient_name']) ?></div>
        <div>
          <div style="font-weight:700;font-size:1rem;"><?= e($appt['patient_name']) ?></div>
          <div style="color:var(--text-muted);font-size:.82rem;"><?= e($appt['patient_email']) ?></div>
        </div>
      </div>
      <div style="font-size:.84rem;">
        <?php $items = [['DNI',$appt['dni']??'—'],['Teléfono',$appt['patient_phone']??'—'],['Grupo sang.',$appt['blood_type']??'—']]; ?>
        <?php foreach ($items as [$l,$v]): ?>
        <div style="display:flex;justify-content:space-between;padding:.4rem 0;border-bottom:1px solid var(--border-color);">
          <span style="color:var(--text-muted);"><?= $l ?></span>
          <strong><?= e($v) ?></strong>
        </div>
        <?php endforeach; ?>
        <?php if ($appt['allergies']): ?>
        <div style="margin-top:.75rem;"><strong style="font-size:.75rem;text-transform:uppercase;color:var(--text-muted);">Alergias</strong>
        <p style="font-size:.82rem;margin-top:.25rem;"><?= nl2br(e($appt['allergies'])) ?></p></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Doctor -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fa-solid fa-user-doctor" style="color:#7C3AED;"></i> Doctor</span></div>
    <div class="card-body">
      <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem;">
        <div class="table-avatar" style="width:52px;height:52px;font-size:1rem;background:#7C3AED;"><?= avatarInitials($appt['doctor_name']) ?></div>
        <div>
          <div style="font-weight:700;font-size:1rem;"><?= e($appt['doctor_name']) ?></div>
          <span class="badge badge-purple"><?= e($appt['specialty']) ?></span>
        </div>
      </div>
      <div style="font-size:.84rem;">
        <?php $items = [['Licencia',$appt['license_number']],['Consultorio',$appt['office']??'—'],['Tarifa','S/ '.number_format($appt['consultation_fee'],2)]]; ?>
        <?php foreach ($items as [$l,$v]): ?>
        <div style="display:flex;justify-content:space-between;padding:.4rem 0;border-bottom:1px solid var(--border-color);">
          <span style="color:var(--text-muted);"><?= $l ?></span>
          <strong><?= e($v) ?></strong>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Detalle de cita -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fa-solid fa-calendar-check text-primary"></i> Detalle de la cita</span></div>
    <div class="card-body" style="font-size:.85rem;">
      <div style="display:flex;flex-wrap:wrap;gap:1.5rem;margin-bottom:1rem;">
        <div>
          <div style="font-size:.72rem;text-transform:uppercase;color:var(--text-muted);font-weight:600;">Fecha</div>
          <div style="font-size:1.1rem;font-weight:700;color:var(--text-dark);"><?= formatDate($appt['appointment_date'],'d/m/Y') ?></div>
        </div>
        <div>
          <div style="font-size:.72rem;text-transform:uppercase;color:var(--text-muted);font-weight:600;">Hora</div>
          <div style="font-size:1.1rem;font-weight:700;color:var(--text-dark);"><?= substr($appt['appointment_time'],0,5) ?></div>
        </div>
        <div>
          <div style="font-size:.72rem;text-transform:uppercase;color:var(--text-muted);font-weight:600;">Estado</div>
          <div style="margin-top:.2rem;"><?= appointmentBadge($appt['status']) ?></div>
        </div>
      </div>
      <?php if ($appt['reason']): ?>
      <div style="margin-bottom:.75rem;"><strong>Motivo:</strong> <?= e($appt['reason']) ?></div>
      <?php endif; ?>
      <?php if ($appt['notes']): ?>
      <div><strong>Notas:</strong><br><?= nl2br(e($appt['notes'])) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Cambiar estado rápido -->
  <div class="card">
    <div class="card-header"><span class="card-title">Cambiar estado</span></div>
    <div class="card-body">
      <form method="POST" action="edit.php?id=<?= $id ?>">
        <?= csrfField() ?>
        <input type="hidden" name="patient_id"       value="<?= $appt['patient_id'] ?>">
        <input type="hidden" name="doctor_id"        value="<?= $appt['doctor_id'] ?>">
        <input type="hidden" name="appointment_date" value="<?= $appt['appointment_date'] ?>">
        <input type="hidden" name="appointment_time" value="<?= $appt['appointment_time'] ?>">
        <input type="hidden" name="reason"           value="<?= e($appt['reason']??'') ?>">
        <input type="hidden" name="notes"            value="<?= e($appt['notes']??'') ?>">
        <div class="form-group mb-2">
          <label>Nuevo estado</label>
          <select name="status" class="form-control">
            <?php foreach(['pendiente','confirmada','en_curso','completada','cancelada','no_asistio'] as $s): ?>
            <option value="<?= $s ?>" <?= $appt['status']===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary w-full" style="justify-content:center;">
          <i class="fa-solid fa-check"></i> Actualizar estado
        </button>
      </form>
    </div>
  </div>

</div>

<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
