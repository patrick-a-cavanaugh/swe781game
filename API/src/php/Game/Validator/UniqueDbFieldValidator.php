<?php

namespace Game\Validator;

use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Constraint;
use Doctrine\DBAL\Connection;

/**
 * Validator for UniqueDbField.
 */ 
class UniqueDbFieldValidator extends ConstraintValidator {

    private $db;

    function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * Only valid if the database doesn't contain a row with the same value in the column.
     * @param mixed $value
     * @param \Symfony\Component\Validator\Constraint $constraint
     * @return bool|void
     */
    public function validate($value, Constraint $constraint)
    {
        /** @var $constraint \Game\Validator\UniqueDbField */
        $query = sprintf('SELECT 1 FROM %s WHERE %s = :value',
            $this->db->quoteIdentifier($constraint->table),
            $this->db->quoteIdentifier($constraint->column)
        );
        $matchingRows = $this->db->fetchArray($query, ['value' => $value]);
        if (!empty($matchingRows)) {
            $this->context->addViolation($constraint->message, [], $value);
        }
        return empty($matchingRows);
    }

}
