<?php

namespace Kreatif\CodiceFiscale\Tests\Feature;

use Kreatif\CodiceFiscale\Models\GeoLocation;
use Kreatif\CodiceFiscale\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TemporalValidityTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_scope_handles_past_records()
    {
        GeoLocation::create([
            'denominazione' => 'Historical City',
            'codice_catastale' => 'XXXX',
            'item_type' => 'comune',
            'valid_to' => now()->subYear(),
        ]);

        $this->assertEquals(0, GeoLocation::valid()->count());
    }

    public function test_valid_scope_handles_future_records()
    {
        GeoLocation::create([
            'denominazione' => 'Future City',
            'codice_catastale' => 'YYYY',
            'item_type' => 'comune',
            'valid_from' => now()->addYear(),
        ]);

        $this->assertEquals(0, GeoLocation::valid()->count());
    }

    public function test_valid_scope_handles_active_records()
    {
        GeoLocation::create([
            'denominazione' => 'Current City',
            'codice_catastale' => 'ZZZZ',
            'item_type' => 'comune',
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addDay(),
        ]);

        $this->assertEquals(1, GeoLocation::valid()->count());
    }
}
