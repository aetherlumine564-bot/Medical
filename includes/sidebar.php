<?php
/**
 * MedCRM – Sidebar dinámico según rol
 */
$user       = currentUser();
$role       = $user['role_slug'] ?? 'patient';
$navItems   = getSidebarItems($role);
$currentFile = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));

// --- LÓGICA PARA MOVER CONFIGURACIÓN AL PRINCIPIO ---
$configItem = null;
foreach ($navItems as $key => $item) {
    // Buscamos por key o por label
    if (isset($item['key']) && ($item['key'] === 'settings' || strtolower($item['label']) === 'configuración')) {
        $configItem = $item;
        unset($navItems[$key]); // Lo quitamos de su posición actual
        break;
    }
}
// Si lo encontramos, lo ponemos al inicio del array
if ($configItem) {
    array_unshift($navItems, $configItem);
}
?>

<aside class="sidebar" id="mainSidebar" style="display: flex; flex-direction: column; height: 100vh;">

  <?php
  $_companyLogo   = getSetting('company_logo', '');
  $_companyName   = getSetting('company_name', APP_NAME);
  $_companySlogan = getSetting('company_slogan', 'Sistema Médico');
  $logoPath = APP_ROOT . '/assets/img/' . $_companyLogo;
  ?>
  
  <div class="sidebar-header-wrapper" style="padding: 25px 15px; text-align: center; flex-shrink: 0;">
    <div class="brand-logo-box" style="margin-bottom: 12px; display: inline-block;">
      <?php if ($_companyLogo && file_exists($logoPath)): ?>
        <img src="<?= APP_URL ?>/assets/img/<?= e($_companyLogo) ?>?v=<?= filemtime($logoPath) ?>"
             alt="<?= e($_companyName) ?>" 
             style="width: 85px; height: 85px; object-fit: contain; background: #ffffff; border-radius: 14px; padding: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
      <?php else: ?>
        <div class="logo-icon-fallback" style="font-size: 2.5rem; color: #fff;"><i class="fa-solid fa-stethoscope"></i></div>
      <?php endif; ?>
    </div>

    <div class="brand-text-box">
      <div style="font-weight: 800; color: #ffffff; font-size: 1.15rem; letter-spacing: 0.5px; text-transform: uppercase;">
        <?= e($_companyName) ?>
      </div>
      <div style="font-size: 0.75rem; color: #adb5bd; margin-top: 2px; font-weight: 500;">
        <?= e($_companySlogan) ?>
      </div>
    </div>
  </div>

  <hr style="border: 0; border-top: 1px solid rgba(255,255,255,0.08); margin: 0 1.5rem 1rem;">

  <nav class="sidebar-nav" style="flex-grow: 1; overflow-y: auto; padding: 0 10px;">
    <div class="nav-section-title" style="padding-left: 10px; font-size: 0.7rem; text-transform: uppercase; color: #6c757d; margin-bottom: 10px;">
        Menú principal
    </div>

    <?php foreach ($navItems as $item):
      $urlParts = explode('/', $item['url']);
      $urlFile  = end($urlParts);
      $urlDir   = count($urlParts) > 1 ? $urlParts[count($urlParts) - 2] : '';
      $isActive = ($currentFile === $urlFile) || ($currentDir === $urlDir && $item['key'] !== 'dashboard');
    ?>
    <a href="<?= APP_URL . '/' . $item['url'] ?>"
       class="nav-item <?= $isActive ? 'active' : '' ?>" 
       style="display: flex; align-items: center; gap: 12px; padding: 10px 15px; border-radius: 8px; margin-bottom: 4px; text-decoration: none; transition: all 0.2s;">
      <i class="fa-solid fa-<?= e($item['icon']) ?>" style="width: 20px; text-align: center;"></i>
      <span style="font-size: 0.9rem; font-weight: 500;"><?= e($item['label']) ?></span>
    </a>
    <?php endforeach; ?>

    <?php if ($role === 'admin'): ?>
    <div class="nav-section-title" style="margin-top:1.5rem; padding-left: 10px; font-size: 0.7rem; text-transform: uppercase; color: #6c757d; margin-bottom: 10px;">
        Sistema
    </div>
    <a href="<?= APP_URL ?>/admin/reports.php"
       class="nav-item <?= $currentFile === 'reports.php' ? 'active' : '' ?>"
       style="display: flex; align-items: center; gap: 12px; padding: 10px 15px; border-radius: 8px; text-decoration: none;">
      <i class="fa-solid fa-chart-line" style="width: 20px; text-align: center;"></i>
      <span>Reportes</span>
    </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer" style="padding: 15px; flex-shrink: 0; background: rgba(0,0,0,0.1);">
    <a href="<?= APP_URL ?>/logout.php" class="nav-item" style="color: #ff6b6b; margin-bottom: 1rem; display: flex; align-items: center; gap: 12px; text-decoration: none;">
      <i class="fa-solid fa-right-from-bracket"></i>
      <span>Cerrar sesión</span>
    </a>
    
    <div class="sidebar-user" style="display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.05); padding: 10px; border-radius: 10px;">
      <div class="user-avatar" style="width: 35px; height: 35px; background: var(--primary); color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem;">
        <?= avatarInitials($user['name'] ?? 'U') ?>
      </div>
      <div class="user-info">
        <div class="user-name" style="font-size: 0.85rem; font-weight: 600; color: #fff; line-height: 1;"><?= e($user['name'] ?? 'Usuario') ?></div>
        <div class="user-role" style="font-size: 0.7rem; color: #adb5bd; margin-top: 3px;"><?= e($user['role_name'] ?? 'Admin') ?></div>
      </div>
    </div>
  </div>

</aside>