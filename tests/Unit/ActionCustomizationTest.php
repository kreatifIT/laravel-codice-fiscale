<?php

namespace Kreatif\CodiceFiscale\Tests\Unit;

use Kreatif\CodiceFiscale\Filament\Forms\Components\CodiceFiscale;
use Kreatif\CodiceFiscale\Tests\TestCase;

class ActionCustomizationTest extends TestCase
{
    public function test_it_can_set_custom_finder_class()
    {
        $component = CodiceFiscale::make('cf')->usingFinder('MyCustomFinder');
        
        $reflector = new \ReflectionClass($component);
        $property = $reflector->getProperty('finderActionClass');
        $property->setAccessible(true);
        
        $this->assertEquals('MyCustomFinder', $property->getValue($component));
    }

    public function test_it_can_set_custom_calculator_closure()
    {
        $closure = fn() => 'CUSTOM_CF';
        $component = CodiceFiscale::make('cf')->usingCalculator($closure);
        
        $reflector = new \ReflectionClass($component);
        $property = $reflector->getProperty('calculatorActionClass');
        $property->setAccessible(true);
        
        $this->assertSame($closure, $property->getValue($component));
    }

    public function test_it_can_set_custom_validator_class()
    {
        $component = CodiceFiscale::make('cf')->usingValidator('MyCustomValidator');
        
        $reflector = new \ReflectionClass($component);
        $property = $reflector->getProperty('validatorActionClass');
        $property->setAccessible(true);
        
        $this->assertEquals('MyCustomValidator', $property->getValue($component));
    }
}
