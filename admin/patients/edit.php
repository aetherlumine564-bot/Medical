<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requirePermission('patients.edit');

$db     = getDB();
$id     = (int)($_GET['id'] ?? 0);
$errors = [];

$stmt = $db->prepare(
    'SELECT p.*, u.name, u.email, u.phone, u.status FROM patients p JOIN users u ON u.id = p.user_id WHERE p.id = ?'
);
$stmt->execute([$id]);
$patient = $stmt->fetch();
if (!$patient) { setFlash('error','Paciente no encontrado.'); redirect('admin/patients/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $d      = $_POST;
    $errors = validateRequired(['name'=>'Nombre','email'=>'Correo'], $d);

    if (empty($errors['email']) && !filter_var($d['email'], FILTER_VALIDATE_EMAIL))
        $errors['email'] = 'Correo inválido.';
    if (empty($errors['email'])) {
        $chk = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $chk->execute([trim($d['email']), $patient['user_id']]);
        if ($chk->fetch()) $errors['email'] = 'El correo ya está en uso.';
    }

    if (empty($errors)) {
        $db->beginTransaction();
        try {
            $sqlUser = 'UPDATE users SET name=?, email=?, phone=?, status=?, updated_at=NOW() WHERE id=?';
            $paramsUser = [trim($d['name']), trim($d['email']), trim($d['phone']??''), isset($d['status'])?1:0, $patient['user_id']];
            if (!empty($d['password']) && strlen($d['password']) >= 6) {
                $sqlUser = 'UPDATE users SET name=?, email=?, phone=?, status=?, password=?, updated_at=NOW() WHERE id=?';
                array_splice($paramsUser, 4, 0, [password_hash($d['password'], PASSWORD_BCRYPT)]);
            }
            $db->prepare($sqlUser)->execute($paramsUser);

            $db->prepare(
                'UPDATE patients SET dni=?, birth_date=?, gender=?, blood_type=?, address=?, city=?,
                 occupation=?, emergency_contact=?, emergency_phone=?, allergies=?, chronic_diseases=?, notes=?,
                 phone=?, updated_at=NOW() WHERE id=?'
            )->execute([
                trim($d['dni']??''), $d['birth_date']?:null, $d['gender']?:null,
                $d['blood_type']?:null, trim($d['address']??''), trim($d['city']??''),
                trim($d['occupation']??''), trim($d['emergency_contact']??''),
                trim($d['emergency_phone']??''), trim($d['allergies']??''),
                trim($d['chronic_diseases']??''), trim($d['notes']??''),
                preg_replace('/\D/', '', $d['phone_wa']??$d['phone']??''), $id,
            ]);
            $db->commit();
            setFlash('success','Paciente actualizado correctamente.');
            redirect('admin/patients/index.php');
        } catch (Exception $e) {
            $db->rollBack();
            $errors['general'] = $e->getMessage();
        }
    }
}

$f = $_POST ?: $patient;
$pageTitle   = 'Editar Paciente';
$breadcrumbs = [['label'=>'Pacientes','url'=>'admin/patients/index.php'],['label'=>'Editar']];
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title">Editar: <?= e($patient['name']) ?></h1>
</div>

<form method="POST">
  <?= csrfField() ?>
  <?php if (!empty($errors['general'])): ?>
  <div class="alert alert-error"><i class="fa-solid fa-circle-xmark"></i><span><?= e($errors['general']) ?></span></div>
  <?php endif; ?>

  <div class="tabs">
    <button type="button" class="tab-btn" data-tab="edt-account">Cuenta</button>
    <button type="button" class="tab-btn" data-tab="edt-personal">Datos personales</button>
    <button type="button" class="tab-btn" data-tab="edt-medical">Info médica</button>
  </div>

  <!-- Cuenta -->
  <div class="tab-content" id="edt-account">
    <div class="card"><div class="card-body">
      <div class="form-grid form-grid-2">
        <div class="form-group">
          <label>Nombre <span class="required">*</span></label>
          <input type="text" name="name" class="form-control" value="<?= e($f['name']) ?>">
        </div>
        <div class="form-group">
          <label>Correo <span class="required">*</span></label>
          <input type="email" name="email" class="form-control" value="<?= e($f['email']) ?>">
          <?php if(isset($errors['email'])): ?><span class="form-error"><?= e($errors['email']) ?></span><?php endif; ?>
        </div>
        <div class="form-group">
          <label>Nueva contraseña</label>
          <input type="password" name="password" class="form-control" placeholder="Dejar en blanco para no cambiar">
        </div>
        <div class="form-group">
          <label>Teléfono</label>
          <input type="text" name="phone" class="form-control" value="<?= e($f['phone']??'') ?>">
        </div>
        <div class="form-group">
          <label><i class="fa-solid fa-phone" style="color:var(--primary);"></i> Teléfono</label>
          <input type="text" name="phone_wa" class="form-control"
                 value="<?= e($f['phone']??'') ?>"
                 placeholder="Ej: 18091234567 (número internacional)">
          <small class="form-hint">Número de teléfono del paciente.</small>
        </div>
        <div class="form-group">
          <label class="form-check">
            <input type="checkbox" name="status" value="1" <?= $f['status']?'checked':'' ?>>
            <span>Cuenta activa</span>
          </label>
        </div>
      </div>
    </div></div>
  </div>

  <!-- Personal -->
  <div class="tab-content" id="edt-personal">
    <div class="card"><div class="card-body">
      <div class="form-grid form-grid-3">
        <div class="form-group"><label>DNI</label><input type="text" name="dni" class="form-control" value="<?= e($f['dni']??'') ?>"></div>
        <div class="form-group"><label>Nacimiento</label><input type="date" name="birth_date" class="form-control" value="<?= e($f['birth_date']??'') ?>"></div>
        <div class="form-group">
          <label>Género</label>
          <select name="gender" class="form-control">
            <option value="">--</option>
            <option value="M" <?= ($f['gender']??'')==='M'?'selected':'' ?>>Masculino</option>
            <option value="F" <?= ($f['gender']??'')==='F'?'selected':'' ?>>Femenino</option>
            <option value="O" <?= ($f['gender']??'')==='O'?'selected':'' ?>>Otro</option>
          </select>
        </div>
        <div class="form-group full-width"><label>Dirección</label><input type="text" name="address" class="form-control" value="<?= e($f['address']??'') ?>"></div>
        <div class="form-group"><label>Ciudad</label><input type="text" name="city" class="form-control" value="<?= e($f['city']??'') ?>"></div>
        <div class="form-group"><label>Ocupación</label><input type="text" name="occupation" class="form-control" value="<?= e($f['occupation']??'') ?>"></div>
        <div class="form-group"><label>Contacto emergencia</label><input type="text" name="emergency_contact" class="form-control" value="<?= e($f['emergency_contact']??'') ?>"></div>
        <div class="form-group"><label>Teléfono emergencia</label><input type="text" name="emergency_phone" class="form-control" value="<?= e($f['emergency_phone']??'') ?>"></div>
      </div>
    </div></div>
  </div>

  <!-- Médica -->
  <div class="tab-content" id="edt-medical">
    <div class="card"><div class="card-body">
      <div class="form-grid form-grid-2">
        <div class="form-group">
          <label>Grupo sanguíneo</label>
          <select name="blood_type" class="form-control">
            <option value="">--</option>
            <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
            <option value="<?= $bt ?>" <?= ($f['blood_type']??'')===$bt?'selected':'' ?>><?= $bt ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div></div>
        <div class="form-group full-width"><label>Alergias</label><textarea name="allergies" class="form-control" rows="3"><?= e($f['allergies']??'') ?></textarea></div>
        <div class="form-group full-width"><label>Enfermedades crónicas</label><textarea name="chronic_diseases" class="form-control" rows="3"><?= e($f['chronic_diseases']??'') ?></textarea></div>
        <div class="form-group full-width"><label>Notas</label><textarea name="notes" class="form-control" rows="3"><?= e($f['notes']??'') ?></textarea></div>
      </div>
    </div></div>
  </div>

  <div style="display:flex;gap:.75rem;margin-top:1rem;">
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
    <a href="index.php" class="btn btn-secondary">Cancelar</a>
  </div>
</form>

<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
