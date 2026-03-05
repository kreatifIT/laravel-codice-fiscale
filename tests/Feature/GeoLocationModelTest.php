<?php

namespace Kreatif\CodiceFiscale\Tests\Feature;

use Kreatif\CodiceFiscale\Models\GeoLocation;
use Kreatif\CodiceFiscale\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GeoLocationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_and_query_geo_locations()
    {
        GeoLocation::create([
            'denominazione' => 'Roma',
            'codice_catastale' => 'H501',
            'item_type' => 'comune',
            'is_foreign_state' => false,
        ]);

        $comune = GeoLocation::where('denominazione', 'Roma')->first();
        
        $this->assertEquals('H501', $comune->codice_catastale);
    }

    public function test_it_correctly_filters_comuni_and_foreign_states_via_scopes()
    {
        GeoLocation::create([
            'denominazione' => 'Milano',
            'codice_catastale' => 'F205',
            'item_type' => 'comune',
            'is_foreign_state' => false,
        ]);

        GeoLocation::create([
            'denominazione' => 'Germany',
            'codice_catastale' => 'Z112',
            'item_type' => 'stato',
            'is_foreign_state' => true,
        ]);

        $this->assertEquals(1, GeoLocation::comuni()->count());
        $this->assertEquals('Milano', GeoLocation::comuni()->first()->denominazione);
        
        $this->assertEquals(1, GeoLocation::foreignStates()->count());
        $this->assertEquals('Germany', GeoLocation::foreignStates()->first()->denominazione);
    }

    public function test_it_searches_options_with_ranking()
    {
        GeoLocation::create([
            'denominazione' => 'Bolzano',
            'denominazione_de' => 'Bozen',
            'codice_catastale' => 'A952',
            'item_type' => 'comune',
        ]);
        
        GeoLocation::create([
            'denominazione' => 'Bologna',
            'codice_catastale' => 'A944',
            'item_type' => 'comune',
        ]);

        $results = GeoLocation::searchOptions('Bozen');
        
        $this->assertEquals(1, $results->count());
        $this->assertEquals('Bolzano', $results->first()->denominazione);
        
        $results = GeoLocation::searchOptions('Bol');
        $this->assertEquals(2, $results->count());
        $this->assertEquals('Bolzano', $results->first()->denominazione);
    }

    public function test_it_handles_validity_dates_correctly()
    {
        GeoLocation::create([
            'denominazione' => 'Old City',
            'codice_catastale' => 'XXXX',
            'item_type' => 'comune',
            'valid_to' => now()->subDay(),
        ]);
        
        GeoLocation::create([
            'denominazione' => 'Active City',
            'codice_catastale' => 'YYYY',
            'item_type' => 'comune',
            'valid_from' => now()->subDay(),
        ]);

        $this->assertEquals(1, GeoLocation::valid()->count());
        $this->assertEquals('Active City', GeoLocation::valid()->first()->denominazione);
    }
}
