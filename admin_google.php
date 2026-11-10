<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
send_security_headers(true);
session_start();
admin_require_login();

$hasVendor = is_file(__DIR__ . '/vendor/autoload.php');

function flash(string $type, string $message): never {
    $_SESSION['flash'] = [$type, $message];
    header('Location: admin_google.php');
    exit;
}

function parse_credentials(string $raw): ?array {
    $data = json_decode($raw, true);
    if (!is_array($data)) return null;
    $section = $data['web'] ?? null;
    if (!is_array($section) || empty($section['client_id']) || empty($section['client_secret'])) return null;
    return $data;
}

function writable_calendars(): array {
    $items = (new Google\Service\Calendar(google_client()))->calendarList->listCalendarList(['minAccessRole' => 'writer'])->getItems();
    return $items ?? [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) flash('error', 'Sessão expirada. Tente novamente.');
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save_credentials') {
        $raw = '';
        if (!empty($_FILES['credentials_file']['tmp_name']) && is_uploaded_file($_FILES['credentials_file']['tmp_name'])) {
            if (($_FILES['credentials_file']['size'] ?? 0) > 65536) flash('error', 'Arquivo muito grande.');
            $raw = (string) file_get_contents($_FILES['credentials_file']['tmp_name']);
        } else {
            $raw = trim((string)($_POST['credentials_json'] ?? ''));
        }
        $data = parse_credentials($raw);
        if ($data === null) flash('error', 'JSON inválido. Use o arquivo OAuth do tipo "Aplicativo da Web" baixado do Google Cloud.');
        secret_write('credentials', $data);
        secret_delete('token'); // credenciais novas invalidam o token anterior
        $settings = app_settings();
        unset($settings['reauth_required'], $settings['connected_at']);
        save_app_settings($settings);
        audit('google_credentials_saved', 'admin');
        flash('ok', 'Credenciais salvas. Agora conecte a conta Google.');
    }

    if ($action === 'disconnect') {
        $revoked = false;
        try {
            if ($hasVendor && google_token() !== null) {
                $token = google_token();
                $revoked = google_base_client()->revokeToken($token['refresh_token'] ?? $token['access_token'] ?? null);
            }
        } catch (Throwable $ex) {
            app_log('warning', 'Falha ao revogar token no Google: ' . $ex->getMessage());
        }
        secret_delete('token');
        audit('google_disconnected', 'admin', null, ['revoked' => $revoked]);
        flash('ok', 'Conta Google desconectada' . ($revoked ? ' e acesso revogado no Google.' : '. Se quiser, remova o acesso em myaccount.google.com/permissions.'));
    }

    if ($action === 'remove_credentials') {
        secret_delete('credentials');
        secret_delete('token');
        audit('google_credentials_removed', 'admin');
        flash('ok', 'Credenciais removidas.');
    }

    if ($action === 'save_calendar') {
        $chosen = trim((string)($_POST['calendar_id'] ?? ''));
        try {
            $allowed = array_map(fn($c) => $c->getId(), writable_calendars());
        } catch (Throwable $ex) {
            app_log('error', 'Falha ao validar agenda: ' . $ex->getMessage());
            flash('error', 'Não foi possível validar a agenda. Reconecte a conta Google.');
        }
        if (!in_array($chosen, $allowed, true)) flash('error', 'Agenda inválida ou sem permissão de escrita.');
        $settings = app_settings();
        $settings['calendar_id'] = $chosen;
        save_app_settings($settings);
        audit('calendar_selected', 'admin');
        flash('ok', 'Agenda salva.');
    }

    // Teste real: cria um evento na agenda escolhida, lê de volta e apaga. Sem convidados (ninguém é notificado).
    if ($action === 'test_event') {
        $result = ['at' => date('c'), 'ok' => false, 'step' => 'inserir', 'detail' => ''];
        try {
            $gateway = calendar_gateway();
            $start = (new DateTimeImmutable('tomorrow 03:00', new DateTimeZone(APP_TIMEZONE)))->getTimestamp();
            $eventId = event_id_for(bin2hex(random_bytes(16)));
            $gateway->insertEvent($eventId, ['summary' => 'TESTE de integração (pode apagar)', 'description' => 'Evento criado e removido automaticamente pelo painel.', 'startTs' => $start, 'endTs' => $start + 1800]);
            $result['step'] = 'ler';
            $read = $gateway->getEvent($eventId);
            if (!$read || $read['startTs'] !== $start) throw new RuntimeException('O evento criado não foi encontrado na leitura.');
            $result['step'] = 'apagar';
            $gateway->deleteEvent($eventId);
            $result['ok'] = true;
            $result['step'] = 'concluído';
        } catch (Throwable $ex) {
            $result['detail'] = get_class($ex) . ': ' . $ex->getMessage();
            app_log('error', 'Teste de evento falhou na etapa ' . $result['step'] . ': ' . $ex->getMessage());
        }
        $settings = app_settings();
        $settings['last_event_test'] = $result;
        save_app_settings($settings);
        audit('google_event_test', 'admin', null, ['ok' => $result['ok'], 'step' => $result['step']]);
        flash($result['ok'] ? 'ok' : 'error', $result['ok'] ? 'Teste concluído: evento criado, lido e removido na sua agenda do Google.' : 'Teste falhou na etapa "' . $result['step'] . '". Veja o log.');
    }
    flash('error', 'Ação desconhecida.');
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$hasCredentials = google_credentials() !== null;
$connected = $hasCredentials && google_token() !== null;
$reauth = (string)(app_settings()['reauth_required'] ?? '');
$lastTest = app_settings()['last_event_test'] ?? null;
$calendars = [];
$calendarError = '';
if ($connected && !$reauth && $hasVendor) {
    try {
        $calendars = writable_calendars();
    } catch (Throwable $ex) {
        app_log('error', 'Falha ao listar agendas: ' . $ex->getMessage());
        $calendarError = 'Não foi possível listar as agendas. Reconecte a conta Google.';
    }
}
$currentCalendar = calendar_id();
$storageInsideWebroot = str_starts_with(str_replace('\\', '/', (string)realpath(STORAGE_DIR)), str_replace('\\', '/', (string)realpath(__DIR__)));
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Configurar Google Agenda | <?= e(BUSINESS_NAME) ?></title>
  <link rel="stylesheet" href="assets/style.css">
  <script src="assets/admin.js" defer></script>
</head>
<body class="admin-page">
  <header class="admin-header">
    <div><p class="eyebrow"><span></span> CONFIGURAÇÃO</p><h1>Google Agenda</h1></div>
    <div class="admin-actions"><a class="admin-link" href="admin.php">← Painel</a></div>
  </header>
  <main class="admin-main">
    <?php if ($flash): ?><p class="admin-flash admin-flash-<?= e($flash[0]) ?>" role="status"><?= e($flash[1]) ?></p><?php endif; ?>
    <?php if (!$hasVendor): ?><p class="admin-flash admin-flash-error">Dependências ausentes. Execute <code>composer install</code> no projeto.</p><?php endif; ?>
    <?php if ($reauth): ?><p class="admin-flash admin-flash-error">A autorização do Google expirou ou foi revogada (<?= e($reauth) ?>). Clique em Reconectar.</p><?php endif; ?>
    <?php if (!is_https() && !in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'], true)): ?><p class="admin-flash admin-flash-error">Este site não está em HTTPS. Em produção o login do Google e o painel exigem HTTPS.</p><?php endif; ?>
    <?php if (APP_URL === '' && !in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'], true)): ?><p class="admin-flash admin-flash-error">Defina APP_URL em config.local.php para fixar o endereço do site.</p><?php endif; ?>
    <?php if ($storageInsideWebroot): ?><p class="admin-flash admin-flash-error">A pasta de armazenamento está dentro do site. Para máxima segurança, mova-a para fora de public_html (STORAGE_DIR em config.local.php).</p><?php endif; ?>

    <section class="admin-card">
      <h2>1. Credenciais do Google</h2>
      <p class="admin-muted">No Google Cloud Console, ative a Google Calendar API e crie um OAuth Client ID do tipo <strong>Aplicativo da Web</strong>. Cadastre este URI de redirecionamento autorizado:</p>
      <p><code class="admin-code" id="redirect-uri"><?= e(google_redirect_uri()) ?></code></p>
      <p class="<?= $hasCredentials ? 'admin-ok' : 'admin-error' ?>"><?= $hasCredentials ? 'Credenciais configuradas.' : 'Credenciais ainda não enviadas.' ?></p>
      <form method="post" action="admin_google.php" enctype="multipart/form-data" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_credentials">
        <label class="admin-label">Arquivo JSON<input type="file" name="credentials_file" accept="application/json,.json"></label>
        <label class="admin-label">Ou cole o conteúdo do JSON<textarea name="credentials_json" rows="5" spellcheck="false" autocomplete="off" placeholder='{"web":{"client_id":"...","client_secret":"..."}}'></textarea></label>
        <button class="button" type="submit"><?= $hasCredentials ? 'Substituir credenciais' : 'Salvar credenciais' ?> <span aria-hidden="true">↗</span></button>
      </form>
      <?php if ($hasCredentials): ?>
      <form method="post" action="admin_google.php" class="admin-inline" data-confirm="Remover as credenciais e desconectar a conta?">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="remove_credentials">
        <button class="admin-link" type="submit">Remover credenciais</button>
      </form>
      <?php endif; ?>
    </section>

    <section class="admin-card">
      <h2>2. Conectar conta</h2>
      <?php if (!$hasCredentials): ?>
        <p class="admin-muted">Envie as credenciais primeiro.</p>
      <?php elseif ($connected && !$reauth): ?>
        <p class="admin-ok">Conta Google conectada. A renovação do acesso é automática.</p>
        <div class="admin-actions">
          <a class="admin-link" href="oauth_start.php">Reconectar</a>
          <form method="post" action="admin_google.php" class="admin-inline" data-confirm="Desconectar e revogar o acesso no Google? Os agendamentos online ficarão indisponíveis.">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="disconnect">
            <button class="admin-link" type="submit">Desconectar</button>
          </form>
        </div>
      <?php else: ?>
        <p class="admin-error"><?= $reauth ? 'Autorização expirada.' : 'Conta não conectada.' ?></p>
        <a class="button" href="oauth_start.php"><?= $reauth ? 'Reconectar com Google' : 'Conectar com Google' ?> <span aria-hidden="true">↗</span></a>
      <?php endif; ?>
      <p class="admin-muted">Permissões solicitadas (mínimas): criar/editar/apagar eventos, consultar horários ocupados e listar suas agendas.</p>
    </section>

    <section class="admin-card">
      <h2>3. Agenda utilizada</h2>
      <?php if (!$connected || $reauth): ?>
        <p class="admin-muted">Conecte a conta para escolher a agenda.</p>
      <?php elseif ($calendarError): ?>
        <p class="admin-error"><?= e($calendarError) ?></p>
      <?php else: ?>
        <p class="admin-muted">Os horários livres e os novos agendamentos usarão esta agenda.</p>
        <form method="post" action="admin_google.php" class="admin-form">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="save_calendar">
          <label class="admin-label">Agenda<select name="calendar_id" required>
            <?php foreach ($calendars as $cal): $id = $cal->getId(); ?>
              <option value="<?= e($id) ?>" <?= ($id === $currentCalendar || ($currentCalendar === 'primary' && $cal->getPrimary())) ? 'selected' : '' ?>><?= e($cal->getSummary()) ?><?= $cal->getPrimary() ? ' (principal)' : '' ?></option>
            <?php endforeach; ?>
          </select></label>
          <button class="button" type="submit">Salvar agenda <span aria-hidden="true">↗</span></button>
        </form>
      <?php endif; ?>
    </section>

    <section class="admin-card">
      <h2>4. Teste real de criação de evento</h2>
      <p class="admin-muted">Cria um evento de teste na agenda escolhida (amanhã, 03:00, sem convidados), lê de volta e apaga. Só considere a integração concluída quando este teste passar.</p>
      <?php if (is_array($lastTest)): ?>
        <p class="<?= !empty($lastTest['ok']) ? 'admin-ok' : 'admin-error' ?>">Último teste: <?= !empty($lastTest['ok']) ? 'APROVADO' : 'FALHOU (' . e((string)$lastTest['step']) . ')' ?> em <?= e((string)$lastTest['at']) ?></p>
      <?php else: ?>
        <p class="admin-error">Teste ainda não executado.</p>
      <?php endif; ?>
      <?php if ($connected && !$reauth): ?>
      <form method="post" action="admin_google.php" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="test_event">
        <button class="button" type="submit">Executar teste <span aria-hidden="true">↗</span></button>
      </form>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
