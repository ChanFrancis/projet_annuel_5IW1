<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class StrongPassword extends Constraint
{
    public string $message = 'Le mot de passe doit contenir au moins 12 caractères, une minuscule, une majuscule, un chiffre et un symbole.';
    public int $minLength = 12;
}
