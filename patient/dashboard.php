<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole('patient');

$db     = getDB();
$userId = $_SESSION['user_id'];

$stmtP = $db->prepare('SELECT * FROM patients WHERE user_id = ?');
$stmtP->execute([$userId]);
$patient = $stmtP->fetch();

if (!$patient) { setFlash('warning','Completa tu perfil de paciente.'); }

$patientId = $patient['id'] ?? 0;

// Citas con prepare (safe)
$stmtAppts = $db->prepare(
    "SELECT a.*, du.name AS doctor_name, s.name AS specialty
     FROM appointments a
     JOIN doctors d ON d.id = a.doctor_id
     JOIN users du ON du.id = d.user_id
     JOIN specialties s ON s.id = d.specialty_id
     WHERE a.patient_id = ?
     ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT 5"
);
$stmtAppts->execute([$patientId]);
$myAppts = $stmtAppts->fetchAll();

// Stats con prepare
$s1 = $db->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id=?");
$s1->execute([$patientId]);
$s2 = $db->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id=? AND appointment_date >= CURDATE() AND status NOT IN ('cancelada','completada')");
$s2->execute([$patientId]);
$s3 = $db->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id=? AND status='completada'");
$s3->execute([$patientId]);

$stats = [
    'total'     => $s1->fetchColumn(),
    'upcoming'  => $s2->fetchColumn(),
    'completed' => $s3->fetchColumn(),
];

$pageTitle   = 'Mi Panel';
$breadcrumbs = [];
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Hola, <?= e(explode(' ', $_SESSION['user_name'])[0]) ?></h1>
    <p class="page-subtitle"><?= date('d \d\e F, Y') ?></p>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/patient/appointments/create.php" class="btn btn-primary">
      <i class="fa-solid fa-calendar-plus"></i> Reservar cita
    </a>
  </div>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa-solid fa-calendar-check"></i></div>
    <div class="stat-info"><div class="value"><?= $stats['total'] ?></div><div class="label">Total citas</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon yellow"><i class="fa-solid fa-clock"></i></div>
    <div class="stat-info"><div class="value"><?= $stats['upcoming'] ?></div><div class="label">Próximas citas</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fa-solid fa-circle-check"></i></div>
    <div class="stat-info"><div class="value"><?= $stats['completed'] ?></div><div class="label">Completadas</div></div>
  </div>
</div>

<?php if ($patient && $patient['blood_type']): ?>
<div class="card" style="margin-bottom:1.25rem;border-left:4px solid var(--danger);">
  <div class="card-body" style="display:flex;align-items:center;gap:1rem;">
    <div style="font-size:2rem;">🩸</div>
    <div>
      <div style="font-size:.75rem;text-transform:uppercase;color:var(--text-muted);font-weight:600;">Grupo sanguíneo</div>
      <div style="font-size:1.5rem;font-weight:800;color:var(--danger);"><?= e($patient['blood_type']) ?></div>
    </div>
    <?php if ($patient['allergies']): ?>
    <div style="margin-left:2rem;">
      <div style="font-size:.75rem;text-transform:uppercase;color:var(--text-muted);font-weight:600;">Alergias conocidas</div>
      <div style="font-size:.88rem;color:var(--text-dark);"><?= nl2br(e($patient['allergies'])) ?></div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="fa-solid fa-calendar-days text-primary" style="margin-right:.4rem;"></i>Mis últimas citas</span>
    <a href="<?= APP_URL ?>/patient/appointments/index.php" class="btn btn-secondary btn-sm" style="margin-left:auto;">Ver todas</a>
  </div>
  <?php if (empty($myAppts)): ?>
  <div class="empty-state">
    <div class="empty-icon"><i class="fa-regular fa-calendar"></i></div>
    <h3>Sin citas registradas</h3>
    <p>Reserva tu primera cita médica</p>
    <a href="<?= APP_URL ?>/patient/appointments/create.php" class="btn btn-primary mt-2">Reservar cita</a>
  </div>
  <?php else: ?>
  <div style="overflow-x:auto;">
    <table class="table">
      <thead><tr><th>Fecha</th><th>Hora</th><th>Doctor</th><th>Especialidad</th><th>Estado</th></tr></thead>
      <tbody>
        <?php foreach ($myAppts as $a): ?>
        <tr>
          <td><?= formatDate($a['appointment_date']) ?></td>
          <td><?= substr($a['appointment_time'],0,5) ?></td>
          <td><?= e($a['doctor_name']) ?></td>
          <td><span class="badge badge-purple"><?= e($a['specialty']) ?></span></td>
          <td><?= appointmentBadge($a['status']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
