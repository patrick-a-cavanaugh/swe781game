<?php

namespace Migration;

use Doctrine\DBAL\Schema\Schema;
use Knp\Migration\AbstractMigration;

/**
 * Adds a player_moves table and adds location and fuel fields to the player table.
 */
class AddSecurityKeyToPasswordResetMigration extends AbstractMigration
{

    public function getMigrationInfo()
    {
        return 'Adds a security key field to the password reset table';
    }

    public function schemaUp(Schema $schema)
    {
        $primaryKeyOpts = ['unsigned' => true, 'autoincrement' => true];

        $passwordResets = $schema->getTable('user_password_reset');
        $passwordResets->addColumn('security_key', 'string', ['length' => 64]);
    }

    public function schemaDown(Schema $schema)
    {
        $passwordResets = $schema->getTable('user_password_reset');
        $passwordResets->dropColumn('security_key');
    }

}
