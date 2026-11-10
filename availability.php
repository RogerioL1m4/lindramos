<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    json_response(405, ['error' => 'Método não permitido.']);
}
if (!is_file(__DIR__ . '/vendor/autoload.php') || !google_is_connected()) {
    app_log('error', 'Disponibilidade indisponível: Google não configurado/conectado.');
    json_response(503, ['error' => 'O agendamento online está temporariamente indisponível. Fale conosco pelo WhatsApp.']);
}
if (throttle_count('avail:' . client_ip(), 300) >= 90) json_response(429, ['error' => 'Muitas consultas. Aguarde um instante.']);
throttle_hit('avail:' . client_ip());

$service = BookingService::serviceByName(trim((string)($_GET['service'] ?? '')));
$date = trim((string)($_GET['date'] ?? ''));
if ($service === null) json_response(400, ['error' => 'Escolha um serviço e uma data válidos.']);

$gateway = calendar_gateway();
$bookings = new BookingService($gateway);

// Remarcação: a reserva atual da própria cliente (ou do admin autenticado) não bloqueia o novo horário.
$excludeId = null;
if (isset($_GET['t'])) {
    $own = $bookings->findByToken((string)$_GET['t']);
    if ($own) $excludeId = (int)$own['id'];
} elseif (isset($_GET['booking']) && admin_is_logged_in()) {
    $excludeId = (int)$_GET['booking'];
}

try {
    $day = $bookings->dayBounds($date);
    if ($day[1] < time()) json_response(400, ['error' => 'Escolha uma data futura.']);
    $bookings->reconcile();
    json_response(200, ['slots' => $bookings->slots($service, $date, $excludeId)]);
} catch (BookingException $ex) {
    json_response(400, ['error' => $ex->getMessage()]);
} catch (Throwable $ex) {
    app_log('error', 'Falha ao consultar disponibilidade: ' . $ex->getMessage(), ['class' => get_class($ex)]);
    json_response(503, ['error' => 'Não foi possível consultar a agenda agora. Fale conosco pelo WhatsApp ou tente novamente em instantes.']);
}
