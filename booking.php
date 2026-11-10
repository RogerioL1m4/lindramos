<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
session_start();

function result_page(string $title, string $message, bool $success = false, ?string $manageUrl = null, int $status = 400): never {
    http_response_code($success ? 200 : $status);
    ?><!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($title) ?></title><link rel="stylesheet" href="assets/style.css"><body class="result-page"><main class="result-card"><p class="eyebrow"><?= $success ? 'AGENDAMENTO CONFIRMADO' : 'ATENÇÃO' ?></p><h1><?= e($title) ?></h1><p><?= e($message) ?></p><?php if ($manageUrl): ?><p>Guarde este link para cancelar ou remarcar: <a href="<?= e($manageUrl) ?>"><?= e($manageUrl) ?></a></p><?php endif; ?><a class="button" href="index.php#agendar">Voltar para a página</a></main></body></html><?php
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') result_page('Acesso inválido', 'Envie o formulário de agendamento pela página inicial.');
if (!csrf_valid()) result_page('Sessão expirada', 'Atualize a página e tente novamente.');
// Campo-isca: robôs preenchem, pessoas não veem.
if (trim((string)($_POST['website'] ?? '')) !== '') {
    app_log('warning', 'Honeypot acionado.');
    result_page('Pedido recebido', 'Obrigada!', true);
}

$name = trim((string)($_POST['name'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$serviceName = trim((string)($_POST['service'] ?? ''));
$date = trim((string)($_POST['date'] ?? ''));
$time = trim((string)($_POST['time'] ?? ''));
$notes = trim((string)($_POST['notes'] ?? ''));

$service = BookingService::serviceByName($serviceName);
$start = DateTimeImmutable::createFromFormat('!Y-m-d H:i', "$date $time", new DateTimeZone(APP_TIMEZONE));
$valid = $name !== '' && mb_strlen($name) <= 100 && preg_match('/^[\d\s()+\-]{8,25}$/', $phone)
    && $email && mb_strlen($email) <= 150 && $service && mb_strlen($notes) <= 500
    && $start && $start->format('Y-m-d H:i') === "$date $time";
if (!$valid) result_page('Confira seus dados', 'Preencha todos os campos obrigatórios com informações válidas.');

try {
    $result = (new BookingService(calendar_gateway()))->create([
        'name' => $name, 'phone' => $phone, 'email' => $email, 'service' => $service['name'],
        'startTs' => $start->getTimestamp(), 'notes' => $notes,
    ]);
    result_page('Pedido de agendamento confirmado!', 'Seu horário foi registrado na agenda e você receberá o convite por e-mail.', true, manage_url($result['token']));
} catch (BookingException $ex) {
    $status = ['slot_taken' => 409, 'rate_limited' => 429, 'unavailable' => 503, 'pending' => 202][$ex->kind] ?? 400;
    $title = ['slot_taken' => 'Esse horário não está mais disponível', 'pending' => 'Estamos confirmando seu horário'][$ex->kind] ?? 'Não foi possível concluir';
    result_page($title, $ex->getMessage(), false, null, $status);
} catch (Throwable $ex) {
    app_log('error', 'Falha inesperada no agendamento: ' . $ex->getMessage(), ['class' => get_class($ex)]);
    result_page('Não foi possível concluir agora', 'Entre em contato pelo WhatsApp ou tente novamente mais tarde.', false, null, 500);
}
