<?php

namespace Kreatif\CodiceFiscale\Tests\Unit;

use Kreatif\CodiceFiscale\Actions\FindBelfioreCode;
use Kreatif\CodiceFiscale\Models\GeoLocation;
use Kreatif\CodiceFiscale\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BelfioreCodeLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_finds_belfiore_code_for_italian_municipality()
    {
        GeoLocation::create([
            'denominazione' => 'Roma',
            'codice_catastale' => 'H501',
            'item_type' => 'comune',
        ]);

        $finder = new FindBelfioreCode();
        $code = $finder->execute('Roma');

        $this->assertEquals('H501', $code);
    }

    public function test_it_finds_belfiore_code_by_german_name()
    {
        GeoLocation::create([
            'denominazione' => 'Bolzano',
            'denominazione_de' => 'Bozen',
            'codice_catastale' => 'A952',
            'item_type' => 'comune',
        ]);

        $finder = new FindBelfioreCode();
        $code = $finder->execute('Bozen');

        $this->assertEquals('A952', $code);
    }

    public function test_it_finds_belfiore_code_for_foreign_state_in_english()
    {
        GeoLocation::create([
            'denominazione' => 'Germania',
            'denominazione_en' => 'Germany',
            'codice_catastale' => 'Z112',
            'item_type' => 'stato',
            'is_foreign_state' => true,
        ]);

        $finder = new FindBelfioreCode();
        $code = $finder->execute('Germany');

        $this->assertEquals('Z112', $code);
    }

    public function test_it_returns_null_for_unknown_place()
    {
        $finder = new FindBelfioreCode();
        $code = $finder->execute('NonExistentPlace');

        $this->assertNull($code);
    }

    public function test_it_returns_the_code_if_it_exists_in_database()
    {
        GeoLocation::create([
            'denominazione' => 'Roma',
            'codice_catastale' => 'H501',
            'item_type' => 'comune',
        ]);

        $finder = new FindBelfioreCode();
        
        // Search by code directly
        $code = $finder->execute('H501');
        $this->assertEquals('H501', $code);

        // Search by lowercase code (should match if database is case-insensitive or exact match)
        // SQLite is case-sensitive for strings by default for some operations, 
        // but let's see how it behaves here with the model/query.
        $code = $finder->execute('h501');
        
        // If it's null, it means SQLite didn't match 'h501' with 'H501'.
        // The action does: ->where($this->codeColumn, $placeName)
    }
}
