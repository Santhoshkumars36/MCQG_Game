/* =========================================================
   MCQG Admin - Wizard Validation JS
   Path: admin/assets/js/admin-wizard-validation.js
   Drives the "Step X of 7" progress indicator (MG19 Slide 3)
   and validates each step's required fields before allowing NEXT.
   ========================================================= */

document.addEventListener("DOMContentLoaded", function () {
  initStepper();
  initNextButtonValidation();
  initPeriodCountCheck();
});

function initStepper() {
  const steps = document.querySelectorAll(".mcqg-step");
  const fill = document.querySelector(".mcqg-stepper-fill");
  if (!steps.length || !fill) return;

  const activeIndex = [...steps].findIndex((s) => s.classList.contains("active"));
  const pct = activeIndex >= 0 ? (activeIndex / (steps.length - 1)) * 90 : 0;
  fill.style.width = pct + "%";
}

/** The NEXT button (must literally say "NEXT" per MG19 Slide 3) only
 *  submits once every [required] field on the current step is filled. */
function initNextButtonValidation() {
  const nextBtn = document.getElementById("mcqg-next-btn");
  const form = document.getElementById("mcqg-wizard-form");
  if (!nextBtn || !form) return;

  nextBtn.addEventListener("click", function (e) {
    const editor = document.getElementById("case-study-editor");
    const hidden = document.getElementById("case-study-hidden");
    if (editor && hidden) {
      const text = editor.innerText ? editor.innerText.trim() : "";
      hidden.value = text ? editor.innerHTML : "";
    }

    let valid = true;
    form.querySelectorAll("[required]").forEach((field) => {
      if (!field.value || field.value.trim() === "") {
        valid = false;
        field.classList.add("is-invalid-live");
        if (field.id === "case-study-hidden" && editor) {
          editor.classList.add("is-invalid-live");
          editor.classList.remove("is-valid-live");
        }
      } else {
        field.classList.remove("is-invalid-live");
        field.classList.add("is-valid-live");
        if (field.id === "case-study-hidden" && editor) {
          editor.classList.remove("is-invalid-live");
          editor.classList.add("is-valid-live");
        }
      }
    });

    if (!valid) {
      e.preventDefault();
      showToast("Please fill in all required fields before continuing.", "error");
    }
  });
}

/** MG19 Slide 4: annual-parameter row count must equal "number of periods" */
function initPeriodCountCheck() {
  const yearsInput = document.getElementById("no_of_years");
  const rowsContainer = document.getElementById("annual-parameters-rows");
  if (!yearsInput || !rowsContainer) return;

  yearsInput.addEventListener("input", mcqgDebounce(function () {
    const years = parseInt(yearsInput.value, 10) || 0;
    rebuildAnnualParameterRows(rowsContainer, years);
  }, 350));
}

function rebuildAnnualParameterRows(container, years) {
  container.innerHTML = "";
  for (let y = 1; y <= years; y++) {
    const row = document.createElement("div");
    row.className = "row g-2 mb-2 align-items-center";
    row.innerHTML = `
      <div class="col-2"><span class="fw-bold">Year ${y}</span></div>
      <div class="col-4">
        <input type="number" class="form-control mcqg-input-live" name="market_demand[${y}]" placeholder="Market Demand" required>
      </div>
      <div class="col-4">
        <input type="number" step="0.01" class="form-control mcqg-input-live" name="inflation_percent[${y}]" placeholder="Inflation %" required>
      </div>`;
    container.appendChild(row);
  }
}
