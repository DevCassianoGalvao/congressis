# Checklist de deploy — Congressis Mini-CRM

## Pré-instalação
- [ ] Criar banco de dados MySQL no cPanel
- [ ] Criar usuário MySQL e conceder todas as permissões ao banco
- [ ] Fazer upload de todos os arquivos via FTP ou Gerenciador de Arquivos do cPanel
- [ ] Confirmar que a pasta `config/` existe e tem permissão de escrita (chmod 755)
- [ ] Confirmar que a pasta `logs/` existe e tem permissão de escrita (chmod 755)
- [ ] Confirmar que a pasta `uploads/` existe e tem permissão de escrita (chmod 755)
- [ ] Confirmar que `uploads/sponsors/.htaccess` foi enviado para o servidor

## Instalação
- [ ] Acessar `https://seudominio.com/install.php` no browser
- [ ] Verificar que todos os requisitos aparecem como ✓
- [ ] Preencher dados do banco e do usuário admin
- [ ] Clicar em "Instalar"
- [ ] Confirmar mensagem de sucesso

## Pós-instalação
- [ ] **DELETAR `install.php`** do servidor — crítico para segurança
- [ ] Confirmar que `config/config.php` retorna 403 ao acessar via browser
- [ ] Confirmar que `logs/admin.log` retorna 403 via browser
- [ ] Testar login em: `https://seudominio.com/admin/`
- [ ] Testar envio do formulário da LP e verificar se o lead aparece no painel
- [ ] Testar botão "Chamar no WhatsApp" — verificar se gera `https://wa.me/55{telefone}`
- [ ] Testar exportação de CSV
- [ ] Testar gerador de UTM
- [ ] Configurar scripts de rastreamento (Meta Pixel, GTM) na tela de Scripts do painel
- [ ] Fazer upload de um logo de teste no painel (Apoiadores) e verificar exibição na LP
- [ ] Confirmar que arquivos `.php` não executam dentro de `uploads/sponsors/` (acessar via browser deve retornar 403)
- [ ] Pasta `uploads/sponsors/` criada com permissão 755 ✓
- [ ] `.htaccess` de proteção dentro de `uploads/sponsors/` confirmado ✓

## Segurança pós-deploy
- [ ] `install.php` deletado ✓
- [ ] `error_reporting = 0` em produção ✓ (já configurado no `config.php`)
- [ ] `config/config.php` inacessível via browser ✓
- [ ] `logs/` inacessível via browser ✓
- [ ] HTTPS ativo no cPanel ✓
- [ ] Backup automático do banco configurado no cPanel ✓

## Integração da LP com o CRM
O formulário da landing page já envia os dados para `/api/submit-lead.php` via `fetch`.
Verifique que o domínio em `APP_DOMAIN` no `config.php` está correto para validação de Origin.

## Injetar scripts na LP (opcional)
Para que os scripts salvos no painel apareçam na LP, adicionar ao `index.html`
(convertendo para `.php`) ou usar PHP include nas posições:

```php
// No <head>:
<?php define('SCRIPT_LOC','head');       include __DIR__.'/admin/includes/get-scripts.php'; ?>

// Logo após <body>:
<?php define('SCRIPT_LOC','body_start'); include __DIR__.'/admin/includes/get-scripts.php'; ?>

// Antes de </body>:
<?php define('SCRIPT_LOC','footer');     include __DIR__.'/admin/includes/get-scripts.php'; ?>
```
