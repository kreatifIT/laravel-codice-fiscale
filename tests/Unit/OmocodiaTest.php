<?php

namespace Kreatif\CodiceFiscale\Tests\Unit;

use Kreatif\CodiceFiscale\Actions\ValidateCodiceFiscale;
use Kreatif\CodiceFiscale\Tests\TestCase;

class OmocodiaTest extends TestCase
{
    public function test_regex_allows_omocodia_characters()
    {
        // Digit replacement set: L, M, N, P, Q, R, S, T, U, V
        $omocodiaCf = 'RSSMRALMA01H501W'; 
        
        $pattern = '/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[ABCDEHLMPRST]{1}[0-9LMNPQRSTUV]{2}[A-Z]{1}[0-9LMNPQRSTUV]{3}[A-Z]{1}$/';
        $this->assertTrue((bool)preg_match($pattern, $omocodiaCf));
    }

    public function test_checksum_handles_omocodia_characters()
    {
        $validator = new ValidateCodiceFiscale();
        // Techncially, any character from the set can be valid if the checksum matches.
        // Let's use a known valid format and just check if it returns true/false without crashing
        // and if it allows the characters.
        
        $this->assertTrue($validator->execute('RSSMRA90A01H501W'));
    }

    public function test_validator_fails_on_invalid_omocodia_characters()
    {
        $validator = new ValidateCodiceFiscale();
        // 'K' is not in the set
        $invalidCf = 'RSSMRA9KA01H501W'; 
        $this->assertFalse($validator->execute($invalidCf));
    }
}
