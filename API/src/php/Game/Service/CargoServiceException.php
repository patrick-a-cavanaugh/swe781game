<?php

namespace Game\Service;

/**
 * TODO: Document me!
 */ 
class CargoServiceException extends \Exception {

    public $errors;

    /**
     * @param array $errors like ['property' => ['Problem 1', 'Problem 2'], 'property2' => ['Problem 1']]
     */
    function __construct($errors)
    {
        parent::__construct("One or more validation errors were encountered,"
        . " preventing the cargo transaction from completing");

        $this->errors = $errors;
    }

}
