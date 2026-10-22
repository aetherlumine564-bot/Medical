<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requireRole('patient');

$db     = getDB();
$userId = $_SESSION['user_id'];
$errors = [];

// Obtener ID del paciente
$stmtP = $db->prepare('SELECT id FROM patients WHERE user_id=?');
$stmtP->execute([$userId]);
$patient = $stmtP->fetch();
if (!$patient) { setFlash('error','No tienes un perfil de paciente.'); redirect('patient/dashboard.php'); }
$patientId = $patient['id'];

$doctors = $db->query(
    'SELECT d.id, u.name, s.name AS specialty FROM doctors d
     JOIN users u ON u.id = d.user_id
     JOIN specialties s ON s.id = d.specialty_id
     WHERE d.status=1 ORDER BY u.name'
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $d      = $_POST;
    $errors = validateRequired([
        'doctor_id'        => 'Doctor',
        'appointment_date' => 'Fecha',
        'appointment_time' => 'Hora',
    ], $d);

    if (empty($errors)) {
        $conflict = $db->prepare(
            "SELECT id FROM appointments WHERE doctor_id=? AND appointment_date=? AND appointment_time=? AND status NOT IN ('cancelada')"
        );
        $conflict->execute([(int)$d['doctor_id'], $d['appointment_date'], $d['appointment_time']]);
        if ($conflict->fetch()) {
            $errors['appointment_time'] = 'El doctor ya tiene una cita en ese horario.';
        }
    }

    if (empty($errors)) {
        $stmtApt = $db->prepare(
            'INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status, reason, created_by)
             VALUES (?,?,?,?,?,?,?)'
        );
        $stmtApt->execute([
            $patientId,
            (int)$d['doctor_id'],
            $d['appointment_date'],
            $d['appointment_time'],
            'pendiente',
            trim($d['reason'] ?? ''),
            $userId,
        ]);
        $newAppointmentId = (int)$db->lastInsertId();

        setFlash('success','¡Cita reservada! Te esperamos el ' . date('d/m/Y', strtotime($d['appointment_date'])) . ' a las ' . substr($d['appointment_time'],0,5) . '.');
        redirect('patient/appointments/index.php');
    }
}

$pageTitle   = 'Reservar Cita';
$breadcrumbs = [['label'=>'Mis citas','url'=>'patient/appointments/index.php'],['label'=>'Reservar']];
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title"><i class="fa-solid fa-calendar-plus text-primary"></i> Reservar Cita</h1>
</div>

<div style="max-width:680px;">
<form method="POST">
  <?= csrfField() ?>
  <div class="card">
    <div class="card-header"><span class="card-title">Datos de la cita</span></div>
    <div class="card-body">
      <div class="form-grid form-grid-2">

        <div class="form-group full-width">
          <label>Doctor <span class="required">*</span></label>
          <select name="doctor_id" id="doctorSelect" class="form-control <?= isset($errors['doctor_id'])?'is-invalid':'' ?>"
                  onchange="loadSlots()">
            <option value="">-- Selecciona un doctor --</option>
            <?php foreach ($doctors as $doc): ?>
            <option value="<?= $doc['id'] ?>" <?= ($_POST['doctor_id']??'') == $doc['id']?'selected':'' ?>>
              <?= e($doc['name']) ?> — <?= e($doc['specialty']) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <?php if(isset($errors['doctor_id'])): ?><span class="form-error"><?= e($errors['doctor_id']) ?></span><?php endif; ?>
        </div>

        <div class="form-group">
          <label>Fecha <span class="required">*</span></label>
          <input type="date" name="appointment_date" id="dateInput"
                 class="form-control <?= isset($errors['appointment_date'])?'is-invalid':'' ?>"
                 value="<?= e($_POST['appointment_date'] ?? date('Y-m-d')) ?>"
                 min="<?= date('Y-m-d') ?>"
                 onchange="loadSlots()">
          <?php if(isset($errors['appointment_date'])): ?><span class="form-error"><?= e($errors['appointment_date']) ?></span><?php endif; ?>
        </div>

        <div class="form-group">
          <label>Hora disponible <span class="required">*</span></label>
          <select name="appointment_time" id="timeSelect" class="form-control <?= isset($errors['appointment_time'])?'is-invalid':'' ?>">
            <option value="">-- Selecciona doctor y fecha primero --</option>
            <?php if (!empty($_POST['appointment_time'])): ?>
            <option value="<?= e($_POST['appointment_time']) ?>" selected><?= e($_POST['appointment_time']) ?></option>
            <?php endif; ?>
          </select>
          <?php if(isset($errors['appointment_time'])): ?><span class="form-error"><?= e($errors['appointment_time']) ?></span><?php endif; ?>
        </div>

        <div class="form-group full-width">
          <label>Motivo de consulta</label>
          <input type="text" name="reason" class="form-control"
                 value="<?= e($_POST['reason']??'') ?>"
                 placeholder="Ej: Control general, dolor de cabeza, revisión...">
        </div>

      </div>
    </div>
  </div>
  <div style="display:flex;gap:.75rem;margin-top:1rem;">
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-calendar-check"></i> Confirmar reserva</button>
    <a href="index.php" class="btn btn-secondary">Cancelar</a>
  </div>
</form>
</div>

<script>
async function loadSlots() {
  const doctorId   = document.getElementById('doctorSelect').value;
  const date       = document.getElementById('dateInput').value;
  const timeSelect = document.getElementById('timeSelect');
  if (!doctorId || !date) {
    timeSelect.innerHTML = '<option value="">-- Selecciona doctor y fecha primero --</option>';
    return;
  }
  timeSelect.innerHTML = '<option value="">Cargando horarios...</option>';
  try {
    const resp  = await fetch(`<?= APP_URL ?>/admin/appointments/get_slots.php?doctor_id=${doctorId}&date=${date}`);
    const slots = await resp.json();
    if (!slots.length) {
      timeSelect.innerHTML = '<option value="">Sin horarios disponibles para este día</option>';
      return;
    }
    timeSelect.innerHTML = '<option value="">-- Selecciona una hora --</option>';
    slots.forEach(s => {
      const opt = document.createElement('option');
      opt.value = s.time;
      opt.textContent = s.time + (s.available ? '' : ' ✗ Ocupado');
      opt.disabled = !s.available;
      timeSelect.appendChild(opt);
    });
  } catch(e) {
    timeSelect.innerHTML = '<option value="">Error al cargar horarios</option>';
  }
}
</script>

<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
