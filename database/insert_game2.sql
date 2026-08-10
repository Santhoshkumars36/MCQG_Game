-- =====================================================================
-- MCQG - New Game Insert (Admin Module Data Only)
-- File: database/insert_game2.sql
-- Game: Mobile Phone Market Challenge (game_id = 2)
-- Run AFTER mcqg_schema.sql and seed_data.sql
-- Scope: Admin tables ONLY:
--   game_master, game_market_year, capacity_driver, demand_driver,
--   investment_master, investment_effect, game_configuration,
--   game_demand_allocation, team_master, game_round_status
-- NOT included: team_decision, team_investment_selection, team_result
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. GAME MASTER
--    Product: Smartphone | 3 Rounds | Currency: INR
-- ---------------------------------------------------------------------
INSERT INTO game_master
(game_id, game_name, description, no_of_years, product_name, unit_of_measure, currency,
 starting_cash, starting_capacity, starting_inventory, starting_plant_value,
 capacity_cost, minimum_capacity, maximum_capacity, capacity_increment,
 demand, sales_price_tolerance_percent, status, created_by)
VALUES
(2,
 'Mobile Phone Market Challenge',
 '<p>Three companies — <strong>Alpha</strong>, <strong>Beta</strong>, and <strong>Gamma</strong> — compete to manufacture and sell smartphones in a rapidly growing mobile market over 3 rounds.</p>
<p>Each team must manage <em>production capacity</em>, <em>pricing strategy</em>, and <em>strategic investments</em> to maximise cumulative profit.</p>
<ul>
  <li>Market demand grows each year as smartphone adoption rises.</li>
  <li>Teams must plan capacity carefully — too little means lost sales, too much means idle cost.</li>
  <li>Smart investments in automation and supply chain can reduce costs and improve demand capture.</li>
</ul>
<p>The team with the highest cumulative operating profit at the end of Round 3 wins.</p>',
 3,
 'Smartphone',
 'Units',
 'INR',
 5000000.00,    -- starting_cash per team
 5000,          -- starting_capacity (units)
 500,           -- starting_inventory (units)
 10000000.00,   -- starting_plant_value
 50000000.00,   -- capacity_cost (total fixed cost for capacity)
 3000,          -- minimum_capacity
 9000,          -- maximum_capacity
 100,           -- capacity_increment step
 15000,         -- base demand
 5.00,          -- sales_price_tolerance_percent
 'Published',   -- status: published so teams can login immediately
 1);            -- created_by admin_id = 1

-- ---------------------------------------------------------------------
-- 2. GAME MARKET YEAR  (Annual Market Setup - demand & inflation per round)
-- ---------------------------------------------------------------------
INSERT INTO game_market_year (game_id, year_no, market_demand, inflation_percent, notes) VALUES
(2, 1, 15000, 5.00, 'Year 1: Market establishing. Teams set baseline pricing and capacity.'),
(2, 2, 17000, 5.00, 'Year 2: Demand grows as smartphone adoption rises. Competition on price intensifies.'),
(2, 3, 19000, 5.00, 'Year 3: Peak competition. Investments from earlier rounds start bearing results.');

-- ---------------------------------------------------------------------
-- 3. CAPACITY DRIVERS  (must total exactly 100%)
-- ---------------------------------------------------------------------
INSERT INTO capacity_driver (game_id, group_name, driver_name, cost_share_percent) VALUES
(2, 'Production',    'Labour Cost',                  40.00),
(2, 'Production',    'Material Cost',                35.00),
(2, 'Quality',       'Cost of Defects',              15.00),
(2, 'Maintenance',   'Preventive Maintenance Cost',  10.00);
-- Total = 100.00 ✓

-- ---------------------------------------------------------------------
-- 4. DEMAND DRIVERS  (must total exactly 100%)
-- ---------------------------------------------------------------------
INSERT INTO demand_driver (game_id, group_name, driver_name, demand_share_percent) VALUES
(2, 'Marketing',     'Advertising Reach',     30.00),
(2, 'Brand',         'Brand Reputation',      25.00),
(2, 'Availability',  'Warehouse Efficiency',  25.00),
(2, 'Insight',       'Demand Analytics',      20.00);
-- Total = 100.00 ✓

-- ---------------------------------------------------------------------
-- 5. INVESTMENT MASTER  (6 investments available to all teams)
--    NOTE: investment_id values will auto-increment from the last
--    inserted row.  We use LAST_INSERT_ID tracking in the effects below.
--    To keep effects simple, explicit IDs are set here.
-- ---------------------------------------------------------------------
INSERT INTO investment_master
(investment_id, game_id, investment_name, description,
 min_investment_value, max_investment_value, increment_value,
 effective_from, repeat_allowed, purchase_limit, display_order, active)
VALUES
(8,  2, 'Automation',
     'Invests in automated assembly lines to reduce labour cost and increase throughput.',
     500000, 3000000, 100000, 'Next Year', 0, 1, 1, 1),

(9,  2, 'Supplier Collaboration',
     'Deep partnerships with component suppliers to reduce material cost and lead times.',
     500000, 2500000, 100000, 'Immediate', 0, 1, 2, 1),

(10, 2, 'Six Sigma Quality',
     'Quality improvement programme to reduce defect rates and rework cost.',
     400000, 2000000, 100000, 'Immediate', 0, 1, 3, 1),

(11, 2, 'Warehouse Automation',
     'Robotics in distribution centres — improves warehouse efficiency (demand driver).',
     500000, 2500000, 100000, 'Immediate', 0, 1, 4, 1),

(12, 2, 'Demand Analytics Platform',
     'AI-powered demand forecasting to improve market capture and reduce over-production.',
     500000, 2000000, 100000, 'Immediate', 0, 1, 5, 1),

(13, 2, 'Preventive Maintenance',
     'Scheduled machine maintenance to eliminate unplanned breakdowns and reduce cost.',
     300000, 1500000, 100000, 'Immediate', 0, 1, 6, 1);

-- ---------------------------------------------------------------------
-- 6. INVESTMENT EFFECTS
--    Maps each investment to the capacity/demand driver it impacts.
--    driver_type = 'Capacity' → capacity_driver.driver_id
--    driver_type = 'Demand'   → demand_driver.driver_id
--
--    Capacity drivers for game 2 (auto-assigned IDs - check after insert):
--      Labour Cost (id=5), Material Cost (id=6),
--      Cost of Defects (id=7), Preventive Maintenance Cost (id=8)
--    Demand drivers for game 2:
--      Advertising Reach (id=5), Brand Reputation (id=6),
--      Warehouse Efficiency (id=7), Demand Analytics (id=8)
--
--    NOTE: The driver_id values below assume the capacity_driver rows
--    for game 2 get IDs 5–8 and demand_driver rows get IDs 5–8.
--    If your DB already has more rows, update these IDs accordingly.
--    Run: SELECT driver_id, driver_name FROM capacity_driver WHERE game_id=2;
--         SELECT driver_id, driver_name FROM demand_driver WHERE game_id=2;
-- ---------------------------------------------------------------------
INSERT INTO investment_effect
(investment_id, driver_type, driver_id, min_percent, max_percent, increment_percent, inject_message)
VALUES
-- Automation → reduces Labour Cost (Capacity)
(8, 'Capacity', 5, -15.00, -5.00, 1.00, 'Automation reduces Labour Cost'),

-- Supplier Collaboration → reduces Material Cost (Capacity)
(9, 'Capacity', 6, -12.00, -4.00, 1.00, 'Supplier Collaboration reduces Material Cost'),

-- Six Sigma Quality → reduces Cost of Defects (Capacity)
(10, 'Capacity', 7, -12.00, -3.00, 1.00, 'Six Sigma reduces Cost of Defects'),

-- Warehouse Automation → improves Warehouse Efficiency (Demand)
(11, 'Demand',  7, 5.00,  15.00, 1.00, 'Warehouse Automation improves Warehouse Efficiency'),

-- Demand Analytics Platform → improves Demand Analytics (Demand)
(12, 'Demand',  8, 5.00,  15.00, 1.00, 'Demand Analytics Platform improves demand forecasting'),

-- Preventive Maintenance → reduces Preventive Maintenance Cost (Capacity)
(13, 'Capacity', 8, -10.00, -3.00, 1.00, 'Preventive Maintenance reduces maintenance cost');

-- ---------------------------------------------------------------------
-- 7. GAME CONFIGURATION  (key-value rules for pricing, capacity, etc.)
-- ---------------------------------------------------------------------
INSERT INTO game_configuration
(game_id, configuration_category, configuration_key, configuration_value, value_type, description)
VALUES
(2, 'Capacity',          'Minimum Capacity',           '3000',           'Number',     'Lowest capacity a team may hold'),
(2, 'Capacity',          'Maximum Capacity',           '9000',           'Number',     'Highest capacity a team may hold'),
(2, 'Capacity',          'Capacity Reduction Allowed', 'No',             'Boolean',    'Teams cannot reduce capacity once built'),
(2, 'Pricing',           'Minimum Selling Price',      '1200',           'Amount',     'Lowest allowed selling price per unit'),
(2, 'Pricing',           'Maximum Selling Price',      '1500',           'Amount',     'Highest allowed selling price per unit'),
(2, 'Pricing',           'Price Increment',            '5',              'Amount',     'Step size for price entry'),
(2, 'Market Allocation', 'Allocation Basis',           'Min% -> DemandDriver% -> LowestPrice', 'Text', 'Order in which demand is allocated'),
(2, 'Dashboard',         'Show Prices',                'Yes',            'Boolean',    'Show competitor prices after each round'),
(2, 'Dashboard',         'Show Market Share',          'Yes',            'Boolean',    'Show market share after each round'),
(2, 'Winning Criteria',  'Winning Basis',              'Cumulative Profit', 'Text',   'Team with highest cumulative profit wins');

-- ---------------------------------------------------------------------
-- 8. GAME DEMAND ALLOCATION  (per-round allocation logic)
-- ---------------------------------------------------------------------
INSERT INTO game_demand_allocation (game_id, year_no, price_tick, min_percent, demand_driver_percent) VALUES
(2, 1, 1, 20.00, 30.00),
(2, 2, 1, 20.00, 30.00),
(2, 3, 1, 20.00, 30.00);

-- ---------------------------------------------------------------------
-- 9. TEAMS  (3 teams — login password: team123 for all)
--    password_hash below is the bcrypt of 'team123'
--    (same demo hash pattern as seed_data.sql — regenerate in production)
-- ---------------------------------------------------------------------
INSERT INTO team_master
(game_id, team_name, username, password_hash, opening_inventory, opening_budget, is_active)
VALUES
(2, 'Alpha', 'alpha', '$2y$10$exampleHashReplaceOnSetup000000000000000000000000000', 500, 5000000.00, 1),
(2, 'Beta',  'beta',  '$2y$10$exampleHashReplaceOnSetup000000000000000000000000000', 500, 5000000.00, 1),
(2, 'Gamma', 'gamma', '$2y$10$exampleHashReplaceOnSetup000000000000000000000000000', 500, 5000000.00, 1);

-- ---------------------------------------------------------------------
-- 10. GAME ROUND STATUS  (3 rounds — all 'Open' so admin can control them)
-- ---------------------------------------------------------------------
INSERT INTO game_round_status (game_id, year_no, status) VALUES
(2, 1, 'Open'),
(2, 2, 'Open'),
(2, 3, 'Open');

-- =====================================================================
-- AFTER RUNNING THIS SCRIPT:
--
-- 1. Verify driver IDs:
--    SELECT driver_id, driver_name FROM capacity_driver WHERE game_id=2;
--    SELECT driver_id, driver_name FROM demand_driver   WHERE game_id=2;
--    Update investment_effect rows above if the IDs differ.
--
-- 2. Assign real password hashes via Admin → Team Management → Assign Login,
--    or run:
--    UPDATE team_master SET password_hash = '$2y$10$...' WHERE game_id = 2;
--
-- 3. Teams can now login at:
--    http://localhost/mcqg_game/player/auth/login.php
--    Username: alpha / beta / gamma   Password: team123
--
-- 4. Admin controls rounds at:
--    http://localhost/mcqg_game/admin/round_control/round_status.php?game_id=2
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 1;
