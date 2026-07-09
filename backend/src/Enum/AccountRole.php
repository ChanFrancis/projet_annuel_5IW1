<?php

namespace App\Enum;

enum AccountRole: string
{
    case OWNER = 'owner';        // propriétaire : tous droits, y compris suppression et gestion des membres
    case CO_OWNER = 'co_owner';  // co-titulaire : lecture + écriture des opérations
    case VIEWER = 'viewer';      // lecteur : lecture seule

    public function canWrite(): bool
    {
        return self::OWNER === $this || self::CO_OWNER === $this;
    }

    public function canManage(): bool
    {
        return self::OWNER === $this;
    }
}
