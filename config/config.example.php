<?php
// ============================================================
// MEDCRM – CONFIGURACIÓN PRINCIPAL
// ============================================================

// ── Base de datos ────────────────────────────────────────────
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');           // Hostinger usa 3306 por defecto
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'medical_crm');
define('DB_CHARSET', 'utf8mb4');

// ── Aplicación ───────────────────────────────────────────────
define('APP_NAME',    'MedCRM');
define('APP_VERSION', '3.0.0');
define('APP_URL', 'http://127.0.0.1:8000');  // El instalador actualiza esto automáticamente
define('APP_ROOT',    dirname(__DIR__));         // Ruta física raíz del proyecto

// ── Zona horaria ─────────────────────────────────────────────
date_default_timezone_set('America/Santo_Domingo');

// ── Sesión ───────────────────────────────────────────────────
define('SESSION_LIFETIME', 7200);   // 2 horas en segundos

// ── Uploads ──────────────────────────────────────────────────
define('UPLOAD_DIR',      APP_ROOT . '/assets/img/avatars/');
define('UPLOAD_URL',      APP_URL  . '/assets/img/avatars/');
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024);   // 2 MB

// ── Errores ───────────────────────────────────────────────────
// En producción (Hostinger) los errores NO deben mostrarse al usuario
// Cambia a 0 / false para producción
define('APP_DEBUG', false);
if (defined('APP_DEBUG') && APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}

// ── Inicio de sesión segura ───────────────────────────────────
// En Hostinger con HTTPS, cambia 'secure' a true para mayor seguridad
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
               || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',   // Lax es más compatible que Strict en Hostinger
        ]);
    } else {
        session_set_cookie_params(SESSION_LIFETIME, '/', '', $isHttps, true);
    }
    session_start();
}

// ── Helpers ───────────────────────────────────────────────────
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/auth.php';

// ── Módulos opcionales ────────────────────────────────────────
require_once APP_ROOT . '/config/oauth.php';

// ── Conexión a la base de datos (PDO singleton) ───────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die('<div style="font-family:sans-serif;padding:2rem;color:#c0392b;">
                <h2>&#9888; Error de conexi&#243;n a la base de datos</h2>
                <p>' . htmlspecialchars($e->getMessage()) . '</p>
                <p>Revisa las credenciales en <code>config/config.php</code></p>
            </div>');
        }
    }
    return $pdo;
}

