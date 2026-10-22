<?php
/**
 * MedCRM – Header HTML reutilizable
 * Variables esperadas:
 *   $pageTitle   : string  – Título de la página
 *   $breadcrumbs : array   – [['label'=>'...','url'=>'...'], ...]  (último sin url)
 */
if (!defined('APP_NAME')) require_once dirname(__DIR__) . '/config/config.php';
requireLogin();
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? 'Panel') ?> – <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <!-- DataTables -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
  <!-- SweetAlert2 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <!-- MedCRM CSS -->
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=5.1">
  <?php if (!empty($extraCss)) echo $extraCss; ?>
</head>
<body>

<!-- Overlay para sidebar en móvil -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="app-layout">

  <!-- ════════ SIDEBAR ════════ -->
  <?php include __DIR__ . '/sidebar.php'; ?>

  <!-- ════════ MAIN CONTENT ════════ -->
  <div class="main-content">

    <!-- Topbar -->
    <header class="topbar">
      <button class="topbar-toggle" onclick="toggleSidebar()" title="Menú">
        <i class="fa-solid fa-bars"></i>
      </button>

      <!-- Breadcrumbs -->
      <nav class="topbar-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= APP_URL ?>"><i class="fa-solid fa-house" style="font-size:.8rem;"></i></a>
        <?php if (!empty($breadcrumbs)): ?>
          <?php foreach ($breadcrumbs as $crumb): ?>
            <i class="fa-solid fa-chevron-right"></i>
            <?php if (!empty($crumb['url'])): ?>
              <a href="<?= APP_URL . '/' . $crumb['url'] ?>"><?= e($crumb['label']) ?></a>
            <?php else: ?>
              <span class="current"><?= e($crumb['label']) ?></span>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php else: ?>
          <i class="fa-solid fa-chevron-right"></i>
          <span class="current"><?= e($pageTitle ?? 'Panel') ?></span>
        <?php endif; ?>
      </nav>

      <div class="topbar-actions">
        <!-- Notificaciones (decorativo) -->
        <button class="topbar-btn" title="Notificaciones">
          <i class="fa-regular fa-bell"></i>
        </button>

        <!-- Avatar + menú desplegable -->
        <div style="position:relative;" id="topbarUserMenu">
          <button class="topbar-avatar" onclick="toggleUserMenu()" title="Mi cuenta">
            <?= avatarInitials($user['name'] ?? 'U') ?>
          </button>
          <div id="userDropdown" style="
            display:none; position:absolute; right:0; top:calc(100% + .5rem);
            background:var(--card-bg); border:1px solid var(--border-color);
            border-radius:10px; box-shadow:var(--shadow-lg);
            min-width:200px; z-index:200; overflow:hidden;
          ">
            <div style="padding:.85rem 1rem; border-bottom:1px solid var(--border-color);">
              <div style="font-size:.85rem;font-weight:700;color:var(--text-dark);"><?= e($user['name'] ?? '') ?></div>
              <div style="font-size:.75rem;color:var(--text-muted);"><?= e($user['email'] ?? '') ?></div>
            </div>
            <a href="<?= APP_URL ?>/profile.php" style="display:flex;align-items:center;gap:.65rem;padding:.65rem 1rem;font-size:.85rem;color:var(--text-body);">
              <i class="fa-regular fa-user" style="width:16px;text-align:center;"></i> Mi perfil
            </a>
            <hr style="margin:0;border-color:var(--border-color);">
            <a href="<?= APP_URL ?>/logout.php" style="display:flex;align-items:center;gap:.65rem;padding:.65rem 1rem;font-size:.85rem;color:var(--danger);">
              <i class="fa-solid fa-right-from-bracket" style="width:16px;text-align:center;"></i> Cerrar sesión
            </a>
          </div>
        </div>
      </div>
    </header><!-- /topbar -->

    <!-- Flash messages -->
    <div style="padding:0 1.75rem;">
      <?php renderFlash(); ?>
    </div>

    <!-- Page body inicia aquí -->
    <div class="page-body fade-in">
