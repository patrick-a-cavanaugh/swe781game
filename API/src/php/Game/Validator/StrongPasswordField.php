<?php

namespace Game\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * A field that is not one of the weak passwords.
 */
class StrongPasswordField extends Constraint
{
    public $message = 'This value is one of the most common passwords and is prohibited.';
}
