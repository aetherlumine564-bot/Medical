<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requirePermission('doctors.create');

$db          = getDB();
$errors      = [];
$specialties = $db->query('SELECT * FROM specialties ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $d      = $_POST;
    $errors = validateRequired([
        'name'           => 'Nombre',
        'email'          => 'Correo',
        'password'       => 'Contraseña',
        'specialty_id'   => 'Especialidad',
        'license_number' => 'Número de licencia',
    ], $d);

    if (empty($errors['email']) && !filter_var($d['email'], FILTER_VALIDATE_EMAIL))
        $errors['email'] = 'Correo inválido.';
    if (empty($errors['email'])) {
        $chk = $db->prepare('SELECT id FROM users WHERE email = ?');
        $chk->execute([trim($d['email'])]);
        if ($chk->fetch()) $errors['email'] = 'El correo ya está registrado.';
    }
    if (empty($errors['password']) && strlen($d['password']) < 6)
        $errors['password'] = 'Mínimo 6 caracteres.';

    if (empty($errors)) {
        $db->beginTransaction();
        try {
            $doctorRoleId = $db->query("SELECT id FROM roles WHERE slug='doctor' LIMIT 1")->fetchColumn();
            $db->prepare(
                'INSERT INTO users (name, email, password, phone, role_id) VALUES (?,?,?,?,?)'
            )->execute([
                trim($d['name']), trim($d['email']),
                password_hash($d['password'], PASSWORD_BCRYPT),
                trim($d['phone'] ?? ''), $doctorRoleId,
            ]);
            $userId = $db->lastInsertId();

            $db->prepare(
                'INSERT INTO doctors (user_id, specialty_id, license_number, office, bio, consultation_fee, status)
                 VALUES (?,?,?,?,?,?,?)'
            )->execute([
                $userId, (int)$d['specialty_id'], trim($d['license_number']),
                trim($d['office'] ?? ''), trim($d['bio'] ?? ''),
                (float)($d['consultation_fee'] ?? 0),
                isset($d['status']) ? 1 : 0,
            ]);
            $db->commit();
            setFlash('success', "Doctor «{$d['name']}» registrado correctamente.");
            redirect('admin/doctors/index.php');
        } catch (Exception $e) {
            $db->rollBack();
            $errors['general'] = $e->getMessage();
        }
    }
}

$pageTitle   = 'Nuevo Doctor';
$breadcrumbs = [['label'=>'Doctores','url'=>'admin/doctors/index.php'],['label'=>'Nuevo doctor']];
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title">Nuevo Doctor</h1>
</div>

<form method="POST">
  <?= csrfField() ?>
  <?php if (!empty($errors['general'])): ?><div class="alert alert-error"><i class="fa-solid fa-circle-xmark"></i><span><?= e($errors['general']) ?></span></div><?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 360px;gap:1.25rem;">

    <div style="display:flex;flex-direction:column;gap:1rem;">

      <!-- Datos de cuenta -->
      <div class="card">
        <div class="card-header"><span class="card-title">Cuenta de acceso</span></div>
        <div class="card-body">
          <div class="form-grid form-grid-2">
            <div class="form-group">
              <label>Nombre completo <span class="required">*</span></label>
              <input type="text" name="name" class="form-control <?= isset($errors['name'])?'is-invalid':'' ?>" value="<?= e($_POST['name']??'') ?>">
              <?php if(isset($errors['name'])): ?><span class="form-error"><?= e($errors['name']) ?></span><?php endif; ?>
            </div>
            <div class="form-group">
              <label>Correo <span class="required">*</span></label>
              <input type="email" name="email" class="form-control <?= isset($errors['email'])?'is-invalid':'' ?>" value="<?= e($_POST['email']??'') ?>">
              <?php if(isset($errors['email'])): ?><span class="form-error"><?= e($errors['email']) ?></span><?php endif; ?>
            </div>
            <div class="form-group">
              <label>Contraseña <span class="required">*</span></label>
              <input type="password" name="password" class="form-control <?= isset($errors['password'])?'is-invalid':'' ?>" placeholder="Mínimo 6 caracteres">
              <?php if(isset($errors['password'])): ?><span class="form-error"><?= e($errors['password']) ?></span><?php endif; ?>
            </div>
            <div class="form-group">
              <label>Teléfono</label>
              <input type="text" name="phone" class="form-control" value="<?= e($_POST['phone']??'') ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- Datos profesionales -->
      <div class="card">
        <div class="card-header"><span class="card-title">Datos profesionales</span></div>
        <div class="card-body">
          <div class="form-grid form-grid-2">
            <div class="form-group">
              <label>Especialidad <span class="required">*</span></label>
              <select name="specialty_id" class="form-control <?= isset($errors['specialty_id'])?'is-invalid':'' ?>">
                <option value="">-- Selecciona --</option>
                <?php foreach ($specialties as $s): ?>
                <option value="<?= $s['id'] ?>" <?= ($_POST['specialty_id']??'') == $s['id']?'selected':'' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if(isset($errors['specialty_id'])): ?><span class="form-error"><?= e($errors['specialty_id']) ?></span><?php endif; ?>
            </div>
            <div class="form-group">
              <label>N° de licencia / CMP <span class="required">*</span></label>
              <input type="text" name="license_number" class="form-control <?= isset($errors['license_number'])?'is-invalid':'' ?>"
                     value="<?= e($_POST['license_number']??'') ?>" placeholder="Ej: CMP-12345">
              <?php if(isset($errors['license_number'])): ?><span class="form-error"><?= e($errors['license_number']) ?></span><?php endif; ?>
            </div>
            <div class="form-group">
              <label>Consultorio</label>
              <input type="text" name="office" class="form-control" value="<?= e($_POST['office']??'') ?>" placeholder="Ej: Consultorio 101">
            </div>
            <div class="form-group">
              <label>Tarifa de consulta (S/)</label>
              <input type="number" name="consultation_fee" class="form-control" step="0.50" min="0"
                     value="<?= e($_POST['consultation_fee']??'0') ?>">
            </div>
            <div class="form-group full-width">
              <label>Biografía / Descripción</label>
              <textarea name="bio" class="form-control" rows="4" placeholder="Años de experiencia, logros, especializaciones..."><?= e($_POST['bio']??'') ?></textarea>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Sidebar -->
    <div style="display:flex;flex-direction:column;gap:1rem;">
      <div class="card">
        <div class="card-header"><span class="card-title">Estado</span></div>
        <div class="card-body">
          <label class="form-check">
            <input type="checkbox" name="status" value="1" checked>
            <span>Doctor activo</span>
          </label>
          <div class="divider"></div>
          <button type="submit" class="btn btn-primary w-full" style="justify-content:center;">
            <i class="fa-solid fa-floppy-disk"></i> Registrar doctor
          </button>
          <a href="index.php" class="btn btn-secondary w-full mt-1" style="justify-content:center;">Cancelar</a>
        </div>
      </div>
    </div>
  </div>
</form>

<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
