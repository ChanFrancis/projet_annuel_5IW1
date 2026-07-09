<?php

namespace App\Service;

/**
 * Generates fictitious but structurally valid French IBANs
 * (correct length + ISO 7064 mod-97 check digits). For demo use only —
 * these accounts do not exist in any real bank.
 */
class IbanGenerator
{
    private const COUNTRY = 'FR';
    private const BBAN_LENGTH = 23; // French BBAN length (5+5+11+2)

    public function generate(): string
    {
        $bban = '';
        for ($i = 0; $i < self::BBAN_LENGTH; ++$i) {
            $bban .= random_int(0, 9);
        }

        $check = $this->checkDigits($bban, self::COUNTRY);

        return self::COUNTRY.$check.$bban;
    }

    /** ISO 13616 / mod-97-10 check digits. */
    private function checkDigits(string $bban, string $country): string
    {
        // Rearranged string: BBAN + country code + "00", letters -> numbers (A=10..Z=35).
        $rearranged = $bban.$country.'00';
        $numeric = '';
        foreach (str_split($rearranged) as $ch) {
            $numeric .= ctype_alpha($ch) ? (string) (ord(strtoupper($ch)) - 55) : $ch;
        }

        $mod = $this->mod97($numeric);
        $check = 98 - $mod;

        return str_pad((string) $check, 2, '0', STR_PAD_LEFT);
    }

    /** mod 97 on an arbitrarily long numeric string. */
    private function mod97(string $numeric): int
    {
        $remainder = 0;
        foreach (str_split($numeric) as $digit) {
            $remainder = ($remainder * 10 + (int) $digit) % 97;
        }

        return $remainder;
    }
}
