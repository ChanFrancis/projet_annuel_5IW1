<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class StrongPasswordValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof StrongPassword) {
            throw new UnexpectedTypeException($constraint, StrongPassword::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        $ok = \strlen((string) $value) >= $constraint->minLength
            && preg_match('/[a-z]/', (string) $value)
            && preg_match('/[A-Z]/', (string) $value)
            && preg_match('/[0-9]/', (string) $value)
            && preg_match('/[^A-Za-z0-9]/', (string) $value);

        if (!$ok) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
