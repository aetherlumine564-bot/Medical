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

// Pacientes únicos que han tenido citas con este doctor
$stmtP = $db->prepare(
    "SELECT DISTINCT p.id, u.name, u.email, u.phone,
            p.blood_type, p.gender,
            TIMESTAMPDIFF(YEAR, p.birth_date, CURDATE()) AS age,
            (SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND patient_id=p.id) AS total_appts,
            (SELECT MAX(appointment_date) FROM appointments WHERE doctor_id=? AND patient_id=p.id) AS last_visit
     FROM appointments a
     JOIN patients p ON p.id = a.patient_id
     JOIN users u ON u.id = p.user_id
     WHERE a.doctor_id = ?
     ORDER BY u.name"
);
$stmtP->execute([$doctorId, $doctorId, $doctorId]);
$patients = $stmtP->fetchAll();

$pageTitle   = 'Mis Pacientes';
$breadcrumbs = [['label'=>'Mis pacientes']];
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-user-injured text-accent"></i> Mis Pacientes</h1>
    <p class="page-subtitle">Pacientes que han tenido citas contigo</p>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <?php if (empty($patients)): ?>
    <div class="empty-state">
      <div class="empty-icon"><i class="fa-solid fa-user-injured"></i></div>
      <h3>Sin pacientes aún</h3>
      <p>Aquí aparecerán los pacientes que tengan citas contigo.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table" id="mainTable">
        <thead>
          <tr><th>Paciente</th><th>Edad</th><th>Género</th><th>Grupo sang.</th><th>Teléfono</th><th>Citas</th><th>Última visita</th></tr>
        </thead>
        <tbody>
          <?php foreach ($patients as $p): ?>
          <tr>
            <td>
              <div class="table-user-cell">
                <div class="table-avatar" style="background:var(--accent);"><?= avatarInitials($p['name']) ?></div>
                <div>
                  <div class="table-user-name"><?= e($p['name']) ?></div>
                  <div class="table-user-email"><?= e($p['email']) ?></div>
                </div>
              </div>
            </td>
            <td><?= $p['age'] ? $p['age'] . ' años' : '—' ?></td>
            <td>
              <?php if ($p['gender'] === 'M'): ?>
              <span class="badge badge-info">Masc.</span>
              <?php elseif ($p['gender'] === 'F'): ?>
              <span class="badge badge-purple">Fem.</span>
              <?php else: ?> — <?php endif; ?>
            </td>
            <td><?= $p['blood_type'] ? '<span class="badge badge-danger">'.$p['blood_type'].'</span>' : '—' ?></td>
            <td><?= e($p['phone'] ?? '—') ?></td>
            <td><span class="badge badge-info"><?= $p['total_appts'] ?> citas</span></td>
            <td><?= $p['last_visit'] ? formatDate($p['last_visit']) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
