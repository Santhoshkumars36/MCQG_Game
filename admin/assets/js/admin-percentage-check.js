/* =========================================================
   MCQG Admin - Percentage Total Live Checker
   Path: admin/assets/js/admin-percentage-check.js
   MG19 Slide 8: Capacity Drivers + Demand Drivers must
   total EXACTLY 100% combined.
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
    if (fill) {
      fill.style.width = clamped + "%";
      fill.classList.remove("under", "exact", "over");
    }

    if (inputClass === "capacity-driver-percent") {
      const remaining = Math.max(0, 100 - total);
      if (total > 100) {
        if (fill) fill.classList.add("over");
        readout.innerHTML = `<span class="effect-negative">Capacity Total: ${total.toFixed(2)}% - Exceeds 100%, reduce by ${(total - 100).toFixed(2)}%</span>`;
      } else if (total > 0) {
        if (fill) fill.classList.add("exact");
        readout.innerHTML = `<span class="effect-positive">Capacity Total: ${total.toFixed(2)}% (Leaves ${remaining.toFixed(2)}% for Demand Drivers) &#10003;</span>`;
      } else {
        if (fill) fill.classList.add("under");
        readout.innerHTML = `<span class="text-muted">Capacity Total: 0.00%</span>`;
      }
    }
  }

  inputs.forEach((input) => input.addEventListener("input", typeof mcqgDebounce === 'function' ? mcqgDebounce(recalc, 150) : recalc));
  recalc();
}

/** Blocks the SAVE button click-side if values violate validation rules */
function initSaveGuard() {
  const saveBtn = document.getElementById("mcqg-save-drivers-btn");
  if (!saveBtn) return;

  saveBtn.addEventListener("click", function (e) {
    const capInputs = document.querySelectorAll(".capacity-driver-percent");
    if (capInputs.length > 0) {
      let capTotal = 0;
      capInputs.forEach((i) => (capTotal += parseFloat(i.value) || 0));
      if (capTotal <= 0) {
        e.preventDefault();
        if (typeof showToast === 'function') showToast("Please enter cost share % for capacity drivers.", "error");
        return;
      }
      if (capTotal > 100) {
        e.preventDefault();
        if (typeof showToast === 'function') showToast("Capacity Drivers total cannot exceed 100%.", "error");
        return;
      }
    }
  });
}

