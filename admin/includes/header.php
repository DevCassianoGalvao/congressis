<?php
// Layout do painel — cabeçalho e menu lateral
// $page_title deve estar definido antes de incluir este arquivo
$page_title = $page_title ?? 'Painel';
$current_page = $current_page ?? '';
$bp = defined('BASE_PATH') ? BASE_PATH : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($page_title) ?> — Congressis Admin</title>
<link rel="icon" type="image/png" href="<?= $bp ?>/assets/favicon-emblem.png">
<link rel="stylesheet" href="<?= $bp ?>/admin/assets/admin.css">
<script>var _BP=<?= json_encode($bp) ?>;</script>
</head>
<body>

<div class="layout">
  <!-- Sidebar -->
  <nav class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <img src="<?= $bp ?>/assets/logos/logo-horizontal-branca.png" alt="Congressis" class="sidebar-logo">
    </div>
    <ul class="nav-menu">
      <li class="<?= $current_page === 'leads'   ? 'active' : '' ?>">
        <a href="<?= $bp ?>/admin/leads.php">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0"/><circle cx="17" cy="9" r="2.4"/><path d="M16 14.5a5 5 0 0 1 5 5"/></svg>
          Leads
        </a>
      </li>
      <li class="<?= $current_page === 'scripts' ? 'active' : '' ?>">
        <a href="<?= $bp ?>/admin/scripts.php">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
          Scripts
        </a>
      </li>
      <li class="<?= $current_page === 'speakers' ? 'active' : '' ?>">
        <a href="<?= $bp ?>/admin/speakers.php">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="9" y="2" width="6" height="11" rx="3"/><path d="M5 10a7 7 0 0 0 14 0"/><line x1="12" y1="19" x2="12" y2="22"/><line x1="8" y1="22" x2="16" y2="22"/></svg>
          Palestrantes
        </a>
      </li>
      <li class="<?= $current_page === 'sponsors' ? 'active' : '' ?>">
        <a href="<?= $bp ?>/admin/sponsors.php">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>
          Apoiadores
        </a>
      </li>
      <li class="<?= $current_page === 'utm'     ? 'active' : '' ?>">
        <a href="<?= $bp ?>/admin/utm.php">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
          Gerador UTM
        </a>
      </li>
    </ul>
    <div class="sidebar-footer">
      <a href="<?= $bp ?>/admin/logout.php" class="btn-logout">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Sair
      </a>
    </div>
  </nav>

  <!-- Overlay mobile -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- Conteúdo principal -->
  <div class="main">
    <header class="topbar">
      <button class="burger-btn" id="burgerBtn" aria-label="Menu">
        <span></span><span></span><span></span>
      </button>
      <h1 class="page-title"><?= e($page_title) ?></h1>
      <span class="topbar-user">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" style="width:16px;height:16px"><circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0 1 16 0"/></svg>
        <?= e(get_current_user_name()) ?>
      </span>
    </header>
    <div class="content">
