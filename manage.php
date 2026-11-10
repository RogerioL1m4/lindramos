<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
send_security_headers(true);
session_start();

$tz = new DateTimeZone(APP_TIMEZONE);
$token = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$service = new BookingService(calendar_gateway());
$booking = $service->findByToken($token);

if ($booking && throttle_count('manage:' . client_ip(), 600) > 60) $booking = null;
throttle_hit('manage:' . client_ip());

$message = '';
$messageType = 'ok';
if ($booking && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    try {
        if (!csrf_valid()) throw new BookingException('Sessão expirada. Atualize a página e tente novamente.');
        if ($action === 'cancel') {
            $service->cancel((int)$booking['id'], 'client', true);
            $message = 'Agendamento cancelado.';
        } elseif ($action === 'reschedule') {
            $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i', (string)($_POST['date'] ?? '') . ' ' . (string)($_POST['time'] ?? ''), $tz);
            if (!$start) throw new BookingException('Escolha uma data e um horário válidos.');
            $service->reschedule((int)$booking['id'], $start->getTimestamp(), 'client', true);
            $message = 'Agendamento remarcado! O convite do Google foi atualizado.';
        }
    } catch (BookingException $ex) {
        $message = $ex->getMessage();
        $messageType = 'error';
    } catch (Throwable $ex) {
        app_log('error', 'Falha em manage.php: ' . $ex->getMessage());
        $message = 'Não foi possível concluir agora. Fale conosco pelo WhatsApp.';
        $messageType = 'error';
    }
    $booking = $service->findByToken($token);
}
$active = $booking && in_array($booking['status'], ['pending', 'confirmed'], true);
$labels = ['pending' => 'Em confirmação', 'confirmed' => 'Confirmado', 'cancelled' => 'Cancelado', 'failed' => 'Não concluído', 'rejected' => 'Não concluído'];
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Meu agendamento | <?= e(BUSINESS_NAME) ?></title>
  <link rel="stylesheet" href="assets/style.css">
  <script src="assets/admin.js" defer></script>
</head>
<body class="result-page">
  <main class="result-card">
    <p class="eyebrow">MEU AGENDAMENTO</p>
    <?php if (!$booking): ?>
      <h1>Link inválido</h1>
      <p>Não encontramos esse agendamento. Fale conosco pelo WhatsApp.</p>
    <?php else: ?>
      <h1><?= e($booking['service']) ?></h1>
      <?php if ($message): ?><p class="admin-flash admin-flash-<?= e($messageType) ?>" role="status"><?= e($message) ?></p><?php endif; ?>
      <p><strong><?= e((new DateTimeImmutable('@' . $booking['start_ts']))->setTimezone($tz)->format('d/m/Y \à\s H:i')) ?></strong> · <?= e($labels[$booking['status']] ?? $booking['status']) ?></p>
      <?php if ($active): ?>
        <?php if ($booking['status'] === 'confirmed'): ?>
        <form method="post" action="manage.php" class="reschedule-form booking-form" data-service="<?= e($booking['service']) ?>" data-token="<?= e($token) ?>">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="t" value="<?= e($token) ?>">
          <input type="hidden" name="action" value="reschedule">
          <label>Nova data<input type="date" name="date" required min="<?= e(date('Y-m-d')) ?>"></label>
          <label>Novo horário<select name="time" required disabled><option value="">Escolha a data</option></select></label>
          <small class="reschedule-status"></small>
          <button class="button button-full" type="submit" disabled>Remarcar <span aria-hidden="true">↗</span></button>
        </form>
        <?php endif; ?>
        <form method="post" action="manage.php" data-confirm="Tem certeza que deseja cancelar este agendamento?">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="t" value="<?= e($token) ?>">
          <input type="hidden" name="action" value="cancel">
          <button class="admin-link" type="submit">Cancelar agendamento</button>
        </form>
        <p class="form-legal">Alterações online até <?= (int)CLIENT_CHANGE_MIN_HOURS ?>h antes do horário.</p>
      <?php endif; ?>
    <?php endif; ?>
    <a class="button" href="index.php">Voltar para a página</a>
  </main>
</body>
</html>
