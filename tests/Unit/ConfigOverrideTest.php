<?php

namespace Kreatif\CodiceFiscale\Tests\Unit;

use Kreatif\CodiceFiscale\Models\GeoLocation;
use Kreatif\CodiceFiscale\Tests\TestCase;

class ConfigOverrideTest extends TestCase
{
    public function test_it_respects_custom_table_name_from_config()
    {
        config()->set('codice-fiscale.geo_locations_table', 'custom_geo_table');
        
        $model = new GeoLocation();
        $this->assertEquals('custom_geo_table', $model->getTable());
    }

    public function test_it_respects_custom_model_class_from_config()
    {
        // This is used in traits to resolve the model
        config()->set('codice-fiscale.geo_locations_model', 'App\Models\MyGeoModel');
        
        $traitOwner = new class {
            use \Kreatif\CodiceFiscale\Filament\Forms\Traits\HasGeoLocationFilamentFields {
                getGeoLocationModel as public;
            }
        };

        $this->assertEquals('App\Models\MyGeoModel', $traitOwner->getGeoLocationModel());
    }
}
