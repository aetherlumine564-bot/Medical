<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requirePermission('users.create');

$db     = getDB();
$errors = [];
$roles  = $db->query('SELECT * FROM roles ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $data   = $_POST;
    $errors = validateRequired(['name'=>'Nombre','email'=>'Correo','password'=>'Contraseña','role_id'=>'Rol'], $data);

    if (empty($errors['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL))
        $errors['email'] = 'Correo inválido.';

    if (empty($errors['email'])) {
        $chk = $db->prepare('SELECT id FROM users WHERE email = ?');
        $chk->execute([trim($data['email'])]);
        if ($chk->fetch()) $errors['email'] = 'El correo ya está registrado.';
    }

    if (empty($errors['password']) && strlen($data['password']) < 6)
        $errors['password'] = 'Mínimo 6 caracteres.';

    if (empty($errors)) {
        $hash = password_hash($data['password'], PASSWORD_BCRYPT);
        $db->prepare(
            'INSERT INTO users (name, email, password, phone, role_id, status) VALUES (?,?,?,?,?,?)'
        )->execute([
            trim($data['name']),
            trim($data['email']),
            $hash,
            trim($data['phone'] ?? ''),
            (int)$data['role_id'],
            isset($data['status']) ? 1 : 0,
        ]);
        setFlash('success', "Usuario «{$data['name']}» creado correctamente.");
        redirect('admin/users/index.php');
    }
}

$pageTitle   = 'Nuevo Usuario';
$breadcrumbs = [['label'=>'Usuarios','url'=>'admin/users/index.php'],['label'=>'Nuevo usuario']];
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title">Nuevo Usuario</h1>
</div>

<div style="max-width:760px;">
<form method="POST">
  <?= csrfField() ?>
  <div class="card">
    <div class="card-header"><span class="card-title">Información de la cuenta</span></div>
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
        <div class="form-group">
          <label>Rol <span class="required">*</span></label>
          <select name="role_id" class="form-control <?= isset($errors['role_id'])?'is-invalid':'' ?>">
            <option value="">-- Selecciona un rol --</option>
            <?php foreach ($roles as $r): ?>
            <option value="<?= $r['id'] ?>" <?= ($_POST['role_id']??'') == $r['id'] ? 'selected':'' ?>>
              <?= e($r['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <?php if(isset($errors['role_id'])): ?><span class="form-error"><?= e($errors['role_id']) ?></span><?php endif; ?>
        </div>
        <div class="form-group">
          <label>&nbsp;</label>
          <label class="form-check">
            <input type="checkbox" name="status" value="1" <?= ($_POST['status']??'1')?'checked':'' ?>>
            <span>Usuario activo</span>
          </label>
        </div>
      </div>
    </div>
  </div>
  <div style="display:flex;gap:.75rem;margin-top:1rem;">
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Crear usuario</button>
    <a href="index.php" class="btn btn-secondary">Cancelar</a>
  </div>
</form>
</div>

<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
