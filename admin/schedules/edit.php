<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requirePermission('schedules.manage');

$db  = getDB();
$id  = (int)($_GET['id'] ?? 0);
if (!$id) { setFlash('error','Horario no encontrado.'); redirect('admin/schedules/index.php'); }

$stmt = $db->prepare('SELECT * FROM schedules WHERE id=?');
$stmt->execute([$id]);
$sch = $stmt->fetch();
if (!$sch) { setFlash('error','Horario no encontrado.'); redirect('admin/schedules/index.php'); }

$doctors = $db->query('SELECT d.id, u.name FROM doctors d JOIN users u ON u.id = d.user_id WHERE d.status=1 ORDER BY u.name')->fetchAll();
$days    = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $d      = $_POST;
    $errors = validateRequired(['doctor_id'=>'Doctor','day_of_week'=>'Día','start_time'=>'Hora inicio','end_time'=>'Hora fin'], $d);
    if (empty($errors)) {
        $db->prepare(
            'UPDATE schedules SET doctor_id=?, day_of_week=?, start_time=?, end_time=?, slot_duration=? WHERE id=?'
        )->execute([(int)$d['doctor_id'],(int)$d['day_of_week'],$d['start_time'],$d['end_time'],(int)($d['slot_duration']??30),$id]);
        setFlash('success','Horario actualizado.');
        redirect('admin/schedules/index.php?doctor_id='.$d['doctor_id']);
    }
}

$pageTitle   = 'Editar Horario';
$breadcrumbs = [['label'=>'Horarios','url'=>'admin/schedules/index.php'],['label'=>'Editar horario']];
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="page-header"><h1 class="page-title">Editar Horario</h1></div>
<div style="max-width:500px;">
<form method="POST">
  <?= csrfField() ?>
  <div class="card"><div class="card-body">
    <div class="form-grid">
      <div class="form-group">
        <label>Doctor <span class="required">*</span></label>
        <select name="doctor_id" class="form-control">
          <?php foreach ($doctors as $doc): ?>
          <option value="<?= $doc['id'] ?>" <?= ($sch['doctor_id']==$doc['id'])?'selected':'' ?>><?= e($doc['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Día <span class="required">*</span></label>
        <select name="day_of_week" class="form-control">
          <?php foreach ($days as $i=>$day): ?>
          <option value="<?= $i ?>" <?= ($sch['day_of_week']==$i)?'selected':'' ?>><?= $day ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Hora inicio <span class="required">*</span></label>
        <input type="time" name="start_time" class="form-control" value="<?= substr($sch['start_time'],0,5) ?>">
      </div>
      <div class="form-group">
        <label>Hora fin <span class="required">*</span></label>
        <input type="time" name="end_time" class="form-control" value="<?= substr($sch['end_time'],0,5) ?>">
      </div>
      <div class="form-group">
        <label>Duración del turno (minutos)</label>
        <select name="slot_duration" class="form-control">
          <?php foreach ([15,20,30,45,60] as $min): ?>
          <option value="<?= $min ?>" <?= ($sch['slot_duration']==$min)?'selected':'' ?>><?= $min ?> min</option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div></div>
  <div style="display:flex;gap:.75rem;margin-top:1rem;">
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar cambios</button>
    <a href="index.php?doctor_id=<?= $sch['doctor_id'] ?>" class="btn btn-secondary">Cancelar</a>
  </div>
</form>
</div>
<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
