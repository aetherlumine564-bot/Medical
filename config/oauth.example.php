<?php
/**
 * MedCRM – Configuración Google OAuth 2.0
 *
 * Credenciales del proyecto MedCRM en Google Cloud Console.
 *
 * URI de redireccionamiento autorizada (agrégala en Google Cloud):
 *   http://localhost/medical/auth/google/callback.php
 *
 * IMPORTANTE: Si tu carpeta tiene otro nombre (medical-crm, medcrm, etc.),
 * cambia GOOGLE_REDIRECT_URI y también en Google Cloud Console
 * → APIs y Servicios → Credenciales → tu OAuth Client → URI autorizados.
 */

if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', '');
    define('GOOGLE_CLIENT_SECRET', '');

    // ── Cambia esta URI según el nombre de tu carpeta en htdocs ────────────
    // Ejemplos:
    //   localhost/medical          → http://localhost/medical/auth/google/callback.php
    //   localhost/medical-crm      → http://localhost/medical-crm/auth/google/callback.php
    //   tudominio.com              → https://tudominio.com/auth/google/callback.php
    define('GOOGLE_REDIRECT_URI', APP_URL . '/auth/google/callback.php');

    define('GOOGLE_OAUTH_ENABLED', false);
    define('GOOGLE_SCOPES',        'openid email profile');
    define('GOOGLE_AUTH_URL',      'https://accounts.google.com/o/oauth2/v2/auth');
    define('GOOGLE_TOKEN_URL',     'https://oauth2.googleapis.com/token');
    define('GOOGLE_USERINFO_URL',  'https://www.googleapis.com/oauth2/v3/userinfo');
}
