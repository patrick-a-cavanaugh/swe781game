<?php

namespace Game\Form;

use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Constraints as Assert;

class CreateGame {

    public $name;

    static public function loadValidatorMetadata(ClassMetadata $metadata) {
        $metadata->addPropertyConstraint('name', new Assert\Length(['min' => 3, 'max' => 64]));
        $metadata->addPropertyConstraint('name', new Assert\NotBlank());
        $metadata->addPropertyConstraint('name', new Assert\Regex([
            'pattern' => '/^[a-zA-Z0-9 ]+$/',
            'message' => 'This value must be only alphanumeric characters and spaces']));
    }
}
