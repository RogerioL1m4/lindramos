<?php
declare(strict_types=1);
date_default_timezone_set('America/Fortaleza');

// Ajustes do ambiente (não versionar): APP_URL, STORAGE_DIR, ADMIN_PASSWORD_HASH, ADMIN_ALLOWED_IPS.
if (is_file(__DIR__ . '/config.local.php')) require __DIR__ . '/config.local.php';
if (!defined('APP_URL')) define('APP_URL', ''); // Produção: 'https://www.seudominio.com.br' (sem barra final).
if (!defined('STORAGE_DIR')) define('STORAGE_DIR', __DIR__ . '/storage'); // Produção: pasta FORA de public_html.
if (!defined('ADMIN_ALLOWED_IPS')) define('ADMIN_ALLOWED_IPS', []); // Opcional: lista de IPs autorizados ao painel.
// Senha inicial do painel (conhecida). Troque pelo painel ou defina outro hash em config.local.php.
if (!defined('ADMIN_PASSWORD_HASH')) define('ADMIN_PASSWORD_HASH', '$2y$10$NfEXepRqtFW5CriXFH4xee47j8WhzO54YH24dpL6rwUmQKzNH4RN6');

const APP_TIMEZONE = 'America/Fortaleza';
// Valores padrão; o painel administrativo (Dados do site) sobrescreve cada um deles.
const DEFAULT_BUSINESS_NAME = 'Ateliê Lindinez';
const DEFAULT_BUSINESS_CITY = 'Mossoró/RN';
const DEFAULT_WHATSAPP_NUMBER = '5584999999999';
const DEFAULT_BUSINESS_EMAIL = 'contato@seudominio.com.br';
const OPENING_HOUR = 8;  // Horário inicial de atendimento.
const CLOSING_HOUR = 18; // Horário em que o atendimento termina.
const SLOT_STEP_MINUTES = 30;
const MIN_NOTICE_MINUTES = 60;      // Antecedência mínima para agendar.
const MAX_DAYS_AHEAD = 90;          // Horizonte máximo de agendamento.
const CLIENT_CHANGE_MIN_HOURS = 6;  // Antecedência mínima para a cliente cancelar/remarcar sozinha.
const BOOKINGS_PER_IP_PER_HOUR = 8;
const MAX_ACTIVE_PER_CLIENT = 3;
const ADMIN_IDLE_SESSION_SECONDS = 1800;
const ADMIN_ABSOLUTE_SESSION_SECONDS = 28800;
const ADMIN_MAX_LOGIN_ATTEMPTS = 5;
const ADMIN_LOCKOUT_SECONDS = 900;

const SERVICES = [
    ['number' => '01', 'name' => 'Design de sobrancelhas', 'description' => 'Mapeamento e desenho personalizado para valorizar seus traços.', 'duration' => '40 min', 'duration_minutes' => 40, 'price' => 'Consulte'],
    ['number' => '02', 'name' => 'Design + henna', 'description' => 'Definição do formato com acabamento de henna sob medida.', 'duration' => '60 min', 'duration_minutes' => 60, 'price' => 'Consulte'],
    ['number' => '03', 'name' => 'Brow lamination', 'description' => 'Alinhamento dos fios para um efeito mais preenchido e elegante.', 'duration' => '60 min', 'duration_minutes' => 60, 'price' => 'Consulte'],
];

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function csrf_token(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function app_base_url(): string {
    if (APP_URL !== '') return rtrim(APP_URL, '/');
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return (is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir;
}
function manage_url(string $token): string {
    return app_base_url() . '/manage.php?t=' . $token;
}

/** Aceita o link da página de agendamento do Google ou o código <iframe>; devolve a URL validada ou null. */
function parse_scheduling_input(string $input): ?string {
    $input = trim($input);
    if (preg_match('/\burl\s*:\s*["\']([^"\']+)["\']/i', $input, $m) || preg_match('/src\s*=\s*["\']([^"\']+)["\']/i', $input, $m)) $input = html_entity_decode($m[1], ENT_QUOTES);
    $parts = parse_url($input);
    if (!$parts || ($parts['scheme'] ?? '') !== 'https' || strtolower($parts['host'] ?? '') !== 'calendar.google.com') return null;
    if (!str_starts_with($parts['path'] ?? '', '/calendar/appointments/')) return null;
    return $input;
}
function scheduling_url(): ?string {
    $url = app_settings()['scheduling_url'] ?? '';
    return is_string($url) && $url !== '' ? parse_scheduling_input($url) : null;
}
function scheduling_text_defaults(): array {
    return [
        'scheduling_eyebrow' => ['AGENDAMENTO · ONLINE', 40],
        'scheduling_title' => ['Escolha seu horário', 60],
        'scheduling_text' => ['Veja os dias e horários disponíveis na agenda e reserve em poucos cliques.', 240],
    ];
}
function scheduling_text(string $key): string {
    $def = scheduling_text_defaults()[$key];
    $v = app_settings()[$key] ?? '';
    return is_string($v) && trim($v) !== '' ? mb_substr(trim($v), 0, $def[1]) : $def[0];
}
function scheduling_button_color(): string {
    $c = app_settings()['scheduling_color'] ?? '';
    return is_string($c) && preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : '#192f1d';
}
function scheduling_button_label(): string {
    $l = app_settings()['scheduling_label'] ?? '';
    return is_string($l) && trim($l) !== '' ? mb_substr(trim($l), 0, 40) : 'Agendar um compromisso';
}

/** Dados do negócio editáveis no painel; cada campo vazio usa o padrão. */
function site_fields(): array {
    return [
        'site_name' => ['label' => 'Nome do negócio', 'max' => 80, 'default' => DEFAULT_BUSINESS_NAME],
        'site_city' => ['label' => 'Cidade/UF', 'max' => 80, 'default' => DEFAULT_BUSINESS_CITY],
        'site_address' => ['label' => 'Endereço', 'max' => 200, 'default' => ''],
        'site_phone' => ['label' => 'Telefone', 'max' => 30, 'default' => ''],
        'site_whatsapp' => ['label' => 'WhatsApp (com DDI e DDD)', 'max' => 20, 'default' => DEFAULT_WHATSAPP_NUMBER],
        'site_email' => ['label' => 'E-mail de contato', 'max' => 120, 'default' => DEFAULT_BUSINESS_EMAIL],
    ];
}
function site_value(string $key): string {
    $f = site_fields()[$key];
    $v = app_settings()[$key] ?? '';
    return is_string($v) && trim($v) !== '' ? mb_substr(trim($v), 0, $f['max']) : $f['default'];
}
function site_whatsapp_url(string $message): string {
    return 'https://wa.me/' . WHATSAPP_NUMBER . '?text=' . rawurlencode($message);
}
/** Aceita código <iframe> do Maps, link de incorporação ou link comum; devolve ['embed' => ?, 'link' => ?]. */
function parse_maps_input(string $input): array {
    $input = trim($input);
    if (preg_match('/src\s*=\s*["\']([^"\']+)["\']/i', $input, $m)) $input = html_entity_decode($m[1], ENT_QUOTES);
    $p = parse_url($input);
    if (!$p || ($p['scheme'] ?? '') !== 'https') return ['embed' => null, 'link' => null];
    $host = strtolower($p['host'] ?? '');
    $path = $p['path'] ?? '';
    if (in_array($host, ['www.google.com', 'google.com', 'maps.google.com', 'www.google.com.br', 'google.com.br'], true) && str_starts_with($path, '/maps')) {
        return ['embed' => str_starts_with($path, '/maps/embed') || str_contains($p['query'] ?? '', 'output=embed') ? $input : null, 'link' => $input];
    }
    if (in_array($host, ['maps.app.goo.gl', 'goo.gl'], true)) return ['embed' => null, 'link' => $input];
    return ['embed' => null, 'link' => null];
}
/** Mapa a exibir: o informado no painel ou, na falta dele, uma busca pelo endereço. */
function site_map(): array {
    $maps = parse_maps_input((string)(app_settings()['site_maps'] ?? ''));
    $address = site_value('site_address');
    if ($maps['embed'] === null && $address !== '') {
        $q = rawurlencode($address . ' ' . site_value('site_city'));
        $maps['embed'] = 'https://www.google.com/maps?q=' . $q . '&output=embed';
    }
    if ($maps['link'] === null && $address !== '') {
        $maps['link'] = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($address . ' ' . site_value('site_city'));
    }
    return $maps;
}

require __DIR__ . '/src/security.php';
require __DIR__ . '/src/storage.php';
require __DIR__ . '/src/db.php';
if (is_file(__DIR__ . '/vendor/autoload.php')) require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/google.php';
require __DIR__ . '/src/booking_service.php';
require __DIR__ . '/src/admin_auth.php';

define('BUSINESS_NAME', site_value('site_name'));
define('BUSINESS_CITY', site_value('site_city'));
define('WHATSAPP_NUMBER', preg_replace('/\D+/', '', site_value('site_whatsapp')) ?: DEFAULT_WHATSAPP_NUMBER);
define('BUSINESS_EMAIL', site_value('site_email'));

init_error_handling();
harden_session();
send_security_headers();
