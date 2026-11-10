<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Agendamento confirmado | <?= e(BUSINESS_NAME) ?></title>
</head>
<body>
  <p>Agendamento confirmado! Voltando ao site… <a href="index.php?agendado=1" target="_top">Clique aqui se não redirecionar</a></p>
  <script src="assets/obrigado.js"></script>
</body>
</html>
