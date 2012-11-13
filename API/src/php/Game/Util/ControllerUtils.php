<?php

namespace Game\Util;

use Symfony\Component\Validator\ConstraintViolation;

/**
 * TODO: Document me!
 */
class ControllerUtils {
    /**
     * @param \Symfony\Component\Validator\ConstraintViolationList $errors
     * @return array
     */
    public static function transformErrorsToArray($errors)
    {
        $errorArray = [];
        foreach ($errors as $key => $error) {
            if ($error instanceof ConstraintViolation) {
                /** @var \Symfony\Component\Validator\ConstraintViolation $error */
                if (!isset($errorArray[$error->getPropertyPath()])) {
                    $errorArray[$error->getPropertyPath()] = [];
                }
                $errorArray[$error->getPropertyPath()][] = $error->getMessage();
            } else {
                $errorArray[$key] = $error;
            }

        }
        return $errorArray;
    }
}
