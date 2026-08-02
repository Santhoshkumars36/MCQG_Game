-- =====================================================================
-- MCQG (Market Competition Quantitative Game) - Seed / Demo Data
-- File: database/seed_data.sql
-- Run AFTER mcqg_schema.sql
-- Data source: "Sample_game_narrative_with_data" document
--              (3 teams - Falcon, Titan, Nova - across 3 rounds)
-- NOTE: Where the source narrative did not give an exact figure
--       (e.g. Round 2/3 production for some teams), a consistent
--       illustrative value has been used so profit/revenue totals
--       still reconcile with the cumulative figures stated in the
--       narrative. These rows are for local testing/demo only.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Admin user (default demo login: admin / admin123 - CHANGE IN PROD)
-- ---------------------------------------------------------------------
INSERT INTO admin_user (username, password_hash, full_name, email, is_active)
VALUES ('admin', '$2y$10$exampleHashReplaceOnSetup000000000000000000000000000', 'Game Administrator', 'admin@mcqg.local', 1);

-- ---------------------------------------------------------------------
-- 2. Game Master - one demo game: "Smart Water Purifiers"
-- ---------------------------------------------------------------------
INSERT INTO game_master
(game_id, game_name, description, no_of_years, product_name, unit_of_measure, currency,
 starting_cash, starting_capacity, starting_inventory, starting_plant_value,
 capacity_cost, minimum_capacity, maximum_capacity, capacity_increment,
 demand, sales_price_tolerance_percent, status, created_by)
VALUES
(1, 'Smart Water Purifier Challenge',
 'Three companies - Falcon, Titan and Nova - compete to manufacture and sell Smart Water Purifiers in a shared market over 3 rounds.',
 3, 'Smart Water Purifier', 'Units', 'INR',
 5000000.00, 6000, 600, 12000000.00,
 60000000.00, 4000, 10000, 100,
 18000, 5.00, 'Published', 1);

-- ---------------------------------------------------------------------
-- 3. Annual Market Setup - demand & inflation per round
-- ---------------------------------------------------------------------
INSERT INTO game_market_year (game_id, year_no, market_demand, inflation_percent, notes) VALUES
(1, 1, 18000, 5.00, 'Market is stable. Titan competes on price, Falcon on operations, Nova on supply chain efficiency.'),
(1, 2, 20000, 5.00, 'Demand grows as the product gains adoption.'),
(1, 3, 22000, 5.00, 'Demand continues to grow; competition intensifies.');

-- ---------------------------------------------------------------------
-- 4. Capacity Drivers (must total 100% cost share)
-- ---------------------------------------------------------------------
INSERT INTO capacity_driver (game_id, group_name, driver_name, cost_share_percent) VALUES
(1, 'Production', 'Labour Cost', 40.00),
(1, 'Production', 'Material Cost', 35.00),
(1, 'Quality', 'Cost of Errors', 15.00),
(1, 'Maintenance', 'Preventive Maintenance Cost', 10.00);

-- ---------------------------------------------------------------------
-- 5. Demand Drivers (must total 100% demand share)
-- ---------------------------------------------------------------------
INSERT INTO demand_driver (game_id, group_name, driver_name, demand_share_percent) VALUES
(1, 'Marketing', 'Advertising Reach', 30.00),
(1, 'Trust', 'Brand Reputation', 25.00),
(1, 'Availability', 'Warehouse Efficiency', 25.00),
(1, 'Insight', 'Demand Analytics', 20.00);

-- ---------------------------------------------------------------------
-- 6. Investment Catalogue
--    NOTE: "Capacity Expansion" (row 7) is included because it appears
--    in the sample narrative's Round 3 data for Falcon, even though it
--    was not part of the original 6-item catalogue - flagged during
--    analysis as an inconsistency to confirm with the source.
-- ---------------------------------------------------------------------
INSERT INTO investment_master
(investment_id, game_id, investment_name, description, min_investment_value, max_investment_value, increment_value, effective_from, repeat_allowed, purchase_limit, display_order, active)
VALUES
(1, 1, 'Automation', 'Reduces labour cost and raises effective capacity.', 500000, 3000000, 100000, 'Next Year', 0, 1, 1, 1),
(2, 1, 'Supplier Collaboration', 'Improves material cost and reliability.', 500000, 2500000, 100000, 'Immediate', 0, 1, 2, 1),
(3, 1, 'Lean Manufacturing', 'Reduces waste and production cost.', 500000, 2500000, 100000, 'Immediate', 0, 1, 3, 1),
(4, 1, 'Warehouse Automation', 'Improves warehouse efficiency (demand driver).', 500000, 2500000, 100000, 'Immediate', 0, 1, 4, 1),
(5, 1, 'Demand Analytics', 'Improves forecasting and demand capture.', 500000, 2000000, 100000, 'Immediate', 0, 1, 5, 1),
(6, 1, 'Preventive Maintenance', 'Reduces breakdown-related cost.', 300000, 1500000, 100000, 'Immediate', 0, 1, 6, 1),
(7, 1, 'Capacity Expansion', 'Increases maximum available capacity for future rounds.', 1000000, 5000000, 200000, 'Next Year', 1, 3, 7, 1);

-- ---------------------------------------------------------------------
-- 7. Investment Effects - how each investment moves a driver
-- ---------------------------------------------------------------------
INSERT INTO investment_effect (investment_id, driver_type, driver_id, min_percent, max_percent, increment_percent, inject_message) VALUES
(1, 'Capacity', 1, -15.00, -5.00, 1.00, 'Automation reduces Labour Cost'),
(1, 'Capacity', 3, 5.00, 10.00, 1.00, 'Automation improves effective capacity via fewer errors'),
(2, 'Capacity', 2, -12.00, -4.00, 1.00, 'Supplier Collaboration reduces Material Cost'),
(3, 'Capacity', 1, -10.00, -3.00, 1.00, 'Lean Manufacturing reduces Labour Cost'),
(3, 'Capacity', 3, -8.00, -2.00, 1.00, 'Lean Manufacturing reduces Cost of Errors'),
(4, 'Demand', 3, 5.00, 15.00, 1.00, 'Warehouse Automation improves Warehouse Efficiency'),
(5, 'Demand', 4, 5.00, 15.00, 1.00, 'Demand Analytics improves forecasting accuracy'),
(6, 'Capacity', 4, -10.00, -3.00, 1.00, 'Preventive Maintenance reduces maintenance cost'),
(7, 'Capacity', 1, -3.00, 3.00, 1.00, 'Capacity Expansion raises maximum production capability');

-- ---------------------------------------------------------------------
-- 8. Game Configuration - general rules
-- ---------------------------------------------------------------------
INSERT INTO game_configuration (game_id, configuration_category, configuration_key, configuration_value, value_type, description) VALUES
(1, 'Capacity', 'Minimum Capacity', '4000', 'Number', 'Lowest capacity a team may hold'),
(1, 'Capacity', 'Maximum Capacity', '10000', 'Number', 'Highest capacity a team may hold'),
(1, 'Capacity', 'Capacity Reduction Allowed', 'No', 'Boolean', 'Teams cannot reduce capacity once built'),
(1, 'Pricing', 'Minimum Selling Price', '1100', 'Amount', 'Lowest allowed selling price'),
(1, 'Pricing', 'Maximum Selling Price', '1350', 'Amount', 'Highest allowed selling price'),
(1, 'Pricing', 'Price Increment', '5', 'Amount', 'Step size for price entry'),
(1, 'Market Allocation', 'Allocation Basis', 'Min% -> DemandDriver% -> LowestPrice', 'Text', 'Order in which demand is allocated'),
(1, 'Dashboard', 'Show Prices', 'Yes', 'Boolean', 'Show competitor prices after each round'),
(1, 'Dashboard', 'Show Market Share', 'Yes', 'Boolean', 'Show market share after each round'),
(1, 'Winning Criteria', 'Winning Basis', 'Cumulative Profit', 'Text', 'Team with highest cumulative profit wins');

-- ---------------------------------------------------------------------
-- 9. Demand Allocation Logic - one row per round
-- ---------------------------------------------------------------------
INSERT INTO game_demand_allocation (game_id, year_no, price_tick, min_percent, demand_driver_percent) VALUES
(1, 1, 1, 20.00, 30.00),
(1, 2, 1, 20.00, 30.00),
(1, 3, 1, 20.00, 30.00);

-- ---------------------------------------------------------------------
-- 10. Teams (demo login: username / team123 - CHANGE IN PROD)
-- ---------------------------------------------------------------------
INSERT INTO team_master (team_id, game_id, team_name, username, password_hash, opening_inventory, opening_budget, is_active) VALUES
(1, 1, 'Falcon', 'falcon', '$2y$10$exampleHashReplaceOnSetup000000000000000000000000000', 600, 5000000.00, 1),
(2, 1, 'Titan',  'titan',  '$2y$10$exampleHashReplaceOnSetup000000000000000000000000000', 600, 5000000.00, 1),
(3, 1, 'Nova',   'nova',   '$2y$10$exampleHashReplaceOnSetup000000000000000000000000000', 600, 5000000.00, 1);

-- ---------------------------------------------------------------------
-- 11. Team Decisions - Round 1, 2, 3
-- ---------------------------------------------------------------------
INSERT INTO team_decision (decision_id, team_id, game_id, year_no, capacity_built, production_qty, selling_price, status, submitted_on) VALUES
-- Round 1
(1, 1, 1, 1, 6000, 5800, 1220.00, 'Submitted', '2026-01-10 10:00:00'),
(2, 2, 1, 1, 6000, 6200, 1180.00, 'Submitted', '2026-01-10 10:05:00'),
(3, 3, 1, 1, 6000, 5400, 1260.00, 'Submitted', '2026-01-10 10:08:00'),
-- Round 2
(4, 1, 1, 2, 6000, 6000, 1210.00, 'Submitted', '2026-02-10 10:00:00'),
(5, 2, 1, 2, 6000, 6800, 1190.00, 'Submitted', '2026-02-10 10:05:00'),
(6, 3, 1, 2, 6000, 5900, 1240.00, 'Submitted', '2026-02-10 10:08:00'),
-- Round 3
(7, 1, 1, 3, 6500, 6200, 1200.00, 'Submitted', '2026-03-10 10:00:00'),
(8, 2, 1, 3, 6000, 7000, 1185.00, 'Submitted', '2026-03-10 10:05:00'),
(9, 3, 1, 3, 6000, 6700, 1235.00, 'Submitted', '2026-03-10 10:08:00');

-- ---------------------------------------------------------------------
-- 12. Team Investment Selections (per round)
-- ---------------------------------------------------------------------
INSERT INTO team_investment_selection (decision_id, investment_id, invested_value, invested_percent) VALUES
-- Round 1
(1, 1, 1500000, 10.00),   -- Falcon: Automation
(1, 2, 1000000, 8.00),    -- Falcon: Supplier Collaboration
(2, 5, 1200000, 10.00),   -- Titan: Demand Analytics
(2, 3, 1000000, 6.00),    -- Titan: Lean Manufacturing
(3, 4, 1300000, 10.00),   -- Nova: Warehouse Automation
(3, 6, 800000, 8.00),     -- Nova: Preventive Maintenance
-- Round 2
(4, 3, 1200000, 8.00),    -- Falcon: Lean Manufacturing
(5, 1, 1500000, 10.00),   -- Titan: Automation
(6, 2, 1200000, 8.00),    -- Nova: Supplier Collaboration
-- Round 3
(7, 7, 2000000, 12.00),   -- Falcon: Capacity Expansion
(8, 3, 1300000, 8.00),    -- Titan: Lean Manufacturing
(9, 4, 1400000, 10.00);   -- Nova: Warehouse Automation

-- ---------------------------------------------------------------------
-- 13. Team Results (as calculated by the engine each round)
--     Revenue = units_sold x selling_price (verified from source doc)
--     Closing Inventory = opening + produced - sold
--     Service Level and Operating Profit: taken from the source narrative
--     where stated; still an open formula gap (see prior analysis notes)
-- ---------------------------------------------------------------------
INSERT INTO team_result
(team_id, game_id, year_no, units_sold, closing_inventory, capacity_utilization_percent, service_level_percent, revenue, variable_cost, fixed_cost, investment_cost, operating_profit, cash_position) VALUES
-- Round 1
(1, 1, 1, 5700, 700, 96.67, 95.00, 6954000.00, 3500000.00, 900000.00, 2500000.00,  1420000.00, 6420000.00),
(2, 1, 1, 6200, 0,   100.00, 100.00, 7316000.00, 3900000.00, 900000.00, 2200000.00, 1280000.00, 6280000.00),
(3, 1, 1, 5000, 1000, 90.00, 83.00, 6300000.00, 3200000.00, 900000.00, 2100000.00,  1550000.00, 6550000.00),
-- Round 2
(1, 1, 2, 5900, 800, 100.00, 98.00, 7139000.00, 3600000.00, 900000.00, 1200000.00,  1680000.00, 8100000.00),
(2, 1, 2, 6800, 0,   100.00, 98.00, 8092000.00, 4400000.00, 900000.00, 1500000.00,  1500000.00, 7780000.00),
(3, 1, 2, 5900, 1000, 98.33, 92.00, 7316000.00, 3700000.00, 900000.00, 1200000.00,  1830000.00, 8380000.00),
-- Round 3
(1, 1, 3, 6100, 900, 95.38, 96.00, 7320000.00, 3800000.00, 900000.00, 2000000.00,  1940000.00, 10040000.00),
(2, 1, 3, 7000, 0,   100.00, 99.00, 8295000.00, 4600000.00, 900000.00, 1300000.00,  1800000.00, 9580000.00),
(3, 1, 3, 6700, 1000, 111.67, 94.00, 8274500.00, 4200000.00, 900000.00, 1400000.00, 2080000.00, 10460000.00);

-- ---------------------------------------------------------------------
-- 14. Round Status - all 3 rounds processed for this demo game
-- ---------------------------------------------------------------------
INSERT INTO game_round_status (game_id, year_no, status, processed_on, processed_by) VALUES
(1, 1, 'Processed', '2026-01-10 18:00:00', 1),
(1, 2, 'Processed', '2026-02-10 18:00:00', 1),
(1, 3, 'Processed', '2026-03-10 18:00:00', 1);

-- =====================================================================
-- END OF SEED DATA
-- =====================================================================
