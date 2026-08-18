<?php
/**
 * =====================================================================
 * MCQG - Market Competition Quantitative Game
 * File: config/constants.php
 * Purpose: Single source of truth for every fixed value the whole
 *          application depends on - database credentials, folder
 *          paths, session keys, and the fixed business rules that
 *          come directly from the requirement documents
 *          (Business Logic doc, Admin/DB Design doc, MG19 screen deck).
 *
 * IMPORTANT: This file must be included first, before any other file.
 * =====================================================================
 */

// Prevent direct browser access to this file
if (!defined('MCQG_APP')) {
    define('MCQG_APP', true);
}

// ---------------------------------------------------------------------
// DATABASE CREDENTIALS  (XAMPP defaults - change for production)
// ---------------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'mcqg_game_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ---------------------------------------------------------------------
// FOLDER / URL PATHS
// ---------------------------------------------------------------------
define('ROOT_PATH', dirname(__DIR__));                 // absolute server path to mcqg-game/
define('BASE_URL', '/mcqg_game');                       // change if hosted in a different sub-folder
define('ADMIN_URL', BASE_URL . '/admin/');
define('PLAYER_URL', BASE_URL . '/player/');
define('AJAX_URL', BASE_URL . '/ajax/');
define('ASSETS_URL', BASE_URL . '/assets/');             // shared assets only (Bootstrap/jQuery)
define('ASSET_URL',  ASSETS_URL);                        // alias - many files use the shorter spelling
define('ADMIN_ASSETS_URL',  ADMIN_URL  . 'assets/');    // admin-only css/js
define('PLAYER_ASSETS_URL', PLAYER_URL . 'assets/');    // player-only css/js

define('LOG_PATH', ROOT_PATH . '/logs');

// ---------------------------------------------------------------------
// SESSION KEYS
// ---------------------------------------------------------------------
define('SESSION_ADMIN_ID', 'mcqg_admin_id');
define('SESSION_ADMIN_NAME', 'mcqg_admin_name');
define('SESSION_TEAM_ID', 'mcqg_team_id');
define('SESSION_TEAM_NAME', 'mcqg_team_name');
define('SESSION_GAME_ID', 'mcqg_active_game_id');
define('SESSION_CSRF_TOKEN', 'mcqg_csrf_token');
define('SESSION_FLASH_MESSAGE', 'mcqg_flash_message');

define('SESSION_LIFETIME_SECONDS', 3600); // 1 hour idle timeout

// ---------------------------------------------------------------------
// USER ROLES
// ---------------------------------------------------------------------
define('ROLE_ADMIN', 'admin');
define('ROLE_TEAM', 'team');

// ---------------------------------------------------------------------
// FIXED BUSINESS RULES  (from the requirement documents - do not change
// these without updating the source ARD documents first)
// ---------------------------------------------------------------------

// Doc 3 (MG19), Slide 5 & 7: Capacity Driver "Cost share %" and
// Demand Driver "Demand share %" must each total exactly this value.
define('DRIVER_SHARE_TOTAL_REQUIRED', 100.00);

// Driver type identifiers (match investment_effect.driver_type ENUM values)
define('DRIVER_TYPE_CAPACITY', 'Capacity');
define('DRIVER_TYPE_DEMAND', 'Demand');

// Doc 3 (MG19), Slide 3: Admin setup wizard has exactly 7 steps.
define('GAME_SETUP_TOTAL_STEPS', 7);

// Doc 3 (MG19), Slide 6: Investment effect percentages are stored to
// one decimal place and can be positive (green) or negative (red).
define('INVESTMENT_EFFECT_DECIMALS', 1);
define('INVESTMENT_EFFECT_POSITIVE_COLOR', '#2E7D32'); // green
define('INVESTMENT_EFFECT_NEGATIVE_COLOR', '#C62828'); // red

// Doc 3 (MG19), Slide 12: order of the 3-step demand allocation rule
define('ALLOCATION_STEP_MINIMUM', 'minimum_guarantee');
define('ALLOCATION_STEP_DEMAND_DRIVER', 'demand_driver_share');
define('ALLOCATION_STEP_PRICE', 'lowest_price_leftover');

// Doc 1 (Business Logic): default game length if admin does not override
define('DEFAULT_NO_OF_YEARS', 5);

// Doc 3 (MG19), Slide 15: a round can only be processed once every
// team in the game has a 'Submitted' decision row for that year.
define('ROUND_STATUS_OPEN', 'Open');
define('ROUND_STATUS_ALL_SUBMITTED', 'AllSubmitted');
define('ROUND_STATUS_PROCESSED', 'Processed');

// Game / game_master.status values
define('GAME_STATUS_DRAFT', 'Draft');
define('GAME_STATUS_PUBLISHED', 'Published');
define('GAME_STATUS_COMPLETED', 'Completed');

// ---------------------------------------------------------------------
// KNOWN GAPS FLAGGED DURING ANALYSIS (kept here as a visible reminder
// for whoever maintains this code - see project documentation)
// ---------------------------------------------------------------------
// 1. Service Level % formula is not defined in any source document.
//    A placeholder formula is used in engine/financials/ - CONFIRM before go-live.
// 2. Exact Operating Profit cost breakdown (amortized vs. immediate
//    investment cost, inflation compounding) is not fully specified.
//    A documented assumption is used in engine/financials/ - CONFIRM before go-live.
define('SERVICE_LEVEL_FORMULA_CONFIRMED', false);
define('OPERATING_PROFIT_FORMULA_CONFIRMED', false);
