<?php

namespace Migration;

use Doctrine\DBAL\Schema\Schema;
use Knp\Migration\AbstractMigration;

/**
 * Adds a email confirmation token field to the user table.
 */
class AddEmailConfirmTokenToUserMigration extends AbstractMigration
{

    public function getMigrationInfo()
    {
        return 'Adds a email confirmation token field to the user table';
    }

    public function schemaUp(Schema $schema)
    {
        $user = $schema->getTable('user');
        $user->addColumn('email_confirm_token', 'string', ['length' => 32, 'notnull' => false]);
        $user->addColumn('email_confirmed', 'boolean', ['default' => false]);
    }

    public function schemaDown(Schema $schema)
    {
        $user = $schema->getTable('user');
        $user->dropColumn('email_confirm_token');
        $user->dropColumn('email_confirmed');
    }

}
