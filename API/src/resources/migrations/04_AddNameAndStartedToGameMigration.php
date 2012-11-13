<?php

namespace Migration;

use Doctrine\DBAL\Schema\Schema;
use Knp\Migration\AbstractMigration;

/**
 * Adds a name field and a started timestamp to the game table.
 */
class AddNameAndStartedToGameMigration extends AbstractMigration
{

    public function getMigrationInfo()
    {
        return 'Adds a name field and a started timestamp to the game table';
    }

    public function schemaUp(Schema $schema)
    {
        $game = $schema->getTable('game');
        $game->addColumn('name', 'string', ['length' => 64]);
        $game->addColumn('date_started', 'datetime', ['notnull' => false]);
    }

    public function schemaDown(Schema $schema)
    {
        $game = $schema->getTable('game');
        $game->dropColumn('name');
        $game->dropColumn('started');
    }

}
