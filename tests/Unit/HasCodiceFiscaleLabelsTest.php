<?php

namespace Kreatif\CodiceFiscale\Tests\Unit;

use Kreatif\CodiceFiscale\Filament\Forms\Traits\HasCodiceFiscaleLabels;
use Kreatif\CodiceFiscale\Tests\TestCase;

class HasCodiceFiscaleLabelsTest extends TestCase
{
    public function test_it_returns_translated_labels()
    {
        $traitOwner = new class {
            use HasCodiceFiscaleLabels;
        };

        // We check if the translated label matches the expected translation key result
        // __() will return the key if the translation file isn't loaded/found,
        // but our ServiceProvider loads them.
        $this->assertEquals(
            __('codice-fiscale::codice-fiscale.fields.firstname'),
            $traitOwner::getFirstnameLabel()
        );

        $this->assertEquals(
            __('codice-fiscale::codice-fiscale.fields.lastname'),
            $traitOwner::getLastnameLabel()
        );
    }
}
