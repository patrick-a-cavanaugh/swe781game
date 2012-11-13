<?php

namespace Game\Model;

/**
 * TODO: Document me!
 */ 
class Game {
    const Status_Waiting = 'WAITING';
    const Status_InProgress = 'IN_PROGRESS';
    const Status_Completed = 'COMPLETED';

    /** Once this number of turns have been completed, it's over! */
    const MAX_TURNS = 5;

    public static function setTypes(&$game) {
        self::setType($game, 'id', 'intval');
        self::setType($game, 'turn', 'intval');
        self::setType($game, 'players', 'intval');
        self::setType($game, 'created_by_id', 'intval');
        self::setType($game, 'joined', 'boolval');
        self::setType($game, 'current_user_player_id', 'intval');
        self::setType($game, 'winner_id', 'intval');
        return $game;
    }

    private static function setType(&$object, $key, $type) {
        if (isset($object[$key])) {
            switch ($type) {
                case 'intval': $object[$key] = intval($object[$key]); break;
                case 'floatval': $object[$key] = floatval($object[$key]); break;
                case 'boolval': $object[$key] = !!$object[$key]; break;
            }
        }
    }
}
