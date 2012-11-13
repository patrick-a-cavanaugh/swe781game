<?php

namespace Migration;

use Doctrine\DBAL\Schema\Schema;
use Knp\Migration\AbstractMigration;

/**
 * Add a turn field to the game table
 */
class AddTurnToGameMigration extends AbstractMigration
{

    public function getMigrationInfo()
    {
        return 'Add turn field to the game table';
    }

    public function schemaUp(Schema $schema)
    {
        $game = $schema->getTable('game');
        $game->addColumn('turn', 'integer', ['unsigned' => true, 'default' => 0]);
    }

    public function schemaDown(Schema $schema)
    {
        $game = $schema->getTable('game');
        $game->dropColumn('turn');
    }

}
