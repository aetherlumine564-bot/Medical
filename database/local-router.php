<?php
// Protecciones locales: el servidor integrado de PHP no lee .htaccess.
if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if (strpos($path, "\0") !== false || strpos($path, '\\') !== false
    || preg_match('~(?:^|/)\.|^/(?:config|database)(?:/|$)|\.(?:sql|log|env|md|sh|ps1)$|^/(?:install|delete_installer)\.php(?:/|$)~i', $path)) {
    http_response_code(404);
    exit('Not found');
}
$root = realpath(dirname(__DIR__));
$target = realpath($root . $path);
if ($target === false || ($target !== $root && stripos($target, $root . DIRECTORY_SEPARATOR) !== 0)) {
    http_response_code(404);
    exit('Not found');
}
return false;