<?php

namespace Migration;

use Doctrine\DBAL\Schema\Schema;
use Knp\Migration\AbstractMigration;

/**
 * Add money field to the player and drop the commerce lock column. we can just lock on money.
 * Add a winner column to the game table
 */
class AddMoneyToPlayerMigration extends AbstractMigration
{

    public function getMigrationInfo()
    {
        return 'Add money column to the player table, drop commerce lock column. Also add a winner_id and winner_name column to game table.';
    }

    public function schemaUp(Schema $schema)
    {
        $player = $schema->getTable('player');
        $player->dropColumn('commerce_lock_counter');
        $player->addColumn('money', 'integer', ['unsigned' => true, 'default' => 0]);

        $game = $schema->getTable('game');
        $game->addColumn('winner_name', 'string', ['length' => '64', 'notnull' => false]);
        $game->addColumn('winner_id', 'integer', ['unsigned' => true, 'notnull' => false]);
    }

}
