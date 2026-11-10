# Ateliê Lindinez — Landing page + agendamento com Google Agenda

Landing page PHP (visual verde/natural) com agendamento online integrado ao Google Calendar, página da cliente para cancelar/remarcar e painel administrativo.

## Arquitetura

| Arquivo | Função |
|---|---|
| `index.php`, `assets/` | Landing page e formulário de agendamento |
| `availability.php` | Horários livres (banco local + Google FreeBusy) |
| `booking.php` | Cria a reserva |
| `manage.php` | A cliente cancela/remarca com o link privado recebido |
| `admin.php`, `admin_google.php` | Painel: agendamentos, auditoria, senha, conexão Google, teste real |
| `oauth_start.php`, `oauth_callback.php` | OAuth 2.0 com PKCE e `state` |
| `src/` | Núcleo: `booking_service`, `google`, `db` (SQLite), `storage` (segredos/logs), `security`, `admin_auth` |
| `tests/run.php` | Testes automatizados (Google simulado) |
| `tools/` | `check_public_access.php`, `unlock_admin.php` |
| `router.php` | Roteador do servidor de desenvolvimento (`php -S`) |

## Rodar localmente

PHP 8.1+ com `pdo_sqlite`, `curl`, `mbstring`, `openssl`; `composer install` já feito (pasta `vendor/`).

```
php -S 127.0.0.1:8000 router.php
```

Painel: `http://127.0.0.1:8000/admin.php`. A senha inicial é provisória: **troque-a no primeiro acesso** (mínimo 12 caracteres, letras e números). Em produção, defina `ADMIN_PASSWORD_HASH` ou troque pelo painel.

## Como o agendamento evita conflitos

1. **Banco local (SQLite, modo WAL)** é a fonte da verdade. A reserva ocorre em `BEGIN IMMEDIATE`: checagem de sobreposição + inserção são atômicas, então duas clientes nunca reservam o mesmo horário. Um índice único parcial (`ux_bookings_active_start`) é a defesa final.
2. **Saga com o Google**: reserva `pending` → consulta FreeBusy → cria o evento com **ID determinístico** (derivado do identificador da reserva, base32hex) → `confirmed`.
   - Google recusa/indisponível antes do insert → reserva `failed` (horário liberado).
   - Resposta perdida/timeout → consulta o evento pelo ID; se existe, confirma (insert é idempotente; HTTP 409 = já criado).
   - Estado incerto → fica `pending` (horário bloqueado) e a **reconciliação** resolve depois (botão "Sincronizar" e rotinas automáticas).
   - Evento apagado direto no Google → reserva é cancelada e o horário liberado.
3. **Remarcação**: reserva o novo horário (`resched_*`), atualiza o Google e só então confirma no banco; falhas mantêm o horário original.
4. **Cancelamento**: remove o evento no Google primeiro; se falhar, a reserva é mantida.
5. Limites: por IP, por cliente (máx. 3 ativos), honeypot, CSRF.

## OAuth e escopos

Escopos mínimos (sem acesso total à agenda):
- `calendar.events` — criar/editar/apagar eventos
- `calendar.freebusy` — consultar horários ocupados
- `calendar.calendarlist.readonly` — listar agendas para escolha

Segurança do token: fluxo com PKCE + `state` (expira em 15 min), `access_type=offline`, renovação com trava de arquivo (`flock`) para evitar refresh concorrente, gravação atômica, tratamento de `invalid_grant` (painel pede reconexão) e revogação ao desconectar.

**Publicação do OAuth (importante):** em modo *Testing* o refresh token expira em ~7 dias. No Google Cloud → *Tela de permissão OAuth* → publique em **"Em produção"**. Para uso próprio não é necessária verificação (aparece o aviso de "app não verificado": Avançado → continuar). Cadastre o URI de redirecionamento exatamente como mostrado em `admin_google.php`.

## Segurança

- Painel: limite de tentativas (IP e global), sessão com timeout ocioso (30 min) e absoluto (8 h), cookie HttpOnly/SameSite/Secure, vínculo ao User-Agent, CSRF, troca de senha, allowlist opcional de IPs.
- Cabeçalhos: CSP sem scripts inline, HSTS (HTTPS), X-Frame-Options, nosniff, Referrer-Policy, `no-store` nas páginas admin.
- Segredos (`credentials`, `token`, `settings`, chave da aplicação) ficam em arquivos `.php` com guarda: mesmo que o servidor os entregue, o conteúdo não é exibido. O banco tem nome imprevisível. Além disso há `.htaccess` (Apache 2.2/2.4) e `web.config` (IIS) de negação em `storage/`, `src/`, `vendor/`, `tools/`, `tests/`, e bloqueio de dotfiles/`config*.php`/`*.json`/`*.md`/`*.sqlite`/`*.log` na raiz.
- **Recomendado:** mover `STORAGE_DIR` para fora de `public_html` (ver abaixo).
- Verificação: `php tools/check_public_access.php https://seudominio.com.br` (falha se algo sensível responder 200).

## Logs e auditoria

- Logs de erro/aviso em `storage/logs/*.log.php` (mensais; tokens, senhas e códigos são mascarados).
- Auditoria no banco (`audit_log`), visível no painel: reserva, confirmação, falha, cancelamento, remarcação, login, falhas de login, conexão/desconexão Google, teste de evento.

## Testes

```
php tests/run.php
```
37 verificações com Google simulado e storage temporário: criação, conflito/sobreposição, ocupação externa, falhas do Google (antes/depois do insert, timeout, estado incerto + reconciliação), remarcação (ok, conflito, falha), cancelamento (ok, falha), evento apagado no Google, auditoria, **concorrência real com 8 processos no mesmo horário (1 vencedora)**, índice único e mascaramento de logs.

> Limite: o Google é simulado. **A integração real só está concluída quando o teste da seção 4 de `admin_google.php` ("Executar teste") passar** com suas credenciais: ele cria, lê e apaga um evento sem convidados.

## Publicação no cPanel (HTTPS)

1. Hospedagem com PHP 8.1+ e extensões `pdo_sqlite`, `curl`, `mbstring`, `openssl` (MultiPHP / Selecionar extensões). Ative **SSL/AutoSSL** para o domínio.
2. Envie todos os arquivos (incluindo `vendor/`, ou rode `composer install --no-dev` por SSH) para `public_html`.
3. Copie `config.local.php.example` para `config.local.php` e ajuste:
   - `APP_URL = 'https://www.seudominio.com.br'`
   - `STORAGE_DIR` para uma pasta **fora** de `public_html` (ex.: `/home/usuario/lindinez_storage`, permissão 700).
4. O `.htaccess` da raiz já força HTTPS e bloqueia arquivos sensíveis (precisa de `AllowOverride` habilitado, padrão no cPanel).
5. Google Cloud Console: ative a Calendar API, crie o OAuth Client "Aplicativo da Web", URI de redirecionamento `https://www.seudominio.com.br/oauth_callback.php`, publique o app (veja acima).
6. Acesse `/admin.php`, **troque a senha**, envie as credenciais, conecte, escolha a agenda e execute o **teste real**.
7. Rode `php tools/check_public_access.php https://www.seudominio.com.br`.
8. Cron (opcional, a cada 10 min): `php /home/usuario/public_html/tools/reconcile.php` — ou use o botão "Sincronizar" do painel.
9. Backup: inclua `STORAGE_DIR` (banco + chave da aplicação) nos backups. Se perder `appkey.php`, o banco é recriado vazio.
10. Ajuste `WHATSAPP_NUMBER` e `BUSINESS_EMAIL` em `config.php`.

Se o painel ficar bloqueado por tentativas: `php tools/unlock_admin.php`.
