/* =========================================================
   MCQG Admin - Reports Charts JS
   Path: admin/assets/js/admin-charts.js
   Requires Chart.js (loaded via assets/js/ shared library).
   ========================================================= */

document.addEventListener("DOMContentLoaded", function () {
  renderCumulativeProfitChart();
  renderMarketShareChart();
  renderInvestmentMatrix();
});

/** Reads JSON data embedded by the PHP report page in a
 *  <script type="application/json" id="..."> tag, keeping
 *  PHP and JS cleanly separated. */
function readEmbeddedData(elementId) {
  const el = document.getElementById(elementId);
  if (!el) return null;
  try {
    return JSON.parse(el.textContent);
  } catch (e) {
    return null;
  }
}

function renderCumulativeProfitChart() {
  const ctx = document.getElementById("mcqg-cumulative-profit-chart");
  const data = readEmbeddedData("cumulative-profit-data");
  if (!ctx || !data) return;

  new Chart(ctx, {
    type: "line",
    data: {
      labels: data.years,
      datasets: data.teams.map((team, i) => ({
        label: team.team_name,
        data: team.profit_by_year,
        borderWidth: 3,
        tension: 0.35,
        fill: false,
      })),
    },
    options: {
      responsive: true,
      plugins: { legend: { position: "bottom" } },
      scales: { y: { ticks: { callback: (v) => "\u20B9" + v.toLocaleString() } } },
    },
  });
}

function renderMarketShareChart() {
  const ctx = document.getElementById("mcqg-market-share-chart");
  const data = readEmbeddedData("market-share-data");
  if (!ctx || !data) return;

  new Chart(ctx, {
    type: "doughnut",
    data: {
      labels: data.labels,
      datasets: [{ data: data.values, backgroundColor: ["#1e2761", "#c99a2e", "#1f9d55", "#5b5f6b"] }],
    },
    options: { responsive: true, plugins: { legend: { position: "right" } } },
  });
}

/** Renders the investment-matrix heat grid (MG19 Slide 13:
 *  "matrix of share of demand drivers and capacity drivers"). */
function renderInvestmentMatrix() {
  const container = document.getElementById("mcqg-investment-matrix");
  const data = readEmbeddedData("investment-matrix-data");
  if (!container || !data) return;

  let html = "<table class='mcqg-report-table'><thead><tr><th>Team</th>";
  data.drivers.forEach((d) => (html += `<th>${d}</th>`));
  html += "</tr></thead><tbody>";

  data.teams.forEach((team) => {
    html += `<tr><td><strong>${team.team_name}</strong></td>`;
    team.shares.forEach((share) => {
      const intensity = Math.min(share / 100, 1);
      const bg = `rgba(30, 39, 97, ${0.2 + intensity * 0.7})`;
      html += `<td class="mcqg-matrix-cell" style="background:${bg}">${share.toFixed(0)}%</td>`;
    });
    html += "</tr>";
  });
  html += "</tbody></table>";
  container.innerHTML = html;
}
