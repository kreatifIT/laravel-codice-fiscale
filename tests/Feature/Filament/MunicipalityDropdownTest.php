<?php

namespace Kreatif\CodiceFiscale\Tests\Feature\Filament;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreatif\CodiceFiscale\Filament\Forms\Traits\HasGeoLocationFilamentFields;
use Kreatif\CodiceFiscale\Models\GeoLocation;
use Kreatif\CodiceFiscale\Tests\TestCase;

class MunicipalityDropdownTest extends TestCase
{
    use RefreshDatabase;

    protected $trait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->trait = new class {
            use HasGeoLocationFilamentFields;
        };
    }

    // -------------------------------------------------------------------------
    // getFilamentDropdownForMunicipality — returns Group
    // -------------------------------------------------------------------------

    public function test_returns_group_instance()
    {
        $group = $this->trait::getFilamentDropdownForMunicipality('pob');
        $this->assertInstanceOf(Group::class, $group);
    }

    // -------------------------------------------------------------------------
    // resolveMunicipalityField — Select vs TextInput switching
    // -------------------------------------------------------------------------

    public function test_returns_select_when_no_country_dependency()
    {
        $fields = $this->trait::resolveMunicipalityField(
            get: fn ($field) => null,
            name: 'pob',
        );

        $this->assertCount(1, $fields);
        $this->assertInstanceOf(Select::class, $fields[0]);
        $this->assertEquals('pob', $fields[0]->getName());
    }

    public function test_returns_select_when_country_is_null()
    {
        $fields = $this->trait::resolveMunicipalityField(
            get: fn ($field) => null,   // no country selected yet
            name: 'pob',
            countryField: 'cob',
        );

        $this->assertCount(1, $fields);
        $this->assertInstanceOf(Select::class, $fields[0]);
    }

    public function test_returns_select_when_country_is_italy()
    {
        $fields = $this->trait::resolveMunicipalityField(
            get: fn ($field) => '*',    // Italy codice_catastale
            name: 'pob',
            countryField: 'cob',
            italyValue: '*',
        );

        $this->assertCount(1, $fields);
        $this->assertInstanceOf(Select::class, $fields[0]);
    }

    public function test_returns_text_input_when_country_is_foreign()
    {
        $fields = $this->trait::resolveMunicipalityField(
            get: fn ($field) => 'Z336',  // Germany
            name: 'pob',
            countryField: 'cob',
            italyValue: '*',
        );

        $this->assertCount(1, $fields);
        $this->assertInstanceOf(TextInput::class, $fields[0]);
        $this->assertEquals('pob', $fields[0]->getName());
    }

    public function test_free_text_input_uses_custom_name_when_provided()
    {
        $fields = $this->trait::resolveMunicipalityField(
            get: fn ($field) => 'Z336',
            name: 'municipality_id',
            countryField: 'cob',
            italyValue: '*',
            freeTextInputName: 'municipality_name',
        );

        $this->assertCount(1, $fields);
        $this->assertInstanceOf(TextInput::class, $fields[0]);
        $this->assertEquals('municipality_name', $fields[0]->getName());
    }

    public function test_select_uses_name_even_when_free_text_name_differs()
    {
        $fields = $this->trait::resolveMunicipalityField(
            get: fn ($field) => '*',    // Italy → Select
            name: 'municipality_id',
            countryField: 'cob',
            italyValue: '*',
            freeTextInputName: 'municipality_name',
        );

        $this->assertCount(1, $fields);
        $this->assertInstanceOf(Select::class, $fields[0]);
        $this->assertEquals('municipality_id', $fields[0]->getName());
    }

    // -------------------------------------------------------------------------
    // $modify closure — called in both Select and TextInput cases
    // -------------------------------------------------------------------------

    public function test_modify_closure_is_called_for_select_no_country()
    {
        $called = false;

        $this->trait::resolveMunicipalityField(
            get: fn ($field) => null,
            name: 'pob',
            modify: function () use (&$called) {
                $called = true;
            },
        );

        $this->assertTrue($called, '$modify was not called when no country dependency');
    }

    public function test_modify_closure_is_called_for_select_italy()
    {
        $called = false;

        $this->trait::resolveMunicipalityField(
            get: fn ($field) => '*',
            name: 'pob',
            countryField: 'cob',
            modify: function () use (&$called) {
                $called = true;
            },
        );

        $this->assertTrue($called, '$modify was not called for Italy (Select case)');
    }

    public function test_modify_closure_is_called_for_text_input_foreign()
    {
        $called = false;

        $this->trait::resolveMunicipalityField(
            get: fn ($field) => 'Z336',
            name: 'pob',
            countryField: 'cob',
            modify: function () use (&$called) {
                $called = true;
            },
        );

        $this->assertTrue($called, '$modify was not called for foreign country (TextInput case)');
    }

    // -------------------------------------------------------------------------
    // $modify closure — named injections
    // -------------------------------------------------------------------------

    public function test_modify_receives_field_injection()
    {
        $capturedField = null;

        $this->trait::resolveMunicipalityField(
            get: fn ($f) => null,
            name: 'pob',
            modify: function ($field) use (&$capturedField) {
                $capturedField = $field;
            },
        );

        $this->assertNotNull($capturedField);
        $this->assertInstanceOf(Select::class, $capturedField);
    }

    public function test_modify_receives_country_value_injection()
    {
        $capturedCountryValue = 'NOT_SET';

        $this->trait::resolveMunicipalityField(
            get: fn ($f) => 'Z336',
            name: 'pob',
            countryField: 'cob',
            modify: function ($countryValue) use (&$capturedCountryValue) {
                $capturedCountryValue = $countryValue;
            },
        );

        $this->assertEquals('Z336', $capturedCountryValue);
    }

    public function test_modify_receives_italy_value_injection()
    {
        $capturedItalyValue = 'NOT_SET';

        $this->trait::resolveMunicipalityField(
            get: fn ($f) => '*',
            name: 'pob',
            countryField: 'cob',
            italyValue: '*',
            modify: function ($italyValue) use (&$capturedItalyValue) {
                $capturedItalyValue = $italyValue;
            },
        );

        $this->assertEquals('*', $capturedItalyValue);
    }

    public function test_modify_receives_is_italy_true_when_italy_selected()
    {
        $capturedIsItaly = null;

        $this->trait::resolveMunicipalityField(
            get: fn ($f) => '*',
            name: 'pob',
            countryField: 'cob',
            modify: function ($isItaly) use (&$capturedIsItaly) {
                $capturedIsItaly = $isItaly;
            },
        );

        $this->assertTrue($capturedIsItaly);
    }

    public function test_modify_receives_is_italy_false_when_foreign_selected()
    {
        $capturedIsItaly = null;

        $this->trait::resolveMunicipalityField(
            get: fn ($f) => 'Z336',
            name: 'pob',
            countryField: 'cob',
            modify: function ($isItaly) use (&$capturedIsItaly) {
                $capturedIsItaly = $isItaly;
            },
        );

        $this->assertFalse($capturedIsItaly);
    }

    public function test_modify_receives_is_italy_false_when_no_country_field()
    {
        $capturedIsItaly = null;

        $this->trait::resolveMunicipalityField(
            get: fn ($f) => null,
            name: 'pob',
            countryField: null,     // no country dependency
            modify: function ($isItaly) use (&$capturedIsItaly) {
                $capturedIsItaly = $isItaly;
            },
        );

        $this->assertFalse($capturedIsItaly);
    }

    // -------------------------------------------------------------------------
    // $modify closure — mutation vs replacement
    // -------------------------------------------------------------------------

    public function test_modify_can_mutate_select_in_place()
    {
        $fields = $this->trait::resolveMunicipalityField(
            get: fn ($f) => null,
            name: 'pob',
            modify: function (Select $field) {
                $field->required();
                // no return — mutation
            },
        );

        $this->assertInstanceOf(Select::class, $fields[0]);
        $this->assertTrue($fields[0]->isRequired());
    }

    public function test_modify_can_mutate_text_input_in_place()
    {
        $fields = $this->trait::resolveMunicipalityField(
            get: fn ($f) => 'Z336',
            name: 'pob',
            countryField: 'cob',
            modify: function (TextInput $field) {
                $field->required();
            },
        );

        $this->assertInstanceOf(TextInput::class, $fields[0]);
        $this->assertTrue($fields[0]->isRequired());
    }

    public function test_modify_can_replace_field_by_returning_component()
    {
        $replacement = TextInput::make('custom_pob')->maxLength(200);

        $fields = $this->trait::resolveMunicipalityField(
            get: fn ($f) => null,
            name: 'pob',
            modify: function () use ($replacement) {
                return $replacement; // replace the default Select with a custom TextInput
            },
        );

        $this->assertCount(1, $fields);
        $this->assertSame($replacement, $fields[0]);
        $this->assertEquals('custom_pob', $fields[0]->getName());
    }

    // -------------------------------------------------------------------------
    // Italy lookup via ID (valueColumn = 'id')
    // -------------------------------------------------------------------------

    public function test_uses_db_lookup_for_italy_when_value_column_is_id()
    {
        // Create Italy in the database
        $italy = GeoLocation::create([
            'item_type'         => config('codice-fiscale.item_types.stato'),
            'denominazione'     => 'Italia',
            'denominazione_en'  => 'Italy',
            'denominazione_de'  => 'Italien',
            'codice_catastale'  => '*',
            'is_foreign_state'  => false,
        ]);

        // User passes Italy's numeric ID as the country value, using valueColumn = 'id'
        $italyId = (string) $italy->id;

        $fields = $this->trait::resolveMunicipalityField(
            get: fn ($f) => $italyId,
            name: 'pob',
            countryField: 'cob',
            italyValue: '*',            // still '*' — triggers the DB lookup
            valueColumn: 'id',
        );

        // Should resolve to Select (Italy), not TextInput
        $this->assertInstanceOf(Select::class, $fields[0]);
    }

    public function test_returns_text_input_for_foreign_country_when_value_column_is_id()
    {
        GeoLocation::create([
            'item_type'         => config('codice-fiscale.item_types.stato'),
            'denominazione'     => 'Italia',
            'denominazione_en'  => 'Italy',
            'denominazione_de'  => 'Italien',
            'codice_catastale'  => '*',
            'is_foreign_state'  => false,
        ]);

        $fields = $this->trait::resolveMunicipalityField(
            get: fn ($f) => '999',      // some non-Italy ID
            name: 'pob',
            countryField: 'cob',
            italyValue: '*',
            valueColumn: 'id',
        );

        $this->assertInstanceOf(TextInput::class, $fields[0]);
    }
}
