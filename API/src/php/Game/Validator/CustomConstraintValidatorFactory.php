<?php

namespace Game\Validator;

use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\Constraint;

/**
 * Mostly the same as Symfony\Component\Validator\ConstraintValidatorFactory, but can fetch validators from the DIC.
 */ 
class CustomConstraintValidatorFactory implements ConstraintValidatorFactoryInterface
{
    protected $validators = array();
    private $serviceContainer;

    function __construct(\Pimple $serviceContainer)
    {
        $this->serviceContainer = $serviceContainer;
    }

    /**
     * {@inheritDoc}
     */
    public function getInstance(Constraint $constraint)
    {
        $className = $constraint->validatedBy();

        if (!isset($this->validators[$className])) {
            if (isset($this->serviceContainer[$className])) {
                $this->validators[$className] = $this->serviceContainer[$className];
            } else {
                $this->validators[$className] = new $className();
            }
        }

        return $this->validators[$className];
    }
}