<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
session_start();
admin_require_login();

function oauth_fail(string $message): never {
    $_SESSION['flash'] = ['error', $message];
    header('Location: admin_google.php');
    exit;
}

if (!is_file(__DIR__ . '/vendor/autoload.php')) oauth_fail('Dependências ausentes. Execute composer install.');
if (google_credentials() === null) oauth_fail('Envie as credenciais do Google antes de conectar.');

$client = google_base_client();
$client->setRedirectUri(google_redirect_uri());
$client->setPrompt('consent'); // garante o refresh_token
$oauth = $client->getOAuth2Service();
$verifier = $oauth->generateCodeVerifier(); // PKCE
$oauth->setCodeVerifier($verifier);
$_SESSION['oauth_state'] = bin2hex(random_bytes(24));
$_SESSION['oauth_verifier'] = $verifier;
$_SESSION['oauth_started'] = time();
$client->setState($_SESSION['oauth_state']);
audit('oauth_started', 'admin');
header('Location: ' . $client->createAuthUrl());
exit;
