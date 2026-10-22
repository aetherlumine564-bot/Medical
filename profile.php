<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$db     = getDB();
$userId = $_SESSION['user_id'];
$errors = [];

$userStmt = $db->prepare('SELECT u.*, r.name AS role_name, r.slug AS role_slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id=?');
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

// Obtener datos del paciente (si aplica)
$patientWa = null;
if (!empty($user['role_slug']) && $user['role_slug'] === 'patient') {
    $stmt = $db->prepare('SELECT id, phone FROM patients WHERE user_id=? LIMIT 1');
    $stmt->execute([$userId]);
    $patientWa = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $d      = $_POST;
    $errors = validateRequired(['name'=>'Nombre'], $d);

    if (empty($errors)) {
        $sql    = 'UPDATE users SET name=?, phone=?, updated_at=NOW() WHERE id=?';
        $params = [trim($d['name']), trim($d['phone']??''), $userId];
        if (!empty($d['password'])) {
            if (strlen($d['password']) < 6) {
                $errors['password'] = 'Mínimo 6 caracteres.';
            } else {
                $sql    = 'UPDATE users SET name=?, phone=?, password=?, updated_at=NOW() WHERE id=?';
                array_splice($params, 2, 0, [password_hash($d['password'], PASSWORD_BCRYPT)]);
            }
        }
        if (empty($errors)) {
            $db->prepare($sql)->execute($params);
            $_SESSION['user_name'] = trim($d['name']);

            // Actualizar teléfono del paciente
            if ($patientWa && !empty($d['phone'])) {
                $db->prepare('UPDATE patients SET phone=? WHERE id=?')
                   ->execute([
                       preg_replace('/\D/', '', $d['phone']??''),
                       $patientWa['id'],
                   ]);
            }

            setFlash('success','Perfil actualizado correctamente.');
            redirect('profile.php');
        }
    }
}

$pageTitle   = 'Mi Perfil';
$breadcrumbs = [['label'=>'Mi perfil']];
include __DIR__ . '/includes/header.php';
?>
<div class="page-header"><h1 class="page-title">Mi Perfil</h1></div>

<div style="max-width:620px;">
  <div class="card">
    <div class="card-header"><span class="card-title">Información personal</span></div>
    <div class="card-body">
      <div style="display:flex;align-items:center;gap:1.25rem;margin-bottom:1.5rem;">
        <div style="width:68px;height:68px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.6rem;font-weight:700;">
          <?= avatarInitials($user['name']) ?>
        </div>
        <div>
          <div style="font-size:1.1rem;font-weight:700;"><?= e($user['name']) ?></div>
          <div style="color:var(--text-muted);font-size:.85rem;"><?= e($user['email']) ?></div>
          <span class="badge badge-primary" style="margin-top:.25rem;"><?= e($user['role_name']) ?></span>
        </div>
      </div>

      <form method="POST">
        <?= csrfField() ?>
        <div class="form-grid form-grid-2">
          <div class="form-group">
            <label>Nombre <span class="required">*</span></label>
            <input type="text" name="name" class="form-control" value="<?= e($_POST['name'] ?? $user['name']) ?>">
            <?php if(isset($errors['name'])): ?><span class="form-error"><?= e($errors['name']) ?></span><?php endif; ?>
          </div>
          <div class="form-group">
            <label>Teléfono</label>
            <input type="text" name="phone" class="form-control" value="<?= e($_POST['phone'] ?? $user['phone']) ?>">
          </div>
          <div class="form-group full-width">
            <label>Correo electrónico</label>
            <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled>
            <span class="form-hint">El correo no puede modificarse desde aquí</span>
          </div>
          <?php if ($patientWa !== null): ?>
          <div class="form-group">
            <label><i class="fa-solid fa-phone" style="color:var(--primary);"></i> Teléfono de contacto</label>
            <input type="text" name="phone" class="form-control"
                   value="<?= e($_POST['phone'] ?? $patientWa['phone'] ?? '') ?>"
                   placeholder="Ej: 18091234567">
            <span class="form-hint">
              <i class="fa-solid fa-circle-info" style="color:var(--primary);"></i>
              Número de teléfono para recordatorios de citas.
            </span>
          </div>
          <?php endif; ?>
          <div class="form-group full-width">
            <hr class="divider">
            <label>Nueva contraseña</label>
            <input type="password" name="password" class="form-control" placeholder="Dejar en blanco para no cambiar">
            <?php if(isset($errors['password'])): ?><span class="form-error"><?= e($errors['password']) ?></span><?php endif; ?>
          </div>
        </div>
        <div style="margin-top:1rem;">
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
