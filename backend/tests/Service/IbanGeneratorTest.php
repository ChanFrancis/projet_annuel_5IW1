<?php

namespace App\Tests\Service;

use App\Service\IbanGenerator;
use PHPUnit\Framework\TestCase;

class IbanGeneratorTest extends TestCase
{
    public function testGeneratesFrenchIbanOfCorrectLength(): void
    {
        $iban = (new IbanGenerator())->generate();
        self::assertStringStartsWith('FR', $iban);
        self::assertSame(27, \strlen($iban), 'French IBAN is 27 chars');
    }

    public function testGeneratedIbanHasValidCheckDigits(): void
    {
        $iban = (new IbanGenerator())->generate();

        // ISO 13616: move first 4 chars to the end, convert letters, mod 97 must equal 1.
        $rearranged = substr($iban, 4).substr($iban, 0, 4);
        $numeric = '';
        foreach (str_split($rearranged) as $ch) {
            $numeric .= ctype_alpha($ch) ? (string) (\ord($ch) - 55) : $ch;
        }
        $remainder = 0;
        foreach (str_split($numeric) as $digit) {
            $remainder = ($remainder * 10 + (int) $digit) % 97;
        }
        self::assertSame(1, $remainder, 'valid IBAN has mod-97 remainder of 1');
    }

    public function testGeneratesDistinctIbans(): void
    {
        $gen = new IbanGenerator();
        self::assertNotSame($gen->generate(), $gen->generate());
    }
}
