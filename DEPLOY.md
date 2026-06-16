# Checklist de deploy — Congressis Mini-CRM

## Pré-instalação no cPanel

- [ ] Criar banco de dados MySQL no cPanel (Databases → MySQL Databases)
- [ ] Criar usuário MySQL e conceder **todas** as permissões ao banco
- [ ] Fazer upload de todos os arquivos via FTP ou Gerenciador de Arquivos do cPanel
  - Upload para a raiz pública do domínio (`public_html/` ou subpasta)
  - **NÃO enviar:** `config/config.php` (será gerado pelo install.php)
  - **NÃO enviar:** arquivos `.claude/`, logs com dados reais
- [ ] Confirmar que `config/` tem permissão de escrita (chmod 755)
- [ ] Confirmar que `logs/` tem permissão de escrita (chmod 755)
- [ ] Confirmar que `uploads/` tem permissão de escrita (chmod 755)
- [ ] Confirmar que `uploads/sponsors/.htaccess` foi enviado
- [ ] Confirmar que `uploads/speakers/.htaccess` foi enviado

## Instalação via install.php

- [ ] Acessar `https://seudominio.com/install.php` no browser
- [ ] Verificar que todos os requisitos aparecem como ✓
  - PHP >= 8.0, PDO, PDO_MySQL, JSON, GD, fileinfo
  - Pastas config/, logs/, uploads/ graváveis
- [ ] Preencher dados do banco de dados
- [ ] Preencher URL do site (ex: `https://congressis.com.br`)
- [ ] Criar usuário e senha do painel admin (mín. 8 caracteres)
- [ ] Clicar em "Instalar"
- [ ] Confirmar mensagem de sucesso — `install.php` é deletado automaticamente

## Pós-instalação

- [ ] Verificar que `install.php` foi deletado (se ainda existir, deletar manualmente)
- [ ] Confirmar que `config/config.php` retorna 403 ao acessar via browser
- [ ] Confirmar que `logs/` retorna 403 ao acessar via browser
- [ ] Testar login: `https://seudominio.com/admin/`
- [ ] Testar envio do formulário da LP — lead deve aparecer no painel
- [ ] Testar botão "Chamar no WhatsApp" — deve gerar `https://wa.me/55{telefone}`
- [ ] Testar exportação de CSV
- [ ] Testar gerador de UTM
- [ ] Fazer upload de um logo no painel (Apoiadores) e verificar exibição na LP
- [ ] Fazer upload de foto de palestrante no painel (Palestrantes) e verificar na LP
- [ ] Confirmar que arquivos `.php` não executam dentro de `uploads/sponsors/` e `uploads/speakers/`
- [ ] Configurar scripts de rastreamento (Meta Pixel, GTM) em: Painel → Scripts

## Segurança pós-deploy

- [ ] `install.php` deletado ✓
- [ ] `error_reporting = 0` em produção ✓ (configurado no `config.php` gerado)
- [ ] `config/config.php` inacessível via browser ✓
- [ ] `logs/` inacessível via browser ✓
- [ ] HTTPS ativo — Ativar SSL grátis no cPanel (Let's Encrypt)
- [ ] Backup automático do banco configurado no cPanel

## Estrutura de tabelas instaladas

| Tabela        | Descrição                                      |
|---------------|------------------------------------------------|
| `leads`       | Captações do formulário da landing page        |
| `admin_users` | Usuários do painel administrativo              |
| `sponsors`    | Logos de apoiadores, patrocinadores etc.       |
| `speakers`    | Palestrantes (foto, nome, subtítulo, citação)  |
| `site_scripts`| Scripts injetados na LP (head/body/footer)     |
| `rate_limit`  | Limite de requisições por IP                   |

## Injeção de scripts na LP

Scripts salvos no painel já são injetados automaticamente em `index.php` nas três posições:

- `<head>` — pixels, metatags
- Logo após `<body>` — noscript tags
- Antes de `</body>` — chat widgets, outros scripts

Nenhuma configuração adicional é necessária.

## Uploads de arquivos

- Logos de apoiadores: `uploads/sponsors/`
- Fotos de palestrantes: `uploads/speakers/`
- Ambas as pastas têm `.htaccess` bloqueando execução de PHP
- Formatos aceitos: JPG, PNG, WebP (máx. 2 MB)
- PNGs são preservados com transparência (canal alpha)
