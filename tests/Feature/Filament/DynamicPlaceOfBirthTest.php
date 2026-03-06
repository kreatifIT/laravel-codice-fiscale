<?php

namespace Kreatif\CodiceFiscale\Tests\Feature\Filament;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Kreatif\CodiceFiscale\Filament\Forms\Traits\HasUserBasicFilamentFields;
use Kreatif\CodiceFiscale\Tests\TestCase;

class DynamicPlaceOfBirthTest extends TestCase
{
    protected $trait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->trait = new class {
            use HasUserBasicFilamentFields;
        };
    }

    public function test_get_place_of_birth_field_returns_group()
    {
        $group = $this->trait::getPlaceOfBirthField('pob', 'cob');
        $this->assertInstanceOf(Group::class, $group);
    }

    public function test_pob_defaults_to_select_when_no_country_selected()
    {
        $fields = $this->trait::resolveMunicipalityField(
            get: fn ($f) => null,
            name: 'pob',
            countryField: 'cob',
        );

        $this->assertInstanceOf(Select::class, $fields[0]);
        $this->assertEquals('pob', $fields[0]->getName());
    }

    public function test_pob_is_select_when_italy_is_selected()
    {
        $fields = $this->trait::resolveMunicipalityField(
            get: fn ($f) => '*',
            name: 'pob',
            countryField: 'cob',
        );

        $this->assertInstanceOf(Select::class, $fields[0]);
    }

    public function test_pob_is_text_input_when_foreign_country_selected()
    {
        $fields = $this->trait::resolveMunicipalityField(
            get: fn ($f) => 'Z336',  // Germany
            name: 'pob',
            countryField: 'cob',
        );

        $this->assertInstanceOf(TextInput::class, $fields[0]);
        $this->assertEquals('pob', $fields[0]->getName());
    }

    public function test_text_input_for_foreign_country_has_max_length()
    {
        $fields = $this->trait::resolveMunicipalityField(
            get: fn ($f) => 'Z404',  // France
            name: 'pob',
            countryField: 'cob',
        );

        $this->assertInstanceOf(TextInput::class, $fields[0]);
        $this->assertEquals(150, $fields[0]->getMaxLength());
    }

    public function test_custom_pob_name_is_respected()
    {
        $fields = $this->trait::resolveMunicipalityField(
            get: fn ($f) => null,
            name: 'place_of_birth',
        );

        $this->assertEquals('place_of_birth', $fields[0]->getName());
    }

    public function test_custom_label_is_applied_to_select()
    {
        $fields = $this->trait::resolveMunicipalityField(
            get: fn ($f) => null,
            name: 'pob',
            label: 'Birth City',
        );

        $this->assertEquals('Birth City', $fields[0]->getLabel());
    }

    public function test_custom_label_is_applied_to_text_input()
    {
        $fields = $this->trait::resolveMunicipalityField(
            get: fn ($f) => 'Z336',
            name: 'pob',
            countryField: 'cob',
            label: 'Birth City',
        );

        $this->assertEquals('Birth City', $fields[0]->getLabel());
    }

    public function test_get_place_of_birth_field_accepts_custom_italy_value()
    {
        // Using a custom $italyValue (e.g. when country stored as code 'IT' instead of '*')
        $fields = $this->trait::resolveMunicipalityField(
            get: fn ($f) => 'IT',
            name: 'pob',
            countryField: 'cob',
            italyValue: 'IT',
        );

        $this->assertInstanceOf(Select::class, $fields[0], 'Should be Select when country matches custom italyValue');
    }
}
