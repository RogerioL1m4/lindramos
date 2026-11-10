<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
$url = scheduling_url();
if ($url === null) {
    header('Location: index.php#agendar');
    exit;
}
$whatsappUrl = site_whatsapp_url('Olá! Preciso de ajuda para agendar meu horário no ' . BUSINESS_NAME . '.');
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#192f1d">
  <title>Agendar horário | <?= e(BUSINESS_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="sched-page">
  <header class="sched-top">
    <a class="brand" href="index.php" aria-label="<?= e(BUSINESS_NAME) ?> — início"><span class="brand-monogram">L</span><span class="brand-name"><?= e(BUSINESS_NAME) ?><small>DESIGN DE SOBRANCELHAS</small></span></a>
    <a class="sched-back" href="index.php"><span aria-hidden="true">←</span> Voltar ao site</a>
  </header>

  <main class="sched-main">
    <aside class="sched-info">
      <p class="eyebrow"><span></span> AGENDAMENTO ONLINE</p>
      <h1>Reserve seu<br><em>momento</em></h1>
      <p class="sched-lead">Escolha o melhor dia e horário na agenda. A confirmação chega por e-mail.</p>
      <ol class="sched-steps">
        <li><b>1</b><span>Selecione o dia no calendário</span></li>
        <li><b>2</b><span>Escolha um horário disponível</span></li>
        <li><b>3</b><span>Informe seus dados e confirme</span></li>
      </ol>
      <div class="sched-help">
        <p>Precisa de ajuda ou de outro horário?</p>
        <a href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener">Fale pelo WhatsApp <span aria-hidden="true">↗</span></a>
      </div>
      <p class="sched-place"><?= e(BUSINESS_CITY) ?> · Atendimento com hora marcada</p>
    </aside>

    <section class="sched-frame" aria-label="Agenda para escolha de horário">
      <div class="sched-loading" aria-hidden="true">Carregando agenda…</div>
      <iframe src="<?= e($url) ?>" title="Agendamento Google Calendar" loading="eager" frameborder="0"></iframe>
      <p class="sched-fallback">Não carregou? <a href="<?= e($url) ?>" target="_blank" rel="noopener">Abrir a agenda em uma nova aba</a></p>
    </section>
  </main>
</body>
</html>
