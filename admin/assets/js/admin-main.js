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
  const colors = { success: "#1f9d55", error: "#d9364f", info: "#1e2761" };
  const toast = document.createElement("div");
  toast.className = "mcqg-toast alert";
  toast.style.background = colors[type] || colors.info;
  toast.style.color = "#fff";
  toast.innerHTML = `<strong>${type === "error" ? "Error" : "Notice"}:</strong> ${message}`;
  document.body.appendChild(toast);
  setTimeout(() => toast.remove(), 4200);
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
