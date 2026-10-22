<?php
/**
 * MedCRM – Funciones de utilidad global
 */

// ── Escape seguro ─────────────────────────────────────────────────────────────
function e($str): string {
    if ($str === null) return '';
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── Redirección ───────────────────────────────────────────────────────────────
function redirect(string $url): void {
    header('Location: ' . APP_URL . '/' . ltrim($url, '/'));
    exit;
}

// ── Flash messages ───────────────────────────────────────────────────────────
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function renderFlash(): void {
    $flash = getFlash();
    if (!$flash) return;
    $icons = [
        'success' => 'circle-check',
        'error'   => 'circle-xmark',
        'warning' => 'triangle-exclamation',
        'info'    => 'circle-info',
    ];
    $icon = $icons[$flash['type']] ?? 'circle-info';
    echo '<div class="alert alert-' . e($flash['type']) . '" id="flash-msg">
        <i class="fa-solid fa-' . $icon . '"></i>
        <span>' . e($flash['message']) . '</span>
        <button class="alert-close" onclick="this.parentElement.remove()"><i class="fa-solid fa-xmark"></i></button>
    </div>';
}

// ── CSRF ──────────────────────────────────────────────────────────────────────
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    $token = $_POST['_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Token CSRF inválido.');
    }
}

function csrfField(): string {
    return '<input type="hidden" name="_token" value="' . csrfToken() . '">';
}

// ── Paginación ────────────────────────────────────────────────────────────────
function paginate(int $total, int $perPage, int $currentPage, string $url): string {
    $pages = (int) ceil($total / $perPage);
    if ($pages <= 1) return '';
    $html = '<div class="pagination">';
    for ($i = 1; $i <= $pages; $i++) {
        $active = $i === $currentPage ? ' active' : '';
        $html .= '<a href="' . $url . '?page=' . $i . '" class="page-btn' . $active . '">' . $i . '</a>';
    }
    $html .= '</div>';
    return $html;
}

// ── Formateo de fechas ────────────────────────────────────────────────────────
function formatDate(string $date, string $format = 'd/m/Y'): string {
    if (empty($date)) return '—';
    return date($format, strtotime($date));
}

function formatDateTime(string $dt): string {
    return formatDate($dt, 'd/m/Y H:i');
}

function timeAgo(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)    return 'hace un momento';
    if ($diff < 3600)  return 'hace ' . floor($diff / 60)   . ' min';
    if ($diff < 86400) return 'hace ' . floor($diff / 3600)  . ' h';
    return 'hace '     . floor($diff / 86400) . ' días';
}

// ── Estado de cita → badge ────────────────────────────────────────────────────
function appointmentBadge(string $status): string {
    $map = [
        'pendiente'   => ['warning', 'Pendiente'],
        'confirmada'  => ['info',    'Confirmada'],
        'en_curso'    => ['primary', 'En curso'],
        'completada'  => ['success', 'Completada'],
        'cancelada'   => ['danger',  'Cancelada'],
        'no_asistio'  => ['secondary','No asistió'],
    ];
    [$color, $label] = $map[$status] ?? ['secondary', $status];
    return '<span class="badge badge-' . $color . '">' . $label . '</span>';
}

// ── Badge de estado genérico ──────────────────────────────────────────────────
function statusBadge(int $status): string {
    return $status
        ? '<span class="badge badge-success">Activo</span>'
        : '<span class="badge badge-danger">Inactivo</span>';
}

// ── Configuración del sistema (tabla settings) ────────────────────────────────
function getSetting(string $key, string $default = ''): string {
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $db   = getDB();
        $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $val  = $stmt->fetchColumn();
        $cache[$key] = ($val !== false) ? (string)$val : $default;
    } catch (Exception $e) {
        $cache[$key] = $default;
    }
    return $cache[$key];
}

function setSetting(string $key, string $value): void {
    $db = getDB();
    $db->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = ?'
    )->execute([$key, $value, $value]);
}

// ── Avatar inicial ────────────────────────────────────────────────────────────
function avatarInitials(string $name): string {
    $parts = explode(' ', trim($name));
    $init  = strtoupper(substr($parts[0], 0, 1));
    if (isset($parts[1])) $init .= strtoupper(substr($parts[1], 0, 1));
    return $init;
}

// ── Validación básica ─────────────────────────────────────────────────────────
function validateRequired(array $fields, array $data): array {
    $errors = [];
    foreach ($fields as $field => $label) {
        if (empty(trim($data[$field] ?? ''))) {
            $errors[$field] = "El campo «{$label}» es obligatorio.";
        }
    }
    return $errors;
}

// ── Obtener ítems del sidebar según rol ───────────────────────────────────────
function getSidebarItems(string $role): array {
    $items = [
        'admin' => [
            ['icon' => 'gauge-high',     'label' => 'Dashboard',    'url' => 'admin/dashboard.php',                 'key' => 'dashboard'],
            ['icon' => 'users',          'label' => 'Usuarios',     'url' => 'admin/users/index.php',               'key' => 'users'],
            ['icon' => 'shield-halved',  'label' => 'Roles',        'url' => 'admin/roles/index.php',               'key' => 'roles'],
            ['icon' => 'user-injured',   'label' => 'Pacientes',    'url' => 'admin/patients/index.php',            'key' => 'patients'],
            ['icon' => 'user-doctor',    'label' => 'Doctores',     'url' => 'admin/doctors/index.php',             'key' => 'doctors'],
            ['icon' => 'calendar-check', 'label' => 'Citas',        'url' => 'admin/appointments/index.php',        'key' => 'appointments'],
            ['icon' => 'clock',          'label' => 'Horarios',     'url' => 'admin/schedules/index.php',           'key' => 'schedules'],
            ['icon' => 'calendar-days',  'label' => 'Calendario',   'url' => 'admin/calendar.php',                  'key' => 'calendar'],
            ['icon' => 'gear',           'label' => 'Configuración','url' => 'admin/settings.php',                  'key' => 'settings'],
        ],
        'doctor' => [
            ['icon' => 'gauge-high',     'label' => 'Mi Panel',     'url' => 'doctor/dashboard.php',                'key' => 'dashboard'],
            ['icon' => 'calendar-check', 'label' => 'Mis Citas',    'url' => 'doctor/appointments.php',             'key' => 'appointments'],
            ['icon' => 'user-injured',   'label' => 'Mis Pacientes','url' => 'doctor/patients.php',                 'key' => 'patients'],
            ['icon' => 'clock',          'label' => 'Horarios',     'url' => 'doctor/schedules.php',                'key' => 'schedules'],
            ['icon' => 'calendar-days',  'label' => 'Calendario',   'url' => 'doctor/calendar.php',                 'key' => 'calendar'],
        ],
        'patient' => [
            ['icon' => 'gauge-high',     'label' => 'Mi Panel',     'url' => 'patient/dashboard.php',               'key' => 'dashboard'],
            ['icon' => 'calendar-plus',  'label' => 'Nueva Cita',   'url' => 'patient/appointments/create.php',     'key' => 'new_appt'],
            ['icon' => 'list-check',     'label' => 'Mis Citas',    'url' => 'patient/appointments/index.php',      'key' => 'appointments'],
            ['icon' => 'notes-medical',  'label' => 'Mi Historial', 'url' => 'patient/history.php',                 'key' => 'history'],
        ],
    ];
    return $items[$role] ?? $items['patient'];
}
