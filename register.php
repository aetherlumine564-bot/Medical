<?php
/**
 * MedCRM – Registro público de pacientes
 * Accesible sin login desde:  /register.php
 */
require_once __DIR__ . '/config/config.php';

// Si ya está logueado redirigir a su panel
if (isLoggedIn()) { redirectAfterLogin(); }

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $d = $_POST;

    // Validaciones
    $name     = trim($d['name']     ?? '');
    $email    = trim($d['email']    ?? '');
    $password = $d['password']      ?? '';
    $confirm  = $d['confirm']       ?? '';
    $phone    = trim($d['phone']    ?? '');
    $gender   = $d['gender']        ?? '';
    $birth    = $d['birth_date']    ?? '';
    $dni      = trim($d['dni']      ?? '');
    $terms    = isset($d['terms']);

    if (empty($name))   $errors['name']     = 'El nombre es requerido.';
    if (empty($email))  $errors['email']    = 'El correo es requerido.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Correo inválido.';
    if (strlen($password) < 6) $errors['password'] = 'Mínimo 6 caracteres.';
    elseif ($password !== $confirm) $errors['confirm'] = 'Las contraseñas no coinciden.';
    if (!$terms) $errors['terms'] = 'Debes aceptar los términos.';

    if (empty($errors)) {
        $db = getDB();

        // Verificar correo único
        $chk = $db->prepare('SELECT id FROM users WHERE email=?');
        $chk->execute([strtolower($email)]);
        if ($chk->fetch()) {
            $errors['email'] = 'Este correo ya está registrado. ¿Olvidaste tu contraseña?';
        }
    }

    if (empty($errors)) {
        $db = getDB();
        $db->beginTransaction();
        try {
            // ── Obtener o crear el rol 'patient' ──────────────────────────────
            $roleRow = $db->query("SELECT id FROM roles WHERE slug='patient' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($roleRow) {
                $roleId = (int)$roleRow['id'];
            } else {
                // El rol no existe aún (instalación parcial): créalo
                $db->exec("INSERT IGNORE INTO roles (name, slug, description, color)
                           VALUES ('Paciente', 'patient', 'Reserva y seguimiento de citas', '#0D9488')");
                $roleId = (int)$db->lastInsertId();
                if (!$roleId) {
                    // Pudo haber sido insertado en otra petición (race condition)
                    $roleId = (int)$db->query("SELECT id FROM roles WHERE slug='patient' LIMIT 1")->fetchColumn();
                }
            }

            if (!$roleId) throw new Exception('No se pudo determinar el rol de paciente.');

            // ── Crear usuario ─────────────────────────────────────────────────
            $db->prepare(
                'INSERT INTO users (name, email, password, phone, role_id, status, created_at)
                 VALUES (?, ?, ?, ?, ?, 1, NOW())'
            )->execute([
                $name,
                strtolower($email),
                password_hash($password, PASSWORD_BCRYPT),
                $phone,
                $roleId,
            ]);
            $userId = (int)$db->lastInsertId();

            // ── Crear perfil de paciente ──────────────────────────────────────
            try {
                $db->prepare(
                    'INSERT INTO patients (user_id, dni, birth_date, gender) VALUES (?, ?, ?, ?)'
                )->execute([
                    $userId,
                    $dni ?: null,
                    $birth ?: null,
                    $gender ?: null,
                ]);
            } catch (Exception $pe) {
                // Si la tabla patients tiene restricciones diferentes, el usuario
                // sigue creado; solo fallará el perfil extendido.
            }

            $db->commit();

            // ── Auto-login ────────────────────────────────────────────────────
            session_regenerate_id(true);
            $_SESSION['user_id']    = $userId;
            $_SESSION['user_name']  = $name;
            $_SESSION['user_email'] = strtolower($email);
            $_SESSION['user_role']  = 'patient';
            $_SESSION['user_avatar']= '';

            try {
                $db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$userId]);
            } catch (Exception $e) { /* no crítico */ }

            setFlash('success', "¡Bienvenido a MedCRM, {$name}! Tu cuenta fue creada exitosamente.");
            redirect('patient/appointments/create.php');

        } catch (Exception $e) {
            $db->rollBack();
            $errors['general'] = 'Error al registrar: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Crear cuenta – <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=5.1">
  <style>
    .register-page {
      min-height: 100vh;
      display: grid;
      grid-template-columns: 380px 1fr;
    }

    /* Panel visual izquierdo */
    .reg-visual {
      background: linear-gradient(145deg, #0D1F3C 0%, #1A56DB 100%);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 2.5rem 2rem;
      position: sticky;
      top: 0;
      height: 100vh;
    }
    .reg-brand-icon {
      width: 64px; height: 64px;
      background: rgba(255,255,255,.15);
      backdrop-filter: blur(8px);
      border-radius: 18px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.6rem; color: #fff;
      margin-bottom: 1.25rem;
      border: 1px solid rgba(255,255,255,.2);
    }
    .reg-brand-name { color: #fff; font-size: 1.5rem; font-weight: 800; margin-bottom: .5rem; }
    .reg-brand-sub  { color: rgba(255,255,255,.65); font-size: .88rem; text-align: center; line-height: 1.6; margin-bottom: 2.5rem; }

    .reg-features { width: 100%; }
    .reg-feature {
      display: flex; align-items: center; gap: .85rem;
      padding: .75rem;
      background: rgba(255,255,255,.07);
      border-radius: 10px;
      margin-bottom: .6rem;
      border: 1px solid rgba(255,255,255,.1);
    }
    .reg-feature-icon {
      width: 36px; height: 36px; border-radius: 9px;
      display: flex; align-items: center; justify-content: center;
      font-size: .9rem; flex-shrink: 0;
    }
    .reg-feature-text { font-size: .8rem; color: rgba(255,255,255,.82); font-weight: 500; }

    /* Panel de formulario */
    .reg-form-panel {
      overflow-y: auto;
      padding: 3rem 3.5rem;
      display: flex;
      align-items: flex-start;
      justify-content: center;
    }
    .reg-box {
      width: 100%;
      max-width: 580px;
      padding-bottom: 2rem;
    }
    .reg-title { font-size: 1.6rem; font-weight: 800; color: var(--text-dark); margin-bottom: .35rem; }
    .reg-sub   { color: var(--text-muted); font-size: .88rem; margin-bottom: 1.75rem; }

    .section-divider {
      display: flex; align-items: center; gap: .75rem;
      margin: 1.5rem 0 1rem;
    }
    .section-divider span {
      font-size: .72rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .06em; color: var(--text-muted);
      white-space: nowrap;
    }
    .section-divider::before,
    .section-divider::after {
      content: ''; flex: 1; height: 1px; background: var(--border-color);
    }

    .terms-box {
      background: var(--primary-light);
      border: 1px solid rgba(26,86,219,.2);
      border-radius: 10px;
      padding: .85rem 1rem;
      font-size: .82rem;
      color: var(--text-body);
      margin-bottom: 1.25rem;
    }
    .strength-bar { height: 4px; border-radius: 99px; background: var(--border-color); margin-top: .35rem; overflow: hidden; }
    .strength-fill { height: 100%; border-radius: 99px; transition: width .3s, background .3s; }

    @media (max-width: 768px) {
      .register-page { grid-template-columns: 1fr; }
      .reg-visual { display: none; }
      .reg-form-panel { padding: 2rem 1.25rem; }
    }
  </style>
</head>
<body>
<div class="register-page">

  <!-- ─── Panel visual ─── -->
  <aside class="reg-visual">
    <div class="reg-brand-icon"><i class="fa-solid fa-stethoscope"></i></div>
    <div class="reg-brand-name"><?= APP_NAME ?></div>
    <div class="reg-brand-sub">Gestión médica inteligente.<br>Crea tu cuenta y agenda tu primera cita en minutos.</div>

    <div class="reg-features">
      <?php $features = [
        ['background:rgba(14,159,110,.25);color:#0E9F6E;', 'fa-calendar-check', 'Agenda citas en línea 24/7'],
        ['background:rgba(26,86,219,.25);color:#93C5FD;',  'fa-notes-medical',  'Historial médico centralizado'],
        ['background:rgba(192,120,3,.25);color:#FCD34D;',   'fa-bell',           'Recordatorios automáticos'],
        ['background:rgba(224,36,36,.25);color:#FCA5A5;',   'fa-shield-halved',  'Datos seguros y privados'],
      ]; foreach ($features as [$bg, $icon, $text]): ?>
      <div class="reg-feature">
        <div class="reg-feature-icon" style="<?= $bg ?>"><i class="fa-solid fa-<?= $icon ?>"></i></div>
        <div class="reg-feature-text"><?= $text ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </aside>

  <!-- ─── Panel formulario ─── -->
  <main class="reg-form-panel">
    <div class="reg-box fade-in">

      <!-- Logo mobile -->
      <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:1.5rem;">
        <div style="width:34px;height:34px;background:var(--primary);border-radius:9px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.85rem;">
          <i class="fa-solid fa-stethoscope"></i>
        </div>
        <span style="font-size:1rem;font-weight:800;color:var(--text-dark);"><?= APP_NAME ?></span>
      </div>

      <h1 class="reg-title">Crea tu cuenta</h1>
      <p class="reg-sub">Únete a MedCRM y gestiona tu salud de forma fácil</p>

      <?php if (!empty($errors['general'])): ?>
      <div class="alert alert-error"><i class="fa-solid fa-circle-xmark"></i> <?= e($errors['general']) ?></div>
      <?php endif; ?>

      <form method="POST" id="regForm">
        <?= csrfField() ?>

        <!-- ── Datos de cuenta ── -->
        <div class="section-divider"><span>Datos de acceso</span></div>

        <div class="form-grid form-grid-2">

          <div class="form-group full-width">
            <label>Nombre completo <span class="required">*</span></label>
            <div class="input-icon-wrap">
              <i class="fa fa-user input-icon"></i>
              <input type="text" name="name" class="form-control <?= isset($errors['name'])?'is-invalid':'' ?>"
                     placeholder="Ej: Juan Pérez García"
                     value="<?= e($_POST['name']??'') ?>" autocomplete="name">
            </div>
            <?php if(isset($errors['name'])): ?><span class="form-error"><?= e($errors['name']) ?></span><?php endif; ?>
          </div>

          <div class="form-group">
            <label>Correo electrónico <span class="required">*</span></label>
            <div class="input-icon-wrap">
              <i class="fa fa-envelope input-icon"></i>
              <input type="email" name="email" class="form-control <?= isset($errors['email'])?'is-invalid':'' ?>"
                     placeholder="correo@ejemplo.com"
                     value="<?= e($_POST['email']??'') ?>" autocomplete="email">
            </div>
            <?php if(isset($errors['email'])): ?><span class="form-error"><?= e($errors['email']) ?></span><?php endif; ?>
          </div>

          <div class="form-group">
            <label>Teléfono</label>
            <div class="input-icon-wrap">
              <i class="fa fa-phone input-icon"></i>
              <input type="tel" name="phone" class="form-control"
                     placeholder="+1 809 000 0000"
                     value="<?= e($_POST['phone']??'') ?>">
            </div>
          </div>

          <div class="form-group">
            <label>Contraseña <span class="required">*</span></label>
            <div class="input-icon-wrap">
              <i class="fa fa-lock input-icon"></i>
              <input type="password" name="password" id="passInput"
                     class="form-control <?= isset($errors['password'])?'is-invalid':'' ?>"
                     placeholder="Mínimo 6 caracteres"
                     oninput="checkStrength(this.value)">
              <button type="button" onclick="togglePass('passInput','eye1')"
                      style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);">
                <i class="fa fa-eye" id="eye1"></i>
              </button>
            </div>
            <div class="strength-bar"><div class="strength-fill" id="strengthBar" style="width:0;background:var(--danger);"></div></div>
            <?php if(isset($errors['password'])): ?><span class="form-error"><?= e($errors['password']) ?></span><?php endif; ?>
          </div>

          <div class="form-group">
            <label>Confirmar contraseña <span class="required">*</span></label>
            <div class="input-icon-wrap">
              <i class="fa fa-lock input-icon"></i>
              <input type="password" name="confirm" id="confirmInput"
                     class="form-control <?= isset($errors['confirm'])?'is-invalid':'' ?>"
                     placeholder="Repite la contraseña">
              <button type="button" onclick="togglePass('confirmInput','eye2')"
                      style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);">
                <i class="fa fa-eye" id="eye2"></i>
              </button>
            </div>
            <?php if(isset($errors['confirm'])): ?><span class="form-error"><?= e($errors['confirm']) ?></span><?php endif; ?>
          </div>

        </div>

        <!-- ── Datos personales (opcionales) ── -->
        <div class="section-divider"><span>Datos personales (opcionales)</span></div>

        <div class="form-grid form-grid-2">

          <div class="form-group">
            <label>Número de documento / DNI</label>
            <div class="input-icon-wrap">
              <i class="fa fa-id-card input-icon"></i>
              <input type="text" name="dni" class="form-control"
                     placeholder="Ej: 001-1234567-8"
                     value="<?= e($_POST['dni']??'') ?>">
            </div>
          </div>

          <div class="form-group">
            <label>Fecha de nacimiento</label>
            <input type="date" name="birth_date" class="form-control"
                   value="<?= e($_POST['birth_date']??'') ?>"
                   max="<?= date('Y-m-d') ?>">
          </div>

          <div class="form-group full-width">
            <label>Género</label>
            <div style="display:flex;gap:.75rem;">
              <?php foreach(['M'=>'Masculino','F'=>'Femenino','O'=>'Prefiero no decirlo'] as $val => $lbl): ?>
              <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.85rem;padding:.5rem .85rem;border:1.5px solid var(--border-color);border-radius:8px;flex:1;transition:all .15s;"
                     class="gender-opt <?= ($_POST['gender']??'')===$val?'active':'' ?>">
                <input type="radio" name="gender" value="<?= $val ?>" style="display:none;"
                       <?= ($_POST['gender']??'')===$val?'checked':'' ?>>
                <?= $lbl ?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

        </div>

        <!-- ── Términos ── -->
        <div class="terms-box">
          <label style="display:flex;align-items:flex-start;gap:.75rem;cursor:pointer;">
            <input type="checkbox" name="terms" style="margin-top:.15rem;width:16px;height:16px;accent-color:var(--primary);flex-shrink:0;"
                   <?= isset($_POST['terms'])?'checked':'' ?>>
            <span>
              Acepto los <a href="#" style="font-weight:600;">Términos y Condiciones</a> y la
              <a href="#" style="font-weight:600;">Política de Privacidad</a> de MedCRM.
              Mis datos serán utilizados exclusivamente para gestionar mis citas médicas.
            </span>
          </label>
          <?php if(isset($errors['terms'])): ?><div class="form-error" style="margin-top:.35rem;"><?= e($errors['terms']) ?></div><?php endif; ?>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:.85rem;font-size:.95rem;font-weight:700;">
          <i class="fa-solid fa-user-plus"></i> Crear mi cuenta gratis
        </button>

        <p style="text-align:center;margin-top:1.25rem;font-size:.85rem;color:var(--text-muted);">
          ¿Ya tienes cuenta?
          <a href="<?= APP_URL ?>/login.php" style="font-weight:700;">Inicia sesión</a>
        </p>
      </form>
    </div>
  </main>

</div>

<style>
  .gender-opt:has(input:checked),
  .gender-opt.active {
    border-color: var(--primary);
    background: var(--primary-light);
    color: var(--primary);
    font-weight: 600;
  }
  .gender-opt:has(input:checked)::before { content: '✓ '; }
</style>

<script>
function togglePass(inputId, eyeId) {
  const input = document.getElementById(inputId);
  const icon  = document.getElementById(eyeId);
  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.replace('fa-eye','fa-eye-slash');
  } else {
    input.type = 'password';
    icon.classList.replace('fa-eye-slash','fa-eye');
  }
}

function checkStrength(val) {
  const bar = document.getElementById('strengthBar');
  let score = 0;
  if (val.length >= 6) score++;
  if (val.length >= 10) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  const pct    = Math.min(100, score * 20);
  const colors = ['','#E02424','#C27803','#C27803','#0E9F6E','#0E9F6E'];
  bar.style.width      = pct + '%';
  bar.style.background = colors[score] || '#E02424';
}

// Gender radio visual
document.querySelectorAll('.gender-opt').forEach(opt => {
  opt.addEventListener('click', function() {
    document.querySelectorAll('.gender-opt').forEach(o => o.classList.remove('active'));
    this.classList.add('active');
    this.querySelector('input[type="radio"]').checked = true;
  });
});
</script>
</body>
</html>
