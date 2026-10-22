<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole('patient');

$db     = getDB();
$userId = $_SESSION['user_id'];

$stmtP = $db->prepare(
    'SELECT p.*, u.name, u.email, u.phone,
            TIMESTAMPDIFF(YEAR, p.birth_date, CURDATE()) AS age
     FROM patients p JOIN users u ON u.id = p.user_id WHERE p.user_id=?'
);
$stmtP->execute([$userId]);
$patient = $stmtP->fetch();
if (!$patient) { setFlash('warning','Perfil de paciente incompleto.'); redirect('patient/dashboard.php'); }

$patientId = $patient['id'];

// Todas las citas completadas
$stmtA = $db->prepare(
    "SELECT a.*, du.name AS doctor_name, s.name AS specialty
     FROM appointments a
     JOIN doctors d ON d.id = a.doctor_id
     JOIN users du ON du.id = d.user_id
     JOIN specialties s ON s.id = d.specialty_id
     WHERE a.patient_id = ? AND a.status = 'completada'
     ORDER BY a.appointment_date DESC"
);
$stmtA->execute([$patientId]);
$history = $stmtA->fetchAll();

$pageTitle   = 'Mi Historial';
$breadcrumbs = [['label'=>'Mi historial']];
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-notes-medical text-accent"></i> Mi Historial Clínico</h1>
    <p class="page-subtitle">Registro de consultas y atenciones médicas</p>
  </div>
</div>

<div style="display:grid;grid-template-columns:280px 1fr;gap:1.25rem;align-items:start;">

  <!-- Ficha del paciente -->
  <div class="card">
    <div class="card-body" style="text-align:center;padding:1.75rem 1.25rem;">
      <div style="width:72px;height:72px;border-radius:50%;background:var(--accent);color:#fff;font-size:1.6rem;font-weight:700;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
        <?= avatarInitials($patient['name']) ?>
      </div>
      <h3 style="font-size:1rem;font-weight:700;"><?= e($patient['name']) ?></h3>
      <p style="font-size:.8rem;color:var(--text-muted);"><?= e($patient['email']) ?></p>
    </div>
    <div style="border-top:1px solid var(--border-color);padding:1rem 1.25rem;">
      <?php $fields = [
        ['DNI',           $patient['dni'] ?? '—'],
        ['Edad',          $patient['age'] ? $patient['age'] . ' años' : '—'],
        ['Género',        $patient['gender'] === 'M' ? 'Masculino' : ($patient['gender'] === 'F' ? 'Femenino' : '—')],
        ['Grupo sang.',   $patient['blood_type'] ?? '—'],
        ['Teléfono',      $patient['phone'] ?? '—'],
        ['Ciudad',        $patient['city'] ?? '—'],
      ]; ?>
      <?php foreach ($fields as [$label, $value]): ?>
      <div style="display:flex;justify-content:space-between;padding:.4rem 0;border-bottom:1px solid var(--border-color);font-size:.82rem;">
        <span style="color:var(--text-muted);"><?= $label ?></span>
        <strong><?= e($value) ?></strong>
      </div>
      <?php endforeach; ?>
      <?php if ($patient['allergies']): ?>
      <div style="margin-top:.75rem;padding:.6rem;background:var(--danger-light);border-radius:8px;">
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--danger);margin-bottom:.25rem;">⚠ Alergias</div>
        <div style="font-size:.8rem;color:var(--danger);"><?= nl2br(e($patient['allergies'])) ?></div>
      </div>
      <?php endif; ?>
      <?php if ($patient['chronic_diseases']): ?>
      <div style="margin-top:.5rem;padding:.6rem;background:var(--warning-light);border-radius:8px;">
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--warning);margin-bottom:.25rem;">Enfermedades crónicas</div>
        <div style="font-size:.8rem;color:var(--warning);"><?= nl2br(e($patient['chronic_diseases'])) ?></div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Historial de consultas -->
  <div>
    <?php if (empty($history)): ?>
    <div class="card">
      <div class="empty-state">
        <div class="empty-icon"><i class="fa-solid fa-file-medical"></i></div>
        <h3>Sin consultas registradas</h3>
        <p>Aquí aparecerán tus consultas médicas completadas.</p>
      </div>
    </div>
    <?php else: ?>
    <?php foreach ($history as $h): ?>
    <div class="card" style="margin-bottom:1rem;">
      <div class="card-header">
        <div style="display:flex;align-items:center;gap:.75rem;width:100%;">
          <div class="table-avatar" style="background:#7C3AED;"><?= avatarInitials($h['doctor_name']) ?></div>
          <div>
            <div style="font-weight:700;font-size:.9rem;"><?= e($h['doctor_name']) ?></div>
            <div style="font-size:.75rem;color:var(--text-muted);"><?= e($h['specialty']) ?></div>
          </div>
          <div style="margin-left:auto;text-align:right;">
            <div style="font-weight:700;font-size:.88rem;"><?= formatDate($h['appointment_date'], 'd/m/Y') ?></div>
            <div style="font-size:.75rem;color:var(--text-muted);"><?= substr($h['appointment_time'],0,5) ?></div>
          </div>
          <?= appointmentBadge($h['status']) ?>
        </div>
      </div>
      <?php if ($h['reason'] || $h['notes']): ?>
      <div class="card-body" style="padding:1rem 1.25rem;">
        <?php if ($h['reason']): ?>
        <div style="margin-bottom:.5rem;font-size:.85rem;">
          <strong>Motivo:</strong> <?= e($h['reason']) ?>
        </div>
        <?php endif; ?>
        <?php if ($h['notes']): ?>
        <div style="font-size:.85rem;color:var(--text-muted);">
          <strong style="color:var(--text-body);">Notas:</strong> <?= nl2br(e($h['notes'])) ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
