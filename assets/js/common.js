/* =========================================================
   MCQG - Shared Common JS
   Path: assets/js/common.js
   Loaded by BOTH admin/ and player/, before the module-specific
   admin-main.js / player-main.js. Only truly universal helpers
   belong here.
   ========================================================= */

/** Formats a number as currency using the game's currency symbol (defaults to Rupee) */
function mcqgFormatCurrency(value, symbol = "\u20B9") {
  const num = Number(value) || 0;
  return symbol + num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/** Formats a plain number with thousands separators */
function mcqgFormatNumber(value) {
  return Number(value || 0).toLocaleString();
}

/** Formats a percentage value to a fixed number of decimals */
function mcqgFormatPercent(value, decimals = 2) {
  return Number(value || 0).toFixed(decimals) + "%";
}

/** Shows a small inline spinner inside a button while an async action runs,
 *  then restores the original label - keeps every "Save"/"Submit" button
 *  consistent across both admin and player screens. */
async function mcqgWithButtonSpinner(button, asyncFn) {
  if (!button) return asyncFn();
  const originalHtml = button.innerHTML;
  button.disabled = true;
  button.innerHTML = '<span class="mcqg-spinner"></span> Working...';
  try {
    return await asyncFn();
  } finally {
    button.disabled = false;
    button.innerHTML = originalHtml;
  }
}

/** Basic client-side confirm wrapper - kept centralized so the wording
 *  and behavior stay consistent everywhere a destructive action happens. */
function mcqgConfirm(message) {
  return window.confirm(message);
}

/** Smoothly scrolls to the first invalid field on a form, used after a
 *  failed client-side or server-side validation response. */
function mcqgScrollToFirstError() {
  const firstError = document.querySelector(".is-invalid-live, .is-invalid");
  if (firstError) {
    firstError.scrollIntoView({ behavior: "smooth", block: "center" });
    firstError.focus({ preventScroll: true });
  }
}
