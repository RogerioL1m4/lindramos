<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
session_start();

function oauth_finish(string $type, string $message): never {
    $_SESSION['flash'] = [$type, $message];
    header('Location: admin_google.php');
    exit;
}

admin_require_login();
$state = $_SESSION['oauth_state'] ?? null;
$verifier = $_SESSION['oauth_verifier'] ?? null;
$started = (int)($_SESSION['oauth_started'] ?? 0);
unset($_SESSION['oauth_state'], $_SESSION['oauth_verifier'], $_SESSION['oauth_started']);

if (!is_string($state) || !isset($_GET['state']) || !hash_equals($state, (string)$_GET['state']) || time() - $started > 900) {
    audit('oauth_failed', 'admin', null, ['reason' => 'state']);
    oauth_finish('error', 'Estado OAuth inválido ou expirado. Reinicie a conexão.');
}
if (isset($_GET['error'])) {
    audit('oauth_failed', 'admin', null, ['reason' => (string)$_GET['error']]);
    oauth_finish('error', 'Autorização não concluída pelo Google.');
}
if (google_credentials() === null) oauth_finish('error', 'Credenciais do Google não configuradas.');

try {
    $client = google_base_client();
    $client->setRedirectUri(google_redirect_uri());
    $token = $client->fetchAccessTokenWithAuthCode((string)($_GET['code'] ?? ''), is_string($verifier) ? $verifier : null);
} catch (Throwable $ex) {
    app_log('error', 'Falha na troca do código OAuth: ' . $ex->getMessage());
    oauth_finish('error', 'Não foi possível concluir a autorização com o Google.');
}
if (isset($token['error'])) {
    app_log('error', 'OAuth recusado pelo Google', ['error' => (string)$token['error']]);
    oauth_finish('error', 'O Google recusou a autorização.');
}
if (empty($token['refresh_token'])) oauth_finish('error', 'O Google não retornou refresh token. Remova o acesso do app em myaccount.google.com/permissions e conecte novamente.');

$granted = explode(' ', (string)($token['scope'] ?? ''));
$missing = array_diff(GOOGLE_SCOPES, $granted);
if ($missing) {
    audit('oauth_failed', 'admin', null, ['reason' => 'missing_scopes']);
    oauth_finish('error', 'Permissões incompletas. Marque todas as permissões solicitadas ao autorizar.');
}

secret_write('token', $token);
$settings = app_settings();
unset($settings['reauth_required'], $settings['reauth_at']);
$settings['connected_at'] = date('c');
save_app_settings($settings);
audit('oauth_connected', 'admin');
oauth_finish('ok', 'Google Agenda conectado. Escolha abaixo qual agenda será usada e rode o teste de criação de evento.');
