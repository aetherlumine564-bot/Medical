<?php
/** Importación local para MySQL Workbench. Nunca reemplaza tablas existentes. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$configPath = dirname(__DIR__) . '/config/config.php';
$config = file_get_contents($configPath);
function configValue($name, $config) {
    if (!preg_match("/define\('" . preg_quote($name, '/') . "',\s*'((?:\\\\.|[^'\\\\])*)'\)/", $config, $match)) {
        throw new RuntimeException('No se pudo leer ' . $name . ' de config/config.php.');
    }
    return str_replace(["\\'", "\\\\"], ["'", "\\"], $match[1]);
}

try {
    $host = configValue('DB_HOST', $config);
    $port = configValue('DB_PORT', $config);
    $name = configValue('DB_NAME', $config);
    $user = getenv('MEDCRM_DB_USER') !== false ? getenv('MEDCRM_DB_USER') : configValue('DB_USER', $config);
    $password = getenv('MEDCRM_DB_PASSWORD') !== false ? getenv('MEDCRM_DB_PASSWORD') : configValue('DB_PASS', $config);
    if ($name !== 'medical_crm') {
        throw new RuntimeException('El esquema incluido requiere DB_NAME=medical_crm.');
    }
    if (!ctype_digit($port)) throw new RuntimeException('Puerto MySQL inválido.');
    $db = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $check = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?');
    $check->execute([$name]);
    if ((int)$check->fetchColumn() !== 0) {
        throw new RuntimeException('medical_crm ya contiene tablas. No se modificó la base; revisa los datos existentes antes de importar.');
    }
    $sql = file_get_contents(__DIR__ . '/medical_crm.sql');
    if ($sql === false) throw new RuntimeException('No se pudo leer medical_crm.sql.');
    // La importación solo está permitida sobre una base nueva o vacía.
    $sql = preg_replace('/^DROP TABLE IF EXISTS[^;]+;\s*$/m', '', $sql);
    $db->exec($sql);
    $roles = $db->query('SELECT r.name, COUNT(DISTINCT rp.permission_id) AS permisos,
        COUNT(DISTINCT u.id) AS usuarios FROM medical_crm.roles r
        LEFT JOIN medical_crm.role_permissions rp ON rp.role_id = r.id
        LEFT JOIN medical_crm.users u ON u.role_id = r.id
        GROUP BY r.id, r.name ORDER BY r.id')->fetchAll();
    $invalid = $db->query('SELECT COUNT(*) FROM medical_crm.users u LEFT JOIN medical_crm.roles r ON r.id = u.role_id WHERE r.id IS NULL')->fetchColumn();
    if ((int)$invalid !== 0) throw new RuntimeException('Se encontraron usuarios sin rol válido.');
    foreach (['DB_USER' => $user, 'DB_PASS' => $password] as $key => $value) {
        $config = preg_replace_callback("/define\('" . $key . "',\s*'(?:\\\\.|[^'\\\\])*'\);/", function () use ($key, $value) {
            return "define('" . $key . "', " . var_export($value, true) . ");";
        }, $config, 1);
    }
    if (file_put_contents($configPath, $config) === false) {
        throw new RuntimeException('La base fue creada, pero no se pudo guardar la configuración de conexión.');
    }
    echo "Base medical_crm creada y conexión configurada en el puerto $port.\n";
    foreach ($roles as $role) {
        echo $role['name'] . ': ' . $role['permisos'] . ' permisos, ' . $role['usuarios'] . " usuarios.\n";
    }
} catch (PDOException $e) {
    fwrite(STDERR, 'Error MySQL (' . ($e->errorInfo[1] ?? $e->getCode()) . "): no se pudo completar la conexión/importación. No se muestran credenciales.\n");
    exit(1);
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}