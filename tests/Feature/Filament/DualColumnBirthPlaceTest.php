<?php

namespace Kreatif\CodiceFiscale\Tests\Feature\Filament;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Kreatif\CodiceFiscale\Filament\Forms\Traits\HasGeoLocationFilamentFields;
use Kreatif\CodiceFiscale\Tests\TestCase;

/**
 * Tests the common pattern where the Select stores an ID (municipality_id)
 * and the TextInput stores a free-text name (municipality_name) for foreign cities.
 */
class DualColumnBirthPlaceTest extends TestCase
{
    protected $trait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->trait = new class {
            use HasGeoLocationFilamentFields;
        };
    }

    public function test_select_uses_main_name_for_italy()
    {
        $fields = $this->trait::resolveMunicipalityField(
            get: fn ($f) => '*',
            name: 'birth_municipality_id',
            countryField: 'cob',
            freeTextInputName: 'birth_municipality',
        );

        $this->assertInstanceOf(Select::class, $fields[0]);
        $this->assertEquals('birth_municipality_id', $fields[0]->getName());
    }

    public function test_text_input_uses_free_text_name_for_foreign_country()
    {
        $fields = $this->trait::resolveMunicipalityField(
            get: fn ($f) => 'Z336',
            name: 'birth_municipality_id',
            countryField: 'cob',
            freeTextInputName: 'birth_municipality',
        );

        $this->assertInstanceOf(TextInput::class, $fields[0]);
        $this->assertEquals('birth_municipality', $fields[0]->getName());
    }

    public function test_text_input_falls_back_to_main_name_when_free_text_name_not_given()
    {
        $fields = $this->trait::resolveMunicipalityField(
            get: fn ($f) => 'Z336',
            name: 'birth_municipality_id',
            countryField: 'cob',
            // freeTextInputName not provided → falls back to $name
        );

        $this->assertInstanceOf(TextInput::class, $fields[0]);
        $this->assertEquals('birth_municipality_id', $fields[0]->getName());
    }

    public function test_dual_column_pattern_with_modify_knows_which_field_is_active()
    {
        $activeFieldName = null;

        // Foreign country → TextInput with custom name
        $this->trait::resolveMunicipalityField(
            get: fn ($f) => 'Z336',
            name: 'birth_municipality_id',
            countryField: 'cob',
            freeTextInputName: 'birth_municipality',
            modify: function ($field) use (&$activeFieldName) {
                $activeFieldName = $field->getName();
            },
        );

        $this->assertEquals('birth_municipality', $activeFieldName);
    }

    public function test_get_filament_dropdown_for_municipality_returns_group()
    {
        $group = $this->trait::getFilamentDropdownForMunicipality(
            name: 'birth_municipality_id',
            countryField: 'cob',
            freeTextInputName: 'birth_municipality',
        );

        $this->assertInstanceOf(Group::class, $group);
    }
}
