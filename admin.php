<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
send_security_headers(true);
session_start();

$weekdays = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
$statusLabels = ['pending' => 'Pendente', 'confirmed' => 'Confirmado', 'cancelled' => 'Cancelado', 'failed' => 'Falhou', 'rejected' => 'Recusado'];
$tz = new DateTimeZone(APP_TIMEZONE);

function admin_flash(string $type, string $message): never {
    $_SESSION['flash'] = [$type, $message];
    header('Location: admin.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if (!csrf_valid()) {
        $error = 'Sessão expirada. Tente novamente.';
    } elseif ($action === 'login') {
        $error = admin_attempt_login((string)($_POST['password'] ?? '')) ?? '';
        if ($error === '') {
            header('Location: admin.php');
            exit;
        }
    } elseif (!admin_is_logged_in()) {
        $error = 'Sessão expirada. Entre novamente.';
    } else {
        $service = new BookingService(calendar_gateway());
        try {
            switch ($action) {
                case 'logout':
                    audit('admin_logout', 'admin');
                    admin_logout();
                    header('Location: admin.php');
                    exit;
                case 'cancel':
                    $service->cancel((int)($_POST['booking_id'] ?? 0), 'admin');
                    admin_flash('ok', 'Agendamento cancelado e removido da agenda do Google.');
                case 'reschedule':
                    $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i', (string)($_POST['date'] ?? '') . ' ' . (string)($_POST['time'] ?? ''), $tz);
                    if (!$start) admin_flash('error', 'Escolha uma data e um horário válidos.');
                    $service->reschedule((int)($_POST['booking_id'] ?? 0), $start->getTimestamp(), 'admin');
                    admin_flash('ok', 'Agendamento remarcado e atualizado no Google Agenda.');
                case 'sync':
                    $service->reconcile();
                    $service->syncUpcoming();
                    admin_flash('ok', 'Reservas sincronizadas com o Google Agenda.');
                case 'change_password':
                    $current = (string)($_POST['current_password'] ?? '');
                    $new = (string)($_POST['new_password'] ?? '');
                    if (!password_verify($current, admin_password_hash())) admin_flash('error', 'Senha atual incorreta.');
                    if ($new !== (string)($_POST['confirm_password'] ?? '')) admin_flash('error', 'A confirmação não confere.');
                    if ($problem = admin_password_problem($new)) admin_flash('error', $problem);
                    admin_set_password($new);
                    audit('admin_password_changed', 'admin');
                    admin_flash('ok', 'Senha alterada com sucesso.');
                case 'save_site':
                    $settings = app_settings();
                    foreach (site_fields() as $key => $f) {
                        $v = mb_substr(trim((string)($_POST[$key] ?? '')), 0, $f['max']);
                        if ($key === 'site_whatsapp') $v = preg_replace('/\D+/', '', $v);
                        $settings[$key] = $v;
                    }
                    $mapsRaw = trim((string)($_POST['site_maps'] ?? ''));
                    if ($mapsRaw !== '' && parse_maps_input($mapsRaw)['link'] === null) admin_flash('error', 'Link do Google Maps inválido. Cole o código “Incorporar um mapa” ou o link do Maps.');
                    $settings['site_maps'] = mb_substr($mapsRaw, 0, 2000);
                    save_app_settings($settings);
                    audit('site_settings_saved', 'admin');
                    admin_flash('ok', 'Dados do site salvos. Já aparecem na página.');
                case 'save_scheduling':
                    $raw = trim((string)($_POST['scheduling_input'] ?? ''));
                    $settings = app_settings();
                    if ($raw === '') {
                        unset($settings['scheduling_url'], $settings['scheduling_code']);
                        save_app_settings($settings);
                        audit('scheduling_url_removed', 'admin');
                        admin_flash('ok', 'Link removido. O site voltou a usar o formulário próprio.');
                    }
                    $url = parse_scheduling_input($raw);
                    if ($url === null) admin_flash('error', 'Link inválido. Use o link ou o código iframe da página de agendamento do Google Calendar.');
                    $settings['scheduling_url'] = $url;
                    $settings['scheduling_code'] = mb_substr($raw, 0, 4000);
                    $color = preg_match('/\bcolor\s*:\s*["\'](#[0-9a-fA-F]{6})["\']/i', $raw, $cm) ? $cm[1] : '#192f1d';
                    $label = preg_match('/\blabel\s*:\s*"([^"]*)"/i', $raw, $lm) || preg_match("/\\blabel\\s*:\\s*'([^']*)'/i", $raw, $lm) ? html_entity_decode($lm[1], ENT_QUOTES) : '';
                    $settings['scheduling_color'] = $color;
                    $settings['scheduling_label'] = mb_substr(trim($label), 0, 40);
                    foreach (array_keys(scheduling_text_defaults()) as $key) unset($settings[$key]);
                    save_app_settings($settings);
                    audit('scheduling_url_saved', 'admin');
                    admin_flash('ok', 'Agendamento do Google salvo. O botão do site já abre essa página.');
                default:
                    admin_flash('error', 'Ação desconhecida.');
            }
        } catch (BookingException $ex) {
            admin_flash('error', $ex->getMessage());
        }
    }
}

$authenticated = admin_is_logged_in();
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$connected = false;
$reauth = '';
$bookings = [];
$events = [];
$calendarError = '';
if ($authenticated) {
    $connected = google_is_connected();
    $reauth = (string)(app_settings()['reauth_required'] ?? '');
}
function fmt_ts(int $ts, string $format, DateTimeZone $tz): string {
    return (new DateTimeImmutable('@' . $ts))->setTimezone($tz)->format($format);
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Painel | <?= e(BUSINESS_NAME) ?></title>
  <link rel="stylesheet" href="assets/style.css">
  <script src="assets/admin.js" defer></script>
</head>
<body class="admin-page">
<?php if (!$authenticated): ?>
  <main class="result-page">
    <form class="result-card admin-login" method="post" action="admin.php" autocomplete="off">
      <p class="eyebrow"><span></span> ÁREA RESTRITA</p>
      <h1>Painel administrativo</h1>
      <?php if ($error): ?><p class="admin-error" role="alert"><?= e($error) ?></p><?php endif; ?>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="login">
      <label class="admin-label">Senha<input type="password" name="password" required autocomplete="current-password" autofocus></label>
      <button class="button button-full" type="submit">Entrar <span aria-hidden="true">↗</span></button>
      <p class="form-legal"><a href="index.php">← Voltar ao site</a></p>
    </form>
  </main>
<?php else: ?>
  <header class="admin-header">
    <div><p class="eyebrow"><span></span> PAINEL</p><h1><?= e(BUSINESS_NAME) ?></h1></div>
    <div class="admin-actions">
      <a class="admin-link" href="index.php">Ver site</a>
      <form method="post" action="admin.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="logout">
        <button class="admin-link" type="submit">Sair</button>
      </form>
    </div>
  </header>
  <main class="admin-main">
    <?php if ($flash): ?><p class="admin-flash admin-flash-<?= e($flash[0]) ?>" role="status"><?= e($flash[1]) ?></p><?php endif; ?>
    <?php if (admin_uses_default_password()): ?><p class="admin-flash admin-flash-error" role="alert">Você ainda usa a senha inicial. Troque-a agora em “Segurança” (abaixo).</p><?php endif; ?>

    <section class="admin-card">
      <h2>Dados do site</h2>
      <p class="admin-muted">Nome, contatos e localização exibidos no site. Campo vazio usa o valor padrão (ou não aparece, no caso de endereço e telefone).</p>
      <form method="post" action="admin.php" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_site">
        <?php foreach (site_fields() as $key => $f): $val = trim((string)(app_settings()[$key] ?? '')); ?>
        <label class="admin-label"><?= e($f['label']) ?><input type="text" name="<?= e($key) ?>" maxlength="<?= (int)$f['max'] ?>" value="<?= e($val !== '' ? $val : $f['default']) ?>"></label>
        <?php endforeach; ?>
        <label class="admin-label">Localização no Google Maps<textarea name="site_maps" rows="4" spellcheck="false" autocomplete="off" placeholder="Cole o código “Incorporar um mapa” (iframe) ou o link do Google Maps. Se vazio, o mapa usa o endereço."><?= e((string)(app_settings()['site_maps'] ?? '')) ?></textarea></label>
        <button class="button" type="submit">Salvar <span aria-hidden="true">↗</span></button>
      </form>
    </section>

    <section class="admin-card">
      <h2>Página de agendamento do Google</h2>
      <p class="admin-muted">Cole o código completo do botão do Google Agenda (Agendamentos → Incorporar → Botão de agendamento). Link e iframe também funcionam. O site usa o link, a cor e o texto do botão definidos no código. Deixe vazio e salve para voltar ao formulário próprio.</p>
      <?php $schedUrl = scheduling_url(); ?>
      <p class="<?= $schedUrl ? 'admin-ok' : 'admin-error' ?>"><?= $schedUrl ? 'Configurada. ' : 'Não configurada. ' ?><?php if ($schedUrl): ?><a class="admin-link" href="agenda.php" target="_blank" rel="noopener">Abrir página</a><?php endif; ?></p>
      <form method="post" action="admin.php" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_scheduling">
        <label class="admin-label">Código do Google Agenda<textarea name="scheduling_input" rows="14" spellcheck="false" autocomplete="off" placeholder="Cole aqui o código completo do Google Agenda (Agendamentos → Incorporar → Botão)"><?= e((string)(app_settings()['scheduling_code'] ?? $schedUrl ?? '')) ?></textarea></label>
        <button class="button" type="submit">Salvar <span aria-hidden="true">↗</span></button>
      </form>
    </section>

    <section class="admin-card">
      <h2>Segurança</h2>
      <p class="admin-muted">Troque a senha do painel (mínimo de 12 caracteres, com letras e números). A sessão expira após <?= (int)(ADMIN_IDLE_SESSION_SECONDS / 60) ?> min sem uso.</p>
      <form method="post" action="admin.php" class="admin-form" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="change_password">
        <label class="admin-label">Senha atual<input type="password" name="current_password" required autocomplete="current-password"></label>
        <label class="admin-label">Nova senha<input type="password" name="new_password" required minlength="12" autocomplete="new-password"></label>
        <label class="admin-label">Confirmar nova senha<input type="password" name="confirm_password" required minlength="12" autocomplete="new-password"></label>
        <button class="button" type="submit">Alterar senha <span aria-hidden="true">↗</span></button>
      </form>
    </section>
  </main>
<?php endif; ?>
</body>
</html>
