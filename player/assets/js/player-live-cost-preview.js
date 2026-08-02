/* =========================================================
   MCQG Player - Live Cost Preview JS
   Path: player/assets/js/player-live-cost-preview.js
   Source: MG19 Slide 10 - "the right side panel keeps changing
   statistics... this will increase the total cost of production
   and per-unit cost, done live." Calls
   ajax/player_ajax/get_live_cost_preview.php on every input change.
   ========================================================= */

document.addEventListener("DOMContentLoaded", function () {
  initLiveCostPreview();
});

function initLiveCostPreview() {
  const form = document.getElementById("mcqg-production-form");
  if (!form) return;

  const inputs = form.querySelectorAll("input[type=number], input[type=range]");
  inputs.forEach((input) => input.addEventListener("input", mcqgDebounce(refreshLiveCostPreview, 300)));

  refreshLiveCostPreview(); // initial load
}

async function refreshLiveCostPreview() {
  const form = document.getElementById("mcqg-production-form");
  if (!form) return;

  const formData = new FormData(form);
  const payload = Object.fromEntries(formData.entries());
  payload.investments = collectInvestmentValues();

  const response = await mcqgPost(AJAX_PLAYER_URL + "get_live_cost_preview.php", payload);
  if (!response.success) return;

  const d = response.data;
  updateStatWithFlash("live-capacity-cost", "\u20B9" + Number(d.capacity_cost || 0).toLocaleString());
  updateStatWithFlash("live-unit-cost", "\u20B9" + Number(d.unit_cost || 0).toFixed(2));
  updateStatWithFlash("live-opening-inventory", d.opening_inventory ?? "-");
  updateStatWithFlash("live-total-investment", "\u20B9" + Number(d.total_investment || 0).toLocaleString());
}

function collectInvestmentValues() {
  const values = {};
  document.querySelectorAll("[data-investment-id]").forEach((el) => {
    values[el.dataset.investmentId] = el.value;
  });
  return values;
}
