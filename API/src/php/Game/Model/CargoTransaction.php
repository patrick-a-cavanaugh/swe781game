<?php

namespace Game\Model;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * TODO: Document me!
 */
class CargoTransaction {

    const Type_Buy = "BUY";
    const Type_Sell = "SELL";

    public $type;
    public $cargoTypeId;
    public $size;
    public $playerId;


    function __construct($cargoTypeId, $playerId, $size, $type)
    {
        $this->cargoTypeId = $cargoTypeId;
        $this->playerId = $playerId;
        $this->size = $size;
        $this->type = $type;
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('type', new Assert\Choice(['choices' => [
            self::Type_Buy, self::Type_Sell
        ]]));
        $metadata->addPropertyConstraint('type', new Assert\NotBlank());
        $metadata->addPropertyConstraint('cargoTypeId', new Assert\NotNull());
        $metadata->addPropertyConstraint('cargoTypeId', new Assert\Range(['min' => 1, 'max' => 4]));
        $metadata->addPropertyConstraint('size', new Assert\Range(['min' => 1,
            'max' => PlayerShipType::DEFAULT_MAX_CARGO]));
        $metadata->addPropertyConstraint('playerId', new Assert\NotNull());
        $metadata->addPropertyConstraint('playerId', new Assert\Range(['min' => 0]));
    }
}
