<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole('admin');

$db = getDB();

// ── Resumen general ──────────────────────────────────────────────────────────
$summary = [
    'total_patients'     => $db->query('SELECT COUNT(*) FROM patients')->fetchColumn(),
    'total_doctors'      => $db->query('SELECT COUNT(*) FROM doctors')->fetchColumn(),
    'total_appointments' => $db->query('SELECT COUNT(*) FROM appointments')->fetchColumn(),
    'this_month'         => $db->query("SELECT COUNT(*) FROM appointments WHERE MONTH(appointment_date)=MONTH(CURDATE()) AND YEAR(appointment_date)=YEAR(CURDATE())")->fetchColumn(),
    'completed'          => $db->query("SELECT COUNT(*) FROM appointments WHERE status='completada'")->fetchColumn(),
    'cancelled'          => $db->query("SELECT COUNT(*) FROM appointments WHERE status='cancelada'")->fetchColumn(),
];

// ── Citas por estado ──────────────────────────────────────────────────────────
$byStatus = $db->query(
    "SELECT status, COUNT(*) AS total FROM appointments GROUP BY status ORDER BY total DESC"
)->fetchAll();

// ── Top 5 doctores con más citas ──────────────────────────────────────────────
$topDoctors = $db->query(
    "SELECT du.name, s.name AS specialty, COUNT(a.id) AS total
     FROM appointments a
     JOIN doctors d ON d.id = a.doctor_id
     JOIN users du ON du.id = d.user_id
     JOIN specialties s ON s.id = d.specialty_id
     GROUP BY d.id ORDER BY total DESC LIMIT 5"
)->fetchAll();

// ── Citas por mes (últimos 6 meses) ──────────────────────────────────────────
$byMonth = $db->query(
    "SELECT DATE_FORMAT(appointment_date,'%Y-%m') AS mes,
            DATE_FORMAT(appointment_date,'%b %Y') AS label,
            COUNT(*) AS total
     FROM appointments
     WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY mes ORDER BY mes ASC"
)->fetchAll();

// ── Especialidades más solicitadas ───────────────────────────────────────────
$topSpecialties = $db->query(
    "SELECT s.name, COUNT(a.id) AS total
     FROM appointments a
     JOIN doctors d ON d.id = a.doctor_id
     JOIN specialties s ON s.id = d.specialty_id
     GROUP BY s.id ORDER BY total DESC LIMIT 8"
)->fetchAll();

$pageTitle   = 'Reportes';
$breadcrumbs = [['label'=>'Reportes']];
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-chart-line text-primary"></i> Reportes</h1>
    <p class="page-subtitle">Estadísticas y resumen del sistema</p>
  </div>
</div>

<!-- Tarjetas resumen -->
<div class="stats-grid" style="margin-bottom:1.5rem;">
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa-solid fa-user-injured"></i></div>
    <div class="stat-info"><div class="value"><?= number_format($summary['total_patients']) ?></div><div class="label">Pacientes</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple"><i class="fa-solid fa-user-doctor"></i></div>
    <div class="stat-info"><div class="value"><?= number_format($summary['total_doctors']) ?></div><div class="label">Doctores</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fa-solid fa-calendar-check"></i></div>
    <div class="stat-info"><div class="value"><?= number_format($summary['total_appointments']) ?></div><div class="label">Total citas</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon yellow"><i class="fa-solid fa-calendar"></i></div>
    <div class="stat-info"><div class="value"><?= number_format($summary['this_month']) ?></div><div class="label">Este mes</div></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.25rem;">

  <!-- Citas por estado -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fa-solid fa-chart-pie text-primary"></i> Citas por estado</span></div>
    <div class="card-body">
      <?php
      $total = max(1, array_sum(array_column($byStatus,'total')));
      foreach ($byStatus as $row):
        $pct = round($row['total'] / $total * 100);
      ?>
      <div style="margin-bottom:.85rem;">
        <div style="display:flex;justify-content:space-between;font-size:.82rem;margin-bottom:.3rem;">
          <span style="font-weight:600;"><?= ucfirst(str_replace('_',' ',$row['status'])) ?></span>
          <span style="color:var(--text-muted);"><?= $row['total'] ?> (<?= $pct ?>%)</span>
        </div>
        <div style="height:8px;background:var(--border-color);border-radius:99px;overflow:hidden;">
          <?php $colors = ['completada'=>'var(--accent)','confirmada'=>'var(--info)','pendiente'=>'var(--warning)','cancelada'=>'var(--danger)','en_curso'=>'var(--primary)','no_asistio'=>'var(--secondary)'];
          $color = $colors[$row['status']] ?? 'var(--primary)'; ?>
          <div style="height:100%;width:<?= $pct ?>%;background:<?= $color ?>;border-radius:99px;transition:width .6s;"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Top doctores -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fa-solid fa-trophy" style="color:var(--warning);"></i> Top doctores</span></div>
    <div class="card-body">
      <?php if (empty($topDoctors)): ?>
      <p style="color:var(--text-muted);font-size:.85rem;">Sin datos disponibles.</p>
      <?php else: ?>
      <?php $maxD = max(array_column($topDoctors,'total')); ?>
      <?php foreach ($topDoctors as $i => $doc): ?>
      <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.75rem;">
        <div style="width:22px;text-align:center;font-weight:800;font-size:.8rem;color:<?= $i===0?'var(--warning)':($i===1?'var(--text-muted)':'var(--text-muted)') ?>;"><?= $i+1 ?></div>
        <div style="flex:1;min-width:0;">
          <div style="font-size:.82rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($doc['name']) ?></div>
          <div style="font-size:.72rem;color:var(--text-muted);"><?= e($doc['specialty']) ?></div>
        </div>
        <span class="badge badge-info"><?= $doc['total'] ?> citas</span>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">

  <!-- Especialidades más solicitadas -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fa-solid fa-stethoscope text-accent"></i> Especialidades solicitadas</span></div>
    <div class="card-body">
      <?php if (empty($topSpecialties)): ?>
      <p style="color:var(--text-muted);font-size:.85rem;">Sin datos disponibles.</p>
      <?php else: ?>
      <?php $maxS = max(array_column($topSpecialties,'total')); ?>
      <?php foreach ($topSpecialties as $sp): $pct = round($sp['total']/$maxS*100); ?>
      <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.65rem;">
        <div style="flex:1;font-size:.82rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($sp['name']) ?></div>
        <div style="width:120px;height:8px;background:var(--border-color);border-radius:99px;overflow:hidden;">
          <div style="height:100%;width:<?= $pct ?>%;background:var(--accent);border-radius:99px;"></div>
        </div>
        <div style="font-size:.78rem;color:var(--text-muted);min-width:28px;text-align:right;"><?= $sp['total'] ?></div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Citas últimos 6 meses -->
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fa-solid fa-chart-bar text-primary"></i> Citas últimos 6 meses</span></div>
    <div class="card-body">
      <?php if (empty($byMonth)): ?>
      <p style="color:var(--text-muted);font-size:.85rem;">Sin datos disponibles.</p>
      <?php else: ?>
      <?php $maxM = max(array_column($byMonth,'total')); ?>
      <?php foreach ($byMonth as $m): $pct = round($m['total']/$maxM*100); ?>
      <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.65rem;">
        <div style="width:70px;font-size:.78rem;color:var(--text-muted);"><?= $m['label'] ?></div>
        <div style="flex:1;height:22px;background:var(--border-color);border-radius:6px;overflow:hidden;">
          <div style="height:100%;width:<?= $pct ?>%;background:var(--primary);border-radius:6px;display:flex;align-items:center;justify-content:flex-end;padding-right:.4rem;">
            <span style="font-size:.68rem;font-weight:700;color:#fff;"><?= $m['total'] ?></span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
