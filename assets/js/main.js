/**
 * MedCRM – JavaScript principal
 */

/* ── Sidebar mobile ──────────────────────────────────────── */
function toggleSidebar() {
  const sidebar  = document.getElementById('mainSidebar');
  const overlay  = document.getElementById('sidebarOverlay');
  sidebar.classList.toggle('open');
  overlay.classList.toggle('open');
}
function closeSidebar() {
  document.getElementById('mainSidebar')?.classList.remove('open');
  document.getElementById('sidebarOverlay')?.classList.remove('open');
}

/* ── User menu dropdown ──────────────────────────────────── */
function toggleUserMenu() {
  const dd = document.getElementById('userDropdown');
  if (dd) dd.style.display = dd.style.display === 'none' ? 'block' : 'none';
}
document.addEventListener('click', function(e) {
  const menu = document.getElementById('topbarUserMenu');
  if (menu && !menu.contains(e.target)) {
    const dd = document.getElementById('userDropdown');
    if (dd) dd.style.display = 'none';
  }
});

/* ── Auto-hide flash messages ────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  const flash = document.getElementById('flash-msg');
  if (flash) setTimeout(() => flash.style.display = 'none', 5000);
});

/* ── Tabs ────────────────────────────────────────────────── */
function initTabs() {
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = btn.dataset.tab;
      // Actualizar botones
      btn.closest('.tabs').querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      // Actualizar contenido
      document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
      document.getElementById(target)?.classList.add('active');
    });
  });
  // Activar primer tab
  document.querySelectorAll('.tabs').forEach(tabGroup => {
    const firstBtn = tabGroup.querySelector('.tab-btn');
    if (firstBtn && !tabGroup.querySelector('.tab-btn.active')) {
      firstBtn.click();
    }
  });
}

/* ── DataTable global ────────────────────────────────────── */
function initDataTable(selector, options = {}) {
  const defaults = {
    language: {
      url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
    },
    pageLength: 10,
    lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]],
    responsive: true,
    dom: '<"table-toolbar d-flex align-center"<"search-box"f><"ms-auto"l>>rt<"d-flex align-center mt-1"<"text-muted"i><"ms-auto"p>>',
  };
  return $(selector).DataTable({ ...defaults, ...options });
}

/* ── Confirm delete (SweetAlert2) ────────────────────────── */
function confirmDelete(form, message = '¿Estás seguro de eliminar este registro?') {
  Swal.fire({
    title: '¿Confirmar eliminación?',
    text: message,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#E02424',
    cancelButtonColor: '#6B7280',
    confirmButtonText: '<i class="fa fa-trash"></i> Sí, eliminar',
    cancelButtonText: 'Cancelar',
    reverseButtons: true,
    focusCancel: true,
  }).then(result => {
    if (result.isConfirmed) form.submit();
  });
}

/* ── Confirm action (genérico) ───────────────────────────── */
function confirmAction(form, title, text, icon = 'question') {
  Swal.fire({
    title, text, icon,
    showCancelButton: true,
    confirmButtonColor: '#1A56DB',
    cancelButtonColor: '#6B7280',
    confirmButtonText: 'Confirmar',
    cancelButtonText: 'Cancelar',
    reverseButtons: true,
  }).then(result => {
    if (result.isConfirmed) form.submit();
  });
}

/* ── Toast notification ──────────────────────────────────── */
function showToast(type, message) {
  const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3500,
    timerProgressBar: true,
  });
  Toast.fire({ icon: type, title: message });
}

/* ── Modal utils ─────────────────────────────────────────── */
function openModal(id) {
  const m = document.getElementById(id);
  if (m) m.classList.add('open');
}
function closeModal(id) {
  const m = document.getElementById(id);
  if (m) m.classList.remove('open');
}

/* ── Preview de imagen ───────────────────────────────────── */
function previewImage(input, previewId) {
  const reader = new FileReader();
  reader.onload = e => {
    const preview = document.getElementById(previewId);
    if (preview) { preview.src = e.target.result; preview.style.display = 'block'; }
  };
  if (input.files[0]) reader.readAsDataURL(input.files[0]);
}

/* ── Init al cargar el DOM ───────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  initTabs();

  // DataTable automático si existe la tabla con id "mainTable"
  if (document.getElementById('mainTable')) {
    initDataTable('#mainTable');
  }
});
