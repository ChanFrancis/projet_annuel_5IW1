<?php

namespace App\Enum;

enum AccountType: string
{
    case COURANT = 'courant';
    case COMMUN = 'commun';
    case LIVRET = 'livret';
    case EPARGNE = 'epargne';

    public function label(): string
    {
        return match ($this) {
            self::COURANT => 'Compte courant',
            self::COMMUN => 'Compte commun',
            self::LIVRET => 'Livret',
            self::EPARGNE => 'Épargne personnalisée',
        };
    }
}
