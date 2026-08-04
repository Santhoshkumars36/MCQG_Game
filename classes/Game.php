<?php
/**
 * =====================================================================
 * MCQG - classes/Game.php
 * Maps to: game_master + game_market_year tables
 * Source: Admin/DB Design Doc, Entity 1 & 2 | MG19 Slides 3, 4, 7
 * =====================================================================
 */

if (!defined('MCQG_APP')) {
    require_once __DIR__ . '/../config/constants.php';
}

class Game
{
    public static function getById(int $gameId): ?array
    {
        return Database::getInstance()->fetchOne(
            'SELECT * FROM game_master WHERE game_id = :id', [':id' => $gameId]
        );
    }

    public static function getAll(): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT * FROM game_master ORDER BY created_on DESC'
        );
    }

    public static function getAllPublished(): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT * FROM game_master WHERE status = :status ORDER BY created_on DESC',
            [':status' => GAME_STATUS_PUBLISHED]
        );
    }

    /** Admin Wizard Step 1: Title & Case Study. Creates the game as Draft. */
    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $id = $db->insert(
            'INSERT INTO game_master
                (game_name, description, no_of_years, product_name, unit_of_measure, currency,
                 starting_cash, starting_capacity, starting_inventory, starting_plant_value, status)
             VALUES
                (:game_name, :description, :no_of_years, :product_name, :unit_of_measure, :currency,
                 :starting_cash, :starting_capacity, :starting_inventory, :starting_plant_value, :status)',
            [
                ':game_name'            => $data['game_name'],
                ':description'          => $data['description'] ?? '',   // rich-text Case Study (Slide 3)
                ':no_of_years'          => $data['no_of_years'] ?? DEFAULT_NO_OF_YEARS,
                ':product_name'         => $data['product_name'],
                ':unit_of_measure'      => $data['unit_of_measure'] ?? 'Units',
                ':currency'             => $data['currency'] ?? 'INR', // free text, not a dropdown (Slide 4)
                ':starting_cash'        => $data['starting_cash'] ?? 0,
                ':starting_capacity'    => $data['starting_capacity'] ?? 0,
                ':starting_inventory'   => $data['starting_inventory'] ?? 0,
                ':starting_plant_value' => $data['starting_plant_value'] ?? 0,
                ':status'               => GAME_STATUS_DRAFT,
            ]
        );
        Logger::activity("Game '{$data['game_name']}' created (ID $id).");
        return (int)$id;
    }

    /** Admin Wizard Step 2: Game Definition core configuration. */
    public static function updateDefinition(int $gameId, array $data): void
    {
        Database::getInstance()->execute(
            'UPDATE game_master SET
                demand = :demand,
                capacity_cost = :capacity_cost,
                minimum_capacity = :minimum_capacity,
                maximum_capacity = :maximum_capacity,
                capacity_increment = :capacity_increment,
                no_of_years = :no_of_years,
                currency = :currency
             WHERE game_id = :game_id',
            [
                ':demand'             => $data['demand'],
                ':capacity_cost'      => $data['capacity_cost'],
                ':minimum_capacity'   => $data['minimum_capacity'],
                ':maximum_capacity'   => $data['maximum_capacity'],
                ':capacity_increment' => $data['capacity_increment'],
                ':no_of_years'        => $data['no_of_years'],
                ':currency'           => $data['currency'],
                ':game_id'            => $gameId,
            ]
        );
        Logger::activity("Game #$gameId definition updated.");
    }

    /** Admin Wizard Step 6: sales price tolerance %. */
    public static function updateSalesPriceTolerance(int $gameId, float $tolerancePercent): void
    {
        Database::getInstance()->execute(
            'UPDATE game_master SET sales_price_tolerance_percent = :t WHERE game_id = :id',
            [':t' => $tolerancePercent, ':id' => $gameId]
        );
    }

    /**
     * Admin Wizard Step 7: Publish the game. Cannot publish unless the
     * Capacity Driver and Demand Driver totals are both exactly 100%
     * (Slides 5 & 7 validation rule), and every year has a market row.
     * Returns [true, null] on success, or [false, 'reason'] on failure.
     */
    public static function publish(int $gameId): array
    {
        $game = self::getById($gameId);
        if (!$game) {
            return [false, 'Game not found.'];
        }

        $capTotal = CapacityDriver::getTotalCostSharePercent($gameId);
        if (!Validator::percentageTotalsEqual100([$capTotal])) {
            return [false, "Capacity Driver totals must equal 100% (currently {$capTotal}%)."];
        }

        $demandTotal = DemandDriver::getTotalDemandSharePercent($gameId);
        if (!Validator::percentageTotalsEqual100([$demandTotal])) {
            return [false, "Demand Driver totals must equal 100% (currently {$demandTotal}%)."];
        }

        $yearsConfigured = count(self::getMarketYears($gameId));
        if ($yearsConfigured < (int)$game['no_of_years']) {
            return [false, "Only $yearsConfigured of {$game['no_of_years']} annual market years are configured."];
        }

        Database::getInstance()->execute(
            'UPDATE game_master SET status = :status WHERE game_id = :id',
            [':status' => GAME_STATUS_PUBLISHED, ':id' => $gameId]
        );
        Logger::activity("Game #$gameId published.");
        return [true, null];
    }

    public static function markCompleted(int $gameId): void
    {
        Database::getInstance()->execute(
            'UPDATE game_master SET status = :status WHERE game_id = :id',
            [':status' => GAME_STATUS_COMPLETED, ':id' => $gameId]
        );
        Logger::activity("Game #$gameId marked completed.");
    }

    /** Auto-calculated, read-only unit cost (Slide 4: Capacity cost / Capacity). */
    public static function getUnitCost(array $game): float
    {
        return (float)($game['unit_cost'] ?? 0); // generated column in DB, kept here for convenience
    }

    // -------------------------------------------------------------
    // ANNUAL MARKET SETUP  (game_market_year)
    // -------------------------------------------------------------

    public static function getMarketYears(int $gameId): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT * FROM game_market_year WHERE game_id = :id ORDER BY year_no ASC',
            [':id' => $gameId]
        );
    }

    public static function getMarketYear(int $gameId, int $yearNo): ?array
    {
        return Database::getInstance()->fetchOne(
            'SELECT * FROM game_market_year WHERE game_id = :id AND year_no = :year',
            [':id' => $gameId, ':year' => $yearNo]
        );
    }

    /** Insert or update the demand/inflation figures for one year (upsert). */
    public static function saveMarketYear(int $gameId, int $yearNo, float $marketDemand, float $inflationPercent, string $notes = ''): void
    {
        $db = Database::getInstance();
        $existing = self::getMarketYear($gameId, $yearNo);
        if ($existing) {
            $db->execute(
                'UPDATE game_market_year SET market_demand = :d, inflation_percent = :i, notes = :n
                 WHERE market_year_id = :id',
                [':d' => $marketDemand, ':i' => $inflationPercent, ':n' => $notes, ':id' => $existing['market_year_id']]
            );
        } else {
            $db->insert(
                'INSERT INTO game_market_year (game_id, year_no, market_demand, inflation_percent, notes)
                 VALUES (:g, :y, :d, :i, :n)',
                [':g' => $gameId, ':y' => $yearNo, ':d' => $marketDemand, ':i' => $inflationPercent, ':n' => $notes]
            );
        }
    }

    /** True while the current date is within a game whose status = Published. */
    public static function isPlayable(array $game): bool
    {
        return $game['status'] === GAME_STATUS_PUBLISHED;
    }
}
