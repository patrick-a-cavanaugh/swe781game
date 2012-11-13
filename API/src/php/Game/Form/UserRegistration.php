<?php

namespace Game\Form;

use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Constraints as Assert;
use Game\Validator\StrongPasswordField;
use Game\Validator\UniqueDbField;

/**
 * These values match those used in the user registration form.
 */
class UserRegistration {

    public $emailAddress;
    public $userName;
    public $password;
    public $passwordConfirmation;

    public function isPasswordConfirmationMatching() {
        return $this->passwordConfirmation === $this->password;
    }

    static public function loadValidatorMetadata(ClassMetadata $metadata) {
        $metadata->addPropertyConstraint('emailAddress', new Assert\NotBlank());
        $metadata->addPropertyConstraint('emailAddress', new Assert\Email());
        $metadata->addPropertyConstraint('emailAddress', new UniqueDbField(['table' => 'user',
                                                                            'column' => 'email_address']));
        $metadata->addPropertyConstraint('userName', new Assert\NotBlank());
        $metadata->addPropertyConstraint('userName', new Assert\Length(['min' => 6, 'max' => 12]));
        $metadata->addPropertyConstraint('userName', new Assert\Regex([
            'pattern' => '/^[a-zA-Z0-9_]+$/',
            'message' => 'This value must be only alphanumeric characters and underscores']));
        $metadata->addPropertyConstraint('userName', new UniqueDbField(['table' => 'user', 'column' => 'username']));
        $metadata->addPropertyConstraint('password', new Assert\NotBlank());
        $metadata->addPropertyConstraint('password', new Assert\Length(['min' => 8, 'max' => 55]));
        $metadata->addPropertyConstraint('password', new StrongPasswordField());
        $metadata->addGetterConstraint('passwordConfirmationMatching', new Assert\True([
            'message' => 'This does not match the password']));
    }
}