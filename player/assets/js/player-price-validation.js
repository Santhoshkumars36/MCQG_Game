/* =========================================================
   MCQG Player - Price Validation JS
   Path: player/assets/js/player-price-validation.js
   Source: MG19 Slide 11 - "sale price... validated against a
   Maximum and Minimum allowed range." Also shows an estimated
   Profit or Loss live, as required by the same slide.
   ========================================================= */

document.addEventListener("DOMContentLoaded", function () {
  initPriceValidation();
});

function initPriceValidation() {
  const priceInput = document.getElementById("selling_price");
  if (!priceInput) return;

  priceInput.addEventListener("input", mcqgDebounce(async function () {
    const price = parseFloat(priceInput.value);
    const min = parseFloat(priceInput.dataset.min);
    const max = parseFloat(priceInput.dataset.max);
    const hint = document.getElementById("price-range-hint");

    priceInput.classList.remove("is-valid-live", "is-invalid-live");

    if (isNaN(price)) return;

    if (price < min || price > max) {
      priceInput.classList.add("is-invalid-live");
      if (hint) hint.innerHTML = `<span class="text-danger">Price must be between \u20B9${min} and \u20B9${max}.</span>`;
    } else {
      priceInput.classList.add("is-valid-live");
      if (hint) hint.innerHTML = `<span class="text-success">Within allowed range (\u20B9${min} - \u20B9${max}).</span>`;
    }

    await refreshProfitPreview();
  }, 250));
}

async function refreshProfitPreview() {
  const form = document.getElementById("mcqg-demand-form");
  const preview = document.getElementById("mcqg-profit-preview");
  if (!form || !preview) return;

  const formData = new FormData(form);
  const payload = Object.fromEntries(formData.entries());

  const response = await mcqgPost(AJAX_PLAYER_URL + "validate_price_range.php", payload);
  if (!response.success) return;

  const d = response.data;
  const isPositive = Number(d.estimated_profit) >= 0;
  preview.className = "mcqg-profit-preview " + (isPositive ? "positive" : "negative");
  preview.innerHTML = `
    <div style="font-size:13px; text-transform:uppercase; letter-spacing:0.5px;">Estimated ${isPositive ? "Profit" : "Loss"}</div>
    <div style="font-size:26px; font-weight:800;">\u20B9${Math.abs(d.estimated_profit).toLocaleString()}</div>`;
}
