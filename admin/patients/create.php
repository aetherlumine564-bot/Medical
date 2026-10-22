<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requirePermission('patients.create');

$db     = getDB();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $d      = $_POST;
    $errors = validateRequired(['name'=>'Nombre','email'=>'Correo','password'=>'Contraseña'], $d);

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
            // Crear usuario
            $patientRoleId = $db->query("SELECT id FROM roles WHERE slug='patient' LIMIT 1")->fetchColumn();
            $db->prepare(
                'INSERT INTO users (name, email, password, phone, role_id) VALUES (?,?,?,?,?)'
            )->execute([
                trim($d['name']), trim($d['email']),
                password_hash($d['password'], PASSWORD_BCRYPT),
                trim($d['phone'] ?? ''),
                $patientRoleId,
            ]);
            $userId = $db->lastInsertId();

            // Crear perfil de paciente
            $db->prepare(
                'INSERT INTO patients (user_id, dni, birth_date, gender, blood_type, address, city,
                 occupation, emergency_contact, emergency_phone, allergies, chronic_diseases, notes)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $userId,
                trim($d['dni'] ?? ''), $d['birth_date'] ?: null, $d['gender'] ?: null,
                $d['blood_type'] ?: null, trim($d['address'] ?? ''), trim($d['city'] ?? ''),
                trim($d['occupation'] ?? ''), trim($d['emergency_contact'] ?? ''),
                trim($d['emergency_phone'] ?? ''), trim($d['allergies'] ?? ''),
                trim($d['chronic_diseases'] ?? ''), trim($d['notes'] ?? ''),
            ]);

            $db->commit();
            setFlash('success', "Paciente «{$d['name']}» registrado correctamente.");
            redirect('admin/patients/index.php');
        } catch (Exception $e) {
            $db->rollBack();
            $errors['general'] = 'Error al guardar: ' . $e->getMessage();
        }
    }
}

$pageTitle   = 'Nuevo Paciente';
$breadcrumbs = [['label'=>'Pacientes','url'=>'admin/patients/index.php'],['label'=>'Nuevo paciente']];
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title">Nuevo Paciente</h1>
</div>

<form method="POST">
  <?= csrfField() ?>
  <?php if (!empty($errors['general'])): ?>
  <div class="alert alert-error"><i class="fa-solid fa-circle-xmark"></i><span><?= e($errors['general']) ?></span></div>
  <?php endif; ?>

  <!-- Tabs -->
  <div class="tabs">
    <button type="button" class="tab-btn" data-tab="tab-account">Cuenta</button>
    <button type="button" class="tab-btn" data-tab="tab-personal">Datos personales</button>
    <button type="button" class="tab-btn" data-tab="tab-medical">Info médica</button>
  </div>

  <!-- Tab: Cuenta -->
  <div class="tab-content" id="tab-account">
    <div class="card">
      <div class="card-header"><span class="card-title">Información de acceso</span></div>
      <div class="card-body">
        <div class="form-grid form-grid-2">
          <div class="form-group">
            <label>Nombre completo <span class="required">*</span></label>
            <input type="text" name="name" class="form-control <?= isset($errors['name'])?'is-invalid':'' ?>"
                   value="<?= e($_POST['name']??'') ?>">
            <?php if(isset($errors['name'])): ?><span class="form-error"><?= e($errors['name']) ?></span><?php endif; ?>
          </div>
          <div class="form-group">
            <label>Correo electrónico <span class="required">*</span></label>
            <input type="email" name="email" class="form-control <?= isset($errors['email'])?'is-invalid':'' ?>"
                   value="<?= e($_POST['email']??'') ?>">
            <?php if(isset($errors['email'])): ?><span class="form-error"><?= e($errors['email']) ?></span><?php endif; ?>
          </div>
          <div class="form-group">
            <label>Contraseña <span class="required">*</span></label>
            <input type="password" name="password" class="form-control <?= isset($errors['password'])?'is-invalid':'' ?>"
                   placeholder="Mínimo 6 caracteres">
            <?php if(isset($errors['password'])): ?><span class="form-error"><?= e($errors['password']) ?></span><?php endif; ?>
          </div>
          <div class="form-group">
            <label>Teléfono</label>
            <input type="text" name="phone" class="form-control" value="<?= e($_POST['phone']??'') ?>">
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Tab: Personal -->
  <div class="tab-content" id="tab-personal">
    <div class="card">
      <div class="card-header"><span class="card-title">Datos personales</span></div>
      <div class="card-body">
        <div class="form-grid form-grid-3">
          <div class="form-group">
            <label>DNI / Documento</label>
            <input type="text" name="dni" class="form-control" value="<?= e($_POST['dni']??'') ?>">
          </div>
          <div class="form-group">
            <label>Fecha de nacimiento</label>
            <input type="date" name="birth_date" class="form-control" value="<?= e($_POST['birth_date']??'') ?>">
          </div>
          <div class="form-group">
            <label>Género</label>
            <select name="gender" class="form-control">
              <option value="">-- Selecciona --</option>
              <option value="M" <?= ($_POST['gender']??'')==='M'?'selected':'' ?>>Masculino</option>
              <option value="F" <?= ($_POST['gender']??'')==='F'?'selected':'' ?>>Femenino</option>
              <option value="O" <?= ($_POST['gender']??'')==='O'?'selected':'' ?>>Otro</option>
            </select>
          </div>
          <div class="form-group full-width">
            <label>Dirección</label>
            <input type="text" name="address" class="form-control" value="<?= e($_POST['address']??'') ?>">
          </div>
          <div class="form-group">
            <label>Ciudad</label>
            <input type="text" name="city" class="form-control" value="<?= e($_POST['city']??'') ?>">
          </div>
          <div class="form-group">
            <label>Ocupación</label>
            <input type="text" name="occupation" class="form-control" value="<?= e($_POST['occupation']??'') ?>">
          </div>
          <div class="form-group">
            <label>Contacto de emergencia</label>
            <input type="text" name="emergency_contact" class="form-control" value="<?= e($_POST['emergency_contact']??'') ?>">
          </div>
          <div class="form-group">
            <label>Teléfono de emergencia</label>
            <input type="text" name="emergency_phone" class="form-control" value="<?= e($_POST['emergency_phone']??'') ?>">
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Tab: Médica -->
  <div class="tab-content" id="tab-medical">
    <div class="card">
      <div class="card-header"><span class="card-title">Información médica</span></div>
      <div class="card-body">
        <div class="form-grid form-grid-2">
          <div class="form-group">
            <label>Grupo sanguíneo</label>
            <select name="blood_type" class="form-control">
              <option value="">-- Selecciona --</option>
              <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
              <option value="<?= $bt ?>" <?= ($_POST['blood_type']??'')===$bt?'selected':'' ?>><?= $bt ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>&nbsp;</label>
            <span class="form-hint">El grupo sanguíneo es importante para emergencias médicas</span>
          </div>
          <div class="form-group full-width">
            <label>Alergias conocidas</label>
            <textarea name="allergies" class="form-control" rows="3" placeholder="Ej: Penicilina, látex..."><?= e($_POST['allergies']??'') ?></textarea>
          </div>
          <div class="form-group full-width">
            <label>Enfermedades crónicas</label>
            <textarea name="chronic_diseases" class="form-control" rows="3" placeholder="Ej: Diabetes tipo 2, hipertensión..."><?= e($_POST['chronic_diseases']??'') ?></textarea>
          </div>
          <div class="form-group full-width">
            <label>Notas adicionales</label>
            <textarea name="notes" class="form-control" rows="3"><?= e($_POST['notes']??'') ?></textarea>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div style="display:flex;gap:.75rem;margin-top:1rem;">
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Registrar paciente</button>
    <a href="index.php" class="btn btn-secondary">Cancelar</a>
  </div>
</form>

<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
