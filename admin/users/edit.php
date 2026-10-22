<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
requirePermission('users.edit');

$db     = getDB();
$id     = (int)($_GET['id'] ?? 0);
$errors = [];
$roles  = $db->query('SELECT * FROM roles ORDER BY name')->fetchAll();

$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) { setFlash('error','Usuario no encontrado.'); redirect('admin/users/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $data   = $_POST;
    $errors = validateRequired(['name'=>'Nombre','email'=>'Correo','role_id'=>'Rol'], $data);

    if (empty($errors['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL))
        $errors['email'] = 'Correo inválido.';

    if (empty($errors['email'])) {
        $chk = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $chk->execute([trim($data['email']), $id]);
        if ($chk->fetch()) $errors['email'] = 'El correo ya está en uso.';
    }

    if (!empty($data['password']) && strlen($data['password']) < 6)
        $errors['password'] = 'Mínimo 6 caracteres.';

    if (empty($errors)) {
        $params = [
            trim($data['name']),
            trim($data['email']),
            trim($data['phone'] ?? ''),
            (int)$data['role_id'],
            isset($data['status']) ? 1 : 0,
            $id,
        ];
        $sql = 'UPDATE users SET name=?, email=?, phone=?, role_id=?, status=?';
        if (!empty($data['password'])) {
            $sql .= ', password=?';
            array_splice($params, 5, 0, [password_hash($data['password'], PASSWORD_BCRYPT)]);
        }
        $sql .= ', updated_at=NOW() WHERE id=?';
        $db->prepare($sql)->execute($params);

        setFlash('success', "Usuario actualizado correctamente.");
        redirect('admin/users/index.php');
    }
}

$pageTitle   = 'Editar Usuario';
$breadcrumbs = [['label'=>'Usuarios','url'=>'admin/users/index.php'],['label'=>'Editar']];
include dirname(dirname(__DIR__)) . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title">Editar: <?= e($user['name']) ?></h1>
</div>

<div style="max-width:760px;">
<form method="POST">
  <?= csrfField() ?>
  <div class="card">
    <div class="card-header"><span class="card-title">Datos del usuario</span></div>
    <div class="card-body">
      <div class="form-grid form-grid-2">
        <div class="form-group">
          <label>Nombre completo <span class="required">*</span></label>
          <input type="text" name="name" class="form-control <?= isset($errors['name'])?'is-invalid':'' ?>"
                 value="<?= e($_POST['name'] ?? $user['name']) ?>">
          <?php if(isset($errors['name'])): ?><span class="form-error"><?= e($errors['name']) ?></span><?php endif; ?>
        </div>
        <div class="form-group">
          <label>Correo electrónico <span class="required">*</span></label>
          <input type="email" name="email" class="form-control <?= isset($errors['email'])?'is-invalid':'' ?>"
                 value="<?= e($_POST['email'] ?? $user['email']) ?>">
          <?php if(isset($errors['email'])): ?><span class="form-error"><?= e($errors['email']) ?></span><?php endif; ?>
        </div>
        <div class="form-group">
          <label>Nueva contraseña</label>
          <input type="password" name="password" class="form-control <?= isset($errors['password'])?'is-invalid':'' ?>"
                 placeholder="Dejar en blanco para no cambiar">
          <?php if(isset($errors['password'])): ?><span class="form-error"><?= e($errors['password']) ?></span><?php endif; ?>
        </div>
        <div class="form-group">
          <label>Teléfono</label>
          <input type="text" name="phone" class="form-control" value="<?= e($_POST['phone'] ?? $user['phone']) ?>">
        </div>
        <div class="form-group">
          <label>Rol <span class="required">*</span></label>
          <select name="role_id" class="form-control">
            <?php foreach ($roles as $r): ?>
            <option value="<?= $r['id'] ?>" <?= ($_POST['role_id'] ?? $user['role_id']) == $r['id'] ? 'selected':'' ?>>
              <?= e($r['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>&nbsp;</label>
          <label class="form-check">
            <input type="checkbox" name="status" value="1" <?= ($_POST['status'] ?? $user['status']) ? 'checked':'' ?>>
            <span>Usuario activo</span>
          </label>
        </div>
      </div>
    </div>
  </div>
  <div style="display:flex;gap:.75rem;margin-top:1rem;">
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
    <a href="index.php" class="btn btn-secondary">Cancelar</a>
  </div>
</form>
</div>

<?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
