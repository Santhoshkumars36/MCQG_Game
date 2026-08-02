/* =========================================================
   MCQG Admin - Investment Mapping JS
   Path: admin/assets/js/admin-investment-mapping.js
   MG19 Slide 6: each investment maps to 1+ Capacity/Demand
   Drivers, with Min%/Max% (positive=green, negative=red,
   1 decimal place) plus an Increment%. Inject message is
   auto-generated, not typed.
   ========================================================= */

document.addEventListener("DOMContentLoaded", function () {
  initAddMappingRow();
  initEffectColoring();
  initInjectMessagePreview();
});

function initAddMappingRow() {
  const addBtn = document.getElementById("mcqg-add-mapping-btn");
  const container = document.getElementById("mcqg-mapping-rows");
  if (!addBtn || !container) return;

  let rowIndex = container.querySelectorAll(".mcqg-mapping-row").length;

  addBtn.addEventListener("click", function () {
    rowIndex++;
    const row = document.createElement("div");
    row.className = "row g-2 mb-2 mcqg-mapping-row align-items-center";
    row.innerHTML = `
      <div class="col-3">
        <select class="form-select" name="driver_type[]">
          <option value="Capacity">Capacity Driver</option>
          <option value="Demand">Demand Driver</option>
        </select>
      </div>
      <div class="col-3">
        <select class="form-select" name="driver_id[]"></select>
      </div>
      <div class="col-2">
        <input type="number" step="0.1" class="form-control effect-percent-input" name="min_percent[]" placeholder="Min %">
      </div>
      <div class="col-2">
        <input type="number" step="0.1" class="form-control effect-percent-input" name="max_percent[]" placeholder="Max %">
      </div>
      <div class="col-1">
        <input type="number" step="0.1" class="form-control" name="increment_percent[]" placeholder="Incr %">
      </div>
      <div class="col-1">
        <button type="button" class="btn btn-sm btn-outline-danger mcqg-remove-row">&times;</button>
      </div>`;
    container.appendChild(row);
    initEffectColoring();
  });

  container.addEventListener("click", function (e) {
    if (e.target.classList.contains("mcqg-remove-row")) {
      e.target.closest(".mcqg-mapping-row").remove();
    }
  });
}

/** Colors Min%/Max% fields green if positive, red if negative - live */
function initEffectColoring() {
  document.querySelectorAll(".effect-percent-input").forEach((input) => {
    input.removeEventListener("input", colorEffectInput);
    input.addEventListener("input", colorEffectInput);
    colorEffectInput.call(input);
  });
}

function colorEffectInput() {
  const val = parseFloat(this.value);
  this.classList.remove("effect-positive", "effect-negative");
  if (!isNaN(val)) {
    this.classList.add(val >= 0 ? "effect-positive" : "effect-negative");
  }
}

/** Live preview of the auto-generated "Inject message" (MG19 Slide 6) */
function initInjectMessagePreview() {
  const nameInput = document.getElementById("investment_name");
  const preview = document.getElementById("inject-message-preview");
  if (!nameInput || !preview) return;

  nameInput.addEventListener("input", mcqgDebounce(function () {
    const name = nameInput.value.trim() || "This investment";
    preview.innerText = `${name} will apply its configured effect to every linked driver once purchased.`;
  }, 200));
}
