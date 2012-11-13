<?php

namespace Game\Service;

use Doctrine\DBAL\Connection;
use Game\Util\User;
use Game\Model\PlayerShipType;
use Game\Model\Player;
use Game\Model\PlayerMove;
use Monolog\Logger;

/**
 * TODO: Document me!
 */
class PlayerService
{

    /** @var \Doctrine\DBAL\Connection */
    private $db;

    /** @var \Monolog\Logger */
    private $logger;

    /** @var User */
    private $currentUser;

    /** @var GameService */
    private $gameService;

    /** @var UniverseService */
    private $universeService;

    function __construct(Connection $db, Logger $logger, User $currentUser, GameService $gameService,
                         UniverseService $universeService)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->currentUser = $currentUser;
        $this->gameService = $gameService;
        $this->universeService = $universeService;
    }


    public function getCurrentUsersPlayerForGameId($gameId)
    {
        $playerId = $this->db->fetchColumn(
            'SELECT p.id FROM player AS p WHERE p.game_id = :game_id AND p.user_id = :user_id', [
            'game_id' => $gameId, 'user_id' => $this->currentUser->getId()
        ]);
        if (empty($playerId)) {
            trigger_error('Unable to find a player for the current user for game id ' . $gameId, E_USER_ERROR);
        }
        return $this->getPlayerById($playerId);
    }

    /**
     * @param $playerId
     * @return array
     */
    public function getPlayerById($playerId, $includeMoves = false)
    {
        $player = $this->db->fetchAssoc(<<<SQL
SELECT p.id, p.user_id, p.game_id, p.date_entered, p.status,
       p.location_id, IFNULL(pm.type, 'LAND') AS location_status,
       p.fuel, p.money,
       ship_type.cargo_space AS max_cargo_space
FROM player AS p
INNER JOIN player_ship ON player_ship.player_id = p.id
INNER JOIN ship_type ON ship_type.id = player_ship.ship_type_id
LEFT OUTER JOIN player_moves pm ON pm.player_id = p.id
LEFT OUTER JOIN player_moves pm2 ON pm2.id > pm.id AND pm2.player_id = p.id
WHERE p.id = ? AND pm2.id IS NULL
SQL
            , [$playerId]
        );
        if (!$player) {
            trigger_error('No player found', E_USER_ERROR);
        }
        $playerCargo = $this->db->fetchAll(<<<SQL
SELECT c.name, psc.cargo_type_id, psc.cargo_size
FROM player_ship_cargo psc
INNER JOIN player_ship AS ps ON psc.player_ship_id = ps.id AND ps.player_id = :playerId
INNER JOIN cargo_type AS c ON c.id = psc.cargo_type_id
SQL
            , ['playerId' => $playerId]);
        $playerCargoSum = array_reduce($playerCargo, function ($result, $cargoRow) {
            return $result + $cargoRow['cargo_size'];
        }, 0);
        $player['cargo'] = $playerCargo;
        $player['free_cargo_space'] = $player['max_cargo_space'] - $playerCargoSum;
        if ($includeMoves) {
            $player['moves'] = $this->fetchAllPlayerMoves($player['id']);
        }
        return Player::setTypes($player);
    }

    /**
     * Adds the current user as a player in the passed game.
     * @param $game
     * @return array
     */
    public function addPlayerToGame($game)
    {
        $start_location_id = $this->db->fetchColumn(
            "SELECT id FROM planet WHERE game_id = ? AND is_start_location = TRUE", [$game['id']]);

        $this->db->insert('player', [
            'user_id' => $this->currentUser->getId(),
            'game_id' => $game['id'],
            'date_entered' => ($this->currentUser->getId() == $game['id'])
                ? $game['date_created'] : date('Y-m-d H:i:s'),
            'status' => Player::Status_Waiting,
            'location_id' => $start_location_id,
            'fuel' => Player::DEFAULT_FUEL
        ]);
        $playerId = $this->db->lastInsertId();

        $this->db->insert('player_ship', [
            'player_id' => $playerId,
            'game_id' => $game['id'],
            'ship_type_id' => PlayerShipType::DEFAULT_ID
        ]);

        return $this->getPlayerById($playerId);
    }

    public function checkAndMakeMove(PlayerMove $proposedMove)
    {
        $this->db->beginTransaction();
        try {
            // Get the player's most recent move and current game state.
            // We need to confirm that the next move is legal.
            // For instance, you cannot lift off twice in a row, or make a hyperjump with no fuel.
            $player = $this->getPlayerById($proposedMove->playerId);
            $mostRecentMove = $this->getMostRecentMoveForPlayer($proposedMove->playerId);
            $hasPlayerFinishedTurn = $this->hasPlayerFinishedTurn($proposedMove->playerId, $proposedMove->gameTurnNo);
            $game = $this->gameService->fetchGameById($player['game_id']);
            $currentPlanet = $this->universeService->fetchPlanetById($player['location_id']);

            if ($player['user_id'] !== $this->currentUser->getId()) {
                trigger_error('Cannot move a player that doesn\'t belong to current user', E_USER_ERROR);
            }
            if ($proposedMove->type === PlayerMove::Type_Hyperjump) {
                if ($player['fuel'] < 1) {
                    trigger_error('Cannot make a jump with no fuel', E_USER_ERROR);
                }
                if (!in_array($mostRecentMove['type'], [PlayerMove::Type_Hyperjump, PlayerMove::Type_Liftoff])) {
                    trigger_error('Cannot make a jump unless already in space', E_USER_ERROR);
                }
                $validDestinations = array_map(function ($a) {
                    return intval($a['connected_planet_id']);
                }, $currentPlanet['links']);
                if (!in_array($proposedMove->playerMoveDestinationId, $validDestinations)
                ) {
                    trigger_error('Cannot make a jump to an unlinked planet', E_USER_ERROR);
                }
            }
            if (in_array($proposedMove->type, [PlayerMove::Type_Liftoff, PlayerMove::Type_Land])) {
                if ($proposedMove->playerMoveDestinationId !== $currentPlanet['id']) {
                    trigger_error('Cannot move to a different location when lifting off or landing', E_USER_ERROR);
                }
            }
            if ($proposedMove->type === PlayerMove::Type_Liftoff) {
                if ($mostRecentMove && $mostRecentMove['type'] !== PlayerMove::Type_Land) {
                    trigger_error('Cannot liftoff unless landed', E_USER_ERROR);
                }
                if ($hasPlayerFinishedTurn) {
                    trigger_error('Can only liftoff once per turn', E_USER_ERROR);
                }
            }
            if ($proposedMove->type === PlayerMove::Type_Land) {
                if (!in_array($mostRecentMove['type'], [PlayerMove::Type_Hyperjump, PlayerMove::Type_Liftoff])) {
                    trigger_error('Cannot land unless lifted off or in space', E_USER_ERROR);
                }
            }
            if ($proposedMove->gameTurnNo < $game['turn'] || $proposedMove->gameTurnNo > $game['turn']) {
                trigger_error('The game is on turn ' . $game['turn'] . ' and you can only make a move on that turn');
            }

            // It's valid! Great. Let's add it to the DB. The DB unique index on (player_id, game_turn_no, move_no)
            // protects us from a malicious user attempting to execute two turns simultaneously.
            if ($game['turn'] > $mostRecentMove['game_turn_no']) {
                $moveNo = 0;
            } else {
                $moveNo = $mostRecentMove['move_no'] + 1;
            }

            $this->db->insert('player_moves', [
                'player_id' => $player['id'],
                'game_turn_no' => $game['turn'],
                'move_no' => $moveNo,
                'type' => $proposedMove->type,
                'player_move_destination_id' => $proposedMove->playerMoveDestinationId
            ], [\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_STR, \PDO::PARAM_INT]);

            $moveId = $this->db->lastInsertId();

            if ($proposedMove->type === PlayerMove::Type_Hyperjump) {
                $playerUpdateAttrs = [
                    'fuel' => $player['fuel'] - 1,
                    'location_id' => $proposedMove->playerMoveDestinationId
                ];
            } else if ($proposedMove->type === PlayerMove::Type_Land) {
                $playerUpdateAttrs = [
                    'fuel' => Player::DEFAULT_FUEL,
                ];
            }

            if (isset($playerUpdateAttrs)) {
                $this->db->update('player', $playerUpdateAttrs, ['id' => $player['id']]);
            }

            // If all players have moved, the game turn needs to be incremented.
            $this->gameService->tickGameIfNeeded($game['id']);

            $this->db->commit();
            return $moveId;
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }


    /**
     * @param $playerId
     * @return array
     */
    public function fetchAllPlayerMoves($playerId)
    {
        $sql = self::getSqlWhereForPlayerMove("pm.player_id = :player_id");

        $moves = $this->db->fetchAll($sql, ['user_id' => intval($this->currentUser->getId()),
            'player_id' => intval($playerId)]);
        foreach($moves as &$move) {
            $move['id'] = intval($move['id']);
            $move['player_id'] = intval($move['player_id']);
            $move['game_turn_no'] = intval($move['game_turn_no']);
            $move['move_no'] = intval($move['move_no']);
            $move['player_move_destination_id'] = intval($move['player_move_destination_id']);
        }
        return $moves;
    }

    /**
     * @param $playerMoveId
     * @return array
     */
    public function fetchPlayerMoveById($playerMoveId)
    {
        $sql = self::getSqlWhereForPlayerMove("pm.id = :player_move_id");

        $move = $this->db->fetchAssoc($sql, ['player_move_id' => intval($playerMoveId),
            'user_id' => intval($this->currentUser->getId())]);
        if ($move) {
            $move['id'] = intval($move['id']);
            $move['player_id'] = intval($move['player_id']);
            $move['game_turn_no'] = intval($move['game_turn_no']);
            $move['move_no'] = intval($move['move_no']);
            $move['player_move_destination_id'] = intval($move['player_move_destination_id']);
        }
        return $move ?: null;
    }

    /**
     * @param $playerId
     * @return array
     */
    private function getMostRecentMoveForPlayer($playerId)
    {
        $sql = <<<SQL
SELECT pm.id, pm.player_id, pm.game_turn_no, pm.move_no, pm.type, pm.player_move_destination_id
FROM player_moves pm
WHERE pm.player_id = :player_id
ORDER BY pm.id DESC
LIMIT 1
SQL;
        return $this->db->fetchAssoc($sql, ['player_id' => $playerId], [\PDO::PARAM_INT]);
    }


    private function hasPlayerFinishedTurn($playerId, $gameTurnNo)
    {
        $sql = <<<SQL
SELECT TRUE FROM player_moves pm
WHERE pm.player_id = :player_id AND pm.game_turn_no = :game_turn_no AND pm.type = :move_type
SQL;
        return (bool)$this->db->fetchColumn($sql,
            ['player_id' => $playerId, 'game_turn_no' => $gameTurnNo, 'move_type' => PlayerMove::Type_Land]);
    }

    private static function getSqlWhereForPlayerMove($whereCond = "1 = 1")
    {
        // check the player id so that a player cannot view another player's moves
        $sql = <<<SQL
SELECT pm.id, pm.player_id, pm.game_turn_no, pm.move_no, pm.type, pm.player_move_destination_id
FROM player_moves pm
INNER JOIN player p ON p.id = pm.player_id
WHERE p.user_id = :user_id
AND 999 = 999
SQL;
        return str_replace("999 = 999", $whereCond, $sql);
    }

}
