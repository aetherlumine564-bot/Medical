<?php
require_once dirname(__DIR__) . '/config/config.php';
requirePermission('appointments.view');

$db = getDB();

$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

$stmtA = $db->prepare(
    "SELECT a.*, pu.name AS patient_name, du.name AS doctor_name
     FROM appointments a
     JOIN patients p ON p.id = a.patient_id
     JOIN users pu ON pu.id = p.user_id
     JOIN doctors d ON d.id = a.doctor_id
     JOIN users du ON du.id = d.user_id
     WHERE MONTH(a.appointment_date)=? AND YEAR(a.appointment_date)=?
       AND a.status NOT IN ('cancelada')
     ORDER BY a.appointment_date, a.appointment_time"
);
$stmtA->execute([$month, $year]);
$appts = $stmtA->fetchAll();

$byDay = [];
foreach ($appts as $a) {
    $day = (int)date('j', strtotime($a['appointment_date']));
    $byDay[$day][] = $a;
}

$firstDay    = (int)date('w', mktime(0,0,0,$month,1,$year));
$daysInMonth = (int)date('t', mktime(0,0,0,$month,1,$year));
$monthNames  = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$dayNames    = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
$prevM = $month - 1; $prevY = $year; if ($prevM < 1) { $prevM = 12; $prevY--; }
$nextM = $month + 1; $nextY = $year; if ($nextM > 12) { $nextM = 1;  $nextY++; }

$pageTitle   = 'Calendario';
$breadcrumbs = [['label'=>'Calendario']];
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-calendar-days text-primary"></i> Calendario de Citas</h1>
    <p class="page-subtitle">Vista mensual de todas las citas</p>
  </div>
  <div class="page-actions">
    <a href="?month=<?= $prevM ?>&year=<?= $prevY ?>" class="btn btn-secondary"><i class="fa-solid fa-chevron-left"></i></a>
    <span style="padding:.5rem 1.25rem;font-weight:700;font-size:.95rem;background:var(--card-bg);border:1px solid var(--border-color);border-radius:8px;">
      <?= $monthNames[$month] ?> <?= $year ?>
    </span>
    <a href="?month=<?= $nextM ?>&year=<?= $nextY ?>" class="btn btn-secondary"><i class="fa-solid fa-chevron-right"></i></a>
    <a href="?month=<?= date('n') ?>&year=<?= date('Y') ?>" class="btn btn-primary btn-sm">Hoy</a>
    <?php if (hasPermission('appointments.create')): ?>
    <a href="appointments/create.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nueva cita</a>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-body" style="padding:0;">
    <div style="display:grid;grid-template-columns:repeat(7,1fr);border-bottom:1px solid var(--border-color);">
      <?php foreach ($dayNames as $d): ?>
      <div style="padding:.75rem;text-align:center;font-size:.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;"><?= $d ?></div>
      <?php endforeach; ?>
    </div>
    <div style="display:grid;grid-template-columns:repeat(7,1fr);">
      <?php
      $todayStr = date('Y-m-d');
      $cell = 0;
      for ($i=0; $i < $firstDay; $i++, $cell++):
      ?>
      <div style="min-height:100px;border-right:1px solid var(--border-color);border-bottom:1px solid var(--border-color);background:#FAFAFA;"></div>
      <?php endfor; ?>
      <?php for ($day=1; $day<=$daysInMonth; $day++, $cell++):
        $dateStr  = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $isToday  = ($dateStr === $todayStr);
        $border   = ($cell % 7 === 6) ? '' : 'border-right:1px solid var(--border-color);';
      ?>
      <div style="min-height:100px;padding:.6rem;<?= $border ?>border-bottom:1px solid var(--border-color);<?= $isToday ? 'background:var(--primary-light);' : '' ?>">
        <div style="font-size:.82rem;font-weight:<?= $isToday?'800':'600' ?>;color:<?= $isToday?'var(--primary)':'var(--text-dark)' ?>;margin-bottom:.35rem;"><?= $day ?></div>
        <?php if (!empty($byDay[$day])): ?>
          <?php foreach (array_slice($byDay[$day], 0, 3) as $a): ?>
          <a href="<?= APP_URL ?>/admin/appointments/view.php?id=<?= $a['id'] ?>"
             style="display:block;background:var(--primary);color:#fff;border-radius:4px;padding:.12rem .4rem;font-size:.68rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:.15rem;text-decoration:none;"
             title="<?= e($a['patient_name']) ?> – <?= e($a['doctor_name']) ?>">
            <?= substr($a['appointment_time'],0,5) ?> <?= e($a['patient_name']) ?>
          </a>
          <?php endforeach; ?>
          <?php if (count($byDay[$day]) > 3): ?>
          <div style="font-size:.65rem;color:var(--primary);font-weight:600;cursor:pointer;">+<?= count($byDay[$day]) - 3 ?> más</div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <?php endfor; ?>
      <?php
      $remaining = (7 - ($cell % 7)) % 7;
      for ($i=0; $i < $remaining; $i++): ?>
      <div style="min-height:100px;border-right:1px solid var(--border-color);border-bottom:1px solid var(--border-color);background:#FAFAFA;"></div>
      <?php endfor; ?>
    </div>
  </div>
</div>

<!-- Leyenda -->
<div style="display:flex;gap:1.5rem;padding:1rem 0;font-size:.8rem;color:var(--text-muted);">
  <div><span style="display:inline-block;width:12px;height:12px;background:var(--primary);border-radius:3px;margin-right:.4rem;"></span>Cita activa</div>
  <div><span style="display:inline-block;width:12px;height:12px;background:var(--primary-light);border-radius:3px;margin-right:.4rem;border:1px solid var(--primary);"></span>Hoy</div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
