<?php

namespace Migration;

use Doctrine\DBAL\Schema\Schema;
use Knp\Migration\AbstractMigration;

/**
 * Create the initial definitions of tables defined in DataModel.txt
 */
class InitialMigration extends AbstractMigration  {
    public function getMigrationInfo()
    {
        return 'Sets up the database schema';
    }

    public function schemaUp(Schema $schema)
    {
        $primaryKeyOpts = ['unsigned' => true, 'autoincrement' => true];

        $user = $schema->createTable('user');
        $user->addColumn('id', 'integer', $primaryKeyOpts);
        $user->addColumn('email_address', 'string', ['length' => 128]);
        $user->addColumn('password_hash', 'string', ['length' => 60]);
        $user->addColumn('username', 'string', ['length' => 64]);
        $user->addColumn('date_registered', 'datetime');
        $user->setPrimaryKey(['id']);
        $user->addUniqueIndex(['username']);

        $passwordResets = $schema->createTable('user_password_reset');
        $passwordResets->addColumn('user_id', 'integer', [
            'unsigned' => true
        ]);
        $passwordResets->addColumn('unsuccessful_logins_count', 'integer', [
            'unsigned' => true,
            'default' => 0
        ]);
        $passwordResets->addColumn('last_attempt_timestamp', 'datetime');
        $passwordResets->setPrimaryKey(['user_id']);
        $passwordResets->addForeignKeyConstraint($user, ['user_id'], ['id']);

        $game = $schema->createTable('game');
        $game->addColumn('id', 'integer', $primaryKeyOpts);
        $game->addColumn('date_created', 'datetime');
        $game->addColumn('status', 'string', ['length' => 32]);
        $game->setPrimaryKey(['id']);

        $player = $schema->createTable('player');
        $player->addColumn('id', 'integer', $primaryKeyOpts);
        $player->addColumn('user_id', 'integer', ['unsigned' => true]);
        $player->addColumn('game_id', 'integer', ['unsigned' => true]);
        $player->addColumn('date_entered', 'datetime');
        $player->addColumn('status', 'string', ['length' => 32]);
        $player->setPrimaryKey(['id']);
        $player->addUniqueIndex(['user_id', 'game_id']);
        $player->addForeignKeyConstraint($user, ['user_id'], ['id']);
        $player->addForeignKeyConstraint($game, ['game_id'], ['id']);

        $shipType = $schema->createTable('ship_type');
        $shipType->addColumn('id', 'integer', $primaryKeyOpts);
        $shipType->addColumn('cargo_space', 'integer', [
            'unsigned' => true
        ]);
        $shipType->setPrimaryKey(['id']);

        $cargoType = $schema->createTable('cargo_type');
        $cargoType->addColumn('id', 'integer', $primaryKeyOpts);
        $cargoType->addColumn('name', 'string', ['length' => 64]);
        $cargoType->setPrimaryKey(['id']);

        $planet = $schema->createTable('planet');
        $planet->addColumn('id', 'integer', $primaryKeyOpts);
        $planet->addColumn('game_id', 'integer', ['unsigned' => true]);
        $planet->setPrimaryKey(['id']);
        $planet->addForeignKeyConstraint($game, ['game_id'], ['id']);

        $planetLink = $schema->createTable('planet_link');
        $planetLink->addColumn('planet_id', 'integer', ['unsigned' => true]);
        $planetLink->addColumn('connected_planet_id', 'integer', ['unsigned' => true]);
        $planetLink->addUniqueIndex(['planet_id', 'connected_planet_id']);

        $planetCargo = $schema->createTable('planet_cargo');
        $planetCargo->addColumn('id', 'integer', $primaryKeyOpts);
        $planetCargo->addColumn('planet_id', 'integer', ['unsigned' => true]);
        $planetCargo->addColumn('cargo_type_id', 'integer', ['unsigned' => true]);
        $planetCargo->addColumn('buy_price', 'integer', ['unsigned' => true]);
        $planetCargo->addColumn('sell_price', 'integer', ['unsigned' => true]);
        $planetCargo->setPrimaryKey(['id']);
        $planetCargo->addUniqueIndex(['planet_id', 'cargo_type_id']);
        $planetCargo->addForeignKeyConstraint($planet, ['planet_id'], ['id']);
        $planetCargo->addForeignKeyConstraint($cargoType, ['cargo_type_id'], ['id']);

        $playerShip = $schema->createTable('player_ship');
        $playerShip->addColumn('id', 'integer', $primaryKeyOpts);
        $playerShip->addColumn('player_id', 'integer', ['unsigned' => true]);
        $playerShip->addColumn('game_id', 'integer', ['unsigned' => true]);
        $playerShip->addColumn('ship_type_id', 'integer', ['unsigned' => true]);
        $playerShip->setPrimaryKey(['id']);
        $playerShip->addUniqueIndex(['player_id', 'game_id']);
        $playerShip->addForeignKeyConstraint($player, ['player_id'], ['id']);
        $playerShip->addForeignKeyConstraint($game, ['game_id'], ['id']);
        $playerShip->addForeignKeyConstraint($shipType, ['ship_type_id'], ['id']);

        $playerShipCargo = $schema->createTable('player_ship_cargo');
        $playerShipCargo->addColumn('player_ship_id', 'integer', ['unsigned' => true]);
        $playerShipCargo->addColumn('cargo_type_id', 'integer', ['unsigned' => true]);
        $playerShipCargo->addColumn('cargo_size', 'integer', ['unsigned' => true]);
        $playerShipCargo->setPrimaryKey(['player_ship_id', 'cargo_type_id']);
        $playerShipCargo->addForeignKeyConstraint($playerShip, ['player_ship_id'], ['id']);
        $playerShipCargo->addForeignKeyConstraint($cargoType, ['cargo_type_id'], ['id']);
    }

}
