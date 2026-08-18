/* =========================================================
   MCQG Admin - Reports Charts JS
   Path: admin/assets/js/admin-charts.js
   Requires Chart.js (loaded via assets/js/ or CDN).
   ========================================================= */

function initAdminCharts() {
  renderCumulativeProfitChart();
  renderMarketShareChart();
  renderInvestmentMatrix();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initAdminCharts);
} else {
  initAdminCharts();
}

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
  if (!ctx || !data || typeof Chart === "undefined") return;

  if (ctx.getAttribute("data-chart-rendered")) return;
  ctx.setAttribute("data-chart-rendered", "true");

  const colors = [
    "#1e2761", "#c99a2e", "#1f9d55", "#e74c3c", 
    "#8e44ad", "#3498db", "#e67e22", "#1abc9c", "#d35400", "#2c3e50"
  ];

  const labels = (data.years && data.years.length > 0) ? data.years : ["Year 1"];

  new Chart(ctx, {
    type: "line",
    data: {
      labels: labels,
      datasets: (data.teams || []).map((team, i) => ({
        label: team.team_name,
        data: team.profit_by_year,
        borderColor: colors[i % colors.length],
        backgroundColor: colors[i % colors.length],
        borderWidth: 3,
        tension: 0.35,
        fill: false,
        pointRadius: 5,
        pointHoverRadius: 7
      })),
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: "bottom" },
        tooltip: {
          callbacks: {
            label: function(context) {
              let label = context.dataset.label || '';
              if (label) label += ': ';
              if (context.parsed.y !== null) {
                label += '\u20B9' + Number(context.parsed.y).toLocaleString();
              }
              return label;
            }
          }
        }
      },
      scales: {
        y: {
          ticks: {
            callback: (v) => "\u20B9" + Number(v).toLocaleString()
          }
        }
      },
    },
  });
}

function renderMarketShareChart() {
  const ctx = document.getElementById("mcqg-market-share-chart");
  const data = readEmbeddedData("market-share-data");
  if (!ctx || !data || typeof Chart === "undefined") return;

  if (ctx.getAttribute("data-chart-rendered")) return;
  ctx.setAttribute("data-chart-rendered", "true");

  new Chart(ctx, {
    type: "doughnut",
    data: {
      labels: data.labels,
      datasets: [{ data: data.values, backgroundColor: ["#1e2761", "#c99a2e", "#1f9d55", "#5b5f6b"] }],
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "right" } } },
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
