<?php

namespace Migration;

use Doctrine\DBAL\Schema\Schema;
use Knp\Migration\AbstractMigration;

/**
 * Adds a created by field to the game table.
 */
class AddCreatedByToGameMigration extends AbstractMigration
{

    public function getMigrationInfo()
    {
        return 'Adds a created by field to the game table';
    }

    public function schemaUp(Schema $schema)
    {
        $game = $schema->getTable('game');
        $game->addColumn('created_by_id', 'integer', ['unsigned' => true]);
        $game->addForeignKeyConstraint($schema->getTable('user'), ['created_by_id'], ['id'], [],
            'game_created_by_id_user_id');
    }

    public function schemaDown(Schema $schema)
    {
        $game = $schema->getTable('game');
        $game->removeForeignKey('game_created_by_id_user_id');
        $game->dropColumn('created_by_id');
    }

}
