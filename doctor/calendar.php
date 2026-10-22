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

$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

$stmtA = $db->prepare(
    "SELECT a.*, pu.name AS patient_name
     FROM appointments a
     JOIN patients p ON p.id = a.patient_id
     JOIN users pu ON pu.id = p.user_id
     WHERE a.doctor_id=?
       AND MONTH(a.appointment_date)=? AND YEAR(a.appointment_date)=?
       AND a.status NOT IN ('cancelada')
     ORDER BY a.appointment_date, a.appointment_time"
);
$stmtA->execute([$doctorId, $month, $year]);
$appts = $stmtA->fetchAll();

// Agrupar por día
$byDay = [];
foreach ($appts as $a) {
    $day = (int)date('j', strtotime($a['appointment_date']));
    $byDay[$day][] = $a;
}

$firstDay   = (int)date('w', mktime(0,0,0,$month,1,$year));
$daysInMonth= (int)date('t', mktime(0,0,0,$month,1,$year));
$monthNames = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$dayNames   = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
$prevM = $month - 1; $prevY = $year; if ($prevM < 1) { $prevM = 12; $prevY--; }
$nextM = $month + 1; $nextY = $year; if ($nextM > 12) { $nextM = 1;  $nextY++; }

$pageTitle   = 'Calendario';
$breadcrumbs = [['label'=>'Calendario']];
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-calendar-days text-primary"></i> Mi Calendario</h1>
    <p class="page-subtitle">Vista mensual de mis citas</p>
  </div>
  <div class="page-actions">
    <a href="?month=<?= $prevM ?>&year=<?= $prevY ?>" class="btn btn-secondary"><i class="fa-solid fa-chevron-left"></i></a>
    <span style="padding:.5rem 1rem;font-weight:700;font-size:.95rem;"><?= $monthNames[$month] ?> <?= $year ?></span>
    <a href="?month=<?= $nextM ?>&year=<?= $nextY ?>" class="btn btn-secondary"><i class="fa-solid fa-chevron-right"></i></a>
    <a href="?month=<?= date('n') ?>&year=<?= date('Y') ?>" class="btn btn-primary btn-sm">Hoy</a>
  </div>
</div>

<div class="card">
  <div class="card-body" style="padding:0;">
    <!-- Encabezado días -->
    <div style="display:grid;grid-template-columns:repeat(7,1fr);border-bottom:1px solid var(--border-color);">
      <?php foreach ($dayNames as $d): ?>
      <div style="padding:.6rem;text-align:center;font-size:.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;"><?= $d ?></div>
      <?php endforeach; ?>
    </div>
    <!-- Grilla días -->
    <div style="display:grid;grid-template-columns:repeat(7,1fr);">
      <?php
      $todayStr = date('Y-m-d');
      $cell = 0;
      // Celdas vacías antes del día 1
      for ($i=0; $i < $firstDay; $i++, $cell++):
      ?>
      <div style="min-height:80px;border-right:1px solid var(--border-color);border-bottom:1px solid var(--border-color);background:#FAFAFA;"></div>
      <?php endfor; ?>
      <?php for ($day=1; $day<=$daysInMonth; $day++, $cell++):
        $dateStr  = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $isToday  = ($dateStr === $todayStr);
        $hasAppts = !empty($byDay[$day]);
        $border   = ($cell % 7 === 6) ? '' : 'border-right:1px solid var(--border-color);';
      ?>
      <div style="min-height:80px;padding:.5rem;<?= $border ?>border-bottom:1px solid var(--border-color);<?= $isToday ? 'background:var(--primary-light);' : '' ?>">
        <div style="font-size:.8rem;font-weight:<?= $isToday?'800':'600' ?>;color:<?= $isToday?'var(--primary)':'var(--text-dark)' ?>;margin-bottom:.3rem;">
          <?= $day ?>
        </div>
        <?php if ($hasAppts): ?>
          <?php foreach (array_slice($byDay[$day], 0, 3) as $a): ?>
          <div style="background:var(--primary);color:#fff;border-radius:4px;padding:.1rem .35rem;font-size:.68rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:.15rem;">
            <?= substr($a['appointment_time'],0,5) ?> <?= e($a['patient_name']) ?>
          </div>
          <?php endforeach; ?>
          <?php if (count($byDay[$day]) > 3): ?>
          <div style="font-size:.65rem;color:var(--primary);font-weight:600;">+<?= count($byDay[$day]) - 3 ?> más</div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <?php endfor; ?>
      <?php // Celdas vacías al final
      $remaining = (7 - ($cell % 7)) % 7;
      for ($i=0; $i < $remaining; $i++): ?>
      <div style="min-height:80px;border-right:1px solid var(--border-color);border-bottom:1px solid var(--border-color);background:#FAFAFA;"></div>
      <?php endfor; ?>
    </div>
  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
