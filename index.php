<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
session_start();
$services = SERVICES;
$whatsappUrl = site_whatsapp_url('Olá! Vim pela página do ' . BUSINESS_NAME . ' e gostaria de conhecer os serviços.');
$schedulingUrl = scheduling_url();
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#192f1d">
  <meta name="description" content="Realce sua beleza com um design de sobrancelhas personalizado no <?= e(BUSINESS_NAME) ?>, em <?= e(BUSINESS_CITY) ?>. Veja os horários disponíveis e agende online.">
  <title><?= e(BUSINESS_NAME) ?> | Design de Sobrancelhas</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
  <?php if (!$schedulingUrl): ?><script src="assets/booking.js" defer></script>
  <?php else: ?><link href="https://calendar.google.com/calendar/scheduling-button-script.css" rel="stylesheet">
  <script src="https://calendar.google.com/calendar/scheduling-button-script.js" async></script>
  <script src="assets/gcal-button.js" defer></script><?php endif; ?>
</head>
<body>
<header class="site-header">
  <a class="brand" href="#inicio" aria-label="<?= e(BUSINESS_NAME) ?> — início"><span class="brand-monogram">L</span><span class="brand-name"><?= e(BUSINESS_NAME) ?><small>DESIGN DE SOBRANCELHAS</small></span></a>
  <nav aria-label="Navegação principal"><a href="#servicos">Serviços</a><a href="#sobre">Nosso cuidado</a><a href="admin.php" class="nav-admin">Painel ADM</a><a href="#agendar" class="nav-cta">Agendar horário <span aria-hidden="true">↗</span></a></nav>
</header>
<?php if (($_GET['agendado'] ?? '') === '1'): ?>
<div class="booked-banner" role="status">✓ Agendamento confirmado! Você receberá a confirmação por e-mail. Obrigada por escolher o <?= e(BUSINESS_NAME) ?>.</div>
<?php endif; ?>
<main>
  <section class="hero" id="inicio" aria-labelledby="hero-title">
    <img class="hero-photo" src="https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?auto=format&amp;fit=crop&amp;w=2000&amp;q=85" alt="Retrato de beleza com maquiagem natural" fetchpriority="high">
    <div class="hero-overlay" aria-hidden="true"></div>
    <div class="hero-copy">
      <p class="eyebrow"><span></span> <?= e(BUSINESS_CITY) ?> <i aria-hidden="true">·</i> BELEZA NATURAL</p>
      <h1 id="hero-title">Seu olhar merece revelar <em>a sua beleza.</em></h1>
      <p class="hero-text">Design de sobrancelhas feito com atenção aos seus traços, ao seu estilo e ao que faz você se sentir bem.</p>
      <a class="button" href="#agendar">Quero agendar meu horário <span aria-hidden="true">↗</span></a>
      <ul class="hero-points"><li><span aria-hidden="true">✓</span> Atendimento personalizado</li><li><span aria-hidden="true">✓</span> Horários consultados na agenda</li></ul>
    </div>
    <a class="hero-scroll" href="#servicos"><span aria-hidden="true">↓</span> DESCUBRA NOSSO CUIDADO</a>
  </section>

  <section class="services section" id="servicos" aria-labelledby="services-title">
    <div class="section-heading"><div><p class="eyebrow"><span></span> CUIDADO FEITO PARA VOCÊ</p><h2 id="services-title">Um cuidado especial<br><em>para o seu olhar</em></h2></div><p class="section-aside">Cada serviço é uma oportunidade de realçar sua beleza com delicadeza, técnica e atenção aos detalhes.</p></div>
    <div class="service-grid">
      <?php foreach ($services as $service): ?>
      <article class="service-card">
        <div class="service-card-top"><span class="service-number"><?= e($service['number']) ?></span><span class="service-arrow" aria-hidden="true">↗</span></div>
        <h3><?= e($service['name']) ?></h3>
        <p><?= e($service['description']) ?></p>
        <div class="service-meta"><span><?= e($service['duration']) ?></span><strong><?= e($service['price']) ?></strong></div>
        <a class="service-link" href="#agendar" data-service="<?= e($service['name']) ?>" aria-label="Agendar <?= e($service['name']) ?>">Agendar este serviço <span aria-hidden="true">→</span></a>
      </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="care-section" id="sobre" aria-labelledby="care-title">
    <div class="care-image"><img src="https://images.unsplash.com/photo-1512496015851-a90fb38ba796?auto=format&amp;fit=crop&amp;w=1200&amp;q=85" alt="Detalhes de beleza e autocuidado" loading="lazy"><span>UM TEMPO PARA VOCÊ</span></div>
    <div class="care-copy"><p class="eyebrow"><span></span> NOSSO JEITO DE CUIDAR</p><h2 id="care-title">O seu rosto é único.<br><em>Seu design também.</em></h2>
      <p>Um bom design começa com atenção. Escutamos o que você deseja e cuidamos de cada detalhe para valorizar seus traços com naturalidade.</p>
      <div class="care-values"><div><span>01</span><p><strong>Escuta atenta</strong> para entender o que você espera.</p></div><div><span>02</span><p><strong>Olhar cuidadoso</strong> para respeitar suas características.</p></div><div><span>03</span><p><strong>Resultado personalizado</strong>, pensado para você.</p></div></div>
      <a class="text-link" href="#agendar">Agende seu momento <span aria-hidden="true">→</span></a>
    </div>
  </section>

  <section class="steps-section section" aria-labelledby="steps-title">
    <div class="section-heading"><div><p class="eyebrow"><span></span> SIMPLES ASSIM</p><h2 id="steps-title">Seu momento de cuidado<br><em>começa aqui</em></h2></div><p class="section-aside">Escolha o serviço e a data para consultar os horários livres diretamente na agenda.</p></div>
    <ol class="steps-grid"><li><span>01</span><h3>Escolha seu serviço</h3><p>Conheça as opções e selecione o cuidado que combina com você.</p></li><li><span>02</span><h3>Veja os horários</h3><p>Consulte os horários disponíveis para a data desejada.</p></li><li><span>03</span><h3>Confirme seu agendamento</h3><p>Informe seus dados e receba a confirmação por e-mail.</p></li></ol>
  </section>

  <section class="booking section" id="agendar" aria-labelledby="booking-title">
    <div class="booking-intro"><p class="eyebrow"><span></span> SEU PRÓXIMO MOMENTO</p><h2 id="booking-title">Vamos cuidar<br><em>de você?</em></h2><p>Escolha um serviço e uma data para ver os horários livres na agenda. Seu horário será confirmado depois que você enviar seus dados.</p>
      <div class="booking-note"><span aria-hidden="true">✳</span><span>Os horários são consultados em tempo real no Google Calendar e verificados novamente antes da confirmação.</span></div>
      <a class="booking-whatsapp" href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener">Prefere tirar uma dúvida? Fale pelo WhatsApp <span aria-hidden="true">↗</span></a>
    </div>
    <?php if ($schedulingUrl): ?>
    <div class="booking-form booking-google">
      <div class="form-heading"><span><?= e(scheduling_text('scheduling_eyebrow')) ?></span><p><?= e(scheduling_text('scheduling_title')) ?></p></div>
      <p><?= e(scheduling_text('scheduling_text')) ?></p>
      <div id="gcal-button" class="gcal-button" data-url="<?= e($schedulingUrl) ?>" data-color="<?= e(scheduling_button_color()) ?>" data-label="<?= e(scheduling_button_label()) ?>"></div>
    </div>
    <?php else: ?>
    <form class="booking-form" action="booking.php" method="post" id="booking-form">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div class="hp" aria-hidden="true"><label>Não preencha<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <div class="form-heading"><span>AGENDAMENTO <i>·</i> ONLINE</span><p>Escolha seu horário</p></div>
      <label for="booking-service">Serviço<select name="service" id="booking-service" required>
        <option value="">Selecione um serviço</option>
        <?php foreach ($services as $service): ?><option value="<?= e($service['name']) ?>"><?= e($service['name']) ?> · <?= e($service['duration']) ?> · <?= e($service['price']) ?></option><?php endforeach; ?>
      </select></label>
      <label for="booking-date">Data<input type="date" name="date" id="booking-date" required min="<?= date('Y-m-d') ?>"></label>
      <label for="booking-time">Horário disponível<select name="time" id="booking-time" required disabled>
        <option value="">Selecione o serviço e a data primeiro</option>
      </select></label>
      <p class="availability-status" id="availability-status" role="status" aria-live="polite">Selecione o serviço e a data para consultar os horários.</p>
      <div class="booking-divider"></div>
      <label for="booking-name">Seu nome<input name="name" id="booking-name" required maxlength="100" autocomplete="name" placeholder="Como podemos chamar você?"></label>
      <div class="form-row"><label for="booking-phone">WhatsApp<input name="phone" id="booking-phone" required maxlength="25" autocomplete="tel" placeholder="(00) 00000-0000"></label>
      <label for="booking-email">E-mail<input type="email" name="email" id="booking-email" required maxlength="150" autocomplete="email" placeholder="voce@email.com"></label></div>
      <label for="booking-notes">Observações <small>(opcional)</small><textarea name="notes" id="booking-notes" rows="3" maxlength="500" placeholder="Escreva aqui, se desejar..."></textarea></label>
      <button class="button button-full" id="booking-submit" type="submit" disabled>Confirmar agendamento <span aria-hidden="true">↗</span></button>
      <p class="form-legal">Ao enviar, você concorda em ser contatada para tratar deste agendamento. O convite de confirmação será enviado para o e-mail informado.</p>
    </form>
    <?php endif; ?>
  </section>
  <?php $map = site_map(); $addr = site_value('site_address'); $phone = site_value('site_phone'); ?>
  <?php if ($addr !== '' || $phone !== '' || $map['embed']): ?>
  <section class="location section" id="localizacao" aria-labelledby="location-title">
    <div class="location-info">
      <p class="eyebrow"><span></span> ONDE ESTAMOS</p>
      <h2 id="location-title">Venha nos <em>visitar</em></h2>
      <?php if ($addr !== ''): ?><p class="location-line"><b>Endereço</b><?= e($addr) ?><br><?= e(BUSINESS_CITY) ?></p><?php endif; ?>
      <?php if ($phone !== ''): ?><p class="location-line"><b>Telefone</b><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= e($phone) ?></a></p><?php endif; ?>
      <p class="location-line"><b>WhatsApp</b><a href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener">Chamar agora</a></p>
      <?php if ($map['link']): ?><a class="booking-whatsapp" href="<?= e($map['link']) ?>" target="_blank" rel="noopener">Abrir no Google Maps <span aria-hidden="true">↗</span></a><?php endif; ?>
    </div>
    <?php if ($map['embed']): ?>
    <div class="location-map"><iframe src="<?= e($map['embed']) ?>" title="Localização de <?= e(BUSINESS_NAME) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div>
    <?php endif; ?>
  </section>
  <?php endif; ?>
</main>
<footer class="site-footer"><div class="footer-main"><a class="brand" href="#inicio"><span class="brand-monogram">L</span><span class="brand-name"><?= e(BUSINESS_NAME) ?><small>DESIGN DE SOBRANCELHAS</small></span></a><p><?= e(BUSINESS_CITY) ?><?php if (site_value('site_address') !== ''): ?><br><?= e(site_value('site_address')) ?><?php endif; ?><?php if (site_value('site_phone') !== ''): ?><br><?= e(site_value('site_phone')) ?><?php endif; ?><br>Atendimento com hora marcada</p><a class="footer-contact" href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener">Fale pelo WhatsApp <span aria-hidden="true">↗</span></a></div><div class="footer-bottom"><small>© <?= date('Y') ?> <?= e(BUSINESS_NAME) ?> · Todos os direitos reservados.</small><a class="admin-access" href="admin.php">Painel do administrador</a><a href="#inicio">Voltar ao início ↑</a></div></footer>
<a class="floating-whatsapp" href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener" aria-label="Fale com a clínica pelo WhatsApp"><span aria-hidden="true">✆</span> WhatsApp</a>
</body>
</html>
