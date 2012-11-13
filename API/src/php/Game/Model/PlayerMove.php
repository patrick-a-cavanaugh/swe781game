<?php

namespace Game\Model;

use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The player's moves in a game.
 */ 
class PlayerMove {
    const Type_Liftoff = 'LIFTOFF';
    const Type_Land = 'LAND';
    const Type_Hyperjump = 'JUMP';

    public $playerId;
    public $gameTurnNo;
    public $moveNo;
    public $type;
    public $playerMoveDestinationId;

    function __construct($options)
    {
        $valid_options = ['playerId', 'gameTurnNo', 'moveNo', 'type', 'playerMoveDestinationId'];
        foreach ($options as $key => $value) {
            if (in_array($key, $valid_options)) {
                $this->$key = $value;
            } else {
                trigger_error('Unexpected key ' . $key, E_USER_WARNING);
            }
        }
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata) {
        $metadata->addPropertyConstraint('type', new Assert\Choice(['choices' => [
            self::Type_Liftoff, self::Type_Hyperjump, self::Type_Land
        ]]));
        $metadata->addPropertyConstraint('playerId', new Assert\NotNull());
        $metadata->addPropertyConstraint('gameTurnNo', new Assert\NotNull());
        $metadata->addPropertyConstraint('moveNo', new Assert\NotNull());
        $metadata->addPropertyConstraint('type', new Assert\NotBlank());
        $metadata->addPropertyConstraint('playerMoveDestinationId', new Assert\NotNull());
    }


}
