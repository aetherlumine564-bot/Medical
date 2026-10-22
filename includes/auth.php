<?php
/**
 * MedCRM: autenticación y permisos.
 * Solo el rol admin tiene acceso total. Los demás usan role_permissions.
 * Las cuentas sin un rol válido no pueden iniciar sesión.
 */

// ── Helpers de rol ────────────────────────────────────────────────────────────

/**
 * Devuelve true si el rol actual es equivalente a "admin"
 * (únicamente el slug admin).
 */
function isAdminRole($role = null) {
    $r = $role !== null ? $role : ($_SESSION['user_role'] ?? '');
    return ($r === 'admin');
}

/**
 * Devuelve true si el rol actual puede acceder al área de doctores.
 */
function isDoctorRole($role = null) {
    $r = $role !== null ? $role : ($_SESSION['user_role'] ?? '');
    return ($r === 'doctor');
}

/**
 * Devuelve true si el rol actual puede acceder al área de pacientes.
 */
function isPatientRole($role = null) {
    $r = $role !== null ? $role : ($_SESSION['user_role'] ?? '');
    return ($r === 'patient');
}

// ── Verificar sesión activa ───────────────────────────────────────────────────
function isLoggedIn() {
    return !empty($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] !== '';
}

// ── Requerir login ────────────────────────────────────────────────────────────
function requireLogin() {
    if (!isLoggedIn()) {
        setFlash('warning', 'Debes iniciar sesión para continuar.');
        redirect('login.php');
    }
}

// ── Requerir rol específico ───────────────────────────────────────────────────
// Acepta un slug o un array de slugs.
// Cada rol se verifica explícitamente.
function requireRole($roles) {
    requireLogin();

    $allowed     = is_array($roles) ? $roles : [$roles];
    $currentRole = $_SESSION['user_role'] ?? '';

    // Caso 1: la página requiere 'admin'.
    if (in_array('admin', $allowed, true) && isAdminRole($currentRole)) {
        return;
    }

    // Caso 2: la página requiere 'doctor' → solo doctors
    if (in_array('doctor', $allowed, true) && isDoctorRole($currentRole)) {
        return;
    }

    // Caso 3: la página requiere 'patient' → solo patients
    if (in_array('patient', $allowed, true) && isPatientRole($currentRole)) {
        return;
    }

    // Caso 4: verificación directa del slug exacto
    if (in_array($currentRole, $allowed, true)) {
        return;
    }

    // Sin acceso
    http_response_code(403);
    include APP_ROOT . '/includes/403.php';
    exit;
}

// ── Obtener usuario de sesión ─────────────────────────────────────────────────
function currentUser() {
    if (!isLoggedIn()) return null;
    static $user = null;
    if ($user === null) {
        $db   = getDB();
        // Exigir un rol existente.
        $stmt = $db->prepare(
            'SELECT u.*,
                    r.name AS role_name,
                    r.slug AS role_slug
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? AND u.status = 1
             LIMIT 1'
        );
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    return $user ?: null;
}

// ── Verificar permiso ─────────────────────────────────────────────────────────
function hasPermission($permissionSlug) {
    if (!isLoggedIn()) return false;

    $role = $_SESSION['user_role'] ?? '';

    // Solo admin tiene todos los permisos.
    if (isAdminRole($role)) return true;

    // Los demás roles requieren permisos explícitos.
    static $permissions = null;
    if ($permissions === null) {
        try {
            $db   = getDB();
            $stmt = $db->prepare(
                'SELECT p.slug
                 FROM permissions p
                 JOIN role_permissions rp ON rp.permission_id = p.id
                 JOIN roles r ON r.id = rp.role_id
                 WHERE r.slug = ?'
            );
            $stmt->execute([$role]);
            $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            $permissions = [];
        }
    }
    return in_array($permissionSlug, $permissions, true);
}

// ── Requerir permiso ──────────────────────────────────────────────────────────
function requirePermission($permissionSlug) {
    requireLogin();
    if (!hasPermission($permissionSlug)) {
        http_response_code(403);
        include APP_ROOT . '/includes/403.php';
        exit;
    }
}

// ── Login ─────────────────────────────────────────────────────────────────────
function login($email, $password) {
    $db = getDB();

    // No permitir el login con un rol inexistente.
    $stmt = $db->prepare(
        'SELECT u.*,
                r.slug AS role_slug
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.email = ? AND u.status = 1
         LIMIT 1'
    );
    $stmt->execute([strtolower(trim($email))]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) return false;
    if (!password_verify($password, $user['password'])) return false;

    // Rechazar roles vacíos.
    $roleSlug = trim($user['role_slug'] ?? '');
    if ($roleSlug === '') return false;

    // Regenerar ID de sesión (seguridad)
    session_regenerate_id(true);

    // Guardar datos en sesión
    $_SESSION['user_id']    = (int)$user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $roleSlug;
    $_SESSION['user_avatar']= $user['avatar'] ?? '';

    // Registrar último acceso (silenciamos errores no críticos)
    try {
        $db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')
           ->execute([$user['id']]);
    } catch (Exception $e) { /* no crítico */ }

    return true;
}

// ── Logout ────────────────────────────────────────────────────────────────────
function logout() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    redirect('login.php');
}

// ── Redirigir según rol después del login ─────────────────────────────────────
// Lógica dinámica: no se necesita enumerar roles personalizados.
function redirectAfterLogin() {
    $role = $_SESSION['user_role'] ?? '';

    if (isDoctorRole($role)) {
        redirect('doctor/dashboard.php');
    } elseif (isPatientRole($role)) {
        redirect('patient/dashboard.php');
    } elseif (isAdminRole($role)) {
        redirect('admin/dashboard.php');
    } else {
        redirect('profile.php');
    }
}
