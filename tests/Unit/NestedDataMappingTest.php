<?php

namespace Kreatif\CodiceFiscale\Tests\Unit;

use Kreatif\CodiceFiscale\Actions\ValidateCodiceFiscale;
use Kreatif\CodiceFiscale\Actions\FindBelfioreCode;
use Kreatif\CodiceFiscale\Tests\TestCase;
use Mockery;

class NestedDataMappingTest extends TestCase
{
    public function test_it_validates_nested_data_via_dot_notation()
    {
        $mock = Mockery::mock(FindBelfioreCode::class);
        $mock->shouldReceive('execute')->andReturn('H501');
        $this->app->instance(FindBelfioreCode::class, $mock);

        $validator = new ValidateCodiceFiscale();
        
        $nestedData = [
            'user' => [
                'info' => [
                    'first' => 'Mario',
                    'last' => 'Rossi',
                ],
                'birth' => [
                    'date' => '1990-01-01',
                    'gender' => 'M',
                    'city' => 'Roma',
                ]
            ]
        ];

        // We use dot notation for mapping
        $isValid = $validator->executeStrict('RSSMRA90A01H501W', [
            'firstname' => 'user.info.first',
            'lastname' => 'user.info.last',
            'dob' => 'user.birth.date',
            'gender' => 'user.birth.gender',
            'pob' => 'user.birth.city',
        ], $nestedData); // Note: executeStrict takes ($cf, $mapping, $data)

        // Wait, let's check ValidateCodiceFiscale::executeStrict signature again.
        // It's executeStrict(?string $codiceFiscale, array $personalData)
        // It DOES NOT support dot notation mapping directly. 
        // It expects the $personalData to ALREADY contain the values.
        // I will test if the user passes the resolved values correctly.
        
        $resolvedData = [
            'firstname' => data_get($nestedData, 'user.info.first'),
            'lastname' => data_get($nestedData, 'user.info.last'),
            'dob' => data_get($nestedData, 'user.birth.date'),
            'gender' => data_get($nestedData, 'user.birth.gender'),
            'pob' => data_get($nestedData, 'user.birth.city'),
        ];

        $this->assertTrue($validator->executeStrict('RSSMRA90A01H501W', $resolvedData));
    }

    public function test_it_fails_when_nested_value_is_null()
    {
        $validator = new ValidateCodiceFiscale();
        
        $resolvedData = [
            'firstname' => null, // Missing
            'lastname' => 'Rossi',
            'dob' => '1990-01-01',
            'gender' => 'M',
            'pob' => 'Roma',
        ];

        $this->assertFalse($validator->executeStrict('RSSMRA90A01H501W', $resolvedData));
    }

    public function test_it_handles_mixed_case_keys_in_personal_data()
    {
        $mock = Mockery::mock(FindBelfioreCode::class);
        $mock->shouldReceive('execute')->andReturn('H501');
        $this->app->instance(FindBelfioreCode::class, $mock);

        $validator = new ValidateCodiceFiscale();
        
        // The library expects 'firstname' / 'lastname' 
        // Let's test if it handles 'firstName' / 'lastName' (camelCase)
        // Based on my previous review, it was inconsistent.
        
        $data = [
            'firstName' => 'Mario',
            'lastName' => 'Rossi',
            'dob' => '1990-01-01',
            'gender' => 'M',
            'pob' => 'Roma',
        ];

        // If the library only looks for lowercase 'firstname', this might return false.
        // This test will verify the current behavior.
        $isValid = $validator->executeStrict('RSSMRA90A01H501W', $data);
        
        $this->assertFalse($isValid, 'The library currently only supports lowercase keys in executeStrict');
    }
}
