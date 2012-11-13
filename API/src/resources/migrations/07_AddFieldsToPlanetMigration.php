<?php

namespace Migration;

use Doctrine\DBAL\Schema\Schema;
use Knp\Migration\AbstractMigration;

/**
 * Adds some needed fields to the Planet table.
 *
 * Our powers combine…
 */
class AddFieldsToPlanetMigration extends AbstractMigration
{

    public function getMigrationInfo()
    {
        return 'Adds some needed fields to the Planet table';
    }

    public function schemaUp(Schema $schema)
    {
        $planet = $schema->getTable('planet');
        $planet->addColumn('name', 'string', ['length' => 64]);
        $planet->addColumn('is_start_location', 'boolean', ['default' => 0]);
    }

    public function schemaDown(Schema $schema)
    {
        $planet = $schema->getTable('planet');
        $planet->dropColumn('name');
        $planet->dropColumn('is_start_location');
    }

}
