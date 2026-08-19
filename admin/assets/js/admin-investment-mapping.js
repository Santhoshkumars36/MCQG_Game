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
    const firstDriverSelect = container.querySelector('select[name="driver_id[]"]');
    const driverOptions = firstDriverSelect ? firstDriverSelect.innerHTML : '';
    const row = document.createElement("div");
    row.className = "row g-2 mb-2 mcqg-mapping-row align-items-center";
    row.innerHTML = `
      <div class="col-md-4">
        <select class="form-select" name="driver_type[]">
          <option value="Capacity">Capacity Driver</option>
          <option value="Demand">Demand Driver</option>
        </select>
      </div>
      <div class="col-md-4">
        <select class="form-select" name="driver_id[]">${driverOptions}</select>
      </div>
      <div class="col-md-3">
        <select class="form-select" name="effect_direction[]">
          <option value="Negative">Negative (-) Reduces driver share</option>
          <option value="Positive">Positive (+) Increases driver share</option>
        </select>
      </div>
      <div class="col-md-1 text-center">
        <button type="button" class="btn btn-sm btn-outline-danger mcqg-remove-row">&times;</button>
      </div>`;
    container.appendChild(row);
  });

  container.addEventListener("click", function (e) {
    if (e.target.classList.contains("mcqg-remove-row")) {
      e.target.closest(".mcqg-mapping-row").remove();
    }
  });
}

function initEffectColoring() {}

/** Live preview of the auto-generated "Inject message" */
function initInjectMessagePreview() {
  const nameInput = document.getElementById("investment_name");
  const preview = document.getElementById("inject-message-preview");
  if (!nameInput || !preview) return;

  nameInput.addEventListener("input", typeof mcqgDebounce === 'function' ? mcqgDebounce(function () {
    const name = nameInput.value.trim() || "This investment";
    preview.innerText = `${name} will apply its configured effect to every linked driver once purchased.`;
  }, 200) : function () {
    const name = nameInput.value.trim() || "This investment";
    preview.innerText = `${name} will apply its configured effect to every linked driver once purchased.`;
  });
}
