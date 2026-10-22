<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requirePermission('appointments.create');

$db = getDB();
$errors = [];

$patients = $db->query(
    'SELECT p.id, u.name FROM patients p JOIN users u ON u.id = p.user_id ORDER BY u.name'
)->fetchAll();
$doctors = $db->query(
    'SELECT d.id, u.name, s.name AS specialty FROM doctors d JOIN users u ON u.id = d.user_id JOIN specialties s ON s.id = d.specialty_id WHERE d.status=1 ORDER BY u.name'
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $d      = $_POST;
    $errors = validateRequired([
        'patient_id'       => 'Paciente',
        'doctor_id'        => 'Doctor',
        'appointment_date' => 'Fecha',
        'appointment_time' => 'Hora',
    ], $d);

    // Validar que no haya conflicto de horario
    if (empty($errors)) {
        $conflict = $db->prepare(
            "SELECT id FROM appointments
             WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ?
             AND status NOT IN ('cancelada')"
        );
        $conflict->execute([(int)$d['doctor_id'], $d['appointment_date'], $d['appointment_time']]);
        if ($conflict->fetch()) {
            $errors['appointment_time'] = 'El doctor ya tiene una cita en ese horario.';
        }
    }

    if (empty($errors)) {
        $stmt = $db->prepare(
            'INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status, reason, notes, created_by)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            (int)$d['patient_id'],
            (int)$d['doctor_id'],
            $d['appointment_date'],
            $d['appointment_time'],
            $d['status'] ?? 'pendiente',
            trim($d['reason'] ?? ''),
            trim($d['notes'] ?? ''),
            $_SESSION['user_id'],
        ]);
        $newAppointmentId = (int)$db->lastInsertId();

        setFlash('success', 'Cita registrada correctamente.');
        redirect('admin/appointments/index.php');
    }
}

// Preselect patient from URL
$prePatient = (int)($_GET['patient_id'] ?? 0);

$pageTitle   = 'Nueva Cita';
$breadcrumbs = [['label'=>'Citas','url'=>'admin/appointments/index.php'],['label'=>'Nueva cita']];
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title">Nueva Cita</h1>
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
          <select name="patient_id" class="form-control <?= isset($errors['patient_id'])?'is-invalid':'' ?>">
            <option value="">-- Selecciona un paciente --</option>
            <?php foreach ($patients as $p): ?>
            <option value="<?= $p['id'] ?>" <?= (($_POST['patient_id']??$prePatient) == $p['id'])?'selected':'' ?>>
              <?= e($p['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <?php if(isset($errors['patient_id'])): ?><span class="form-error"><?= e($errors['patient_id']) ?></span><?php endif; ?>
        </div>

        <div class="form-group">
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
          <label>Hora <span class="required">*</span></label>
          <select name="appointment_time" id="timeSelect" class="form-control <?= isset($errors['appointment_time'])?'is-invalid':'' ?>">
            <option value="">-- Primero selecciona doctor y fecha --</option>
            <?php if (!empty($_POST['appointment_time'])): ?>
            <option value="<?= e($_POST['appointment_time']) ?>" selected><?= e($_POST['appointment_time']) ?></option>
            <?php endif; ?>
          </select>
          <?php if(isset($errors['appointment_time'])): ?><span class="form-error"><?= e($errors['appointment_time']) ?></span><?php endif; ?>
        </div>

        <div class="form-group">
          <label>Estado</label>
          <select name="status" class="form-control">
            <?php foreach(['pendiente'=>'Pendiente','confirmada'=>'Confirmada'] as $val => $label): ?>
            <option value="<?= $val ?>" <?= ($_POST['status']??'pendiente')===$val?'selected':'' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Motivo de consulta</label>
          <input type="text" name="reason" class="form-control"
                 value="<?= e($_POST['reason']??'') ?>" placeholder="Ej: Control mensual, dolor de cabeza...">
        </div>

        <div class="form-group full-width">
          <label>Notas adicionales</label>
          <textarea name="notes" class="form-control" rows="3"><?= e($_POST['notes']??'') ?></textarea>
        </div>
      </div>
    </div>
  </div>

  <div style="display:flex;gap:.75rem;margin-top:1rem;">
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-calendar-plus"></i> Guardar cita</button>
    <a href="index.php" class="btn btn-secondary">Cancelar</a>
  </div>
</form>
</div>

<script>
async function loadSlots() {
  const doctorId = document.getElementById('doctorSelect').value;
  const date     = document.getElementById('dateInput').value;
  const timeSelect = document.getElementById('timeSelect');

  if (!doctorId || !date) {
    timeSelect.innerHTML = '<option value="">-- Primero selecciona doctor y fecha --</option>';
    return;
  }

  timeSelect.innerHTML = '<option value="">Cargando horarios...</option>';

  try {
    const resp = await fetch(`<?= APP_URL ?>/admin/appointments/get_slots.php?doctor_id=${doctorId}&date=${date}`);
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
