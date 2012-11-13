<?php

namespace Game\Model;

/**
 * TODO: Document me!
 */ 
class Player {
    //waiting for game, active in game, completed game, left game in progress
    const Status_Waiting = 'WAITING';
    const Status_InGame = 'IN_GAME';
    const Status_CompletedGame = 'COMPLETED_GAME';
    const Status_LeftInProgress = 'QUIT';

    const DEFAULT_FUEL = 4;

    const START_MONEY = 50000;

    public static function setTypes(&$player) {
        $player['id'] = intval($player['id']);
        $player['user_id'] = intval($player['user_id']);
        $player['game_id'] = intval($player['game_id']);
        $player['location_id'] = intval($player['location_id']);
        $player['fuel'] = intval($player['fuel']);
        $player['money'] = intval($player['money']);
        $player['max_cargo_space'] = intval($player['max_cargo_space']);
        $player['free_cargo_space'] = intval($player['free_cargo_space']);
        return $player;
    }
}
