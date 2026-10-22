<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requirePermission('patients.view');

$db  = getDB();
$id  = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare(
    'SELECT p.*, u.name, u.email, u.phone, u.status, u.created_at AS user_created,
            TIMESTAMPDIFF(YEAR, p.birth_date, CURDATE()) AS age
     FROM patients p JOIN users u ON u.id = p.user_id WHERE p.id = ?'
);
$stmt->execute([$id]);
$patient = $stmt->fetch();
if (!$patient) { setFlash('error','Paciente no encontrado.'); redirect('admin/patients/index.php'); }

// Citas del paciente
$appointments = $db->prepare(
    "SELECT a.*, du.name AS doctor_name, s.name AS specialty
     FROM appointments a
     JOIN doctors d ON d.id = a.doctor_id
     JOIN users du  ON du.id = d.user_id
     JOIN specialties s ON s.id = d.specialty_id
     WHERE a.patient_id = ?
     ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT 10"
);
$appointments->execute([$id]);
$appointments = $appointments->fetchAll();

$pageTitle   = 'Detalle Paciente';
$breadcrumbs = [['label'=>'Pacientes','url'=>'admin/patients/index.php'],['label'=>e($patient['name'])]];
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Ficha del Paciente</h1>
  </div>
  <div class="page-actions">
    <a href="edit.php?id=<?= $id ?>" class="btn btn-primary"><i class="fa-solid fa-pen"></i> Editar</a>
    <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Volver</a>
  </div>
</div>

<div style="display:grid;grid-template-columns:300px 1fr;gap:1.25rem;">

  <!-- Perfil -->
  <div style="display:flex;flex-direction:column;gap:1rem;">
    <div class="card">
      <div class="card-body" style="text-align:center;padding:2rem 1.5rem;">
        <div style="width:80px;height:80px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.8rem;font-weight:700;margin:0 auto 1rem;">
          <?= avatarInitials($patient['name']) ?>
        </div>
        <h2 style="font-size:1.05rem;color:var(--text-dark);"><?= e($patient['name']) ?></h2>
        <p style="font-size:.8rem;color:var(--text-muted);"><?= e($patient['email']) ?></p>
        <?= statusBadge($patient['status']) ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><span class="card-title">Datos clave</span></div>
      <div class="card-body">
        <?php
        $items = [
          ['DNI',          e($patient['dni'] ?? '—')],
          ['Edad',         $patient['age'] ? $patient['age'].' años' : '—'],
          ['Género',       $patient['gender'] === 'M' ? 'Masculino' : ($patient['gender'] === 'F' ? 'Femenino' : '—')],
          ['Grupo sang.',  $patient['blood_type'] ?? '—'],
          ['Ciudad',       e($patient['city'] ?? '—')],
          ['Teléfono',     e($patient['phone'] ?? '—')],
          ['Registrado',   formatDate($patient['user_created'])],
        ];
        foreach ($items as [$label, $val]): ?>
        <div style="display:flex;justify-content:space-between;padding:.45rem 0;border-bottom:1px solid var(--border-color);font-size:.84rem;">
          <span style="color:var(--text-muted);"><?= $label ?></span>
          <span style="font-weight:600;color:var(--text-dark);"><?= $val ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if ($patient['emergency_contact']): ?>
    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fa-solid fa-phone-volume text-danger"></i> Emergencias</span></div>
      <div class="card-body" style="font-size:.85rem;">
        <div><strong><?= e($patient['emergency_contact']) ?></strong></div>
        <div style="color:var(--text-muted);"><?= e($patient['emergency_phone'] ?? '') ?></div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Detalle médico -->
  <div style="display:flex;flex-direction:column;gap:1rem;">
    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fa-solid fa-notes-medical text-accent"></i> Historia clínica</span></div>
      <div class="card-body">
        <div class="form-grid form-grid-2">
          <div>
            <div style="font-size:.75rem;text-transform:uppercase;color:var(--text-muted);font-weight:600;margin-bottom:.4rem;">Alergias</div>
            <p style="font-size:.875rem;"><?= nl2br(e($patient['allergies'] ?? '—')) ?></p>
          </div>
          <div>
            <div style="font-size:.75rem;text-transform:uppercase;color:var(--text-muted);font-weight:600;margin-bottom:.4rem;">Enfermedades crónicas</div>
            <p style="font-size:.875rem;"><?= nl2br(e($patient['chronic_diseases'] ?? '—')) ?></p>
          </div>
          <?php if ($patient['notes']): ?>
          <div class="full-width">
            <div style="font-size:.75rem;text-transform:uppercase;color:var(--text-muted);font-weight:600;margin-bottom:.4rem;">Notas</div>
            <p style="font-size:.875rem;"><?= nl2br(e($patient['notes'])) ?></p>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Historial de citas -->
    <div class="card">
      <div class="card-header">
        <span class="card-title"><i class="fa-solid fa-calendar-check text-primary"></i> Historial de citas</span>
        <a href="<?= APP_URL ?>/admin/appointments/create.php?patient_id=<?= $id ?>" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-plus"></i> Nueva cita
        </a>
      </div>
      <?php if (empty($appointments)): ?>
      <div class="empty-state"><div class="empty-icon"><i class="fa-regular fa-calendar"></i></div><h3>Sin citas</h3></div>
      <?php else: ?>
      <div style="overflow-x:auto;">
        <table class="table">
          <thead><tr><th>Fecha</th><th>Hora</th><th>Doctor</th><th>Especialidad</th><th>Estado</th></tr></thead>
          <tbody>
            <?php foreach ($appointments as $a): ?>
            <tr>
              <td><?= formatDate($a['appointment_date']) ?></td>
              <td><?= substr($a['appointment_time'],0,5) ?></td>
              <td><?= e($a['doctor_name']) ?></td>
              <td><?= e($a['specialty']) ?></td>
              <td><?= appointmentBadge($a['status']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
