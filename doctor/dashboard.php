<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole('doctor');

$db     = getDB();
$userId = $_SESSION['user_id'];

// Obtener datos del doctor
$doctor = $db->prepare(
    'SELECT d.*, s.name AS specialty FROM doctors d JOIN specialties s ON s.id = d.specialty_id WHERE d.user_id = ?'
);
$doctor->execute([$userId]);
$doctor = $doctor->fetch();

if (!$doctor) { setFlash('error','Perfil de doctor no encontrado.'); logout(); }

$doctorId = $doctor['id'];

// Stats con prepare para seguridad
$stmtToday = $db->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND appointment_date=CURDATE() AND status NOT IN ('cancelada')");
$stmtToday->execute([$doctorId]);

$stmtPending = $db->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND status='pendiente'");
$stmtPending->execute([$doctorId]);

$stmtWeek = $db->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND WEEK(appointment_date)=WEEK(CURDATE()) AND YEAR(appointment_date)=YEAR(CURDATE())");
$stmtWeek->execute([$doctorId]);

$stmtPatients = $db->prepare("SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE doctor_id=?");
$stmtPatients->execute([$doctorId]);

$stats = [
    'today'    => $stmtToday->fetchColumn(),
    'pending'  => $stmtPending->fetchColumn(),
    'week'     => $stmtWeek->fetchColumn(),
    'patients' => $stmtPatients->fetchColumn(),
];

$stmtTA = $db->prepare(
    "SELECT a.*, pu.name AS patient_name
     FROM appointments a
     JOIN patients p ON p.id = a.patient_id
     JOIN users pu ON pu.id = p.user_id
     WHERE a.doctor_id = ? AND a.appointment_date = CURDATE()
     AND a.status NOT IN ('cancelada')
     ORDER BY a.appointment_time"
);
$stmtTA->execute([$doctorId]);
$todayAppts = $stmtTA->fetchAll();

$pageTitle   = 'Mi Panel';
$breadcrumbs = [];
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Bienvenido, <?= e(explode(' ', $_SESSION['user_name'])[0]) ?></h1>
    <p class="page-subtitle"><?= e($doctor['specialty']) ?> — <?= date('d \d\e F, Y') ?></p>
  </div>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon yellow"><i class="fa-solid fa-calendar-day"></i></div>
    <div class="stat-info"><div class="value"><?= $stats['today'] ?></div><div class="label">Citas hoy</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red"><i class="fa-solid fa-hourglass-half"></i></div>
    <div class="stat-info"><div class="value"><?= $stats['pending'] ?></div><div class="label">Pendientes</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa-solid fa-calendar-week"></i></div>
    <div class="stat-info"><div class="value"><?= $stats['week'] ?></div><div class="label">Esta semana</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fa-solid fa-users"></i></div>
    <div class="stat-info"><div class="value"><?= $stats['patients'] ?></div><div class="label">Mis pacientes</div></div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="fa-solid fa-calendar-day text-primary" style="margin-right:.4rem;"></i>Citas de hoy</span>
  </div>
  <?php if (empty($todayAppts)): ?>
  <div class="empty-state"><div class="empty-icon"><i class="fa-regular fa-calendar-check"></i></div><h3>Sin citas para hoy</h3></div>
  <?php else: ?>
  <div style="overflow-x:auto;">
    <table class="table">
      <thead><tr><th>Hora</th><th>Paciente</th><th>Motivo</th><th>Estado</th><th>Acción</th></tr></thead>
      <tbody>
        <?php foreach ($todayAppts as $a): ?>
        <tr>
          <td><strong style="font-size:1rem;"><?= substr($a['appointment_time'],0,5) ?></strong></td>
          <td>
            <div class="table-user-cell">
              <div class="table-avatar" style="background:var(--accent);"><?= avatarInitials($a['patient_name']) ?></div>
              <span class="table-user-name"><?= e($a['patient_name']) ?></span>
            </div>
          </td>
          <td><?= e($a['reason'] ?? '—') ?></td>
          <td><?= appointmentBadge($a['status']) ?></td>
          <td>
            <a href="<?= APP_URL ?>/admin/appointments/view.php?id=<?= $a['id'] ?>" class="btn btn-secondary btn-sm">
              <i class="fa-solid fa-eye"></i> Ver
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
