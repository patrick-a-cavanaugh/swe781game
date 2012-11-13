<?php

namespace Game\Service;

use Game\Util\User;
use Monolog\Logger;
use Doctrine\DBAL\Connection;
use Game\Model\CargoTransaction;
use Game\Model\PlayerShipType;

/**
 * TODO: Document me!
 */
class CargoService
{

    /** @var \Doctrine\DBAL\Connection */
    private $db;

    /** @var \Monolog\Logger */
    private $logger;

    /** @var User */
    private $currentUser;

    /** @var GameService */
    private $gameService;

    /** @var PlayerService */
    private $playerService;

    /** @var UniverseService */
    private $universeService;

    function __construct(Connection $db, Logger $logger, User $currentUser, GameService $gameService,
                         PlayerService $playerService, UniverseService $universeService)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->currentUser = $currentUser;
        $this->gameService = $gameService;
        $this->playerService = $playerService;
        $this->universeService = $universeService;
    }

    /**
     * @param $planetId
     * @return array
     */
    public function getCargoForPlanet($planetId)
    {
        // A player may only see the cargo for his current location.
        $planet = $this->universeService->fetchPlanetById($planetId);
        if (empty($planet)) {
            trigger_error('Unable to find a planet with this id', E_USER_ERROR);
        }
        $player = $this->playerService->getCurrentUsersPlayerForGameId($planet['game_id']);
        if ($player['location_id'] !== intval($planetId)) {
            trigger_error('Player must be located at a planet to view its cargo', E_USER_ERROR);
        }

        $sql = <<<SQL
SELECT pc.id, pc.cargo_type_id, ct.name, pc.buy_price, pc.sell_price, pc.planet_id
FROM planet p
INNER JOIN planet_cargo pc ON pc.planet_id = p.id
INNER JOIN cargo_type ct ON ct.id = pc.cargo_type_id
WHERE p.id = ?
SQL;

        /** @var $stmt \Doctrine\DBAL\Driver\Statement */
        $stmt = $this->db->executeQuery($sql,
            [$planetId],
            [\PDO::PARAM_INT]
        );

        $cargoRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $cargoRows;
    }

    public function executeTransaction(CargoTransaction $proposedTransaction)
    {
        $this->db->beginTransaction();
        try {
            // Get an exclusive lock on the player's credit balance.
            $player = $this->db->fetchAssoc(<<<SQL
SELECT p.location_id, p.money
FROM player p
WHERE p.id = :playerId AND p.user_id = :userId
FOR UPDATE
SQL
                , ['playerId' => intval($proposedTransaction->playerId), 'userId' => $this->currentUser->getId()]
            );
            if (is_null($player)) {
                throw new CargoServiceException(['playerId' => ['not found or not valid for user id']]);
            }
            $cargoPrices = $this->db->fetchAssoc(<<<SQL
SELECT pc.buy_price, pc.sell_price
FROM planet_cargo pc
WHERE pc.cargo_type_id = :cargo_type_id AND pc.planet_id = :planet_id
SQL
                , ['cargo_type_id' => $proposedTransaction->cargoTypeId, 'planet_id' => $player['location_id']]
            );
            if (is_null($cargoPrices)) {
                throw new CargoServiceException(['cargoTypeId' => ['not valid for player\'s current location']]);
            }

            $playersCurrentCargo = $this->getPlayersCurrentCargo($proposedTransaction->playerId);
            $playersCurrentFreeCargoSpace = array_reduce($playersCurrentCargo, function (&$result, $value) {
                return $result - $value;
            }, PlayerShipType::DEFAULT_MAX_CARGO);
            $playerShipId =
                $this->db->fetchColumn('SELECT ps.id FROM player_ship ps WHERE ps.player_id = :player_id',
                    ['player_id' => $proposedTransaction->playerId]);

            if ($proposedTransaction->type === CargoTransaction::Type_Sell) {
                // Confirm that the player has the cargo in question, and in sufficient quantities.
                if (!isset($playersCurrentCargo[$proposedTransaction->cargoTypeId])
                    || $playersCurrentCargo[$proposedTransaction->cargoTypeId] < $proposedTransaction->size) {
                    throw new CargoServiceException(['size' => ['is more than you have available to sell']]);
                }

                // Remove the cargo
                $this->db->executeUpdate(<<<SQL
UPDATE player_ship_cargo psc
SET psc.cargo_size = psc.cargo_size - :size
WHERE psc.cargo_type_id = :cargo_type_id AND psc.player_ship_id = :player_ship_id
SQL
                    , ['size' => $proposedTransaction->size, 'cargo_type_id' => $proposedTransaction->cargoTypeId,
                        'player_ship_id' => $playerShipId],
                    ['size' => \PDO::PARAM_INT, 'cargo_type_id' =>  \PDO::PARAM_INT,
                        'player_ship_id' => \PDO::PARAM_INT]);
                $this->db->exec('DELETE FROM player_ship_cargo WHERE player_ship_cargo.cargo_size = 0');
                $moneyChange = $cargoPrices['sell_price'] * $proposedTransaction->size;
            } else { // if ($proposedTransaction->type === CargoTransaction::Type_Buy)
                // Confirm that the player has sufficient funds to make the transaction
                $totalCost = $cargoPrices['buy_price'] * $proposedTransaction->size;
                if ($totalCost > $player['money']) {
                    throw new CargoServiceException(['size' => ['Insufficient funds']]);
                }
                // Confirm that the player has sufficient cargo space to make the transaction
                $remainingCargoSpaceAfterwards = $playersCurrentFreeCargoSpace - $proposedTransaction->size;
                if ($remainingCargoSpaceAfterwards < 0) {
                    throw new CargoServiceException(['size' => ['Insufficient space in your ship']]);
                }

                // Add the cargo and debit the player's account
                if (isset($playersCurrentCargo[$proposedTransaction->cargoTypeId])) {
                    // UPDATE
                    $this->db->executeUpdate(<<<SQL
UPDATE player_ship_cargo psc
SET psc.cargo_size = psc.cargo_size + :size
WHERE psc.cargo_type_id = :cargo_type_id AND psc.player_ship_id = :player_ship_id
SQL
                        , ['size' => $proposedTransaction->size, 'cargo_type_id' => $proposedTransaction->cargoTypeId,
                            'player_ship_id' => $playerShipId],
                        ['size' => \PDO::PARAM_INT, 'cargo_type_id' => \PDO::PARAM_INT,
                            'player_ship_id' => \PDO::PARAM_INT]
                    );
                } else {
                    // INSERT
                    $this->db->insert('player_ship_cargo', [
                        'player_ship_id' => $playerShipId,
                        'cargo_type_id' => $proposedTransaction->cargoTypeId,
                        'cargo_size' => $proposedTransaction->size
                    ]);
                }
                $moneyChange = 0 - $totalCost;
            }

            // update the player's money
            $this->db->executeUpdate(<<<SQL
UPDATE player p SET p.money = (p.money + :moneyChange) WHERE p.id = :playerId
SQL
                , ['moneyChange' => $moneyChange, 'playerId' => $proposedTransaction->playerId],
                ['moneyChange' => \PDO::PARAM_INT, 'playerId' => \PDO::PARAM_INT]
            );

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    private function getPlayersCurrentCargo($playerId)
    {
        $sql = <<<SQL
SELECT psc.cargo_type_id, psc.cargo_size
FROM player_ship_cargo psc
INNER JOIN player_ship ps ON ps.id = psc.player_ship_id
WHERE ps.player_id = :player_id
SQL;
        $currentCargo = $this->db->fetchAll($sql, ['player_id' => intval($playerId)]);

        $cargoKeyValue = [];

        foreach ($currentCargo as $cargoRow) {
            $cargoKeyValue[intval($cargoRow['cargo_type_id'])] = intval($cargoRow['cargo_size']);
        }

        return $cargoKeyValue;
    }

}
