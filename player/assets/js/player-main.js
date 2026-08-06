/* =========================================================
   MCQG Player - Main JS
   Path: player/assets/js/player-main.js
   Shared utilities loaded on every player page.
   ========================================================= */

document.addEventListener("DOMContentLoaded", function () {
  initFlashToasts();
  initSidebarToggle();
});

function initSidebarToggle() {
  const toggleBtn = document.getElementById("mcqg-sidebar-toggle");
  const main = document.querySelector(".mcqg-main");
  if (!toggleBtn || !main) return;
  toggleBtn.addEventListener("click", function () {
    main.classList.toggle("collapsed");
  });
}

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
    toast.innerHTML = `<strong>${type === "error" ? "Notice" : "Success"}:</strong> ${message}`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4200);
  }
}

/** Generic JSON POST helper used by every ajax/player_ajax/* call */
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

function mcqgDebounce(fn, delay = 300) {
  let timer;
  return function (...args) {
    clearTimeout(timer);
    timer = setTimeout(() => fn.apply(this, args), delay);
  };
}

/** Animates a stat value when it changes, so live updates are visible (not just instant swaps) */
function updateStatWithFlash(elementId, newValue) {
  const el = document.getElementById(elementId);
  if (!el) return;
  el.textContent = newValue;
  el.classList.remove("flash-update");
  void el.offsetWidth; // force reflow so the animation restarts
  el.classList.add("flash-update");
}
