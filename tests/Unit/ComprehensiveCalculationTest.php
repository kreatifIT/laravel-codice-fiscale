<?php

namespace Kreatif\CodiceFiscale\Tests\Unit;

use Kreatif\CodiceFiscale\Actions\CalculateCodiceFiscale;
use Kreatif\CodiceFiscale\Actions\FindBelfioreCode;
use Kreatif\CodiceFiscale\Tests\TestCase;
use Mockery;

class ComprehensiveCalculationTest extends TestCase
{
    /**
     * @dataProvider calculationDataProvider
     */
    public function test_it_calculates_correctly_for_various_cases($firstname, $lastname, $dob, $gender, $pob, $expectedBelfiore, $expectedCf)
    {
        $mock = Mockery::mock(FindBelfioreCode::class);
        $mock->shouldReceive('execute')->with($pob)->andReturn($expectedBelfiore);
        $this->app->instance(FindBelfioreCode::class, $mock);

        $calculator = new CalculateCodiceFiscale();
        $cf = $calculator->execute([
            'firstname' => $firstname,
            'lastname' => $lastname,
            'dob' => $dob,
            'gender' => $gender,
            'pob' => $pob,
        ]);

        $this->assertEquals($expectedCf, $cf);
    }

    public static function calculationDataProvider(): array
    {
        return [
            'Short names (Po)' => [
                'Anna', 'Po', '1995-01-01', 'F', 'Roma', 'H501', 'POXNNA95A41H501W'
            ],
            'Many consonants (Gianfranco)' => [
                'Gianfranco', 'Bianchi', '1980-05-15', 'M', 'Milano', 'F205', 'BNCGFR80E15F205P'
            ],
            'Foreign Born (Germany)' => [
                'Hans', 'Schmidt', '1970-12-31', 'M', 'Germany', 'Z112', 'SCHHNS70T31Z112L'
            ],
            'Leap Year (Feb 29)' => [
                'Mario', 'Rossi', '2000-02-29', 'M', 'Roma', 'H501', 'RSSMRA00B29H501Y'
            ],
            'Recent Year (2024)' => [
                'Bimbo', 'Nuovo', '2024-03-10', 'M', 'Bolzano', 'A952', 'NVUBMB24C10A952U'
            ],
        ];
    }

    public function test_it_handles_accents_and_special_characters()
    {
        $mock = Mockery::mock(FindBelfioreCode::class);
        $mock->shouldReceive('execute')->andReturn('H501');
        $this->app->instance(FindBelfioreCode::class, $mock);

        $calculator = new CalculateCodiceFiscale();
        
        // "Niccolò" should be treated as "Niccolo"
        $cf = $calculator->execute([
            'firstname' => 'Niccolò',
            'lastname' => 'Rossi',
            'dob' => '1990-01-01',
            'gender' => 'M',
            'pob' => 'Roma',
        ]);

        $this->assertNotNull($cf);
        $this->assertEquals('RSSNCL90A01H501F', $cf);
    }
}
