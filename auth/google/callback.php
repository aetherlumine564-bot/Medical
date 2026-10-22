<?php
/**
 * MedCRM – Google OAuth: Callback
 * Intercambia el código por token, obtiene datos del usuario,
 * lo crea o loguea, y redirige al dashboard.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once APP_ROOT . '/config/oauth.php';

// ── Función auxiliar: HTTP POST con file_get_contents (sin cURL) ──────────
function oauthPost(string $url, array $data): ?array {
    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($data),
            'timeout' => 15,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) return null;
    return json_decode($response, true);
}

// ── Función auxiliar: HTTP GET con token Bearer ───────────────────────────
function oauthGet(string $url, string $token): ?array {
    $context = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'header'  => "Authorization: Bearer $token\r\n",
            'timeout' => 15,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) return null;
    return json_decode($response, true);
}

// ── 1. Verificar que OAuth esté habilitado ────────────────────────────────
if (!GOOGLE_OAUTH_ENABLED) {
    setFlash('error', 'El inicio de sesión con Google no está disponible.');
    redirect('login.php');
}

// ── 2. Verificar estado CSRF ──────────────────────────────────────────────
$stateReceived = $_GET['state'] ?? '';
$stateStored   = $_SESSION['oauth_state'] ?? '';
$stateTime     = $_SESSION['oauth_state_time'] ?? 0;

// Limpiar state de sesión (uso único)
unset($_SESSION['oauth_state'], $_SESSION['oauth_state_time']);

if (empty($stateReceived) ||
    !hash_equals($stateStored, $stateReceived) ||
    (time() - $stateTime) > 600)   // 10 minutos máximo
{
    setFlash('error', 'Token de seguridad inválido o expirado. Intenta de nuevo.');
    redirect('login.php');
}

// ── 3. Verificar que no hubo error de Google ──────────────────────────────
if (!empty($_GET['error'])) {
    $msg = $_GET['error'] === 'access_denied'
        ? 'Cancelaste el inicio de sesión con Google.'
        : 'Error de autenticación con Google: ' . e($_GET['error']);
    setFlash('warning', $msg);
    redirect('login.php');
}

// ── 4. Obtener código de autorización ─────────────────────────────────────
$code = $_GET['code'] ?? '';
if (empty($code)) {
    setFlash('error', 'No se recibió el código de autorización de Google.');
    redirect('login.php');
}

// ── 5. Intercambiar código por Access Token ───────────────────────────────
$tokenData = oauthPost(GOOGLE_TOKEN_URL, [
    'code'          => $code,
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'grant_type'    => 'authorization_code',
]);

if (empty($tokenData['access_token'])) {
    setFlash('error', 'No se pudo obtener el token de acceso de Google. Intenta de nuevo.');
    redirect('login.php');
}

$accessToken = $tokenData['access_token'];

// ── 6. Obtener datos del usuario desde Google ─────────────────────────────
$googleUser = oauthGet(GOOGLE_USERINFO_URL, $accessToken);

if (empty($googleUser['email'])) {
    setFlash('error', 'No se pudo obtener la información de tu cuenta de Google.');
    redirect('login.php');
}

$googleId    = $googleUser['sub']     ?? '';
$googleEmail = strtolower(trim($googleUser['email']));
$googleName  = trim($googleUser['name'] ?? '');
$googlePic   = $googleUser['picture'] ?? '';

// ── 7. Buscar usuario en la base de datos ─────────────────────────────────
$db = getDB();

// Buscar por google_id primero, luego por email
$stmt = $db->prepare(
    'SELECT u.*, r.slug AS role_slug
     FROM users u
     JOIN roles r ON r.id = u.role_id
     WHERE (u.google_id = ? OR u.email = ?) AND u.status = 1
     LIMIT 1'
);
$stmt->execute([$googleId, $googleEmail]);
$user = $stmt->fetch();

// ── 8. Crear usuario si no existe ─────────────────────────────────────────
if (!$user) {
    // Obtener rol "patient" (los usuarios de Google se registran como pacientes)
    $roleStmt = $db->prepare('SELECT id FROM roles WHERE slug = ? LIMIT 1');
    $roleStmt->execute(['patient']);
    $patientRole = $roleStmt->fetch();

    if (!$patientRole) {
        setFlash('error', 'Error de configuración: rol de paciente no encontrado.');
        redirect('login.php');
    }

    // Insertar nuevo usuario
    $insertStmt = $db->prepare(
        'INSERT INTO users (name, email, password, google_id, role_id, status, created_at)
         VALUES (?, ?, ?, ?, ?, 1, NOW())'
    );
    $insertStmt->execute([
        $googleName ?: $googleEmail,
        $googleEmail,
        '',          // sin contraseña (usuario OAuth)
        $googleId,
        $patientRole['id'],
    ]);

    $newUserId = (int)$db->lastInsertId();

    // Crear registro de paciente automáticamente
    $patientStmt = $db->prepare(
        'INSERT INTO patients (user_id, created_at) VALUES (?, NOW())'
    );
    $patientStmt->execute([$newUserId]);

    // Volver a leer el usuario completo con role_slug
    $stmt2 = $db->prepare(
        'SELECT u.*, r.slug AS role_slug
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.id = ? LIMIT 1'
    );
    $stmt2->execute([$newUserId]);
    $user = $stmt2->fetch();
}

// ── 9. Actualizar google_id si aún no lo tiene ────────────────────────────
if ($user && empty($user['google_id']) && !empty($googleId)) {
    $db->prepare('UPDATE users SET google_id = ? WHERE id = ?')
       ->execute([$googleId, $user['id']]);
}

// ── 10. Verificar que el usuario fue encontrado/creado ────────────────────
if (!$user) {
    setFlash('error', 'No se pudo acceder a tu cuenta. Contacta al administrador.');
    redirect('login.php');
}

// ── 11. Establecer sesión EXACTAMENTE igual que login() ──────────────────
session_regenerate_id(true);

$_SESSION['user_id']    = $user['id'];
$_SESSION['user_name']  = $user['name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_role']  = $user['role_slug'];
$_SESSION['user_avatar']= $user['avatar'] ?? '';

// Registrar último acceso
$db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')
   ->execute([$user['id']]);

// ── 12. Redirigir al dashboard según rol ─────────────────────────────────
redirectAfterLogin();
