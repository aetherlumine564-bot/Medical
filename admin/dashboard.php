<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole('admin');

$db = getDB();

// ── Estadísticas ─────────────────────────────────────────────────────────────
$stats = [
    'patients'     => $db->query('SELECT COUNT(*) FROM patients')->fetchColumn(),
    'doctors'      => $db->query('SELECT COUNT(*) FROM doctors WHERE status = 1')->fetchColumn(),
    'appointments' => $db->query('SELECT COUNT(*) FROM appointments')->fetchColumn(),
    'today'        => $db->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()")->fetchColumn(),
    'pending'      => $db->query("SELECT COUNT(*) FROM appointments WHERE status = 'pendiente'")->fetchColumn(),
    'users'        => $db->query('SELECT COUNT(*) FROM users WHERE status = 1')->fetchColumn(),
];

// ── Próximas citas (hoy + mañana) ────────────────────────────────────────────
$upcomingAppts = $db->query(
    "SELECT a.*,
            CONCAT(pu.name) AS patient_name,
            CONCAT(du.name) AS doctor_name,-
            s.name          AS specialty
     FROM appointments a
     JOIN patients p  ON p.id = a.patient_id
     JOIN users pu    ON pu.id = p.user_id
     JOIN doctors d   ON d.id = a.doctor_id
     JOIN users du    ON du.id = d.user_id
     JOIN specialties s ON s.id = d.specialty_id
     WHERE a.appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 2 DAY)
       AND a.status NOT IN ('cancelada','completada')
     ORDER BY a.appointment_date, a.appointment_time
     LIMIT 8"
)->fetchAll();

// ── Últimos pacientes registrados ─────────────────────────────────────────────
$latestPatients = $db->query(
    "SELECT u.name, u.email, u.created_at, p.blood_type, p.gender
     FROM patients p
     JOIN users u ON u.id = p.user_id
     ORDER BY p.created_at DESC LIMIT 5"
)->fetchAll();

// ── Citas por estado ──────────────────────────────────────────────────────────
$apptByStatus = $db->query(
    "SELECT status, COUNT(*) AS total FROM appointments GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);

// ── Variables para el header ──────────────────────────────────────────────────
$pageTitle   = 'Dashboard';
$breadcrumbs = [];
include dirname(__DIR__) . '/includes/header.php';
?>

<!-- ── Page header ─────────────────────────────────────────────────────────── -->
<div class="page-header">
  <div>
    <h1 class="page-title">Panel de Administración</h1>
    <p class="page-subtitle">Resumen general del sistema — <?= date('d \d\e F, Y') ?></p>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/admin/appointments/create.php" class="btn btn-primary">
      <i class="fa-solid fa-calendar-plus"></i> Nueva cita
    </a>
  </div>
</div>

<!-- ── Stats cards ─────────────────────────────────────────────────────────── -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa-solid fa-user-injured"></i></div>
    <div class="stat-info">
      <div class="value"><?= number_format($stats['patients']) ?></div>
      <div class="label">Pacientes registrados</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fa-solid fa-user-doctor"></i></div>
    <div class="stat-info">
      <div class="value"><?= number_format($stats['doctors']) ?></div>
      <div class="label">Doctores activos</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon yellow"><i class="fa-solid fa-calendar-check"></i></div>
    <div class="stat-info">
      <div class="value"><?= number_format($stats['appointments']) ?></div>
      <div class="label">Total de citas</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red"><i class="fa-solid fa-clock"></i></div>
    <div class="stat-info">
      <div class="value"><?= number_format($stats['today']) ?></div>
      <div class="label">Citas de hoy</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple"><i class="fa-solid fa-hourglass-half"></i></div>
    <div class="stat-info">
      <div class="value"><?= number_format($stats['pending']) ?></div>
      <div class="label">Citas pendientes</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa-solid fa-users"></i></div>
    <div class="stat-info">
      <div class="value"><?= number_format($stats['users']) ?></div>
      <div class="label">Usuarios activos</div>
    </div>
  </div>
</div>

<!-- ── Grid de contenido ───────────────────────────────────────────────────── -->
<div style="display:grid; grid-template-columns:1fr 340px; gap:1.25rem;">

  <!-- Próximas citas -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fa-solid fa-calendar-days text-primary" style="margin-right:.5rem;"></i>Próximas citas</span>
      <a href="<?= APP_URL ?>/admin/appointments/index.php" class="btn btn-secondary btn-sm">
        Ver todas <i class="fa-solid fa-arrow-right"></i>
      </a>
    </div>
    <div style="overflow-x:auto;">
      <?php if (empty($upcomingAppts)): ?>
      <div class="empty-state">
        <div class="empty-icon"><i class="fa-regular fa-calendar-xmark"></i></div>
        <h3>Sin citas próximas</h3>
        <p>No hay citas programadas para los próximos 2 días.</p>
      </div>
      <?php else: ?>
      <table class="table">
        <thead>
          <tr>
            <th>Paciente</th>
            <th>Doctor</th>
            <th>Especialidad</th>
            <th>Fecha</th>
            <th>Hora</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($upcomingAppts as $a): ?>
          <tr>
            <td>
              <div class="table-user-cell">
                <div class="table-avatar" style="background:var(--primary);"><?= avatarInitials($a['patient_name']) ?></div>
                <span class="table-user-name"><?= e($a['patient_name']) ?></span>
              </div>
            </td>
            <td><?= e($a['doctor_name']) ?></td>
            <td><span style="font-size:.78rem;color:var(--text-muted);"><?= e($a['specialty']) ?></span></td>
            <td><?= formatDate($a['appointment_date']) ?></td>
            <td><?= substr($a['appointment_time'], 0, 5) ?></td>
            <td><?= appointmentBadge($a['status']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Panel lateral derecho -->
  <div style="display:flex;flex-direction:column;gap:1.25rem;">

    <!-- Citas por estado -->
    <div class="card">
      <div class="card-header">
        <span class="card-title"><i class="fa-solid fa-chart-pie text-primary" style="margin-right:.5rem;"></i>Estado de citas</span>
      </div>
      <div class="card-body" style="padding:1rem 1.4rem;">
        <?php
        $statusLabels = [
            'pendiente'  => ['Pendiente',  'warning'],
            'confirmada' => ['Confirmada', 'info'],
            'en_curso'   => ['En curso',   'primary'],
            'completada' => ['Completada', 'success'],
            'cancelada'  => ['Cancelada',  'danger'],
            'no_asistio' => ['No asistió', 'secondary'],
        ];
        $total = array_sum($apptByStatus);
        foreach ($statusLabels as $slug => [$label, $color]):
            $count = $apptByStatus[$slug] ?? 0;
            $pct   = $total > 0 ? round($count / $total * 100) : 0;
        ?>
        <div style="margin-bottom:.9rem;">
          <div style="display:flex;justify-content:space-between;margin-bottom:.3rem;">
            <span style="font-size:.8rem;font-weight:600;color:var(--text-dark);"><?= $label ?></span>
            <span style="font-size:.8rem;color:var(--text-muted);"><?= $count ?> (<?= $pct ?>%)</span>
          </div>
          <div style="background:#F3F4F6;border-radius:999px;height:6px;overflow:hidden;">
            <div style="width:<?= $pct ?>%;background:var(--<?= $color === 'primary' ? 'primary' : ($color === 'success' ? 'accent' : ($color === 'warning' ? 'warning' : ($color === 'danger' ? 'danger' : ($color === 'info' ? 'info' : 'secondary')))) ?>);height:100%;border-radius:999px;transition:width .4s;"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Últimos pacientes -->
    <div class="card">
      <div class="card-header">
        <span class="card-title"><i class="fa-solid fa-user-plus text-accent" style="margin-right:.5rem;"></i>Nuevos pacientes</span>
        <a href="<?= APP_URL ?>/admin/patients/index.php" class="btn btn-secondary btn-sm">Ver</a>
      </div>
      <div style="padding:.5rem 0;">
        <?php foreach ($latestPatients as $p): ?>
        <div style="display:flex;align-items:center;gap:.75rem;padding:.65rem 1.2rem;">
          <div class="table-avatar" style="background:var(--accent);"><?= avatarInitials($p['name']) ?></div>
          <div style="flex:1;min-width:0;">
            <div style="font-size:.83rem;font-weight:600;color:var(--text-dark);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($p['name']) ?></div>
            <div style="font-size:.74rem;color:var(--text-muted);"><?= timeAgo($p['created_at']) ?></div>
          </div>
          <?php if ($p['blood_type']): ?>
          <span class="badge badge-danger" style="font-size:.7rem;"><?= e($p['blood_type']) ?></span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (empty($latestPatients)): ?>
        <div style="padding:1.5rem;text-align:center;color:var(--text-muted);font-size:.85rem;">Sin registros</div>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /sidebar derecho -->
</div><!-- /grid -->

<!-- Accesos rápidos -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.75rem;margin-top:1.25rem;">
  <?php
  $quickLinks = [
    ['url'=>'admin/users/create.php',        'icon'=>'user-plus',     'label'=>'Nuevo usuario',   'color'=>'var(--primary)'],
    ['url'=>'admin/patients/create.php',     'icon'=>'user-injured',  'label'=>'Nuevo paciente',  'color'=>'var(--accent)'],
    ['url'=>'admin/doctors/create.php',      'icon'=>'user-doctor',   'label'=>'Nuevo doctor',    'color'=>'#7C3AED'],
    ['url'=>'admin/appointments/create.php', 'icon'=>'calendar-plus', 'label'=>'Nueva cita',      'color'=>'var(--warning)'],
    ['url'=>'admin/roles/index.php',         'icon'=>'shield-halved', 'label'=>'Roles',           'color'=>'var(--danger)'],
    ['url'=>'admin/schedules/index.php',     'icon'=>'clock',         'label'=>'Horarios',        'color'=>'#0891B2'],
  ];
  foreach ($quickLinks as $ql): ?>
  <a href="<?= APP_URL . '/' . $ql['url'] ?>" style="
    background:var(--card-bg); border:1px solid var(--border-color);
    border-radius:12px; padding:1.1rem 1rem;
    display:flex;align-items:center;gap:.75rem;
    text-decoration:none; color:var(--text-body);
    transition:var(--transition);
  " onmouseover="this.style.borderColor='<?= $ql['color'] ?>'; this.style.boxShadow='0 0 0 3px <?= str_replace(')',',0.12)',$ql['color']) ?>';"
     onmouseout="this.style.borderColor='var(--border-color)'; this.style.boxShadow='none';">
    <div style="width:38px;height:38px;border-radius:10px;background:<?= $ql['color'] ?>22;color:<?= $ql['color'] ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="fa-solid fa-<?= $ql['icon'] ?>"></i>
    </div>
    <span style="font-size:.83rem;font-weight:600;"><?= $ql['label'] ?></span>
  </a>
  <?php endforeach; ?>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
