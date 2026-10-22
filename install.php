<?php
/**
 * ================================================================
 * MedCRM – Instalador Web v3
 * ================================================================
 * - Sin bloqueo .installed (se puede reinstalar siempre)
 * - Actualiza config.php automáticamente
 * - Importa el SQL completo con soporte para Google OAuth
 * - Crea cuenta de administrador (INSERT garantizado)
 * - Soporta roles personalizados (secretaria, recepcionista, etc.)
 * ================================================================
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

define('INSTALL_ROOT', __DIR__);
define('SQL_FILE',    INSTALL_ROOT . '/database/medical_crm.sql');
define('CONFIG_FILE', INSTALL_ROOT . '/config/config.php');
define('OAUTH_FILE',  INSTALL_ROOT . '/config/oauth.php');

session_start();

$step   = (int)($_GET['step'] ?? $_SESSION['install_step'] ?? 1);
$errors = [];

// ── Helpers ───────────────────────────────────────────────────────────────────
function tryPDO($host, $port, $user, $pass, $dbname = '') {
    $dsn = "mysql:host={$host};port={$port};charset=utf8mb4" . ($dbname ? ";dbname={$dbname}" : '');
    return new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

function updateConfig($file, $map) {
    if (!file_exists($file)) return false;
    $content = file_get_contents($file);
    foreach ($map as $key => $val) {
        $val     = addslashes($val);
        $content = preg_replace(
            "/define\('{$key}',\s*'[^']*'\)/",
            "define('{$key}', '{$val}')",
            $content
        );
    }
    return file_put_contents($file, $content) !== false;
}

// ── Procesamiento POST ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // PASO 1 → 2
    if ($step === 1) {
        $_SESSION['install_step'] = 2;
        header('Location: ?step=2'); exit;
    }

    // PASO 2: probar conexión a la BD
    if ($step === 2) {
        $db = [
            'host'    => trim($_POST['db_host'] ?? 'localhost'),
            'port'    => trim($_POST['db_port'] ?? '3306'),
            'name'    => trim($_POST['db_name'] ?? 'medical_crm'),
            'user'    => trim($_POST['db_user'] ?? ''),
            'pass'    => $_POST['db_pass'] ?? '',
            'app_url' => rtrim(trim($_POST['app_url'] ?? ''), '/'),
        ];
        if (empty($db['user'])) $errors[] = 'El usuario de la base de datos es requerido.';
        if (empty($db['name'])) $errors[] = 'El nombre de la base de datos es requerido.';
        if (empty($db['app_url'])) $errors[] = 'La URL de la aplicaci&oacute;n es requerida.';

        if (empty($errors)) {
            try {
                tryPDO($db['host'], $db['port'], $db['user'], $db['pass']);
                $_SESSION['db'] = $db;
                $_SESSION['install_step'] = 3;
                header('Location: ?step=3'); exit;
            } catch (PDOException $e) {
                $errors[] = 'Error de conexi&oacute;n MySQL: ' . htmlspecialchars($e->getMessage());
            }
        }
    }

    // PASO 3: crear BD e importar SQL
    if ($step === 3) {
        $db = $_SESSION['db'] ?? null;
        if (!$db) { header('Location: ?step=2'); exit; }

        try {
            $pdo = tryPDO($db['host'], $db['port'], $db['user'], $db['pass']);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$db['name']}`");

            if (!file_exists(SQL_FILE)) throw new Exception('No se encuentra el archivo SQL: ' . SQL_FILE);

            $sql = file_get_contents(SQL_FILE);
            $sql = preg_replace('/^--.*$/m', '', $sql);
            $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

            $statements = array_filter(array_map('trim', explode(';', $sql)));
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach ($statements as $stm) {
                if (!empty($stm)) $pdo->exec($stm);
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

            updateConfig(CONFIG_FILE, [
                'DB_HOST' => $db['host'],
                'DB_PORT' => $db['port'],
                'DB_USER' => $db['user'],
                'DB_PASS' => $db['pass'],
                'DB_NAME' => $db['name'],
                'APP_URL' => $db['app_url'],
            ]);

            $_SESSION['install_step'] = 4;
            header('Location: ?step=4'); exit;
        } catch (Exception $e) {
            $errors[] = 'Error al instalar la base de datos: ' . htmlspecialchars($e->getMessage());
        }
    }

    // PASO 4: crear cuenta de administrador
    if ($step === 4) {
        $db = $_SESSION['db'] ?? null;
        if (!$db) { header('Location: ?step=2'); exit; }

        $adminName  = trim($_POST['admin_name']  ?? 'Administrador');
        $adminEmail = trim($_POST['admin_email'] ?? '');
        $adminPass  = $_POST['admin_pass']        ?? '';
        $clinicName = trim($_POST['clinic_name'] ?? 'Mi Clinica');

        $googleId     = trim($_POST['google_client_id']     ?? '');
        $googleSecret = trim($_POST['google_client_secret'] ?? '');

        if (empty($adminEmail) || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL))
            $errors[] = 'Correo de administrador inv&aacute;lido.';
        if (strlen($adminPass) < 6)
            $errors[] = 'La contrase&ntilde;a debe tener al menos 6 caracteres.';

        if (empty($errors)) {
            try {
                $pdo  = tryPDO($db['host'], $db['port'], $db['user'], $db['pass'], $db['name']);
                $hash = password_hash($adminPass, PASSWORD_BCRYPT);

                // Garantizar que existe el rol admin (id=1)
                $pdo->exec("INSERT IGNORE INTO roles (id, name, slug, description, color)
                            VALUES (1, 'Administrador', 'admin', 'Acceso total al sistema', '#083344')");

                // Buscar admin existente
                $existing = $pdo->prepare('SELECT id FROM users WHERE role_id = 1 ORDER BY id ASC LIMIT 1');
                $existing->execute();
                $existingAdmin = $existing->fetch(PDO::FETCH_ASSOC);

                if ($existingAdmin) {
                    // Actualizar admin existente
                    $pdo->prepare('UPDATE users SET name = ?, email = ?, password = ?, status = 1 WHERE id = ?')
                        ->execute([$adminName, $adminEmail, $hash, $existingAdmin['id']]);
                } else {
                    // Insertar nuevo admin (tabla vacía tras reinstalación)
                    $pdo->prepare('INSERT INTO users (role_id, name, email, password, phone, status, created_at)
                                   VALUES (1, ?, ?, ?, \'\', 1, NOW())')
                        ->execute([$adminName, $adminEmail, $hash]);
                }

                // Guardar nombre de la clínica en tabla settings (usa setting_key/setting_value)
                $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
                    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `setting_key`   VARCHAR(100) NOT NULL,
                    `setting_value` TEXT         DEFAULT NULL,
                    `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `settings_key_unique` (`setting_key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('company_name', ?)
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
                    ->execute([$clinicName]);
                $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('company_slogan', 'Sistema Medico')
                               ON DUPLICATE KEY UPDATE setting_value = setting_value")
                    ->execute();

                // Google OAuth (opcional)
                if (!empty($googleId) && !empty($googleSecret) && file_exists(OAUTH_FILE)) {
                    updateConfig(OAUTH_FILE, [
                        'GOOGLE_CLIENT_ID'     => $googleId,
                        'GOOGLE_CLIENT_SECRET' => $googleSecret,
                        'GOOGLE_REDIRECT_URI'  => $db['app_url'] . '/auth/google/callback.php',
                    ]);
                    $oc = file_get_contents(OAUTH_FILE);
                    $oc = preg_replace("/define\('GOOGLE_OAUTH_ENABLED',\s*(true|false)\)/",
                                       "define('GOOGLE_OAUTH_ENABLED', true)", $oc);
                    file_put_contents(OAUTH_FILE, $oc);
                }

                $_SESSION['admin_email_done'] = $adminEmail;
                $_SESSION['clinic_name_done'] = $clinicName;
                session_write_close();
                header('Location: ?step=5'); exit;
            } catch (Exception $e) {
                $errors[] = 'Error al configurar el administrador: ' . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// ── Requisitos del sistema ────────────────────────────────────────────────────
$requirements = [
    ['PHP 7.4+',            version_compare(PHP_VERSION, '7.4', '>='), 'Version actual: ' . PHP_VERSION],
    ['Extension PDO MySQL', extension_loaded('pdo_mysql'),             ''],
    ['Extension OpenSSL',   extension_loaded('openssl'),               'Requerida para Google OAuth'],
    ['Carpeta config/',     is_writable(dirname(CONFIG_FILE)),         'Necesita permisos de escritura (755)'],
    ['Archivo SQL',         file_exists(SQL_FILE),                     'database/medical_crm.sql debe existir'],
    ['allow_url_fopen',     (bool)ini_get('allow_url_fopen'),          'Requerido para Google OAuth'],
];
$allOk = !in_array(false, array_column($requirements, 1), true);

$detectedUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    . rtrim(dirname($_SERVER['PHP_SELF'] ?? '/install.php'), '/');

$adminEmailDone = $_SESSION['admin_email_done'] ?? '';
$clinicNameDone = $_SESSION['clinic_name_done'] ?? 'Mi Clinica';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Instalador – MedCRM</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root {
      --primary:       #0891B2;
      --primary-dark:  #0E7490;
      --primary-light: #E0F2FE;
      --sidebar-bg:    #0C1E3A;
      --body-bg:       #F1F5F9;
      --card-bg:       #FFFFFF;
      --text-dark:     #0F172A;
      --text-body:     #334155;
      --text-muted:    #64748B;
      --border:        #E2E8F0;
      --success:       #0D9488;
      --danger:        #DC2626;
      --warning:       #D97706;
      --radius:        14px;
      --radius-sm:     9px;
      --shadow:        0 1px 3px rgba(0,0,0,.08), 0 4px 16px rgba(0,0,0,.05);
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', sans-serif; background: var(--body-bg); color: var(--text-body); min-height: 100vh; }
    a { text-decoration: none; color: inherit; }

    /* Layout */
    .layout { display: grid; grid-template-columns: 280px 1fr; min-height: 100vh; }

    /* Sidebar */
    .sidebar {
      background: var(--sidebar-bg);
      display: flex; flex-direction: column;
      position: sticky; top: 0; height: 100vh; overflow: hidden;
    }
    .sidebar-top { padding: 2rem 1.75rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,.07); }
    .brand { display: flex; align-items: center; gap: .9rem; margin-bottom: 2rem; }
    .brand-logo {
      width: 44px; height: 44px; border-radius: 12px;
      background: linear-gradient(135deg, var(--primary) 0%, #0E7490 100%);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.2rem; color: #fff; flex-shrink: 0;
      box-shadow: 0 4px 12px rgba(8,145,178,.4);
    }
    .brand-name { font-size: 1.1rem; font-weight: 800; color: #fff; line-height: 1.2; }
    .brand-tagline { font-size: .72rem; color: rgba(255,255,255,.4); margin-top: .1rem; }

    .progress-wrap { padding: 0 1.75rem 1.5rem; }
    .progress-label { font-size: .7rem; font-weight: 600; color: rgba(255,255,255,.35); text-transform: uppercase; letter-spacing: .06em; margin-bottom: .6rem; }
    .progress-bar { height: 3px; background: rgba(255,255,255,.1); border-radius: 3px; overflow: hidden; }
    .progress-fill { height: 100%; background: linear-gradient(90deg, var(--primary) 0%, #38BDF8 100%); border-radius: 3px; transition: width .4s ease; }

    .step-list { flex: 1; padding: .5rem 1rem; display: flex; flex-direction: column; gap: .25rem; overflow-y: auto; }
    .step-item { display: flex; align-items: center; gap: .85rem; padding: .7rem .85rem; border-radius: 10px; cursor: default; }
    .step-item.done    { color: rgba(255,255,255,.5); }
    .step-item.active  { background: rgba(255,255,255,.09); color: #fff; }
    .step-item.pending { color: rgba(255,255,255,.3); }
    .step-circle {
      width: 28px; height: 28px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: .72rem; font-weight: 700; flex-shrink: 0;
      border: 1.5px solid currentColor; transition: all .2s;
    }
    .step-item.done   .step-circle { background: var(--success); border-color: var(--success); color: #fff; }
    .step-item.active .step-circle { background: var(--primary); border-color: var(--primary); color: #fff; box-shadow: 0 0 0 4px rgba(8,145,178,.25); }
    .step-title { font-size: .84rem; font-weight: 600; }
    .step-desc  { font-size: .72rem; color: rgba(255,255,255,.35); margin-top: .05rem; }
    .step-item.active .step-desc { color: rgba(255,255,255,.5); }

    .sidebar-footer { padding: 1.25rem 1.75rem; border-top: 1px solid rgba(255,255,255,.07); }
    .sidebar-footer p { font-size: .72rem; color: rgba(255,255,255,.3); line-height: 1.6; }

    /* Main */
    .main-content { display: flex; flex-direction: column; min-height: 100vh; }

    /* Hero */
    .step-hero {
      background: linear-gradient(135deg, #0C1E3A 0%, #0E7490 60%, #0891B2 100%);
      padding: 2.5rem 3rem; color: #fff; position: relative; overflow: hidden;
    }
    .step-hero::before {
      content: ''; position: absolute; inset: 0;
      background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }
    .hero-icon { width: 56px; height: 56px; border-radius: 16px; background: rgba(255,255,255,.15); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1rem; }
    .hero-step-label { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: rgba(255,255,255,.55); margin-bottom: .4rem; }
    .hero-title { font-size: 1.75rem; font-weight: 800; line-height: 1.2; margin-bottom: .5rem; }
    .hero-subtitle { font-size: .92rem; color: rgba(255,255,255,.65); max-width: 520px; line-height: 1.6; }

    /* Content */
    .content-body { flex: 1; padding: 2.25rem 3rem; max-width: 720px; }

    /* Cards */
    .card { background: var(--card-bg); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; margin-bottom: 1.25rem; }
    .card-header { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border); background: #FAFBFC; display: flex; align-items: center; gap: .75rem; }
    .card-header-icon { width: 32px; height: 32px; border-radius: 8px; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: .85rem; }
    .card-header-title { font-size: .875rem; font-weight: 700; color: var(--text-dark); }
    .card-header-sub { font-size: .75rem; color: var(--text-muted); }
    .card-body { padding: 1.5rem; }

    /* Alerts */
    .alert { padding: .9rem 1.1rem; border-radius: var(--radius-sm); font-size: .875rem; display: flex; align-items: flex-start; gap: .65rem; margin-bottom: 1.25rem; }
    .alert-error   { background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; }
    .alert-success { background: #F0FDF4; border: 1px solid #BBF7D0; color: #166534; }
    .alert-warning { background: #FFFBEB; border: 1px solid #FDE68A; color: #92400E; }
    .alert i { margin-top: .1rem; flex-shrink: 0; }

    /* Forms */
    .form-group { margin-bottom: 1.1rem; }
    .form-label { display: block; font-size: .8rem; font-weight: 600; color: var(--text-dark); margin-bottom: .4rem; }
    .req { color: var(--danger); margin-left: 2px; }
    .form-hint { font-size: .73rem; color: var(--text-muted); margin-top: .3rem; }
    .form-input { width: 100%; padding: .6rem .9rem; border: 1.5px solid var(--border); border-radius: var(--radius-sm); font-size: .875rem; font-family: inherit; color: var(--text-dark); background: #fff; transition: border-color .15s, box-shadow .15s; }
    .form-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(8,145,178,.1); }
    .form-input::placeholder { color: #94A3B8; }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

    /* Buttons */
    .btn { display: inline-flex; align-items: center; gap: .5rem; padding: .72rem 1.75rem; border-radius: var(--radius-sm); border: none; cursor: pointer; font-size: .875rem; font-weight: 700; font-family: inherit; transition: all .15s; text-decoration: none; }
    .btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color: #fff; box-shadow: 0 2px 8px rgba(8,145,178,.35); }
    .btn-primary:hover { background: linear-gradient(135deg, var(--primary-dark) 0%, #0C6E8A 100%); box-shadow: 0 4px 14px rgba(8,145,178,.4); transform: translateY(-1px); }
    .btn-primary:disabled { opacity: .55; cursor: not-allowed; transform: none; box-shadow: none; }
    .btn-success { background: linear-gradient(135deg, #0D9488 0%, #0F766E 100%); color: #fff; box-shadow: 0 2px 8px rgba(13,148,136,.35); }
    .btn-success:hover { background: linear-gradient(135deg, #0F766E 0%, #115E59 100%); transform: translateY(-1px); }

    /* Requirements */
    .req-list { display: flex; flex-direction: column; }
    .req-row { display: flex; align-items: center; gap: 1rem; padding: .75rem 0; border-bottom: 1px solid var(--border); }
    .req-row:last-child { border: none; }
    .req-icon { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: .9rem; flex-shrink: 0; }
    .req-icon.ok  { background: #F0FDF4; color: var(--success); }
    .req-icon.err { background: #FEF2F2; color: var(--danger); }
    .req-label  { font-size: .875rem; font-weight: 600; color: var(--text-dark); }
    .req-detail { font-size: .75rem; color: var(--text-muted); }
    .req-badge  { margin-left: auto; flex-shrink: 0; }
    .badge-ok  { color: var(--success); font-size: 1.1rem; }
    .badge-err { color: var(--danger);  font-size: 1.1rem; }

    /* Section label */
    .section-label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: var(--text-muted); padding-bottom: .6rem; border-bottom: 1px solid var(--border); margin-bottom: 1rem; }

    /* Info box */
    .info-box { display: flex; align-items: center; gap: 1rem; padding: 1.1rem 1.25rem; background: var(--primary-light); border: 1px solid #BAE6FD; border-radius: var(--radius-sm); margin-bottom: 1.25rem; }
    .info-box-icon { font-size: 1.75rem; color: var(--primary); flex-shrink: 0; }
    .info-box-title { font-size: .875rem; font-weight: 700; color: var(--text-dark); }
    .info-box-sub   { font-size: .78rem; color: #0E7490; margin-top: .15rem; }

    /* Success */
    .success-wrap { text-align: center; padding: 2.5rem 0 1rem; }
    .success-badge { display: inline-flex; align-items: center; justify-content: center; width: 90px; height: 90px; border-radius: 50%; background: linear-gradient(135deg, #0D9488 0%, #0891B2 100%); font-size: 2.5rem; color: #fff; margin-bottom: 1.5rem; box-shadow: 0 8px 32px rgba(13,148,136,.35); }
    .success-title { font-size: 2rem; font-weight: 900; color: var(--text-dark); margin-bottom: .5rem; }
    .success-sub   { font-size: 1rem; color: var(--text-muted); max-width: 460px; margin: 0 auto 2rem; line-height: 1.6; }

    .credentials-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow); padding: 1.5rem; max-width: 520px; margin: 0 auto 1.25rem; text-align: left; }
    .credentials-card h4 { font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--text-muted); margin-bottom: 1rem; }
    .cred-item { display: flex; align-items: center; gap: .75rem; padding: .6rem 0; border-bottom: 1px solid var(--border); }
    .cred-item:last-child { border: none; padding-bottom: 0; }
    .cred-icon { width: 32px; height: 32px; border-radius: 8px; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: .8rem; flex-shrink: 0; }
    .cred-label { font-size: .75rem; color: var(--text-muted); }
    .cred-val   { font-size: .875rem; font-weight: 600; color: var(--text-dark); font-family: monospace; }

    /* Optional toggle */
    .optional-toggle { display: flex; align-items: center; gap: .6rem; cursor: pointer; font-size: .82rem; font-weight: 600; color: var(--text-muted); padding: .5rem 0; user-select: none; }
    .optional-toggle:hover { color: var(--primary); }
    .optional-body { display: none; padding-top: .75rem; }
    .optional-body.show { display: block; }

    /* Responsive */
    @media (max-width: 768px) {
      .layout { grid-template-columns: 1fr; }
      .sidebar { display: none; }
      .step-hero, .content-body { padding: 1.5rem; }
      .grid-2 { grid-template-columns: 1fr; }
    }

    /* Animation */
    @keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
    .fade-up { animation: fadeUp .35s ease both; }
  </style>
</head>
<body>
<div class="layout">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-top">
      <div class="brand">
        <div class="brand-logo"><i class="fa-solid fa-stethoscope"></i></div>
        <div>
          <div class="brand-name">MedCRM</div>
          <div class="brand-tagline">Sistema Medico v3</div>
        </div>
      </div>
    </div>

    <div class="progress-wrap">
      <div class="progress-label">Progreso de instalacion</div>
      <div class="progress-bar">
        <div class="progress-fill" style="width:<?= min(100, ($step - 1) * 25) ?>%"></div>
      </div>
    </div>

    <div class="step-list">
      <?php
      $stepsData = [
        ['title' => 'Requisitos',    'desc' => 'Verificar el entorno'],
        ['title' => 'Base de Datos', 'desc' => 'Conexion MySQL'],
        ['title' => 'Instalacion',   'desc' => 'Importar esquema'],
        ['title' => 'Administrador', 'desc' => 'Cuenta y credenciales'],
        ['title' => 'Completado',    'desc' => 'Listo para usar'],
      ];
      foreach ($stepsData as $i => $sd):
        $n   = $i + 1;
        $cls = $n < $step ? 'done' : ($n === $step ? 'active' : 'pending');
      ?>
      <div class="step-item <?= $cls ?>">
        <div class="step-circle">
          <?php if ($n < $step): ?>
            <i class="fa-solid fa-check" style="font-size:.65rem;"></i>
          <?php else: echo $n; ?>
          <?php endif; ?>
        </div>
        <div>
          <div class="step-title"><?= $sd['title'] ?></div>
          <div class="step-desc"><?= $sd['desc'] ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="sidebar-footer">
      <p>MedCRM &copy; <?= date('Y') ?><br>PHP <?= PHP_VERSION ?> &middot; MySQL PDO</p>
    </div>
  </aside>

  <!-- MAIN -->
  <div class="main-content">

    <?php
    $heroes = [
      1 => ['icon' => 'fa-clipboard-check', 'label' => 'Paso 1 de 5',
            'title' => 'Verificar Requisitos',
            'sub'   => 'Comprobamos que tu servidor tenga todo lo necesario para ejecutar MedCRM correctamente.'],
      2 => ['icon' => 'fa-server',           'label' => 'Paso 2 de 5',
            'title' => 'Base de Datos',
            'sub'   => 'Ingresa los datos de conexion a MySQL. La base de datos se creara automaticamente si no existe.'],
      3 => ['icon' => 'fa-layer-group',      'label' => 'Paso 3 de 5',
            'title' => 'Importar Esquema',
            'sub'   => 'Se crearan todas las tablas del sistema: pacientes, doctores, citas, historial medico y mas.'],
      4 => ['icon' => 'fa-user-doctor',      'label' => 'Paso 4 de 5',
            'title' => 'Configurar Administrador',
            'sub'   => 'Crea la cuenta principal. Podras agregar roles personalizados (secretaria, recepcionista) desde el panel.'],
      5 => ['icon' => 'fa-circle-check',     'label' => 'Paso 5 de 5',
            'title' => 'Instalacion Completa',
            'sub'   => 'MedCRM esta listo. Inicia sesion con las credenciales que configuraste.'],
    ];
    $h = $heroes[$step] ?? $heroes[1];
    ?>
    <div class="step-hero">
      <div class="hero-icon"><i class="fa-solid <?= $h['icon'] ?>"></i></div>
      <div class="hero-step-label"><?= $h['label'] ?></div>
      <h1 class="hero-title"><?= $h['title'] ?></h1>
      <p class="hero-subtitle"><?= $h['sub'] ?></p>
    </div>

    <div class="content-body fade-up">

      <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <i class="fa-solid fa-circle-xmark"></i>
        <div><?= implode('<br>', $errors) ?></div>
      </div>
      <?php endif; ?>

      <?php /* ── PASO 1 ── */ if ($step === 1): ?>

      <div class="card">
        <div class="card-header">
          <div class="card-header-icon"><i class="fa-solid fa-server"></i></div>
          <div>
            <div class="card-header-title">Estado del Servidor</div>
            <div class="card-header-sub">Todos los items deben estar en verde para continuar</div>
          </div>
        </div>
        <div class="card-body">
          <div class="req-list">
            <?php foreach ($requirements as $req): ?>
            <?php list($lbl, $ok, $detail) = $req; ?>
            <div class="req-row">
              <div class="req-icon <?= $ok ? 'ok' : 'err' ?>">
                <i class="fa-solid <?= $ok ? 'fa-check' : 'fa-xmark' ?>"></i>
              </div>
              <div style="flex:1;">
                <div class="req-label"><?= htmlspecialchars($lbl) ?></div>
                <?php if ($detail): ?><div class="req-detail"><?= htmlspecialchars($detail) ?></div><?php endif; ?>
              </div>
              <div class="req-badge">
                <?php if ($ok): ?>
                  <span class="badge-ok"><i class="fa-solid fa-circle-check"></i></span>
                <?php else: ?>
                  <span class="badge-err"><i class="fa-solid fa-circle-xmark"></i></span>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <?php if (!$allOk): ?>
      <div class="alert alert-warning">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <div>Corrige los elementos marcados en rojo antes de continuar.</div>
      </div>
      <?php else: ?>
      <div class="alert alert-success">
        <i class="fa-solid fa-circle-check"></i>
        <div><strong>Todo en orden.</strong> Tu servidor cumple todos los requisitos para instalar MedCRM.</div>
      </div>
      <?php endif; ?>

      <form method="POST">
        <button type="submit" class="btn btn-primary" <?= !$allOk ? 'disabled' : '' ?>>
          <i class="fa-solid fa-arrow-right"></i> Continuar
        </button>
      </form>

      <?php /* ── PASO 2 ── */ elseif ($step === 2): ?>

      <form method="POST">
        <div class="card">
          <div class="card-header">
            <div class="card-header-icon"><i class="fa-solid fa-database"></i></div>
            <div>
              <div class="card-header-title">Conexion MySQL</div>
              <div class="card-header-sub">Credenciales de tu servidor de base de datos</div>
            </div>
          </div>
          <div class="card-body">
            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Host</label>
                <input type="text" class="form-input" name="db_host"
                       value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>"
                       placeholder="localhost">
                <div class="form-hint">Generalmente: localhost</div>
              </div>
              <div class="form-group">
                <label class="form-label">Puerto</label>
                <input type="text" class="form-input" name="db_port"
                       value="<?= htmlspecialchars($_POST['db_port'] ?? '3306') ?>"
                       placeholder="3306">
                <div class="form-hint">XAMPP: 3306 &middot; WAMP64: 3308</div>
              </div>
              <div class="form-group">
                <label class="form-label">Usuario MySQL <span class="req">*</span></label>
                <input type="text" class="form-input" name="db_user"
                       value="<?= htmlspecialchars($_POST['db_user'] ?? 'root') ?>"
                       placeholder="root" required>
              </div>
              <div class="form-group">
                <label class="form-label">Contrasena MySQL</label>
                <input type="password" class="form-input" name="db_pass"
                       value="" placeholder="(dejar vacio si no hay)">
                <div class="form-hint">En Hostinger, usa la contrasena de tu BD</div>
              </div>
              <div class="form-group">
                <label class="form-label">Nombre de la Base de Datos <span class="req">*</span></label>
                <input type="text" class="form-input" name="db_name"
                       value="<?= htmlspecialchars($_POST['db_name'] ?? 'medical_crm') ?>"
                       placeholder="medical_crm" required>
                <div class="form-hint">Se creara si no existe</div>
              </div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <div class="card-header-icon"><i class="fa-solid fa-link"></i></div>
            <div>
              <div class="card-header-title">URL de la Aplicacion</div>
              <div class="card-header-sub">Direccion publica donde estara instalado MedCRM</div>
            </div>
          </div>
          <div class="card-body">
            <div class="form-group">
              <label class="form-label">URL Base <span class="req">*</span></label>
              <input type="text" class="form-input" name="app_url"
                     value="<?= htmlspecialchars($_POST['app_url'] ?? $detectedUrl) ?>"
                     placeholder="https://tudominio.com/medical" required>
              <div class="form-hint">
                Sin barra final &nbsp;&middot;&nbsp; Detectada: <strong><?= htmlspecialchars($detectedUrl) ?></strong>
              </div>
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary">
          <i class="fa-solid fa-plug"></i> Probar conexion y continuar
        </button>
      </form>

      <?php /* ── PASO 3 ── */ elseif ($step === 3): ?>

      <div class="info-box">
        <div class="info-box-icon"><i class="fa-solid fa-database"></i></div>
        <div>
          <div class="info-box-title">Base de datos: <?= htmlspecialchars($_SESSION['db']['name'] ?? '') ?></div>
          <div class="info-box-sub">
            <?= htmlspecialchars($_SESSION['db']['user'] ?? '') ?>@<?= htmlspecialchars($_SESSION['db']['host'] ?? '') ?>:<?= htmlspecialchars($_SESSION['db']['port'] ?? '') ?>
            &nbsp;&middot;&nbsp; <?= htmlspecialchars($_SESSION['db']['app_url'] ?? '') ?>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <div class="card-header-icon"><i class="fa-solid fa-table"></i></div>
          <div>
            <div class="card-header-title">Tablas que se instalaran</div>
          </div>
        </div>
        <div class="card-body">
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.6rem;margin-bottom:.75rem;">
            <?php
            $tables = ['roles','permissions','users','doctors','patients','appointments',
                       'medical_records','specialties','settings','role_permissions'];
            foreach ($tables as $t):
            ?>
            <div style="display:flex;align-items:center;gap:.5rem;font-size:.8rem;color:var(--text-body);">
              <i class="fa-solid fa-table-cells" style="color:var(--primary);font-size:.7rem;"></i>
              <?= $t ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="alert alert-warning">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <div><strong>Atencion:</strong> Se recrearan todas las tablas. Los datos existentes seran eliminados.</div>
      </div>

      <form method="POST">
        <button type="submit" class="btn btn-primary">
          <i class="fa-solid fa-play"></i> Iniciar instalacion
        </button>
      </form>

      <?php /* ── PASO 4 ── */ elseif ($step === 4): ?>

      <form method="POST" id="adminForm">
        <div class="card">
          <div class="card-header">
            <div class="card-header-icon"><i class="fa-solid fa-building-columns"></i></div>
            <div>
              <div class="card-header-title">Informacion de la Clinica</div>
              <div class="card-header-sub">Nombre que aparecera en el sistema</div>
            </div>
          </div>
          <div class="card-body">
            <div class="form-group">
              <label class="form-label">Nombre de la clinica / empresa</label>
              <input type="text" class="form-input" name="clinic_name"
                     value="<?= htmlspecialchars($_POST['clinic_name'] ?? 'Mi Clinica') ?>"
                     placeholder="Clinica San Rafael">
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <div class="card-header-icon"><i class="fa-solid fa-user-shield"></i></div>
            <div>
              <div class="card-header-title">Cuenta de Administrador</div>
              <div class="card-header-sub">Credenciales para acceder al sistema como administrador</div>
            </div>
          </div>
          <div class="card-body">
            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Nombre completo</label>
                <input type="text" class="form-input" name="admin_name"
                       value="<?= htmlspecialchars($_POST['admin_name'] ?? 'Administrador') ?>"
                       placeholder="Dr. Roberto Garcia">
              </div>
              <div class="form-group">
                <label class="form-label">Correo electronico <span class="req">*</span></label>
                <input type="email" class="form-input" name="admin_email"
                       value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>"
                       placeholder="admin@tudominio.com" required>
              </div>
              <div class="form-group">
                <label class="form-label">Contrasena <span class="req">*</span></label>
                <input type="password" class="form-input" name="admin_pass" id="adminPass"
                       placeholder="Minimo 6 caracteres" required minlength="6">
              </div>
              <div class="form-group">
                <label class="form-label">Confirmar contrasena <span class="req">*</span></label>
                <input type="password" class="form-input" id="adminConfirm"
                       placeholder="Repite la contrasena" required minlength="6">
              </div>
            </div>
            <div style="background:#F0F9FF;border:1px solid #BAE6FD;border-radius:8px;padding:.75rem 1rem;font-size:.78rem;color:#0E7490;">
              <i class="fa-solid fa-circle-info"></i>
              Los roles personalizados (secretaria, recepcionista) se pueden crear desde el panel de administracion
              despues de instalar.
            </div>
          </div>
        </div>

        <!-- Google OAuth (colapsable) -->
        <div class="card">
          <div class="card-body" style="padding-bottom:.75rem;">
            <div class="optional-toggle" onclick="toggleOauth(this)">
              <i class="fa-brands fa-google" style="color:#4285F4;"></i>
              Google OAuth (opcional)
              <i class="fa-solid fa-chevron-down toggle-icon" style="font-size:.7rem;margin-left:auto;transition:transform .2s;"></i>
            </div>
            <div class="optional-body" id="oauthBody">
              <p style="font-size:.78rem;color:var(--text-muted);margin-bottom:.85rem;">
                Permite iniciar sesion con Google. Obtén credenciales en
                <a href="https://console.cloud.google.com" target="_blank" style="color:var(--primary);">console.cloud.google.com</a>
              </p>
              <div class="grid-2">
                <div class="form-group">
                  <label class="form-label">Google Client ID</label>
                  <input type="text" class="form-input" name="google_client_id"
                         value="<?= htmlspecialchars($_POST['google_client_id'] ?? '') ?>"
                         placeholder="xxxxx.apps.googleusercontent.com">
                </div>
                <div class="form-group">
                  <label class="form-label">Google Client Secret</label>
                  <input type="text" class="form-input" name="google_client_secret"
                         value="<?= htmlspecialchars($_POST['google_client_secret'] ?? '') ?>"
                         placeholder="GOCSPX-...">
                </div>
              </div>
              <div class="form-hint" style="margin-top:-.5rem;">
                URI de callback:
                <code style="background:#F1F5F9;padding:2px 6px;border-radius:4px;font-size:.75rem;">
                  <?= htmlspecialchars(($_SESSION['db']['app_url'] ?? '[APP_URL]') . '/auth/google/callback.php') ?>
                </code>
              </div>
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary">
          <i class="fa-solid fa-rocket"></i> Finalizar instalacion
        </button>
      </form>

      <?php /* ── PASO 5 ── */ elseif ($step === 5): ?>

      <div class="success-wrap">
        <div class="success-badge">
          <i class="fa-solid fa-circle-check"></i>
        </div>
        <h2 class="success-title">Sistema Instalado</h2>
        <p class="success-sub">
          <?= htmlspecialchars($clinicNameDone) ?> esta configurado y listo para operar.
          Inicia sesion con las credenciales del administrador.
        </p>
        <a href="login.php" class="btn btn-success" style="font-size:1rem;padding:.9rem 2.5rem;margin-bottom:2rem;">
          <i class="fa-solid fa-right-to-bracket"></i> Acceder al sistema
        </a>
      </div>

      <div class="credentials-card">
        <h4><i class="fa-solid fa-key" style="color:var(--primary);"></i> &nbsp;Tu cuenta de administrador</h4>
        <div class="cred-item">
          <div class="cred-icon"><i class="fa-solid fa-envelope"></i></div>
          <div>
            <div class="cred-label">Correo electronico</div>
            <div class="cred-val"><?= htmlspecialchars($adminEmailDone) ?></div>
          </div>
        </div>
        <div class="cred-item">
          <div class="cred-icon"><i class="fa-solid fa-lock"></i></div>
          <div>
            <div class="cred-label">Contrasena</div>
            <div class="cred-val">La que definiste en el paso anterior</div>
          </div>
        </div>
      </div>

      <div class="credentials-card">
        <h4><i class="fa-solid fa-users" style="color:var(--text-muted);"></i> &nbsp;Usuarios de prueba (contrasena: password)</h4>
        <div class="cred-item">
          <div class="cred-icon" style="background:#FFF7ED;color:#C2410C;"><i class="fa-solid fa-user-doctor"></i></div>
          <div>
            <div class="cred-label">Doctor</div>
            <div class="cred-val">doctor@medcrm.com</div>
          </div>
        </div>
        <div class="cred-item">
          <div class="cred-icon" style="background:#F0FDF4;color:#15803D;"><i class="fa-solid fa-user"></i></div>
          <div>
            <div class="cred-label">Paciente</div>
            <div class="cred-val">paciente@medcrm.com</div>
          </div>
        </div>
      </div>

      <div class="alert alert-warning" style="max-width:520px;margin:0 auto;">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <div>
          <strong>Seguridad:</strong> Elimina o protege el archivo <code>install.php</code>
          antes de pasar a produccion.
        </div>
      </div>

      <?php endif; ?>
    </div><!-- /content-body -->
  </div><!-- /main-content -->
</div><!-- /layout -->

<script>
function toggleOauth(el) {
  var body = document.getElementById('oauthBody');
  var icon = el.querySelector('.toggle-icon');
  body.classList.toggle('show');
  if (icon) icon.style.transform = body.classList.contains('show') ? 'rotate(180deg)' : '';
}

// Validar que las contraseñas coincidan antes de enviar
var adminForm = document.getElementById('adminForm');
if (adminForm) {
  adminForm.addEventListener('submit', function(e) {
    var pass    = document.getElementById('adminPass');
    var confirm = document.getElementById('adminConfirm');
    if (pass && confirm && pass.value !== confirm.value) {
      e.preventDefault();
      alert('Las contrasenas no coinciden. Verificalas e intenta nuevamente.');
      confirm.focus();
    }
  });
}
</script>
</body>
</html>
