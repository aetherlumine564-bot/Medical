<?php
/**
 * MedCRM – Google OAuth: Redirigir al proveedor
 * Genera el state CSRF y redirige a Google
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once APP_ROOT . '/config/oauth.php';

// Si ya está logueado, no necesita OAuth
if (isLoggedIn()) {
    redirectAfterLogin();
}

// Verificar que OAuth esté habilitado
if (!GOOGLE_OAUTH_ENABLED) {
    setFlash('error', 'El inicio de sesión con Google no está disponible en este momento.');
    redirect('login.php');
}

// Generar token CSRF anti-forgery
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;
$_SESSION['oauth_state_time'] = time();

// Construir URL de autorización
$params = http_build_query([
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => GOOGLE_SCOPES,
    'state'         => $state,
    'access_type'   => 'online',
    'prompt'        => 'select_account',
]);

header('Location: ' . GOOGLE_AUTH_URL . '?' . $params);
exit;
