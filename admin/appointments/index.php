<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requirePermission('appointments.view');

$db = getDB();
$appointments = $db->query(
    "SELECT a.*,
            pu.name AS patient_name,
            du.name AS doctor_name,
            s.name  AS specialty
     FROM appointments a
     JOIN patients p   ON p.id = a.patient_id
     JOIN users pu     ON pu.id = p.user_id
     JOIN doctors d    ON d.id = a.doctor_id
     JOIN users du     ON du.id = d.user_id
     JOIN specialties s ON s.id = d.specialty_id
     ORDER BY a.appointment_date DESC, a.appointment_time DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$pageTitle   = 'Citas Médicas';
$breadcrumbs = [['label'=>'Citas']];
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-calendar-check text-primary"></i> Citas Médicas</h1>
    <p class="page-subtitle">Listado y gestión de todas las citas</p>
  </div>
  <?php if (hasPermission('appointments.create')): ?>
  <div class="page-actions">
    <a href="create.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nueva cita</a>
  </div>
  <?php endif; ?>
</div>

<!-- Filtros -->
<div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.25rem;">
  <?php
  $statusFilters = [
    '' => 'Todas',
    'pendiente'  => 'Pendientes',
    'confirmada' => 'Confirmadas',
    'en_curso'   => 'En curso',
    'completada' => 'Completadas',
    'cancelada'  => 'Canceladas',
  ];
  $currentStatus = $_GET['status'] ?? '';
  foreach ($statusFilters as $val => $label):
  ?>
  <a href="?status=<?= $val ?>" class="btn <?= $currentStatus === $val ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
    <?= $label ?>
  </a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table" id="mainTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Paciente</th>
            <th>Doctor</th>
            <th>Especialidad</th>
            <th>Fecha</th>
            <th>Hora</th>
            <th>Motivo</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php
          if ($currentStatus) {
              $filtered = array_filter($appointments, function($a) use ($currentStatus) {
                  return $a['status'] === $currentStatus;
              });
          } else {
              $filtered = $appointments;
          }

          foreach ($filtered as $a):
          ?>
          <tr>
            <td><?= $a['id'] ?></td>

            <td>
              <div class="table-user-cell">
                <div class="table-avatar" style="background:var(--accent);"><?= avatarInitials($a['patient_name']) ?></div>
                <span class="table-user-name"><?= e($a['patient_name']) ?></span>
              </div>
            </td>

            <td>
              <div class="table-user-cell">
                <div class="table-avatar" style="background:#7C3AED;"><?= avatarInitials($a['doctor_name']) ?></div>
                <span class="table-user-name"><?= e($a['doctor_name']) ?></span>
              </div>
            </td>

            <td><span class="badge badge-purple"><?= e($a['specialty']) ?></span></td>
            <td><?= formatDate($a['appointment_date']) ?></td>
            <td><strong><?= substr($a['appointment_time'],0,5) ?></strong></td>

            <td style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
              <?= e($a['reason'] ?? '—') ?>
            </td>

            <!-- 🔥 ESTADO CON CHECK -->
            <td>
              <div style="display:flex;align-items:center;gap:.5rem;">
                <?= appointmentBadge($a['status']) ?>

                <?php if ($a['status'] !== 'completada' && hasPermission('appointments.edit')): ?>
                  <form method="POST" action="complete.php" style="margin:0;">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $a['id'] ?>">
                    <input type="checkbox" 
                           onchange="this.form.submit()" 
                           title="Marcar como completada"
                           style="cursor:pointer;">
                  </form>
                <?php endif; ?>
              </div>
            </td>

            <td>
              <div class="table-actions">
                <a href="view.php?id=<?= $a['id'] ?>" class="btn btn-secondary btn-sm btn-icon">
                  <i class="fa-solid fa-eye"></i>
                </a>

                <?php if (hasPermission('appointments.edit')): ?>
                <a href="edit.php?id=<?= $a['id'] ?>" class="btn btn-secondary btn-sm btn-icon">
                  <i class="fa-solid fa-pen"></i>
                </a>
                <?php endif; ?>

                <?php if (hasPermission('appointments.delete') && !in_array($a['status'],['completada'])): ?>
                <form method="POST" action="delete.php">
                  <?= csrfField() ?>
                  <input type="hidden" name="id" value="<?= $a['id'] ?>">
                  <button class="btn btn-danger btn-sm btn-icon">
                    <i class="fa-solid fa-ban"></i>
                  </button>
                </form>
                <?php endif; ?>
              </div>
            </td>

          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>