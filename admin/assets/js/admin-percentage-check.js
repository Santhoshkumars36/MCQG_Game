/* =========================================================
   MCQG Admin - Percentage Total Live Checker
   Path: admin/assets/js/admin-percentage-check.js
   MG19 Slide 5: Cost share % (Capacity Drivers) and Demand
   share % (Demand Drivers) must each total EXACTLY 100%,
   validated live on screen AND again on SAVE.
   ========================================================= */

document.addEventListener("DOMContentLoaded", function () {
  initPercentGroup("capacity-driver-percent", "capacity-percent-total-bar", "capacity-percent-readout");
  initPercentGroup("demand-driver-percent", "demand-percent-total-bar", "demand-percent-readout");
  initSaveGuard();
});

function initPercentGroup(inputClass, barId, readoutId) {
  const inputs = document.querySelectorAll("." + inputClass);
  const bar = document.getElementById(barId);
  const readout = document.getElementById(readoutId);
  if (!inputs.length || !bar || !readout) return;

  function recalc() {
    let total = 0;
    inputs.forEach((i) => (total += parseFloat(i.value) || 0));
    const fill = bar.querySelector(".mcqg-percent-total-fill");
    const clamped = Math.min(total, 100);
    fill.style.width = clamped + "%";

    fill.classList.remove("under", "exact", "over");
    if (Math.abs(total - 100) < 0.01) {
      fill.classList.add("exact");
      readout.innerHTML = `<span class="effect-positive">Total: ${total.toFixed(2)}% - Balanced &#10003;</span>`;
    } else if (total > 100) {
      fill.classList.add("over");
      readout.innerHTML = `<span class="effect-negative">Total: ${total.toFixed(2)}% - Exceeds 100%, reduce by ${(total - 100).toFixed(2)}%</span>`;
    } else {
      fill.classList.add("under");
      readout.innerHTML = `<span class="text-muted">Total: ${total.toFixed(2)}% - Needs ${(100 - total).toFixed(2)}% more</span>`;
    }
  }

  inputs.forEach((input) => input.addEventListener("input", mcqgDebounce(recalc, 150)));
  recalc();
}

/** Blocks the SAVE button click-side if either group isn't exactly 100% -
 *  the authoritative check still happens server-side in
 *  ajax/admin_ajax/validate_percentage_total.php on submit. */
function initSaveGuard() {
  const saveBtn = document.getElementById("mcqg-save-drivers-btn");
  if (!saveBtn) return;

  saveBtn.addEventListener("click", function (e) {
    const readouts = document.querySelectorAll("[id$='-percent-readout']");
    let allExact = true;
    readouts.forEach((r) => {
      if (!r.innerText.includes("Balanced")) allExact = false;
    });

    if (!allExact) {
      e.preventDefault();
      showToast("Cost share % and Demand share % must each total exactly 100% before saving.", "error");
    }
  });
}
