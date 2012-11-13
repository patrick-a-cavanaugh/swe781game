<?php

namespace Game\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\MissingOptionsException;
use Doctrine\DBAL\Connection;
use Symfony\Component\Validator\Exception\InvalidOptionsException;

/**
 * A field that is not one of the weak passwords.
 */
class UniqueDbField extends Constraint
{
    public $table;
    public $column;

    public $message = 'This value is already in use and cannot be duplicated.';

    /**
     * Requires 'table', 'column', and 'db' options.
     * @param array|null $options
     * @throws \Symfony\Component\Validator\Exception\MissingOptionsException
     * @throws \Symfony\Component\Validator\Exception\InvalidOptionsException
     */
    function __construct($options = null)
    {
        parent::__construct($options);

        $missingRequiredOpts = [];
        if (is_null($this->table)) $missingRequiredOpts[] = 'table';
        if (is_null($this->column)) $missingRequiredOpts[] = 'column';

        if (!empty($missingRequiredOpts)) {
            throw new MissingOptionsException("Both table and column options must be set on this constraint.",
                                              $missingRequiredOpts);
        }
    }

    public function validatedBy()
    {
        return 'unique_db_field_validator';
    }

}
