<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requirePermission('doctors.edit');

$db          = getDB();
$id          = (int)($_GET['id'] ?? 0);
$errors      = [];
$specialties = $db->query('SELECT * FROM specialties ORDER BY name')->fetchAll();

$stmt = $db->prepare(
    'SELECT d.*, u.name, u.email, u.phone, u.status AS user_status FROM doctors d JOIN users u ON u.id = d.user_id WHERE d.id = ?'
);
$stmt->execute([$id]);
$doc = $stmt->fetch();
if (!$doc) { setFlash('error','Doctor no encontrado.'); redirect('admin/doctors/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $d      = $_POST;
    $errors = validateRequired(['name'=>'Nombre','email'=>'Correo','specialty_id'=>'Especialidad','license_number'=>'Licencia'], $d);

    if (empty($errors['email']) && !filter_var($d['email'], FILTER_VALIDATE_EMAIL))
        $errors['email'] = 'Correo inválido.';
    if (empty($errors['email'])) {
        $chk = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $chk->execute([trim($d['email']), $doc['user_id']]);
        if ($chk->fetch()) $errors['email'] = 'Correo ya en uso.';
    }

    if (empty($errors)) {
        $db->beginTransaction();
        try {
            $sqlUser = 'UPDATE users SET name=?, email=?, phone=?, status=?, updated_at=NOW() WHERE id=?';
            $paramsUser = [trim($d['name']), trim($d['email']), trim($d['phone']??''), isset($d['user_status'])?1:0, $doc['user_id']];
            if (!empty($d['password']) && strlen($d['password']) >= 6) {
                $sqlUser = 'UPDATE users SET name=?, email=?, phone=?, status=?, password=?, updated_at=NOW() WHERE id=?';
                array_splice($paramsUser, 4, 0, [password_hash($d['password'], PASSWORD_BCRYPT)]);
            }
            $db->prepare($sqlUser)->execute($paramsUser);
            $db->prepare(
                'UPDATE doctors SET specialty_id=?, license_number=?, office=?, bio=?, consultation_fee=?,
                 phone=?, status=?, updated_at=NOW() WHERE id=?'
            )->execute([
                (int)$d['specialty_id'], trim($d['license_number']), trim($d['office']??''),
                trim($d['bio']??''), (float)($d['consultation_fee']??0),
                preg_replace('/\D/', '', $d['phone_wa']??$d['phone']??''),
                isset($d['status'])?1:0, $id,
            ]);
            $db->commit();
            setFlash('success','Doctor actualizado correctamente.');
            redirect('admin/doctors/index.php');
        } catch (Exception $e) {
            $db->rollBack();
            $errors['general'] = $e->getMessage();
        }
    }
}

$f = $_POST ?: $doc;
$pageTitle   = 'Editar Doctor';
$breadcrumbs = [['label'=>'Doctores','url'=>'admin/doctors/index.php'],['label'=>'Editar']];
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title">Editar: <?= e($doc['name']) ?></h1>
</div>

<form method="POST">
  <?= csrfField() ?>
  <?php if (!empty($errors['general'])): ?><div class="alert alert-error"><i class="fa-solid fa-circle-xmark"></i><span><?= e($errors['general']) ?></span></div><?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 360px;gap:1.25rem;">
    <div style="display:flex;flex-direction:column;gap:1rem;">
      <div class="card">
        <div class="card-header"><span class="card-title">Cuenta</span></div>
        <div class="card-body">
          <div class="form-grid form-grid-2">
            <div class="form-group"><label>Nombre <span class="required">*</span></label><input type="text" name="name" class="form-control" value="<?= e($f['name']) ?>"></div>
            <div class="form-group"><label>Correo <span class="required">*</span></label><input type="email" name="email" class="form-control" value="<?= e($f['email']) ?>">
              <?php if(isset($errors['email'])): ?><span class="form-error"><?= e($errors['email']) ?></span><?php endif; ?>
            </div>
            <div class="form-group"><label>Nueva contraseña</label><input type="password" name="password" class="form-control" placeholder="Dejar en blanco para no cambiar"></div>
            <div class="form-group"><label>Teléfono</label><input type="text" name="phone" class="form-control" value="<?= e($f['phone']??'') ?>"></div>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><span class="card-title">Datos profesionales</span></div>
        <div class="card-body">
          <div class="form-grid form-grid-2">
            <div class="form-group">
              <label>Especialidad <span class="required">*</span></label>
              <select name="specialty_id" class="form-control">
                <?php foreach ($specialties as $s): ?>
                <option value="<?= $s['id'] ?>" <?= ($f['specialty_id']??$doc['specialty_id']) == $s['id']?'selected':'' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group"><label>Licencia <span class="required">*</span></label><input type="text" name="license_number" class="form-control" value="<?= e($f['license_number']) ?>"></div>
            <div class="form-group"><label>Consultorio</label><input type="text" name="office" class="form-control" value="<?= e($f['office']??'') ?>"></div>
            <div class="form-group"><label>Tarifa (S/)</label><input type="number" name="consultation_fee" class="form-control" step="0.50" min="0" value="<?= e($f['consultation_fee']??0) ?>"></div>
            <div class="form-group">
              <label><i class="fa-solid fa-phone" style="color:var(--primary);"></i> Teléfono</label>
              <input type="text" name="phone_wa" class="form-control"
                     value="<?= e($f['phone']??'') ?>"
                     placeholder="Ej: 18091234567 (número internacional)">
              <small class="form-hint">Teléfono de contacto del doctor.</small>
            </div>
            <div class="form-group full-width"><label>Biografía</label><textarea name="bio" class="form-control" rows="4"><?= e($f['bio']??'') ?></textarea></div>
          </div>
        </div>
      </div>
    </div>
    <div class="card" style="align-self:start;">
      <div class="card-header"><span class="card-title">Estado</span></div>
      <div class="card-body">
        <label class="form-check mb-1"><input type="checkbox" name="status" value="1" <?= ($f['status']??$doc['status'])?'checked':'' ?>><span>Doctor activo</span></label>
        <label class="form-check"><input type="checkbox" name="user_status" value="1" <?= ($f['user_status']??$doc['user_status'])?'checked':'' ?>><span>Cuenta activa</span></label>
        <div class="divider"></div>
        <button type="submit" class="btn btn-primary w-full" style="justify-content:center;"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
        <a href="index.php" class="btn btn-secondary w-full mt-1" style="justify-content:center;">Cancelar</a>
      </div>
    </div>
  </div>
</form>

<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
