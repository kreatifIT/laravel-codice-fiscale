<?php

namespace Kreatif\CodiceFiscale\Tests\Feature;

use Kreatif\CodiceFiscale\Models\GeoLocation;
use Kreatif\CodiceFiscale\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

class SyncGeoLocationsTest extends TestCase
{
    use RefreshDatabase;

    protected string $tempCsv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempCsv = tempnam(sys_get_temp_dir(), 'cf_test_') . '.csv';
    }

    protected function tearDown(): void
    {
        if (File::exists($this->tempCsv)) {
            File::delete($this->tempCsv);
        }
        parent::tearDown();
    }

    public function test_it_syncs_municipalities_from_csv()
    {
        $csvContent = "denominazione,sigla_provincia,codat,nascita\n";
        $csvContent .= "ROMA,RM,H501,S\n";
        $csvContent .= "MILANO,MI,F205,S\n";
        $csvContent .= "OLD_CITY,XX,XXXX,N\n"; 

        File::put($this->tempCsv, $csvContent);

        config()->set('codice-fiscale.data_sources.csv.comune', [
            'driver' => 'csv',
            'source_type' => 'file',
            'source' => $this->tempCsv,
            'mapping' => [
                'denominazione' => ['column' => 'denominazione'],
                'sigla_provincia' => ['column' => 'sigla_provincia'],
                'codice_catastale' => ['column' => 'codat'],
                'nascita' => ['column' => 'nascita', 'transform' => 'bool_s_n'],
            ],
            'options' => ['header_row' => true, 'delimiter' => ','],
            'defaults' => [],
        ]);

        $this->artisan('codice-fiscale:sync-geo-locations', [
            '--type' => 'comune',
            '--source' => 'csv',
            '--no-truncate' => true,
        ])->assertSuccessful();

        $this->assertEquals(2, GeoLocation::comuni()->count());
        $this->assertDatabaseHas('geo_locations', [
            'denominazione' => 'Roma',
            'codice_catastale' => 'H501',
            'sigla_provincia' => 'RM'
        ]);
    }

    public function test_it_syncs_foreign_states_and_adds_italy()
    {
        $csvContent = "denominazione,codat,nascita\n";
        $csvContent .= "GERMANIA,Z112,S\n";
        $csvContent .= "FRANCIA,Z110,S\n";

        File::put($this->tempCsv, $csvContent);

        config()->set('codice-fiscale.data_sources.csv.stato', [
            'driver' => 'csv',
            'source_type' => 'file',
            'source' => $this->tempCsv,
            'mapping' => [
                'denominazione' => ['column' => 'denominazione'],
                'codice_catastale' => ['column' => 'codat'],
                'nascita' => ['column' => 'nascita', 'transform' => 'bool_s_n'],
            ],
            'options' => ['header_row' => true, 'delimiter' => ','],
            'defaults' => [],
        ]);

        $this->artisan('codice-fiscale:sync-geo-locations', [
            '--type' => 'stato',
            '--source' => 'csv',
            '--no-truncate' => true,
        ])->assertSuccessful();

        // 2 from CSV + 1 (Italia with *) = 3
        $this->assertEquals(3, GeoLocation::count());
        $this->assertDatabaseHas('geo_locations', [
            'codice_catastale' => '*',
            'denominazione' => 'Italia'
        ]);
        $this->assertDatabaseHas('geo_locations', [
            'codice_catastale' => 'Z112',
            'denominazione' => 'Germania'
        ]);
    }

    public function test_it_updates_existing_records_via_upsert()
    {
        // 1. First sync with one name
        $csvContent = "denominazione,codat,nascita\n";
        $csvContent .= "FRANCE,Z110,S\n";
        File::put($this->tempCsv, $csvContent);

        config()->set('codice-fiscale.data_sources.csv.stato', [
            'driver' => 'csv',
            'source_type' => 'file',
            'source' => $this->tempCsv,
            'mapping' => ['denominazione' => ['column' => 'denominazione'], 'codice_catastale' => ['column' => 'codat'], 'nascita' => ['column' => 'nascita', 'transform' => 'bool_s_n']],
            'options' => ['header_row' => true, 'delimiter' => ','],
            'defaults' => [],
        ]);

        $this->artisan('codice-fiscale:sync-geo-locations', ['--type' => 'stato', '--source' => 'csv', '--no-truncate' => true])->execute();
        $this->assertDatabaseHas('geo_locations', ['codice_catastale' => 'Z110', 'denominazione' => 'France']);

        // 2. Second sync with CHANGED name for same code
        $csvContent = "denominazione,codat,nascita\n";
        $csvContent .= "FRANCIA,Z110,S\n";
        File::put($this->tempCsv, $csvContent);

        // We MUST ensure the sync command is configured to update on upsert
        config()->set('codice-fiscale.sync.upsert.update', ['denominazione']);

        $this->artisan('codice-fiscale:sync-geo-locations', ['--type' => 'stato', '--source' => 'csv', '--no-truncate' => true])->execute();

        // 3. Count should still be 2 (Francia + Italia) and name should be updated
        // Note: When sync type is stato, it also adds Italia (*) if not present. 
        $this->assertEquals(2, GeoLocation::where('item_type', 'stato')->count());
        $this->assertDatabaseHas('geo_locations', ['codice_catastale' => 'Z110', 'denominazione' => 'Francia']);
        $this->assertDatabaseMissing('geo_locations', ['denominazione' => 'France']);
    }
}
