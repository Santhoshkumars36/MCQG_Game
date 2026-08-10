/* =========================================================
   MCQG Admin - Main JS
   Path: admin/assets/js/admin-main.js
   Shared utilities loaded on every admin page.
   ========================================================= */

document.addEventListener("DOMContentLoaded", function () {
  initSidebarToggle();
  initFlashToasts();
});

function initSidebarToggle() {
  const toggleBtn = document.getElementById("mcqg-sidebar-toggle");
  const main = document.querySelector(".mcqg-main");
  const sidebar = document.querySelector(".mcqg-sidebar");
  if (!toggleBtn || !main || !sidebar) return;

  toggleBtn.addEventListener("click", function () {
    main.classList.toggle("collapsed");
    sidebar.classList.toggle("collapsed");
  });
}

/** Renders any PHP flash messages (Session::getFlash) as animated toasts */
function initFlashToasts() {
  document.querySelectorAll("[data-mcqg-flash]").forEach(function (el) {
    showToast(el.dataset.mcqgFlash, el.dataset.mcqgFlashType || "success");
    el.remove();
  });
}

function showToast(message, type = "success") {
  if (typeof Swal !== "undefined") {
    const iconMap = { success: 'success', error: 'error', warning: 'warning', info: 'info' };
    const Toast = Swal.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 3500,
      timerProgressBar: true,
      didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer);
        toast.addEventListener('mouseleave', Swal.resumeTimer);
      }
    });
    Toast.fire({
      icon: iconMap[type] || 'info',
      title: message
    });
  } else {
    const colors = { success: "#1f9d55", error: "#d9364f", info: "#1e2761" };
    const toast = document.createElement("div");
    toast.className = "mcqg-toast alert";
    toast.style.background = colors[type] || colors.info;
    toast.style.color = "#fff";
    toast.innerHTML = `<strong>${type === "error" ? "Error" : "Notice"}:</strong> ${message}`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4200);
  }
}

/** SweetAlert confirmation before deleting a game */
function confirmDeleteGame(gameId, gameName) {
  let adminUrl = typeof ADMIN_URL !== 'undefined' ? ADMIN_URL : '';
  if (!adminUrl && window.location.pathname.includes('/admin/')) {
    adminUrl = window.location.pathname.substring(0, window.location.pathname.indexOf('/admin/') + 7);
  }
  const targetUrl = adminUrl + 'delete_game.php?game_id=' + gameId;

  if (typeof Swal !== "undefined") {
    Swal.fire({
      title: 'Delete Game?',
      text: `Are you sure you want to delete "${gameName}"? This action cannot be undone.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d9364f',
      cancelButtonColor: '#6c757d',
      confirmButtonText: '<i class="fas fa-trash-alt me-1"></i> Yes, Delete',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        window.location.href = targetUrl;
      }
    });
  } else {
    if (confirm(`Are you sure you want to delete "${gameName}"?`)) {
      window.location.href = targetUrl;
    }
  }
}

/** Generic JSON POST helper used by every admin_ajax/* call */
async function mcqgPost(url, payload) {
  try {
    const res = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    return await res.json();
  } catch (err) {
    showToast("Network error - please try again.", "error");
    return { success: false, message: "Network error", data: {} };
  }
}

/** Debounce helper - used to avoid flooding AJAX calls on every keystroke */
function mcqgDebounce(fn, delay = 300) {
  let timer;
  return function (...args) {
    clearTimeout(timer);
    timer = setTimeout(() => fn.apply(this, args), delay);
  };
}
