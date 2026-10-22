<?php
require_once __DIR__ . '/config/config.php';

// Si ya está logueado, redirigir
if (isLoggedIn()) redirectAfterLogin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Por favor completa todos los campos.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ingresa un correo electrónico válido.';
    } elseif (login($email, $password)) {
        redirectAfterLogin();
    } else {
        $error = 'Credenciales incorrectas. Verifica tu correo y contraseña.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar sesión – <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=5.1">
</head>
<body>

<div class="auth-page">

  <?php
  $_loginLogo    = getSetting('company_logo', '');
  $_loginName    = getSetting('company_name', APP_NAME);
  $_loginSlogan  = getSetting('company_slogan', 'Sistema Médico');
  $_logoPath     = APP_ROOT . '/assets/img/' . $_loginLogo;
  $hasLogo       = $_loginLogo && file_exists($_logoPath);
  ?>
  <!-- ── Panel visual izquierdo ───────────────────────── -->
  <div class="auth-visual">
    <div class="auth-brand">
      <?php if ($hasLogo): ?>
        <img src="<?= APP_URL ?>/assets/img/<?= e($_loginLogo) ?>?v=<?= filemtime($_logoPath) ?>"
             alt="<?= e($_loginName) ?>"
             style="width:80px;height:80px;object-fit:contain;border-radius:16px;background:rgba(255,255,255,.15);padding:8px;margin-bottom:1rem;border:1px solid rgba(255,255,255,.2);">
      <?php else: ?>
        <div class="auth-brand-icon">
          <i class="fa-solid fa-stethoscope"></i>
        </div>
      <?php endif; ?>
      <div class="auth-brand-name"><?= e($_loginName) ?></div>
      <div class="auth-brand-tagline">
        <?= e($_loginSlogan) ?><br>
        Sistema integral de gestión médica.<br>
        Administra pacientes, doctores y citas desde un solo lugar.
      </div>
    </div>
    <div class="auth-pills">
      <?php foreach (['Pacientes','Doctores','Citas','Historiales','Reportes'] as $tag): ?>
        <span><?= $tag ?></span>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── Panel formulario derecho ─────────────────────── -->
  <div class="auth-form-panel">
    <div class="auth-box fade-in">

      <!-- Logo -->
      <div class="auth-logo">
        <?php if ($hasLogo): ?>
          <img src="<?= APP_URL ?>/assets/img/<?= e($_loginLogo) ?>?v=<?= filemtime($_logoPath) ?>"
               alt="<?= e($_loginName) ?>"
               style="width:40px;height:40px;object-fit:contain;border-radius:8px;background:var(--primary-light);padding:4px;">
        <?php else: ?>
          <div class="auth-logo-icon">
            <i class="fa-solid fa-stethoscope"></i>
          </div>
        <?php endif; ?>
        <span class="auth-logo-name"><?= e($_loginName) ?></span>
      </div>

      <h1 class="auth-title">Bienvenido de nuevo</h1>
      <p class="auth-subtitle">Ingresa tus credenciales para continuar</p>

      <?php if ($error): ?>
      <div class="alert alert-error">
        <i class="fa-solid fa-circle-xmark"></i>
        <span><?= e($error) ?></span>
      </div>
      <?php endif; ?>

      <form method="POST" action="">
        <?= csrfField() ?>

        <div class="form-group mb-2">
          <label for="email">Correo electrónico</label>
          <div class="input-icon-wrap">
            <i class="fa fa-envelope input-icon"></i>
            <input type="email" id="email" name="email"
                   class="form-control <?= $error ? 'is-invalid' : '' ?>"
                   placeholder="usuario@correo.com"
                   value="<?= e($_POST['email'] ?? '') ?>"
                   autocomplete="email" required>
          </div>
        </div>

        <div class="form-group mb-2">
          <label for="password">Contraseña</label>
          <div class="input-icon-wrap">
            <i class="fa fa-lock input-icon"></i>
            <input type="password" id="password" name="password"
                   class="form-control <?= $error ? 'is-invalid' : '' ?>"
                   placeholder="••••••••"
                   autocomplete="current-password" required>
            <button type="button" id="togglePass"
                    style="position:absolute;right:.85rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);">
              <i class="fa fa-eye" id="eyeIcon"></i>
            </button>
          </div>
        </div>

        <div style="margin-top:1.5rem;">
          <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:.75rem;font-size:.95rem;">
            <i class="fa-solid fa-right-to-bracket"></i>
            Iniciar sesión
          </button>
        </div>
      </form>

      <?php if (defined('GOOGLE_OAUTH_ENABLED') && GOOGLE_OAUTH_ENABLED): ?>
      <!-- Divisor OAuth -->
      <div class="auth-divider">o continúa con</div>

      <!-- Botón Google -->
      <a href="<?= APP_URL ?>/auth/google/redirect.php"
         style="display:flex;align-items:center;justify-content:center;gap:.65rem;
                width:100%;padding:.7rem 1rem;border:1.5px solid var(--border-color);
                border-radius:var(--radius);background:#fff;color:var(--text-dark);
                font-size:.875rem;font-weight:600;text-decoration:none;
                transition:border-color .2s,box-shadow .2s;margin-bottom:.5rem;"
         onmouseover="this.style.borderColor='#4285F4';this.style.boxShadow='0 2px 8px rgba(66,133,244,.2)'"
         onmouseout="this.style.borderColor='var(--border-color)';this.style.boxShadow='none'">
        <svg width="18" height="18" viewBox="0 0 48 48" style="flex-shrink:0;">
          <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
          <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
          <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
          <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
        </svg>
        Iniciar sesión con Google
      </a>
      <?php endif; ?>

      <!-- Registro de pacientes -->
      <div style="text-align:center;margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid var(--border-color);">
        <p style="font-size:.875rem;color:var(--text-muted);margin:0;">
          ¿No tienes cuenta?
          <a href="<?= APP_URL ?>/register.php" style="color:var(--primary);font-weight:600;">
            <i class="fa-solid fa-user-plus" style="margin-right:.25rem;"></i>Regístrate aquí
          </a>
        </p>
      </div>

    </div>
  </div>
</div>

<script>
document.getElementById('togglePass').addEventListener('click', function() {
  const pass = document.getElementById('password');
  const icon = document.getElementById('eyeIcon');
  if (pass.type === 'password') {
    pass.type = 'text';
    icon.classList.replace('fa-eye', 'fa-eye-slash');
  } else {
    pass.type = 'password';
    icon.classList.replace('fa-eye-slash', 'fa-eye');
  }
});
</script>
</body>
</html>
