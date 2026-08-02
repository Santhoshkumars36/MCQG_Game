/* =========================================================
   MCQG Player - Investment Slider JS
   Path: player/assets/js/player-investment-slider.js
   Source: MG19 Slides 10-11 - investment amounts entered via
   increment fields (min/max/increment set by admin). Rendered
   here as interactive sliders for a more engaging, highly
   interactive experience than plain number boxes.
   ========================================================= */

document.addEventListener("DOMContentLoaded", function () {
  initInvestmentSliders();
});

function initInvestmentSliders() {
  document.querySelectorAll(".mcqg-slider").forEach((slider) => {
    const valueLabel = document.querySelector(`[data-slider-value-for="${slider.id}"]`);
    const hiddenInput = document.querySelector(`[data-investment-id="${slider.dataset.investmentId}"]`);

    function syncValue() {
      const val = parseFloat(slider.value);
      if (valueLabel) valueLabel.textContent = "\u20B9" + val.toLocaleString();
      if (hiddenInput) hiddenInput.value = val;
    }

    slider.addEventListener("input", syncValue);
    syncValue(); // initialize on load
  });
}

/** Validates a chosen investment amount client-side against the
 *  admin-configured min/max/increment before allowing it to count
 *  toward the live cost preview (server re-validates in
 *  engine/investment/validate_investment_mapping.php regardless). */
function isValidIncrement(value, min, increment) {
  const steps = (value - min) / increment;
  return Math.abs(steps - Math.round(steps)) < 0.0001;
}
