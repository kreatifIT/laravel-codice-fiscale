<?php

namespace Kreatif\CodiceFiscale\Tests\Feature;

use Kreatif\CodiceFiscale\Models\GeoLocation;
use Kreatif\CodiceFiscale\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BilingualSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_finds_by_german_name_regardless_of_app_locale()
    {
        GeoLocation::create([
            'denominazione' => 'Bolzano',
            'denominazione_de' => 'Bozen',
            'codice_catastale' => 'A952',
            'item_type' => 'comune',
        ]);

        app()->setLocale('it');
        $results = GeoLocation::searchOptions('Bozen');
        $this->assertCount(1, $results);
        $this->assertEquals('Bolzano', $results->first()->denominazione);
    }

    public function test_search_ranking_prioritizes_shorter_matches()
    {
        GeoLocation::create([
            'denominazione' => 'Roma',
            'codice_catastale' => 'H501',
            'item_type' => 'comune',
        ]);
        
        GeoLocation::create([
            'denominazione' => 'Romagnano Sesia',
            'codice_catastale' => 'H502',
            'item_type' => 'comune',
        ]);

        $results = GeoLocation::searchOptions('Roma');
        
        $this->assertCount(2, $results);
        // "Roma" (4 chars) should be before "Romagnano Sesia" (15 chars)
        $this->assertEquals('Roma', $results->first()->denominazione);
    }

    public function test_label_generation_falls_back_to_italian()
    {
        $location = GeoLocation::create([
            'denominazione' => 'Milano',
            // 'denominazione_de' is NULL
            'codice_catastale' => 'F205',
            'item_type' => 'comune',
        ]);

        // When locale is 'de' but translation is missing
        $this->assertEquals('Milano', $location->getLabel('de'));
    }
}
