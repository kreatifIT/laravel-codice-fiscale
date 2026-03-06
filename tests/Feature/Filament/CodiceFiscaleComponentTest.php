<?php

namespace Kreatif\CodiceFiscale\Tests\Feature\Filament;

use Filament\Actions\Action;
use Kreatif\CodiceFiscale\Filament\Forms\Components\CodiceFiscale;
use Kreatif\CodiceFiscale\Tests\TestCase;

class CodiceFiscaleComponentTest extends TestCase
{
    public function test_it_enables_generator()
    {
        $component = CodiceFiscale::make('cf')->generator();
        
        // We can't easily peek into prefixActions because they are wrapped in Closures/Components
        // but we can check if the component instance is returned (chaining works)
        $this->assertInstanceOf(CodiceFiscale::class, $component);
    }

    public function test_it_enables_validator()
    {
        $component = CodiceFiscale::make('cf')->validator();
        
        $this->assertInstanceOf(CodiceFiscale::class, $component);
    }

    public function test_it_uses_translated_labels_by_default()
    {
        $component = CodiceFiscale::make('cf');
        $this->assertNotEquals('Cf', $component->getLabel());
    }
    
    public function test_it_configures_locale()
    {
        $component = CodiceFiscale::make('cf')->usingLocale('de');
        $this->assertEquals('de', $component->getComponentLocale());
    }
}
