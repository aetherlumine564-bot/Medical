<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requirePermission('appointments.edit');

$db  = getDB();
$id  = (int)($_GET['id'] ?? 0);
$errors = [];

$stmt = $db->prepare(
    'SELECT a.*, p.id AS patient_id, d.id AS doctor_id FROM appointments a
     JOIN patients p ON p.id = a.patient_id
     JOIN doctors  d ON d.id = a.doctor_id
     WHERE a.id = ?'
);
$stmt->execute([$id]);
$appt = $stmt->fetch();
if (!$appt) { setFlash('error','Cita no encontrada.'); redirect('admin/appointments/index.php'); }

$patients = $db->query('SELECT p.id, u.name FROM patients p JOIN users u ON u.id = p.user_id ORDER BY u.name')->fetchAll();
$doctors  = $db->query('SELECT d.id, u.name, s.name AS specialty FROM doctors d JOIN users u ON u.id = d.user_id JOIN specialties s ON s.id = d.specialty_id WHERE d.status=1 ORDER BY u.name')->fetchAll();
$statuses = ['pendiente','confirmada','en_curso','completada','cancelada','no_asistio'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $d      = $_POST;
    $errors = validateRequired(['patient_id'=>'Paciente','doctor_id'=>'Doctor','appointment_date'=>'Fecha','appointment_time'=>'Hora'], $d);

    if (empty($errors)) {
        $db->prepare(
            'UPDATE appointments SET patient_id=?, doctor_id=?, appointment_date=?, appointment_time=?,
             status=?, reason=?, notes=?, updated_at=NOW() WHERE id=?'
        )->execute([
            (int)$d['patient_id'], (int)$d['doctor_id'],
            $d['appointment_date'], $d['appointment_time'],
            $d['status'], trim($d['reason']??''), trim($d['notes']??''), $id,
        ]);
        setFlash('success','Cita actualizada correctamente.');
        redirect('admin/appointments/index.php');
    }
}

$f = $_POST ?: $appt;
$pageTitle   = 'Editar Cita';
$breadcrumbs = [['label'=>'Citas','url'=>'admin/appointments/index.php'],['label'=>'Editar cita']];
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title">Editar Cita #<?= $id ?></h1>
</div>

<div style="max-width:800px;">
<form method="POST">
  <?= csrfField() ?>
  <div class="card">
    <div class="card-header"><span class="card-title">Datos de la cita</span></div>
    <div class="card-body">
      <div class="form-grid form-grid-2">
        <div class="form-group">
          <label>Paciente <span class="required">*</span></label>
          <select name="patient_id" class="form-control">
            <?php foreach ($patients as $p): ?><option value="<?= $p['id'] ?>" <?= ($f['patient_id']==$p['id'])?'selected':'' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Doctor <span class="required">*</span></label>
          <select name="doctor_id" class="form-control">
            <?php foreach ($doctors as $doc): ?><option value="<?= $doc['id'] ?>" <?= ($f['doctor_id']==$doc['id'])?'selected':'' ?>><?= e($doc['name']) ?> — <?= e($doc['specialty']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Fecha <span class="required">*</span></label>
          <input type="date" name="appointment_date" class="form-control" value="<?= e($f['appointment_date']) ?>">
        </div>
        <div class="form-group">
          <label>Hora <span class="required">*</span></label>
          <input type="time" name="appointment_time" class="form-control" value="<?= substr($f['appointment_time'],0,5) ?>">
        </div>
        <div class="form-group">
          <label>Estado</label>
          <select name="status" class="form-control">
            <?php foreach ($statuses as $s): ?>
            <option value="<?= $s ?>" <?= ($f['status']===$s)?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Motivo</label>
          <input type="text" name="reason" class="form-control" value="<?= e($f['reason']??'') ?>">
        </div>
        <div class="form-group full-width">
          <label>Notas</label>
          <textarea name="notes" class="form-control" rows="3"><?= e($f['notes']??'') ?></textarea>
        </div>
      </div>
    </div>
  </div>
  <div style="display:flex;gap:.75rem;margin-top:1rem;">
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
    <a href="index.php" class="btn btn-secondary">Cancelar</a>
  </div>
</form>
</div>

<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
