<?php

namespace Kreatif\CodiceFiscale\Tests\Unit;

use Kreatif\CodiceFiscale\Actions\ValidateCodiceFiscale;
use Kreatif\CodiceFiscale\Actions\FindBelfioreCode;
use Kreatif\CodiceFiscale\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;

class CodiceFiscaleValidatorTest extends TestCase
{
    public function test_it_validates_correct_codice_fiscale_format()
    {
        $validator = new ValidateCodiceFiscale();
        
        $this->assertTrue($validator->execute('RSSMRA90A01H501W'));
        $this->assertTrue($validator->execute('MRAMRA85E55A952I'));
    }

    public function test_it_fails_invalid_codice_fiscale_format()
    {
        $validator = new ValidateCodiceFiscale();
        
        $this->assertFalse($validator->execute('INVALID'));
        $this->assertFalse($validator->execute('RSSMRA90A01H501Z')); // Wrong checksum
    }

    public function test_it_validates_strict_matching_data()
    {
        $mock = Mockery::mock(FindBelfioreCode::class, function (MockInterface $mock) {
            $mock->shouldReceive('execute')->with('Roma')->andReturn('H501');
        });
        
        // Bind the mock to the container so app(CalculateCodiceFiscale::class) uses it
        $this->app->instance(FindBelfioreCode::class, $mock);

        $validator = new ValidateCodiceFiscale();

        $isValid = $validator->executeStrict('RSSMRA90A01H501W', [
            'firstname' => 'Mario',
            'lastname' => 'Rossi',
            'dob' => '1990-01-01',
            'gender' => 'M',
            'pob' => 'Roma',
        ]);

        $this->assertTrue($isValid);
    }

    public function test_it_fails_strict_matching_when_data_differs()
    {
        $mock = Mockery::mock(FindBelfioreCode::class, function (MockInterface $mock) {
            $mock->shouldReceive('execute')->with('Roma')->andReturn('H501');
        });
        
        $this->app->instance(FindBelfioreCode::class, $mock);

        $validator = new ValidateCodiceFiscale();

        // Wrong firstname (Mario vs Giuseppe)
        $isValid = $validator->executeStrict('RSSMRA90A01H501W', [
            'firstname' => 'Giuseppe',
            'lastname' => 'Rossi',
            'dob' => '1990-01-01',
            'gender' => 'M',
            'pob' => 'Roma',
        ]);

        $this->assertFalse($isValid);
    }

    public function test_it_fails_strict_validation_on_data_mismatches()
    {
        $mock = Mockery::mock(FindBelfioreCode::class);
        $mock->shouldReceive('execute')->andReturn('H501');
        $this->app->instance(FindBelfioreCode::class, $mock);

        $validator = new ValidateCodiceFiscale();
        $baseData = [
            'firstname' => 'Mario',
            'lastname' => 'Rossi',
            'dob' => '1990-01-01',
            'gender' => 'M',
            'pob' => 'Roma',
        ];
        $cf = 'RSSMRA90A01H501W';

        // Wrong Surname
        $this->assertFalse($validator->executeStrict($cf, array_merge($baseData, ['lastname' => 'Bianchi'])));
        
        // Wrong Gender
        $this->assertFalse($validator->executeStrict($cf, array_merge($baseData, ['gender' => 'F'])));
        
        // Wrong DOB
        $this->assertFalse($validator->executeStrict($cf, array_merge($baseData, ['dob' => '1990-01-02'])));
        
        // Invalid CF format
        $this->assertFalse($validator->executeStrict('INVALID', $baseData));
        
        // Empty CF
        $this->assertFalse($validator->executeStrict('', $baseData));
    }

    public function test_it_returns_validation_details()
    {
        $validator = new ValidateCodiceFiscale();
        
        // Valid case
        $details = $validator->getValidationDetails('RSSMRA90A01H501W');
        $this->assertTrue($details['valid']);
        $this->assertTrue($details['format_valid']);
        $this->assertTrue($details['checksum_valid']);
        $this->assertEmpty($details['errors']);

        // Invalid format (too short)
        $details = $validator->getValidationDetails('RSSMRA90A01');
        $this->assertFalse($details['valid']);
        $this->assertFalse($details['format_valid']);
        
        // Invalid checksum
        $details = $validator->getValidationDetails('RSSMRA90A01H501Z');
        $this->assertFalse($details['valid']);
        $this->assertTrue($details['format_valid']);
        $this->assertFalse($details['checksum_valid']);
        $this->assertContains('Invalid checksum', $details['errors']);
    }
}
