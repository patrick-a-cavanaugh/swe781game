<?php

namespace Migration;

use Doctrine\DBAL\Schema\Schema;
use Knp\Migration\AbstractMigration;

/**
 * Adds a player_moves table and adds location and fuel fields to the player table.
 */
class AddPlayerMovesAndLocationMigration extends AbstractMigration  {

    public function getMigrationInfo()
    {
        return 'Adds a player_moves table and adds location and fuel fields to the player table';
    }

    public function schemaUp(Schema $schema)
    {
        $primaryKeyOpts = ['unsigned' => true, 'autoincrement' => true];

        $playerMoves = $schema->createTable('player_moves');
        $playerMoves->addColumn('id', 'integer', $primaryKeyOpts);
        $playerMoves->addColumn('player_id', 'integer', ['unsigned' => true]);
        $playerMoves->addColumn('game_turn_no', 'integer', ['unsigned' => true]);
        $playerMoves->addColumn('move_no', 'integer', ['unsigned' => true]);
        // "LIFTOFF", "LAND", or "HYPERJUMP"
        $playerMoves->addColumn('type', 'string', ['length' => 64]);
        // For a "LIFTOFF" or "LAND", this is the current planet
        $playerMoves->addColumn('player_move_destination_id', 'integer', ['unsigned' => true]);
        $playerMoves->setPrimaryKey(['id']);
        $playerMoves->addUniqueIndex(['player_id', 'game_turn_no', 'move_no']);

        $player = $schema->getTable('player');
        // planet_id of current planet
        $player->addColumn('location_id', 'integer', ['unsigned' => true]);
        // current fuel
        $player->addColumn('fuel', 'integer', ['unsigned' => true]);
    }

    public function schemaDown(Schema $schema)
    {
        $schema->dropTable('player_moves');

        $player = $schema->getTable('player');
        $player->dropColumn('location_id');
        $player->dropColumn('fuel');
    }

}
