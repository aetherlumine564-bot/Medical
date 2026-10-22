<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requirePermission('schedules.view');

$db      = getDB();
$doctors = $db->query(
    'SELECT d.id, u.name, s.name AS specialty FROM doctors d JOIN users u ON u.id = d.user_id JOIN specialties s ON s.id = d.specialty_id WHERE d.status=1 ORDER BY u.name'
)->fetchAll();

$selectedDoctor = (int)($_GET['doctor_id'] ?? ($doctors[0]['id'] ?? 0));
$schedules = [];
if ($selectedDoctor) {
    $stmt = $db->prepare('SELECT * FROM schedules WHERE doctor_id=? ORDER BY day_of_week, start_time');
    $stmt->execute([$selectedDoctor]);
    $schedules = $stmt->fetchAll();
}

$days = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];

$pageTitle   = 'Horarios';
$breadcrumbs = [['label'=>'Horarios']];
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-clock text-primary"></i> Horarios de Doctores</h1>
  </div>
  <div class="page-actions">
    <a href="create.php?doctor_id=<?= $selectedDoctor ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Agregar horario</a>
  </div>
</div>

<!-- Selector de doctor -->
<div class="card" style="margin-bottom:1.25rem;">
  <div class="card-body" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
    <label style="font-weight:600;font-size:.88rem;">Doctor:</label>
    <form method="GET" style="display:flex;gap:.75rem;align-items:center;">
      <select name="doctor_id" class="form-control" style="width:280px;" onchange="this.form.submit()">
        <?php foreach ($doctors as $d): ?>
        <option value="<?= $d['id'] ?>" <?= $selectedDoctor == $d['id']?'selected':'' ?>>
          <?= e($d['name']) ?> — <?= e($d['specialty']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
</div>

<!-- Grilla de horarios por día -->
<div style="display:grid;grid-template-columns:repeat(7,1fr);gap:.75rem;">
  <?php for ($dayNum = 0; $dayNum <= 6; $dayNum++):
    $daySchedules = array_filter($schedules, function($s) use ($dayNum) { return (int)$s['day_of_week'] === $dayNum; });
    $isWeekend    = in_array($dayNum, [0,6]);
  ?>
  <div class="card" style="<?= $isWeekend ? 'opacity:.6;' : '' ?>">
    <div class="card-header" style="padding:.75rem;background:<?= $isWeekend ? '#F3F4F6' : 'var(--primary-light)' ?>;">
      <span style="font-size:.8rem;font-weight:700;color:<?= $isWeekend ? 'var(--text-muted)' : 'var(--primary)' ?>;">
        <?= $days[$dayNum] ?>
      </span>
    </div>
    <div style="padding:.75rem;min-height:120px;">
      <?php if (empty($daySchedules)): ?>
      <div style="color:var(--text-muted);font-size:.75rem;text-align:center;padding:.75rem 0;">Sin horario</div>
      <?php else: ?>
        <?php foreach ($daySchedules as $sch): ?>
        <div style="background:var(--primary-light);border-radius:7px;padding:.5rem .6rem;margin-bottom:.4rem;font-size:.75rem;">
          <div style="font-weight:700;color:var(--primary);"><?= substr($sch['start_time'],0,5) ?> – <?= substr($sch['end_time'],0,5) ?></div>
          <div style="color:var(--text-muted);">Turno: <?= $sch['slot_duration'] ?>min</div>
          <div style="display:flex;gap:.3rem;margin-top:.3rem;">
            <a href="edit.php?id=<?= $sch['id'] ?>" class="btn btn-secondary btn-sm" style="padding:.15rem .4rem;font-size:.7rem;">Editar</a>
            <form method="POST" action="delete.php" onsubmit="return false;" style="display:inline;">
              <?= csrfField() ?>
              <input type="hidden" name="id" value="<?= $sch['id'] ?>">
              <button type="button" class="btn btn-danger btn-sm" style="padding:.15rem .4rem;font-size:.7rem;"
                onclick="confirmDelete(this.closest('form'))">Del</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php endfor; ?>
</div>

<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
