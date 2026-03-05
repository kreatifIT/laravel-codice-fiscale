<?php

namespace Kreatif\CodiceFiscale\Tests\Unit;

use Kreatif\CodiceFiscale\Actions\CalculateCodiceFiscale;
use Kreatif\CodiceFiscale\Actions\FindBelfioreCode;
use Kreatif\CodiceFiscale\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;

class CodiceFiscaleCalculationTest extends TestCase
{
    public function test_it_calculates_a_valid_codice_fiscale()
    {
        // Mock Belfiore code finder to return H501 for Roma
        $mock = Mockery::mock(FindBelfioreCode::class, function (MockInterface $mock) {
            $mock->shouldReceive('execute')->with('Roma')->andReturn('H501');
        });

        $calculator = new CalculateCodiceFiscale($mock);

        $cf = $calculator->execute([
            'firstname' => 'Mario',
            'lastname' => 'Rossi',
            'dob' => '1990-01-01',
            'gender' => 'M',
            'pob' => 'Roma',
        ]);

        $this->assertEquals('RSSMRA90A01H501W', $cf);
    }

    public function test_it_calculates_a_valid_codice_fiscale_for_females()
    {
        // Mock Belfiore code finder to return A952 for Bozen
        $mock = Mockery::mock(FindBelfioreCode::class, function (MockInterface $mock) {
            $mock->shouldReceive('execute')->with('Bozen')->andReturn('A952');
        });

        $calculator = new CalculateCodiceFiscale($mock);

        $cf = $calculator->execute([
            'firstname' => 'Maria',
            'lastname' => 'Maier',
            'dob' => '1985-05-15',
            'gender' => 'F',
            'pob' => 'Bozen',
        ]);

        $this->assertEquals('MRAMRA85E55A952I', $cf);
    }

    public function test_it_returns_null_when_required_fields_are_missing()
    {
        $calculator = new CalculateCodiceFiscale();
        
        $cf = $calculator->execute([
            'firstname' => 'Mario',
            // 'lastname' missing
            'dob' => '1990-01-01',
            'gender' => 'M',
            'pob' => 'Roma',
        ]);

        $this->assertNull($cf);
    }
}
