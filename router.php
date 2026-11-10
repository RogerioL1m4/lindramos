<?php
// Roteador do servidor de desenvolvimento (php -S ... router.php): aplica as mesmas negações do .htaccess.
$path = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
if (preg_match('#(^|/)(src|vendor|tools|tests|storage)(/|$)#i', $path)
    || preg_match('#(^|/)\.#', $path)
    || preg_match('#(^|/)config[^/]*\.php$#i', $path)
    || preg_match('#\.(json|lock|md|sqlite3?|db|log|bak|old|new|example|ini)$#i', $path)
    || str_contains($path, '.sqlite')) {
    http_response_code(404);
    echo 'Not found';
    return true;
}
return false;
