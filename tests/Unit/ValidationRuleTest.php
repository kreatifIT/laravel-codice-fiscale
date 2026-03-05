<?php

namespace Kreatif\CodiceFiscale\Tests\Unit;

use Kreatif\CodiceFiscale\Rules\ValidCodiceFiscale;
use Kreatif\CodiceFiscale\Rules\CodiceFiscaleMatchesData;
use Kreatif\CodiceFiscale\Actions\FindBelfioreCode;
use Kreatif\CodiceFiscale\Tests\TestCase;
use Illuminate\Support\Facades\Validator;
use Mockery;

class ValidationRuleTest extends TestCase
{
    public function test_it_validates_codice_fiscale_via_laravel_validator()
    {
        $v = Validator::make(
            ['cf' => 'RSSMRA90A01H501W'],
            ['cf' => new ValidCodiceFiscale()]
        );
        $this->assertTrue($v->passes());

        $v = Validator::make(
            ['cf' => 'INVALID'],
            ['cf' => new ValidCodiceFiscale()]
        );
        $this->assertFalse($v->passes());
    }

    public function test_it_validates_matching_data_via_laravel_validator()
    {
        $mock = Mockery::mock(FindBelfioreCode::class);
        $mock->shouldReceive('execute')->with('Roma')->andReturn('H501');
        $this->app->instance(FindBelfioreCode::class, $mock);

        $data = [
            'firstname' => 'Mario',
            'lastname' => 'Rossi',
            'dob' => '1990-01-01',
            'gender' => 'M',
            'pob' => 'Roma',
            'cf' => 'RSSMRA90A01H501W',
        ];

        $v = Validator::make($data, [
            'cf' => CodiceFiscaleMatchesData::strict([
                'firstname' => 'firstname',
                'lastname' => 'lastname',
                'dob' => 'dob',
                'gender' => 'gender',
                'pob' => 'pob',
            ])
        ]);

        $this->assertTrue($v->passes());
    }
}
