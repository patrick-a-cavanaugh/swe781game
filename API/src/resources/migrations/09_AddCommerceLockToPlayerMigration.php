<?php

namespace Migration;

use Doctrine\DBAL\Schema\Schema;
use Knp\Migration\AbstractMigration;

/**
 * Add commerce lock field to the player table - prevent concurrent cargo commerce operations.
 */
class AddCommerceLockToPlayerMigration extends AbstractMigration
{

    public function getMigrationInfo()
    {
        return 'Add commerce lock field to the player table.';
    }

    public function schemaUp(Schema $schema)
    {
        $player = $schema->getTable('player');
        $player->addColumn('commerce_lock_counter', 'integer', ['unsigned' => true, 'default' => 0]);
    }

    public function schemaDown(Schema $schema)
    {
        $player = $schema->getTable('player');
        $player->dropColumn('commerce_lock_counter');
    }

}
