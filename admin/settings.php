<?php
/**
 * MedCRM – Configuración General del Sistema
 * Admin: nombre de la empresa y logo
 */
require_once dirname(__DIR__) . '/config/config.php';
requireRole('admin');

$pageTitle   = 'Configuración';
$breadcrumbs = [['label' => 'Configuración']];

// ── Procesar formulario ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ── Guardar configuración general ──────────────────────────────────────
    if ($action === 'save_general') {
        $companyName   = trim($_POST['company_name']   ?? '');
        $companySlogan = trim($_POST['company_slogan'] ?? '');

        if (!empty($companyName)) {
            setSetting('company_name',   $companyName);
            setSetting('company_slogan', $companySlogan);
        }

        // ── Subir logo ─────────────────────────────────────────────────────
        if (!empty($_FILES['company_logo']['name'])) {
            $file    = $_FILES['company_logo'];
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
            $mime    = mime_content_type($file['tmp_name']);

            if (!in_array($mime, $allowed, true)) {
                setFlash('error', 'Formato de imagen no permitido. Use JPG, PNG, GIF, WebP o SVG.');
                redirect('admin/settings.php');
            }
            if ($file['size'] > 2 * 1024 * 1024) {
                setFlash('error', 'El logo no puede superar los 2 MB.');
                redirect('admin/settings.php');
            }

            $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'png');
            $logoDir = APP_ROOT . '/assets/img/';
            if (!is_dir($logoDir)) mkdir($logoDir, 0755, true);

            $logoName = 'company_logo.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $logoDir . $logoName)) {
                setSetting('company_logo', $logoName);
            } else {
                setFlash('error', 'No se pudo guardar el logo. Verifique permisos en assets/img/.');
                redirect('admin/settings.php');
            }
        }

        setFlash('success', 'Configuración guardada correctamente.');
        redirect('admin/settings.php');
    }

    // ── Eliminar logo ──────────────────────────────────────────────────────
    if ($action === 'remove_logo') {
        $current = getSetting('company_logo');
        if ($current) {
            $path = APP_ROOT . '/assets/img/' . $current;
            if (file_exists($path)) @unlink($path);
            setSetting('company_logo', '');
        }
        setFlash('success', 'Logo eliminado.');
        redirect('admin/settings.php');
    }
}

// ── Valores actuales ───────────────────────────────────────────────────────────
$companyName   = getSetting('company_name',   APP_NAME);
$companySlogan = getSetting('company_slogan', 'Sistema Médico');
$companyLogo   = getSetting('company_logo',   '');

include APP_ROOT . '/includes/header.php';
?>

<style>
.settings-wrap {
  max-width: 680px;
}
.settings-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-lg, 14px);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}
.settings-card-header {
  display: flex;
  align-items: center;
  gap: .75rem;
  padding: 1.1rem 1.4rem;
  border-bottom: 1px solid var(--border-color);
  background: var(--bg-soft, #f8fafc);
}
.settings-card-header i { color: var(--primary); }
.settings-card-title { font-size: .95rem; font-weight: 700; color: var(--text-dark); }
.settings-card-body  { padding: 1.4rem; }

.logo-preview-box {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: var(--bg-soft, #f8fafc);
  border: 1.5px dashed var(--border-color);
  border-radius: 10px;
  margin-bottom: 1rem;
}
.logo-preview-box img {
  width: 60px; height: 60px;
  object-fit: contain;
  border-radius: 10px;
  background: #fff;
  padding: 4px;
  border: 1px solid var(--border-color);
}
.logo-placeholder {
  width: 60px; height: 60px;
  border-radius: 10px;
  background: var(--primary-light);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem; color: var(--primary);
}
.logo-upload-label {
  display: inline-flex;
  align-items: center;
  gap: .5rem;
  background: var(--primary);
  color: #fff;
  padding: .5rem 1.1rem;
  border-radius: 8px;
  font-size: .85rem;
  font-weight: 600;
  cursor: pointer;
  transition: background .15s;
}
.logo-upload-label:hover { background: var(--primary-dark); }
</style>

<!-- ── Page header ──────────────────────────────────────────────────────────── -->
<div class="page-header">
  <div>
    <h1 class="page-title">Configuración del Sistema</h1>
    <p class="page-subtitle">Personaliza el nombre y logo de tu clínica</p>
  </div>
</div>

<div class="settings-wrap">
  <div class="settings-card">
    <div class="settings-card-header">
      <i class="fa-solid fa-building-columns"></i>
      <span class="settings-card-title">Identidad de la Empresa</span>
    </div>
    <div class="settings-card-body">
      <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save_general">

        <!-- Vista previa del logo -->
        <div class="logo-preview-box">
          <?php if ($companyLogo && file_exists(APP_ROOT . '/assets/img/' . $companyLogo)): ?>
            <img src="<?= APP_URL ?>/assets/img/<?= e($companyLogo) ?>?v=<?= time() ?>" alt="Logo actual">
            <div>
              <div style="font-size:.82rem;font-weight:600;color:var(--text-dark);">Logo actual</div>
              <div style="font-size:.75rem;color:var(--text-muted);margin-bottom:.4rem;"><?= e($companyLogo) ?></div>
              <button type="button" onclick="removeLogo()"
                style="font-size:.78rem;color:var(--danger);background:none;border:none;cursor:pointer;padding:0;">
                <i class="fa-solid fa-trash-can"></i> Eliminar logo
              </button>
            </div>
          <?php else: ?>
            <div class="logo-placeholder"><i class="fa-solid fa-stethoscope"></i></div>
            <div>
              <div style="font-size:.82rem;font-weight:600;color:var(--text-dark);">Sin logo configurado</div>
              <div style="font-size:.75rem;color:var(--text-muted);">Se mostrará el ícono por defecto</div>
            </div>
          <?php endif; ?>
        </div>

        <!-- Botón subir -->
        <div class="form-group" style="margin-bottom:1.25rem;">
          <label class="logo-upload-label" for="logoInput">
            <i class="fa-solid fa-cloud-arrow-up"></i>
            <?= $companyLogo ? 'Cambiar logo' : 'Subir logo' ?>
          </label>
          <input type="file" id="logoInput" name="company_logo"
                 accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml"
                 style="display:none;" onchange="previewLogo(this)">
          <div id="logoPreviewNew" style="display:none;margin-top:.75rem;">
            <img id="logoPreviewImg" src="" alt="Vista previa"
                 style="max-width:100px;border-radius:10px;border:1px solid var(--border-color);">
          </div>
          <p style="font-size:.75rem;color:var(--text-muted);margin-top:.4rem;">
            JPG, PNG, SVG o WebP · Máx. 2 MB · Recomendado: 200 × 200 px
          </p>
        </div>

        <div class="form-grid form-grid-2" style="margin-bottom:1.25rem;">
          <!-- Nombre empresa -->
          <div class="form-group">
            <label class="form-label">Nombre de la empresa / clínica</label>
            <input type="text" name="company_name" class="form-input"
                   value="<?= e($companyName) ?>" required>
          </div>
          <!-- Eslogan -->
          <div class="form-group">
            <label class="form-label">Eslogan / subtítulo</label>
            <input type="text" name="company_slogan" class="form-input"
                   value="<?= e($companySlogan) ?>">
            <p class="form-hint">Texto debajo del nombre en el menú lateral.</p>
          </div>
        </div>

        <button type="submit" class="btn btn-primary">
          <i class="fa-solid fa-floppy-disk"></i> Guardar configuración
        </button>
      </form>

      <!-- Formulario oculto para eliminar logo -->
      <form id="removeLogoForm" method="POST" style="display:none;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="remove_logo">
      </form>
    </div>
  </div>
</div>

<script>
function previewLogo(input) {
  const wrap = document.getElementById('logoPreviewNew');
  const img  = document.getElementById('logoPreviewImg');
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => { img.src = e.target.result; wrap.style.display = 'block'; };
    reader.readAsDataURL(input.files[0]);
  }
}
function removeLogo() {
  if (confirm('¿Eliminar el logo actual?')) {
    document.getElementById('removeLogoForm').submit();
  }
}
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
