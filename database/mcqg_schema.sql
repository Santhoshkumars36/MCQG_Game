-- =====================================================================
-- MCQG (Market Competition Quantitative Game) - Database Schema
-- File: database/mcqg_schema.sql
-- Engine: MySQL / MariaDB (XAMPP)
-- Built from: Business Logic doc, Admin/DB Design doc, MG19 screen deck,
--             Sample Game Narrative, and locked folder structure.
-- Updated: Soft delete (isDeleted, deletedOn) & audit (createdOn) added to every table
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- ---------------------------------------------------------------------
-- 1. ADMIN_USER
-- Purpose: login accounts for the game administrator (admin/auth/login.php)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_user (
    admin_id        INT AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(100) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    full_name       VARCHAR(150) NULL,
    email           VARCHAR(150) NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    isDeleted       BIT NOT NULL DEFAULT 0,
    deletedOn       DATETIME NULL,
    createdOn       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_on      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 2. GAME_MASTER
-- Purpose: root/master record for one Quantity Game template.
-- Source: Admin/DB Design Doc, Entity 1 | MG19 Slides 3 & 4
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS game_master (
    game_id             INT AUTO_INCREMENT PRIMARY KEY,
    game_name           VARCHAR(150) NOT NULL,
    description         TEXT NULL,                     -- Case Study (rich text)
    game_image          VARCHAR(255) NULL DEFAULT NULL, -- Game thumbnail / logo
    no_of_years         INT NOT NULL DEFAULT 5,
    product_name        VARCHAR(100) NOT NULL,
    unit_of_measure     VARCHAR(50)  NOT NULL DEFAULT 'Units',
    currency            VARCHAR(20)  NOT NULL DEFAULT 'INR',   -- free text, not a dropdown
    starting_cash       DECIMAL(18,2) NOT NULL DEFAULT 0,
    starting_capacity   INT NOT NULL DEFAULT 0,
    starting_inventory  INT NOT NULL DEFAULT 0,
    starting_plant_value DECIMAL(18,2) NOT NULL DEFAULT 0,
    capacity_cost       DECIMAL(18,2) NOT NULL DEFAULT 0,      -- Slide 4
    minimum_capacity    INT NOT NULL DEFAULT 0,
    maximum_capacity    INT NOT NULL DEFAULT 0,
    capacity_increment  INT NOT NULL DEFAULT 1,
    unit_cost           DECIMAL(18,4) GENERATED ALWAYS AS
                         (CASE WHEN starting_capacity > 0
                               THEN capacity_cost / starting_capacity
                               ELSE 0 END) STORED,             -- read-only, auto-calculated
    demand              INT NOT NULL DEFAULT 0,                -- core configuration numeric field
    tolerance_percent   DECIMAL(6,2) NOT NULL DEFAULT 20.00,   -- Slide 8 Tolerance %
    sales_price_tolerance_percent DECIMAL(6,2) NOT NULL DEFAULT 20.00,  -- Slide 7
    status              ENUM('Draft','Published','Completed') NOT NULL DEFAULT 'Draft',
    created_by          INT NULL,
    isDeleted           BIT NOT NULL DEFAULT 0,
    deletedOn           DATETIME NULL,
    createdOn           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_on          DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_game_created_by FOREIGN KEY (created_by) REFERENCES admin_user(admin_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 3. GAME_MARKET_YEAR
-- Purpose: per-year market demand & inflation (Annual Market Setup)
-- Source: Admin/DB Design Doc, Entity 2
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS game_market_year (
    market_year_id   INT AUTO_INCREMENT PRIMARY KEY,
    game_id          INT NOT NULL,
    year_no          INT NOT NULL,
    market_demand    INT NOT NULL DEFAULT 0,
    inflation_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
    notes            TEXT NULL,
    isDeleted        BIT NOT NULL DEFAULT 0,
    deletedOn        DATETIME NULL,
    createdOn        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_market_year_game FOREIGN KEY (game_id) REFERENCES game_master(game_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT uq_market_year UNIQUE (game_id, year_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 4. CAPACITY_DRIVER
-- Purpose: grouped cost-related drivers (renamed from "Financial Parameter")
-- Source: Admin/DB Design Doc, Entity 3 | MG19 Slide 5
-- Rule: SUM(cost_share_percent) per game must equal 100 (validated in app layer)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS capacity_driver (
    driver_id          INT AUTO_INCREMENT PRIMARY KEY,
    game_id            INT NOT NULL,
    group_name         VARCHAR(120) NOT NULL,
    driver_name        VARCHAR(120) NOT NULL,
    cost_share_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
    cost_value         DECIMAL(18,4) NULL,   -- = game_master.capacity_cost * cost_share_percent (calculated in app/engine)
    isDeleted          BIT NOT NULL DEFAULT 0,
    deletedOn          DATETIME NULL,
    createdOn          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_capacity_driver_game FOREIGN KEY (game_id) REFERENCES game_master(game_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 5. DEMAND_DRIVER
-- Purpose: grouped demand-related drivers (new section added in Slide 5)
-- Rule: SUM(demand_share_percent) per game must equal 100 (validated in app layer)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS demand_driver (
    driver_id            INT AUTO_INCREMENT PRIMARY KEY,
    game_id              INT NOT NULL,
    group_name           VARCHAR(120) NOT NULL,
    driver_name          VARCHAR(120) NOT NULL,
    demand_share_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
    isDeleted            BIT NOT NULL DEFAULT 0,
    deletedOn            DATETIME NULL,
    createdOn            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_demand_driver_game FOREIGN KEY (game_id) REFERENCES game_master(game_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 6. INVESTMENT_MASTER
-- Purpose: catalogue of investments teams can buy (no logic embedded)
-- Source: Admin/DB Design Doc, Entity 4 | MG19 Slide 6
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS investment_master (
    investment_id      INT AUTO_INCREMENT PRIMARY KEY,
    game_id            INT NOT NULL,
    investment_name    VARCHAR(150) NOT NULL,
    description        TEXT NULL,
    min_investment_value DECIMAL(18,2) NOT NULL DEFAULT 0,
    max_investment_value DECIMAL(18,2) NOT NULL DEFAULT 0,
    increment_value    DECIMAL(18,2) NOT NULL DEFAULT 1,
    effective_from     ENUM('Immediate','Next Year') NOT NULL DEFAULT 'Immediate',
    repeat_allowed     TINYINT(1) NOT NULL DEFAULT 0,
    purchase_limit     INT NOT NULL DEFAULT 1,
    display_order      INT NOT NULL DEFAULT 0,
    active             TINYINT(1) NOT NULL DEFAULT 1,
    isDeleted          BIT NOT NULL DEFAULT 0,
    deletedOn          DATETIME NULL,
    createdOn          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_investment_game FOREIGN KEY (game_id) REFERENCES game_master(game_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 7. INVESTMENT_EFFECT
-- Purpose: many-to-many link -> how an investment changes a Capacity/Demand driver
-- Source: Admin/DB Design Doc, Entity 5 | MG19 Slide 6
-- driver_type + driver_id together point to either capacity_driver or demand_driver
-- (kept as a polymorphic reference to avoid two near-identical tables)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS investment_effect (
    effect_id          INT AUTO_INCREMENT PRIMARY KEY,
    investment_id       INT NOT NULL,
    driver_type          ENUM('Capacity','Demand') NOT NULL,
    driver_id            INT NOT NULL,               -- FK to capacity_driver.driver_id OR demand_driver.driver_id, based on driver_type
    min_percent           DECIMAL(6,2) NOT NULL DEFAULT 0,   -- can be negative, e.g. -1.1
    max_percent           DECIMAL(6,2) NOT NULL DEFAULT 0,   -- can be positive, e.g. +1.1
    increment_percent     DECIMAL(6,2) NOT NULL DEFAULT 0,
    inject_message        VARCHAR(255) NULL,          -- auto-generated, not typed by admin
    isDeleted             BIT NOT NULL DEFAULT 0,
    deletedOn             DATETIME NULL,
    createdOn             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_effect_investment FOREIGN KEY (investment_id) REFERENCES investment_master(investment_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 8. GAME_CONFIGURATION
-- Purpose: generic key-value rule store (Capacity/Pricing/Market Allocation/
--          Dashboard/Winning Criteria rules) - no schema change needed for new rules
-- Source: Admin/DB Design Doc, Entity 6 | MG19 Slide 7
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS game_configuration (
    configuration_id       INT AUTO_INCREMENT PRIMARY KEY,
    game_id                INT NOT NULL,
    configuration_category VARCHAR(50) NOT NULL,   -- e.g. Capacity, Pricing, Market Allocation, Dashboard, Winning Criteria
    configuration_key      VARCHAR(100) NOT NULL,
    configuration_value    VARCHAR(250) NOT NULL,
    value_type             ENUM('Number','Amount','Percentage','Text','Boolean') NOT NULL DEFAULT 'Text',
    description             TEXT NULL,
    isDeleted              BIT NOT NULL DEFAULT 0,
    deletedOn              DATETIME NULL,
    createdOn              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_configuration_game FOREIGN KEY (game_id) REFERENCES game_master(game_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 9. GAME_DEMAND_ALLOCATION
-- Purpose: per-period demand allocation logic table (Slide 7):
--          Price tick, Min %, Demand driver % - one row per year
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS game_demand_allocation (
    allocation_id        INT AUTO_INCREMENT PRIMARY KEY,
    game_id               INT NOT NULL,
    year_no                INT NOT NULL,
    price_tick             TINYINT(1) NOT NULL DEFAULT 1,   -- ticked ON by default
    min_percent             DECIMAL(6,2) NOT NULL DEFAULT 0,
    demand_driver_percent   DECIMAL(6,2) NOT NULL DEFAULT 0,
    isDeleted              BIT NOT NULL DEFAULT 0,
    deletedOn              DATETIME NULL,
    createdOn              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_allocation_game FOREIGN KEY (game_id) REFERENCES game_master(game_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT uq_allocation_year UNIQUE (game_id, year_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 10. TEAM_MASTER
-- Purpose: teams/players competing in a game (closes the multiplayer
--          setup gap identified during analysis)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS team_master (
    team_id           INT AUTO_INCREMENT PRIMARY KEY,
    game_id            INT NOT NULL,
    team_name          VARCHAR(150) NOT NULL,
    username           VARCHAR(100) NOT NULL,
    password_hash      VARCHAR(255) NOT NULL,
    opening_inventory  INT NOT NULL DEFAULT 0,
    opening_budget     DECIMAL(18,2) NOT NULL DEFAULT 0,
    is_active          TINYINT(1) NOT NULL DEFAULT 1,
    isDeleted          BIT NOT NULL DEFAULT 0,
    deletedOn          DATETIME NULL,
    createdOn          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_team_game FOREIGN KEY (game_id) REFERENCES game_master(game_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT uq_team_game_teamname UNIQUE (game_id, team_name),
    CONSTRAINT uq_team_game_username UNIQUE (game_id, username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 11. TEAM_DECISION
-- Purpose: one row per team per year = capacity, production, price submitted
-- Source: Player Screens 2 & 3 (build_production.php, demand_economics.php)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS team_decision (
    decision_id        INT AUTO_INCREMENT PRIMARY KEY,
    team_id             INT NOT NULL,
    game_id              INT NOT NULL,
    year_no               INT NOT NULL,
    capacity_built        INT NOT NULL DEFAULT 0,     -- capacity planning decision
    production_qty        INT NOT NULL DEFAULT 0,
    selling_price          DECIMAL(18,2) NOT NULL DEFAULT 0,
    status                  ENUM('Draft','Submitted') NOT NULL DEFAULT 'Draft',
    submitted_on            DATETIME NULL,
    isDeleted               BIT NOT NULL DEFAULT 0,
    deletedOn               DATETIME NULL,
    createdOn               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_decision_team FOREIGN KEY (team_id) REFERENCES team_master(team_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_decision_game FOREIGN KEY (game_id) REFERENCES game_master(game_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT uq_decision_team_year UNIQUE (team_id, year_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 12. TEAM_INVESTMENT_SELECTION
-- Purpose: which investments a team bought in a given decision/year,
--          and how much they invested (feeds engine/investment/*)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS team_investment_selection (
    selection_id        INT AUTO_INCREMENT PRIMARY KEY,
    decision_id           INT NOT NULL,
    investment_id           INT NOT NULL,
    invested_value           DECIMAL(18,2) NOT NULL DEFAULT 0,
    invested_percent          DECIMAL(6,2) NOT NULL DEFAULT 0,  -- share within min-max range chosen
    isDeleted                 BIT NOT NULL DEFAULT 0,
    deletedOn                 DATETIME NULL,
    createdOn                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_selection_decision FOREIGN KEY (decision_id) REFERENCES team_decision(decision_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_selection_investment FOREIGN KEY (investment_id) REFERENCES investment_master(investment_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 13. TEAM_RESULT
-- Purpose: calculated results per team per year, shown on Report Screen 4
-- Source: MG19 Slides 12-13
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS team_result (
    result_id            INT AUTO_INCREMENT PRIMARY KEY,
    team_id                INT NOT NULL,
    game_id                  INT NOT NULL,
    year_no                    INT NOT NULL,
    units_sold                  INT NOT NULL DEFAULT 0,
    closing_inventory             INT NOT NULL DEFAULT 0,      -- opening + produced - sold
    capacity_utilization_percent    DECIMAL(6,2) NOT NULL DEFAULT 0,
    service_level_percent            DECIMAL(6,2) NULL,        -- flagged gap: formula not defined in source docs
    revenue                            DECIMAL(18,2) NOT NULL DEFAULT 0,
    variable_cost                       DECIMAL(18,2) NOT NULL DEFAULT 0,
    fixed_cost                           DECIMAL(18,2) NOT NULL DEFAULT 0,
    investment_cost                       DECIMAL(18,2) NOT NULL DEFAULT 0,
    operating_profit                       DECIMAL(18,2) NOT NULL DEFAULT 0,
    cash_position                           DECIMAL(18,2) NOT NULL DEFAULT 0,
    isDeleted                               BIT NOT NULL DEFAULT 0,
    deletedOn                               DATETIME NULL,
    createdOn                               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_result_team FOREIGN KEY (team_id) REFERENCES team_master(team_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_result_game FOREIGN KEY (game_id) REFERENCES game_master(game_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT uq_result_team_year UNIQUE (team_id, year_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 14. GAME_ROUND_STATUS
-- Purpose: admin control of round progress (Slide 15) -
--          results only processed once every team has submitted
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS game_round_status (
    round_status_id      INT AUTO_INCREMENT PRIMARY KEY,
    game_id                INT NOT NULL,
    year_no                  INT NOT NULL,
    status                    ENUM('Open','AllSubmitted','Processed') NOT NULL DEFAULT 'Open',
    processed_on               DATETIME NULL,
    processed_by                INT NULL,
    isDeleted                   BIT NOT NULL DEFAULT 0,
    deletedOn                   DATETIME NULL,
    createdOn                   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_round_game FOREIGN KEY (game_id) REFERENCES game_master(game_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_round_processed_by FOREIGN KEY (processed_by) REFERENCES admin_user(admin_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT uq_round_year UNIQUE (game_id, year_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- END OF SCHEMA
-- =====================================================================
