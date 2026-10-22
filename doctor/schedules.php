<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole('doctor');

$db     = getDB();
$userId = $_SESSION['user_id'];

$stmtD = $db->prepare('SELECT d.id FROM doctors d WHERE d.user_id=?');
$stmtD->execute([$userId]);
$doctor = $stmtD->fetch();
if (!$doctor) { setFlash('error','Perfil de doctor no encontrado.'); logout(); }
$doctorId = $doctor['id'];

$stmtS = $db->prepare('SELECT * FROM schedules WHERE doctor_id=? ORDER BY day_of_week, start_time');
$stmtS->execute([$doctorId]);
$schedules = $stmtS->fetchAll();

$days = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];

$pageTitle   = 'Mis Horarios';
$breadcrumbs = [['label'=>'Mis horarios']];
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-clock text-primary"></i> Mis Horarios</h1>
    <p class="page-subtitle">Consulta y gestiona tu disponibilidad semanal</p>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/admin/schedules/create.php?doctor_id=<?= $doctorId ?>" class="btn btn-primary">
      <i class="fa-solid fa-plus"></i> Agregar horario
    </a>
  </div>
</div>

<div style="display:grid;grid-template-columns:repeat(7,1fr);gap:.75rem;">
  <?php for ($dayNum = 0; $dayNum <= 6; $dayNum++):
    $daySchedules = array_filter($schedules, function($s) use ($dayNum) { return (int)$s['day_of_week'] === $dayNum; });
    $isWeekend    = in_array($dayNum, [0,6]);
  ?>
  <div class="card" style="<?= $isWeekend ? 'opacity:.6;' : '' ?>">
    <div class="card-header" style="padding:.6rem .75rem;background:<?= $isWeekend ? '#F3F4F6' : 'var(--primary-light)' ?>;">
      <span style="font-size:.78rem;font-weight:700;color:<?= $isWeekend ? 'var(--text-muted)':'var(--primary)' ?>;"><?= $days[$dayNum] ?></span>
    </div>
    <div style="padding:.75rem;min-height:100px;">
      <?php if (empty($daySchedules)): ?>
      <div style="color:var(--text-muted);font-size:.72rem;text-align:center;padding:.5rem 0;">Sin horario</div>
      <?php else: ?>
        <?php foreach ($daySchedules as $sch): ?>
        <div style="background:var(--primary-light);border-radius:7px;padding:.45rem .6rem;margin-bottom:.35rem;font-size:.73rem;">
          <div style="font-weight:700;color:var(--primary);"><?= substr($sch['start_time'],0,5) ?> – <?= substr($sch['end_time'],0,5) ?></div>
          <div style="color:var(--text-muted);margin-bottom:.3rem;">Turno: <?= $sch['slot_duration'] ?>min</div>
          <div style="display:flex;gap:.25rem;">
            <a href="<?= APP_URL ?>/admin/schedules/edit.php?id=<?= $sch['id'] ?>" class="btn btn-secondary btn-sm" style="padding:.1rem .35rem;font-size:.68rem;">Editar</a>
            <form method="POST" action="<?= APP_URL ?>/admin/schedules/delete.php" onsubmit="return false;" style="display:inline;">
              <?= csrfField() ?>
              <input type="hidden" name="id" value="<?= $sch['id'] ?>">
              <button type="button" class="btn btn-danger btn-sm" style="padding:.1rem .35rem;font-size:.68rem;"
                onclick="confirmDelete(this.closest('form'),'Se eliminará este bloque de horario.')">Del</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php endfor; ?>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
