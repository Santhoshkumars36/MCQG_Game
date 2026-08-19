<?php
/**
 * MCQG Admin - Enterprise Moderator Control Center
 * Path: admin/moderator/moderator_dashboard.php
 * Interactive, real-time game management platform.
 */
require_once __DIR__ . '/../../config/app_config.php';
Auth::requireAdmin();

$db = Database::getInstance();
$games = $db->fetchAll("SELECT game_id, game_name, no_of_years, status, unit_cost, starting_capacity, starting_cash FROM game_master WHERE isDeleted = 0 ORDER BY created_on DESC");

$gameId = (int) ($_GET['game_id'] ?? ($games[0]['game_id'] ?? 0));
$dashboardData = $gameId ? Moderator::getDashboardState($gameId) : [];

$game = $dashboardData['game'] ?? null;
$rounds = $dashboardData['rounds'] ?? [];
$teams = $dashboardData['teams'] ?? [];
$activeYear = $dashboardData['active_year'] ?? 1;
$teamStatuses = $dashboardData['team_statuses'] ?? [];
$submittedCount = $dashboardData['submitted_count'] ?? 0;
$totalTeams = $dashboardData['total_teams'] ?? 0;
$messages = $dashboardData['messages'] ?? [];

// Calculate overall submissions percentage
$submissionPercent = $totalTeams > 0 ? round(($submittedCount / $totalTeams) * 100) : 0;

$pageTitle = 'Moderator Control Center';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<link rel="stylesheet" href="<?php echo ADMIN_URL; ?>assets/css/moderator-control.css">

<div class="mcqg-main mod-dashboard-wrapper" style="padding: 0; min-height: 100vh;">
<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

  <!-- Top Navigation & Game Selector Bar -->
  <div class="mod-top-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
      <div>
        <div class="mod-header-brand-title">
          <span class="mod-header-brand">StrategyEngine</span>
          <span class="mod-header-divider">|</span>
          <span class="mod-header-title">Enterprise Control Center</span>
        </div>
        <div class="mod-breadcrumb">
          <span>Game: <strong class="crumb-item text-dark"><?php echo htmlspecialchars($game['game_name'] ?? 'Select Game'); ?></strong></span>
          <span class="crumb-sep">&bull;</span>
          <span>Status: <span class="badge bg-success-subtle text-success border border-success-subtle fw-bold"><?php echo htmlspecialchars($game['status'] ?? 'Draft'); ?></span></span>
          <span class="crumb-sep">&bull;</span>
          <span>Total Rounds: <strong><?php echo (int)($game['no_of_years'] ?? 0); ?> Years</strong></span>
        </div>
      </div>

      <!-- Right Toolbar & Game Selector -->
      <div class="d-flex align-items-center gap-3">
        <form method="GET" id="game-selector-form" class="m-0">
          <select name="game_id" class="form-select form-select-sm fw-bold shadow-sm" style="min-width:260px; border-color:#cbd5e1;" onchange="this.form.submit()">
            <?php foreach ($games as $g): ?>
              <option value="<?php echo $g['game_id']; ?>" <?php echo $g['game_id'] == $gameId ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($g['game_name']); ?> (<?php echo $g['status']; ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </form>

        <button class="btn btn-sm btn-outline-primary fw-bold" onclick="fetchLiveStatus()"><i class="bi bi-arrow-clockwise me-1"></i> Sync Live</button>
      </div>
    </div>
  </div>

  <div style="padding: 24px 32px 40px;">
    <?php if (!$game): ?>
      <div class="alert alert-warning">Please select or publish a game to access the Moderator Control Center.</div>
    <?php else: ?>

    <!-- KPI Summary Grid -->
    <div class="mod-kpi-grid">
      
      <!-- KPI 1: Active Round Status -->
      <div class="mod-kpi-card">
        <div class="mod-kpi-label">Active Round</div>
        <div class="mod-kpi-value">
          <span>Round <?php echo $activeYear; ?></span>
          <?php 
            $activeRoundObj = null;
            foreach ($rounds as $r) { if ($r['year_no'] == $activeYear) { $activeRoundObj = $r; break; } }
            $activeStatus = $activeRoundObj['status'] ?? 'Not Launched';
          ?>
          <span class="status-pill <?php echo strtolower(str_replace(' ', '-', $activeStatus)); ?> ms-auto" style="font-size:10px;">
            <?php echo strtoupper($activeStatus); ?>
          </span>
        </div>
        <div class="mod-kpi-sub">
          Launched: <?php echo ($activeRoundObj && $activeRoundObj['launched_at']) ? date('h:i A', strtotime($activeRoundObj['launched_at'])) : 'Not Launched Yet'; ?>
        </div>
      </div>

      <!-- KPI 2: Live Team Submissions Progress -->
      <div class="mod-kpi-card kpi-success">
        <div class="mod-kpi-label">Team Submissions</div>
        <div class="mod-kpi-value">
          <span id="kpi-submitted-count"><?php echo $submittedCount; ?></span> / <span><?php echo $totalTeams; ?> Teams</span>
          <span class="fs-6 text-success ms-auto fw-bold" id="kpi-submission-percent"><?php echo $submissionPercent; ?>%</span>
        </div>
        <div class="progress mt-2" style="height:6px;">
          <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" id="kpi-progress-bar" style="width: <?php echo $submissionPercent; ?>%"></div>
        </div>
      </div>

      <!-- KPI 3: Game Baseline Specs -->
      <div class="mod-kpi-card kpi-gold">
        <div class="mod-kpi-label">Baseline Specs</div>
        <div class="mod-kpi-value">
          <span>&#8377;<?php echo number_format((float)$game['unit_cost'], 2); ?></span>
          <span class="fs-6 text-muted ms-auto">Unit Cost</span>
        </div>
        <div class="mod-kpi-sub d-flex justify-content-between">
          <span>Capacity: <?php echo number_format((int)$game['starting_capacity']); ?> units</span>
          <span>Cash: &#8377;<?php echo number_format((float)$game['starting_cash']); ?></span>
        </div>
      </div>

      <!-- KPI 4: Broadcast Message Center -->
      <div class="mod-kpi-card kpi-warning">
        <div class="mod-kpi-label">Message Stream</div>
        <div class="mod-kpi-value">
          <span><?php echo count($messages); ?></span>
          <button class="btn btn-sm btn-light border ms-auto fw-bold text-primary" style="font-size:11px;" onclick="openBroadcastModal()">+ Send Message</button>
        </div>
        <div class="mod-kpi-sub text-truncate">
          Latest: <?php echo !empty($messages[0]['message_text']) ? htmlspecialchars($messages[0]['message_text']) : 'No announcements posted'; ?>
        </div>
      </div>

    </div>

    <!-- Interactive Tabs Navigation -->
    <div class="mod-tabs-bar">
      <div class="mod-tab-item active" data-tab="tab-control">
        <i class="bi bi-display"></i> LIVE CONTROL DASHBOARD
      </div>
      <div class="mod-tab-item" data-tab="tab-rounds">
        <i class="bi bi-layers-half"></i> ROUND SPECS &amp; MANAGEMENT
      </div>
      <div class="mod-tab-item" data-tab="tab-teams">
        <i class="bi bi-people-fill"></i> TEAM DRILL-DOWN &amp; LEADERBOARD
      </div>
      <div class="mod-tab-item" data-tab="tab-messages">
        <i class="bi bi-chat-left-text-fill"></i> MESSAGE CENTER (<?php echo count($messages); ?>)
      </div>
    </div>

    <!-- Tab 1 Content: LIVE CONTROL DASHBOARD -->
    <div id="tab-control" class="mod-tab-content active">
      
      <!-- Interactive Round Selector Timeline Cards -->
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="fw-bold text-uppercase text-muted" style="font-size:12px; letter-spacing:0.5px;">Select Round Timeline to View Details</h6>
        <span class="text-muted small">Click any round card below to dynamically view round status &amp; teams</span>
      </div>

      <div class="mod-round-selector-bar">
        <?php foreach ($rounds as $r): 
          $rYear = (int)$r['year_no'];
          $rStatus = $r['status'];
          $isSelected = ($rYear == $activeYear);
        ?>
          <div class="mod-round-pill-card <?php echo $isSelected ? 'active' : ''; ?>" data-year="<?php echo $rYear; ?>" onclick="selectRound(<?php echo $rYear; ?>)">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="mod-round-pill-title">Round <?php echo $rYear; ?></span>
              <?php if ($isSelected): ?><span class="badge bg-primary" style="font-size:9px;">SELECTED</span><?php endif; ?>
            </div>
            <div class="mod-round-pill-status">
              <span class="status-pill <?php echo strtolower(str_replace(' ', '-', $rStatus)); ?>">
                <?php echo strtoupper($rStatus); ?>
              </span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Selected Round Actions & Status Panel -->
      <div class="mod-card mb-4" id="round-control-banner">
        <div class="mod-card-header bg-light">
          <div>
            <h5 id="selected-round-title">Round <?php echo $activeYear; ?> Overview</h5>
            <span class="text-muted small" id="selected-round-sub">Auto-refreshing team submissions every 5 seconds</span>
          </div>
          <div id="selected-round-actions" class="d-flex gap-2">
            <?php if ($activeStatus === 'Not Launched'): ?>
              <button class="btn btn-sm btn-primary fw-bold px-3 btn-launch-round" data-year="<?php echo $activeYear; ?>">
                🚀 LAUNCH ROUND <?php echo $activeYear; ?>
              </button>
            <?php elseif ($activeStatus === 'Live' || $activeStatus === 'Open'): ?>
              <button class="btn btn-sm btn-warning fw-bold px-3 btn-process-round" data-year="<?php echo $activeYear; ?>">
                ⚡ PROCESS RESULTS
              </button>
            <?php elseif ($activeStatus === 'Processed'): ?>
              <a href="../reports/round_report.php?game_id=<?php echo $gameId; ?>&year_no=<?php echo $activeYear; ?>" class="btn btn-sm btn-outline-primary fw-bold">
                📊 View Processed Results Report
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Dynamic Team Cards Grid & Submissions Table -->
      <div class="mod-card">
        <div class="mod-card-header">
          <div>
            <h5 id="team-grid-header-title">Team Submissions (Round <?php echo $activeYear; ?>)</h5>
            <div class="header-sub">Click on any team card to open detailed round decisions and results</div>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-sm btn-light border" onclick="fetchLiveStatus()"><i class="bi bi-arrow-repeat"></i> Refresh</button>
          </div>
        </div>

        <div class="p-3">
          <div class="mod-team-grid" id="team-cards-container">
            <?php foreach ($teamStatuses as $ts): ?>
              <div class="mod-team-card" data-team-id="<?php echo $ts['team_id']; ?>" onclick="openTeamDetailModal(<?php echo $ts['team_id']; ?>, <?php echo $activeYear; ?>)">
                <div class="mod-team-card-header">
                  <span class="mod-team-name"><?php echo htmlspecialchars($ts['team_name']); ?></span>
                  <span class="status-pill <?php echo strtolower(str_replace(' ', '-', $ts['status'])); ?>">
                    <?php echo htmlspecialchars($ts['status']); ?>
                  </span>
                </div>
                <div class="mod-team-metrics">
                  <div class="mod-team-metric-row">
                    <span>Last Action:</span>
                    <strong><?php echo $ts['last_action'] ? date('h:i:s A', strtotime($ts['last_action'])) : '--'; ?></strong>
                  </div>
                  <?php if (isset($ts['operating_profit'])): ?>
                    <div class="mod-team-metric-row">
                      <span>Operating Profit:</span>
                      <strong class="<?php echo $ts['operating_profit'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                        &#8377;<?php echo number_format((float)$ts['operating_profit']); ?>
                      </strong>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="d-flex justify-content-between mt-3 pt-2 border-top">
                  <button class="btn btn-xs btn-outline-primary fw-bold" style="font-size:11px;" onclick="event.stopPropagation(); openTeamDetailModal(<?php echo $ts['team_id']; ?>, <?php echo $activeYear; ?>)">
                    🔍 Details
                  </button>
                  <button class="btn btn-xs btn-outline-secondary fw-bold" style="font-size:11px;" onclick="event.stopPropagation(); triggerAdjustmentModal(<?php echo $ts['team_id']; ?>, '<?php echo addslashes(htmlspecialchars($ts['team_name'])); ?>')">
                    ⚙ Adjustment
                  </button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

    </div>

    <!-- Tab 2 Content: ROUND SPECS & MANAGEMENT -->
    <div id="tab-rounds" class="mod-tab-content" style="display:none;">
      <div class="mod-card">
        <div class="mod-card-header">
          <h5>All Game Rounds Management</h5>
        </div>
        <div class="table-responsive">
          <table class="mod-table">
            <thead>
              <tr>
                <th>Round</th>
                <th>Status</th>
                <th>Launched At</th>
                <th>Processed On</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rounds as $r): 
                $rYear = (int)$r['year_no'];
                $rStatus = $r['status'];
              ?>
                <tr>
                  <td class="fw-bold">Round <?php echo $rYear; ?></td>
                  <td><span class="status-pill <?php echo strtolower(str_replace(' ', '-', $rStatus)); ?>"><?php echo strtoupper($rStatus); ?></span></td>
                  <td><?php echo $r['launched_at'] ? date('d M Y, h:i A', strtotime($r['launched_at'])) : '--'; ?></td>
                  <td><?php echo isset($r['processed_on']) && $r['processed_on'] ? date('d M Y, h:i A', strtotime($r['processed_on'])) : '--'; ?></td>
                  <td style="text-align:right;">
                    <?php if ($rStatus === 'Not Launched'): ?>
                      <button class="btn-mod-launch btn-launch-round" data-year="<?php echo $rYear; ?>">🚀 LAUNCH</button>
                    <?php elseif ($rStatus === 'Live' || $rStatus === 'Open'): ?>
                      <button class="btn-mod-process btn-process-round" data-year="<?php echo $rYear; ?>">⚡ PROCESS</button>
                    <?php elseif ($rStatus === 'Processed'): ?>
                      <a href="../reports/round_report.php?game_id=<?php echo $gameId; ?>&year_no=<?php echo $rYear; ?>" class="btn btn-sm btn-outline-secondary">View Report</a>
                    <?php else: ?>
                      <span class="text-muted small">Locked</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Tab 3 Content: TEAM DRILL-DOWN & LEADERBOARD -->
    <div id="tab-teams" class="mod-tab-content" style="display:none;">
      <div class="mod-card">
        <div class="mod-card-header">
          <h5>Registered Teams Leaderboard &amp; Status</h5>
        </div>
        <div class="table-responsive">
          <table class="mod-table">
            <thead>
              <tr>
                <th>Team Name</th>
                <th>Username</th>
                <th>Active Round Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($teams as $t): ?>
                <tr>
                  <td class="fw-bold"><?php echo htmlspecialchars($t['team_name']); ?></td>
                  <td><code><?php echo htmlspecialchars($t['username']); ?></code></td>
                  <td><span class="badge bg-light text-dark border">Registered</span></td>
                  <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="openTeamDetailModal(<?php echo $t['team_id']; ?>, <?php echo $activeYear; ?>)">🔍 View Detail</button>
                    <button class="btn btn-sm btn-outline-secondary ms-1" onclick="triggerAdjustmentModal(<?php echo $t['team_id']; ?>, '<?php echo addslashes(htmlspecialchars($t['team_name'])); ?>')">⚙ Adjustment</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Tab 4 Content: MESSAGE CENTER -->
    <div id="tab-messages" class="mod-tab-content" style="display:none;">
      <div class="row g-4">
        <div class="col-lg-8">
          <div class="mod-card">
            <div class="mod-card-header">
              <h5>Message Feed Stream</h5>
              <button class="btn btn-sm btn-primary fw-bold" onclick="openBroadcastModal()">+ Post New Message</button>
            </div>
            <div class="p-3" style="max-height: 500px; overflow-y: auto; background:#f8fafc;">
              <?php foreach ($messages as $m): ?>
                <div class="mod-message-item">
                  <div class="mod-message-sender">
                    <span>
                      <?php echo htmlspecialchars($m['sender_name']); ?>
                      <?php if ($m['team_name']): ?> &rsaquo; <span style="color:#0284c7;"><?php echo htmlspecialchars($m['team_name']); ?></span><?php endif; ?>
                    </span>
                    <span class="text-muted fw-normal small"><?php echo date('d M Y, h:i A', strtotime($m['created_on'])); ?></span>
                  </div>
                  <div class="mod-message-body"><?php echo htmlspecialchars($m['message_text']); ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="mod-card p-3">
            <h6 class="fw-bold mb-3">Broadcast Composer</h6>
            <form id="tab-message-form">
              <input type="hidden" name="game_id" value="<?php echo $gameId; ?>">
              <input type="hidden" name="year_no" value="<?php echo $activeYear; ?>">
              <input type="hidden" name="action" value="send_message">
              
              <div class="mb-3">
                <label class="form-label small fw-bold">Recipient</label>
                <select name="team_id" class="form-select form-select-sm">
                  <option value="">Broadcast to All Teams</option>
                  <?php foreach ($teams as $t): ?>
                    <option value="<?php echo $t['team_id']; ?>"><?php echo htmlspecialchars($t['team_name']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label small fw-bold">Message Content</label>
                <textarea name="message_text" class="form-control" rows="4" placeholder="Enter broadcast message or announcement..." required></textarea>
              </div>
              <button type="submit" class="btn btn-primary w-100 fw-bold">Send Announcement</button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <?php endif; ?>
  </div>
</div>

<!-- Modal 1: Team Round Performance Detail Modal -->
<div class="modal fade" id="teamDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius:12px;">
      <div class="modal-header bg-navy text-white" style="background:#0f172a;">
        <h5 class="modal-title fw-bold" id="teamDetailModalTitle">Team Performance Detail</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4" id="teamDetailModalBody">
        <div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><div class="mt-2 text-muted">Loading team details...</div></div>
      </div>
    </div>
  </div>
</div>

<!-- Modal 2: Moderator Adjustment Modal -->
<div class="modal fade" id="moderatorAdjustmentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius:12px;">
      <div class="modal-header text-white" style="background:#0f172a;">
        <h5 class="modal-title fw-bold">⚙ Moderator Adjustment</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="moderator-adjustment-form">
        <input type="hidden" name="game_id" value="<?php echo $gameId; ?>">
        <input type="hidden" name="action" value="save_adjustment">

        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-bold">Select Team</label>
            <select name="team_id" id="adj-team-select" class="form-select" required>
              <?php foreach ($teams as $t): ?>
                <option value="<?php echo $t['team_id']; ?>"><?php echo htmlspecialchars($t['team_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Target Round</label>
            <select name="year_no" class="form-select" required>
              <?php for ($y = 1; $y <= ($game['no_of_years'] ?? 5); $y++): ?>
                <option value="<?php echo $y; ?>" <?php echo $y == ($activeYear + 1) ? 'selected' : ''; ?>>
                  Round <?php echo $y; ?>
                </option>
              <?php endfor; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Adjustment Amount (Bonus + / Penalty -)</label>
            <input type="number" step="0.01" name="amount" class="form-control" placeholder="e.g. 50000 or -10000" required>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Reason / Note</label>
            <input type="text" name="reason" class="form-control" placeholder="e.g. Presentation bonus" required>
          </div>
        </div>

        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary fw-bold">Save Adjustment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal 3: Unsubmitted Teams Warning Modal -->
<div class="modal fade" id="unsubmittedWarningModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-danger text-white py-3">
        <h5 class="modal-title fw-bold">⚠️ Unsubmitted Teams Warning</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4 text-center">
        <h5 class="fw-bold mb-2">Not All Teams Have Submitted!</h5>
        <p class="text-muted">Only <strong id="warning-submitted-count" class="text-danger">0</strong> of <strong id="warning-total-count">0</strong> teams have submitted.</p>
        <p class="small text-secondary mb-0">Processing results now will force-lock unsubmitted team decisions.</p>
      </div>
      <div class="modal-footer bg-light justify-content-between">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="confirm-force-process-btn" class="btn btn-danger fw-bold">Force Process Results</button>
      </div>
    </div>
  </div>
</div>

<script>
  const currentGameId = <?php echo $gameId ? $gameId : 0; ?>;
  let selectedRoundYear = <?php echo $activeYear; ?>;
  let targetProcessYear = 1;

  // Interactive Tab Navigation
  document.querySelectorAll('.mod-tab-item').forEach(tab => {
    tab.addEventListener('click', function() {
      document.querySelectorAll('.mod-tab-item').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.mod-tab-content').forEach(c => c.style.display = 'none');
      
      this.classList.add('active');
      const target = this.getAttribute('data-tab');
      const contentEl = document.getElementById(target);
      if (contentEl) contentEl.style.display = 'block';
    });
  });

  // Select Round dynamically
  function selectRound(yearNo) {
    selectedRoundYear = yearNo;
    document.querySelectorAll('.mod-round-pill-card').forEach(card => {
      if (card.getAttribute('data-year') == yearNo) {
        card.classList.add('active');
      } else {
        card.classList.remove('active');
      }
    });

    document.getElementById('selected-round-title').textContent = `Round ${yearNo} Overview`;
    document.getElementById('team-grid-header-title').textContent = `Team Submissions (Round ${yearNo})`;

    fetchRoundOverview(yearNo);
  }

  // Fetch Round Overview via AJAX
  function fetchRoundOverview(yearNo) {
    fetch(`../../ajax/admin_ajax/moderator_actions.php?action=get_round_overview&game_id=${currentGameId}&year_no=${yearNo}`)
      .then(res => res.json())
      .then(res => {
        if (res.success && res.data) {
          const d = res.data;
          renderTeamCards(d.team_statuses, yearNo);
        }
      });
  }

  // Render Team Cards Grid
  function renderTeamCards(teams, yearNo) {
    const container = document.getElementById('team-cards-container');
    if (!teams || teams.length === 0) {
      container.innerHTML = '<div class="text-muted py-4">No active teams registered.</div>';
      return;
    }

    container.innerHTML = teams.map(ts => {
      const statusClass = ts.status.toLowerCase().replace(/\s+/g, '-');
      const lastAct = ts.last_action ? new Date(ts.last_action).toLocaleTimeString() : '--';
      const profitHtml = ts.operating_profit !== null 
        ? `<div class="mod-team-metric-row"><span>Operating Profit:</span><strong class="${ts.operating_profit >= 0 ? 'text-success' : 'text-danger'}">&#8377;${Number(ts.operating_profit).toLocaleString()}</strong></div>`
        : '';

      return `
        <div class="mod-team-card" onclick="openTeamDetailModal(${ts.team_id}, ${yearNo})">
          <div class="mod-team-card-header">
            <span class="mod-team-name">${escapeHtml(ts.team_name)}</span>
            <span class="status-pill ${statusClass}">${escapeHtml(ts.status)}</span>
          </div>
          <div class="mod-team-metrics">
            <div class="mod-team-metric-row">
              <span>Last Action:</span>
              <strong>${lastAct}</strong>
            </div>
            ${profitHtml}
          </div>
          <div class="d-flex justify-content-between mt-3 pt-2 border-top">
            <button class="btn btn-xs btn-outline-primary fw-bold" style="font-size:11px;" onclick="event.stopPropagation(); openTeamDetailModal(${ts.team_id}, ${yearNo})">
              🔍 Details
            </button>
            <button class="btn btn-xs btn-outline-secondary fw-bold" style="font-size:11px;" onclick="event.stopPropagation(); triggerAdjustmentModal(${ts.team_id}, '${escapeHtml(ts.team_name)}')">
              ⚙ Adjustment
            </button>
          </div>
        </div>
      `;
    }).join('');
  }

  // Open Team Detail Performance Modal
  function openTeamDetailModal(teamId, yearNo) {
    const modalEl = document.getElementById('teamDetailModal');
    const bodyEl = document.getElementById('teamDetailModalBody');
    bodyEl.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div><div class="mt-2 text-muted">Loading team details...</div></div>';
    
    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    fetch(`../../ajax/admin_ajax/moderator_actions.php?action=get_team_round_detail&game_id=${currentGameId}&team_id=${teamId}&year_no=${yearNo}`)
      .then(res => res.json())
      .then(res => {
        if (res.success && res.data) {
          const d = res.data;
          document.getElementById('teamDetailModalTitle').textContent = `${d.team.team_name} — Round ${yearNo} Detail`;

          const dec = d.decision;
          const res = d.result;

          bodyEl.innerHTML = `
            <div class="row g-3">
              <div class="col-md-6">
                <div class="card p-3 border shadow-sm">
                  <h6 class="fw-bold text-primary">Production Decision</h6>
                  <div class="d-flex justify-content-between py-1 border-bottom"><span>Capacity Built:</span><strong>${dec ? dec.capacity_built : '--'} units</strong></div>
                  <div class="d-flex justify-content-between py-1 border-bottom"><span>Production Qty:</span><strong>${dec ? dec.production_qty : '--'} units</strong></div>
                  <div class="d-flex justify-content-between py-1"><span>Decision Status:</span><span class="badge bg-info">${dec ? dec.status : 'Not Started'}</span></div>
                </div>
              </div>

              <div class="col-md-6">
                <div class="card p-3 border shadow-sm">
                  <h6 class="fw-bold text-success">Financial Performance</h6>
                  <div class="d-flex justify-content-between py-1 border-bottom"><span>Selling Price:</span><strong>&#8377;${dec ? Number(dec.selling_price).toFixed(2) : '--'}</strong></div>
                  <div class="d-flex justify-content-between py-1 border-bottom"><span>Sales Revenue:</span><strong>&#8377;${res ? Number(res.sales_revenue).toLocaleString() : '--'}</strong></div>
                  <div class="d-flex justify-content-between py-1"><span>Operating Profit:</span><strong class="${res && res.operating_profit >= 0 ? 'text-success' : 'text-danger'}">&#8377;${res ? Number(res.operating_profit).toLocaleString() : '--'}</strong></div>
                </div>
              </div>
            </div>

            <div class="mt-4">
              <h6 class="fw-bold">Capacity Driver Investments Purchased</h6>
              ${d.investments && d.investments.length > 0 ? `
                <table class="table table-sm border mt-2">
                  <thead><tr><th>Investment Name</th><th>Invested Amount</th></tr></thead>
                  <tbody>${d.investments.map(i => `<tr><td>${escapeHtml(i.investment_name)}</td><td>&#8377;${Number(i.invested_value).toLocaleString()}</td></tr>`).join('')}</tbody>
                </table>
              ` : '<p class="text-muted small mb-0">No driver investments selected for this round.</p>'}
            </div>
          `;
        }
      });
  }

  // Trigger Moderator Adjustment Modal
  function triggerAdjustmentModal(teamId, teamName) {
    const select = document.getElementById('adj-team-select');
    if (select) select.value = teamId;
    const modal = new bootstrap.Modal(document.getElementById('moderatorAdjustmentModal'));
    modal.show();
  }

  function openBroadcastModal() {
    const modal = new bootstrap.Modal(document.getElementById('moderatorAdjustmentModal'));
    modal.show();
  }

  // Auto Refresh Live Status
  function fetchLiveStatus() {
    if (!currentGameId) return;
    fetch(`../../ajax/admin_ajax/moderator_actions.php?action=get_live_status&game_id=${currentGameId}`)
      .then(res => res.json())
      .then(res => {
        if (res.success && res.data) {
          const d = res.data;
          document.getElementById('kpi-submitted-count').textContent = d.submitted_count;
          const pct = d.total_teams > 0 ? Math.round((d.submitted_count / d.total_teams) * 100) : 0;
          document.getElementById('kpi-submission-percent').textContent = `${pct}%`;
          document.getElementById('kpi-progress-bar').style.width = `${pct}%`;

          fetchRoundOverview(selectedRoundYear);
        }
      });
  }

  function escapeHtml(text) {
    return text ? text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;") : '';
  }

  setInterval(fetchLiveStatus, 5000);

  // Round Action Listeners
  document.addEventListener('click', function(e) {
    if (e.target.classList.contains('btn-launch-round')) {
      const yearNo = e.target.getAttribute('data-year');
      if (confirm(`Are you sure you want to LAUNCH Round ${yearNo}?`)) {
        const formData = new FormData();
        formData.append('action', 'launch_round');
        formData.append('game_id', currentGameId);
        formData.append('year_no', yearNo);

        fetch('../../ajax/admin_ajax/moderator_actions.php', { method: 'POST', body: formData })
          .then(res => res.json())
          .then(res => {
            if (res.success) location.reload();
            else alert(res.message);
          });
      }
    }

    if (e.target.classList.contains('btn-process-round')) {
      targetProcessYear = e.target.getAttribute('data-year');
      processRoundRequest(targetProcessYear, false);
    }
  });

  function processRoundRequest(yearNo, force) {
    const formData = new FormData();
    formData.append('action', 'process_round');
    formData.append('game_id', currentGameId);
    formData.append('year_no', yearNo);
    if (force) formData.append('force', '1');

    fetch('../../ajax/admin_ajax/moderator_actions.php', { method: 'POST', body: formData })
      .then(res => res.json())
      .then(res => {
        if (res.unsubmitted && !force) {
          document.getElementById('warning-submitted-count').textContent = res.submitted_count;
          document.getElementById('warning-total-count').textContent = res.total_teams;
          const modal = new bootstrap.Modal(document.getElementById('unsubmittedWarningModal'));
          modal.show();
        } else if (res.success) {
          alert(res.message);
          location.reload();
        } else alert(res.message);
      });
  }

  document.getElementById('confirm-force-process-btn').addEventListener('click', function() {
    const modalEl = document.getElementById('unsubmittedWarningModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
    processRoundRequest(targetProcessYear, true);
  });

  // Forms
  document.getElementById('moderator-adjustment-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch('../../ajax/admin_ajax/moderator_actions.php', { method: 'POST', body: formData })
      .then(res => res.json())
      .then(res => {
        if (res.success) { alert(res.message); location.reload(); }
        else alert(res.message);
      });
  });

  document.getElementById('tab-message-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch('../../ajax/admin_ajax/moderator_actions.php', { method: 'POST', body: formData })
      .then(res => res.json())
      .then(res => {
        if (res.success) { alert('Broadcast announcement posted!'); location.reload(); }
        else alert(res.message);
      });
  });
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
