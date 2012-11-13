<?php

namespace Game\Service;

use Doctrine\DBAL\Connection;
use Game\Util\User;
use Game\Model\Game;
use Game\Model\PlayerMove;

class GameService {

    /** @var \Doctrine\DBAL\Connection */
    private $db;

    /** @var \Monolog\Logger */
    private $logger;

    /** @var User */
    private $currentUser;

    function __construct(Connection $db, $logger, User $currentUser)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->currentUser = $currentUser;
    }

    public function fetchAllGames()
    {
        $games = $this->db->fetchAll(self::getSqlWhere(),
            ['current_user_id' => $this->currentUser->getId(), 'current_user_id2' => $this->currentUser->getId()]
        );
        foreach ($games as &$game) {
            Game::setTypes($game);
        }
        return $games;
    }

    public function fetchGameById($gameId)
    {
        $games = $this->db->fetchAll(self::getSqlWhere('game.id = :game_id'),
            ['current_user_id' => $this->currentUser->getId(), 'current_user_id2' => $this->currentUser->getId(),
                'game_id' => $gameId]
        );
        return Game::setTypes($games[0]);
    }

    private static function getSqlWhere($whereCond = "1 = 1")
    {
        $sqlBase = <<<SQL
SELECT game.id, date_created, date_started, game.status, name, created_by_id,
       game.turn, game.winner_id, game.winner_name,
       (SELECT player.id FROM player
        WHERE player.game_id = game.id AND player.user_id = :current_user_id) AS current_user_player_id,
       (SELECT COUNT(*) FROM player WHERE player.game_id = game.id) AS players,
       (SELECT COUNT(*) FROM player WHERE player.game_id = game.id AND player.user_id = :current_user_id2) AS joined
FROM game
WHERE 999 = 999
ORDER BY game.id ASC
SQL;
        return str_replace('999 = 999', $whereCond, $sqlBase);
    }

    /**
     * @param $currentGame array the array of the game to start (uses $currentGame['id'])
     * @throws \Exception
     */
    public function startGame($currentGame)
    {
        $this->db->beginTransaction();
        try {
            $affectedRows = $this->db->update('game',
                ['status' => Game::Status_InProgress],
                ['id' => $currentGame['id']],
                [\PDO::PARAM_INT]
            );
            if ($affectedRows != 1) {
                trigger_error("startGame failed (affected $affectedRows rows)", E_USER_ERROR);
            }
            $this->db->update('player',
                ['status' => 'IN_GAME', 'money' => \Game\Model\Player::START_MONEY],
                ['game_id' => $currentGame['id']], [\PDO::PARAM_INT, \PDO::PARAM_INT]);
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * @param $gameId
     * @throws \Exception
     */
    public function tickGameIfNeeded($gameId)
    {
        $this->db->beginTransaction();
        try {
            // Lock the game row to make sure we don't double tick this.
            $gameTurnNo = $this->db->fetchColumn('SELECT turn FROM game WHERE id = :game_id FOR UPDATE',
                ['game_id' => intval($gameId)]);

            $allPlayersInGameCount = $this->db->fetchColumn(
                'SELECT COUNT(*) FROM player p WHERE p.game_id = :game_id',
                ['game_id' => intval($gameId)]
            );
            $allPlayersMostRecentMoves = $this->db->fetchAll(<<<SQL
    SELECT pm.type FROM player_moves pm
    LEFT OUTER JOIN player_moves pm2 ON pm.player_id = pm2.player_id AND pm.id < pm2.id
    WHERE
      pm2.id IS NULL
      AND pm.game_turn_no = :game_turn_no
      AND pm.player_id IN (SELECT p.id FROM player p WHERE p.game_id = :game_id)
SQL
                , ['game_turn_no' => $gameTurnNo, 'game_id' => $gameId],
                [\PDO::PARAM_INT, \PDO::PARAM_INT]
            );

            // Only if all players have moved can the game possibly move to the next tick.
            if (count($allPlayersMostRecentMoves) === intval($allPlayersInGameCount)) {
                $shouldTickGame = true;

                foreach ($allPlayersMostRecentMoves as $move) {
                    if ($move['type'] !== PlayerMove::Type_Land) {
                        $this->logger->addDebug('Not ticking game, player has not moved');
                        $shouldTickGame = false;
                    }
                }

                if ($shouldTickGame) {
                    $this->tickGameInternal($gameId, $gameTurnNo + 1);
                }
            } else {
                $this->logger->addDebug('Not ticking game, not all players have moved.');
            }
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    private function tickGameInternal($gameId, $newTurnNo)
    {
        $this->logger->addInfo('Ticking game id #', ['gameId' => $gameId]);

        if ($newTurnNo === Game::MAX_TURNS) {
            // Complete the game instead of making the next turn.
            $winningPlayer = $this->db->fetchAssoc(<<<SQL
SELECT p.id, u.username
FROM player p
INNER JOIN user u ON u.id = p.user_id
WHERE p.game_id = :game_id
ORDER BY p.money DESC
LIMIT 1
SQL
                , ['game_id' => $gameId], [\PDO::PARAM_INT]
            );

            $updatedRows = $this->db->update('game',
                [
                    'status' => Game::Status_Completed,
                    'winner_name' => $winningPlayer['username'],
                    'winner_id' => $winningPlayer['id']
                ],
                ['id' => $gameId],
                [\PDO::PARAM_INT]
            );
            if (!$updatedRows) {
                trigger_error('Game did not update properly when completed', E_USER_ERROR);
            }
        } else {
            // Just move to the next turn.
            $this->db->update('game',
                ['turn' => intval($newTurnNo)],
                ['id' => intval($gameId)],
                [\PDO::PARAM_INT]
            );
        }
    }
}
