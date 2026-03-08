<?php

namespace Kreatif\CodiceFiscale\Tests\Feature\Filament;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreatif\CodiceFiscale\Filament\Forms\Traits\HasGeoLocationFilamentFields;
use Kreatif\CodiceFiscale\Models\GeoLocation;
use Kreatif\CodiceFiscale\Tests\TestCase;

class DualColumnSupportTest extends TestCase
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

    /**
     * Extracts the child resolver Closure from a Group and calls it with the given $get.
     *
     * Group::make(function($get) {...}) stores the closure in $childComponents['default'].
     * Calling it directly with a fake $get lets us test the switching logic without
     * needing the full Filament schema lifecycle.
     *
     * @return array<int, \Filament\Forms\Components\Field>
     */
    private function resolveGroupFields(Group $group, callable $get): array
    {
        $ref = new \ReflectionProperty($group, 'childComponents');
        $ref->setAccessible(true);
        $components = $ref->getValue($group);

        $closure = $components['default'];

        return $closure($get);
    }

    // -------------------------------------------------------------------------
    // getGeoLocationSearchResults — $valueColumn
    // -------------------------------------------------------------------------

    public function test_search_results_keyed_by_codice_catastale_by_default(): void
    {
        GeoLocation::create([
            'item_type'        => 'comune',
            'denominazione'    => 'Roma',
            'codice_catastale' => 'H501',
            'is_foreign_state' => false,
        ]);

        $results = $this->trait::getGeoLocationSearchResults('Roma', 'comune', 5);

        $this->assertArrayHasKey('H501', $results);
        $this->assertEquals('Roma', $results['H501']);
    }

    public function test_search_results_keyed_by_id_when_value_column_is_id(): void
    {
        $comune = GeoLocation::create([
            'item_type'        => 'comune',
            'denominazione'    => 'Milano',
            'codice_catastale' => 'F205',
            'is_foreign_state' => false,
        ]);

        $results = $this->trait::getGeoLocationSearchResults('Milano', 'comune', 5, null, 'id');

        $this->assertArrayHasKey($comune->id, $results);
        $this->assertEquals('Milano', $results[$comune->id]);

        // Must NOT be keyed by codice_catastale
        $this->assertArrayNotHasKey('F205', $results);
    }

    public function test_search_results_multiple_results_all_keyed_by_id(): void
    {
        $roma   = GeoLocation::create(['item_type' => 'comune', 'denominazione' => 'Roma',   'codice_catastale' => 'H501', 'is_foreign_state' => false]);
        $romano = GeoLocation::create(['item_type' => 'comune', 'denominazione' => 'Romano', 'codice_catastale' => 'H509', 'is_foreign_state' => false]);

        $results = $this->trait::getGeoLocationSearchResults('Roma', 'comune', 10, null, 'id');

        $this->assertArrayHasKey($roma->id,   $results);
        $this->assertArrayHasKey($romano->id, $results);
        $this->assertArrayNotHasKey('H501', $results);
        $this->assertArrayNotHasKey('H509', $results);
    }

    public function test_search_results_returns_empty_array_for_no_match(): void
    {
        $results = $this->trait::getGeoLocationSearchResults('ZZZNOMATCH', 'comune', 5);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    // -------------------------------------------------------------------------
    // getFilamentDropdownForMunicipality — returns Group
    // -------------------------------------------------------------------------

    public function test_returns_group_instance(): void
    {
        $group = $this->trait->getFilamentDropdownForMunicipality('pob');

        $this->assertInstanceOf(Group::class, $group);
    }

    // -------------------------------------------------------------------------
    // Select vs TextInput switching
    // -------------------------------------------------------------------------

    public function test_select_when_no_country_field_dependency(): void
    {
        $fields = $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality('pob'),
            fn ($f) => null,
        );

        $this->assertCount(1, $fields);
        $this->assertInstanceOf(Select::class, $fields[0]);
        $this->assertEquals('pob', $fields[0]->getName());
    }

    public function test_select_when_country_is_italy(): void
    {
        $fields = $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality('pob', 'cob'),
            fn ($f) => '*',
        );

        $this->assertCount(1, $fields);
        $this->assertInstanceOf(Select::class, $fields[0]);
        $this->assertEquals('pob', $fields[0]->getName());
    }

    public function test_select_when_country_is_null(): void
    {
        $fields = $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality('pob', 'cob'),
            fn ($f) => null,
        );

        $this->assertCount(1, $fields);
        $this->assertInstanceOf(Select::class, $fields[0]);
    }

    public function test_text_input_when_country_is_foreign(): void
    {
        $fields = $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality('pob', 'cob'),
            fn ($f) => 'Z336', // Germany
        );

        $this->assertCount(1, $fields);
        $this->assertInstanceOf(TextInput::class, $fields[0]);
    }

    public function test_text_input_has_max_length_150(): void
    {
        $fields = $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality('pob', 'cob'),
            fn ($f) => 'Z336',
        );

        // maxLength(150) is set on the TextInput
        $this->assertInstanceOf(TextInput::class, $fields[0]);
        $this->assertEquals(150, $fields[0]->getMaxLength());
    }

    // -------------------------------------------------------------------------
    // Dual-column: $freeTextInputName
    // -------------------------------------------------------------------------

    public function test_text_input_uses_main_name_when_free_text_name_not_given(): void
    {
        $fields = $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality('birth_municipality_id', 'cob'),
            fn ($f) => 'Z336',
        );

        $this->assertInstanceOf(TextInput::class, $fields[0]);
        $this->assertEquals('birth_municipality_id', $fields[0]->getName());
    }

    public function test_text_input_uses_free_text_name_for_foreign_country(): void
    {
        $fields = $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'birth_municipality_id',
                'cob',
                freeTextInputName: 'birth_municipality_name',
            ),
            fn ($f) => 'Z336', // foreign country codice catastale
        );

        $this->assertInstanceOf(TextInput::class, $fields[0]);
        $this->assertEquals('birth_municipality_name', $fields[0]->getName());
    }

    public function test_select_still_uses_main_name_when_free_text_name_given_and_country_is_italy(): void
    {
        $fields = $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'birth_municipality_id',
                'cob',
                freeTextInputName: 'birth_municipality_name',
            ),
            fn ($f) => '*',
        );

        $this->assertInstanceOf(Select::class, $fields[0]);
        $this->assertEquals('birth_municipality_id', $fields[0]->getName());
    }

    public function test_select_uses_main_name_when_country_is_null_and_free_text_name_given(): void
    {
        $fields = $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'birth_municipality_id',
                'cob',
                freeTextInputName: 'birth_municipality_name',
            ),
            fn ($f) => null,
        );

        $this->assertInstanceOf(Select::class, $fields[0]);
        $this->assertEquals('birth_municipality_id', $fields[0]->getName());
    }

    public function test_dual_column_pattern_select_stores_id_free_text_stores_name(): void
    {
        // Italy → Select keyed by ID (valueColumn='id' pattern)
        $italyFields = $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'birth_municipality_id',
                'cob',
                freeTextInputName: 'birth_municipality_name',
            ),
            fn ($f) => '*',
        );
        $this->assertEquals('birth_municipality_id', $italyFields[0]->getName());

        // Foreign → TextInput with the free-text column name
        $foreignFields = $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'birth_municipality_id',
                'cob',
                freeTextInputName: 'birth_municipality_name',
            ),
            fn ($f) => 'Z336',
        );
        $this->assertEquals('birth_municipality_name', $foreignFields[0]->getName());
    }

    // -------------------------------------------------------------------------
    // $closure parameter
    // -------------------------------------------------------------------------

    public function test_closure_is_called_for_italy(): void
    {
        $called = false;

        $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'pob',
                'cob',
                closure: function () use (&$called) {
                    $called = true;
                },
            ),
            fn ($f) => '*',
        );

        $this->assertTrue($called, '$closure was not called for Italy (Select case)');
    }

    public function test_closure_is_called_for_foreign_country(): void
    {
        $called = false;

        $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'pob',
                'cob',
                closure: function () use (&$called) {
                    $called = true;
                },
            ),
            fn ($f) => 'Z336',
        );

        $this->assertTrue($called, '$closure was not called for foreign country (TextInput case)');
    }

    public function test_closure_is_called_when_no_country_field(): void
    {
        $called = false;

        $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'pob',
                null,
                closure: function () use (&$called) {
                    $called = true;
                },
            ),
            fn ($f) => null,
        );

        $this->assertTrue($called, '$closure was not called when no country dependency');
    }

    public function test_closure_receives_country_value_injection(): void
    {
        $captured = 'NOT_SET';

        $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'pob',
                'cob',
                closure: function ($countryValue) use (&$captured) {
                    $captured = $countryValue;
                },
            ),
            fn ($f) => 'Z336',
        );

        $this->assertEquals('Z336', $captured);
    }

    public function test_closure_receives_null_country_value_when_country_not_set(): void
    {
        $captured = 'NOT_SET';

        $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'pob',
                'cob',
                closure: function ($countryValue) use (&$captured) {
                    $captured = $countryValue;
                },
            ),
            fn ($f) => null,
        );

        $this->assertNull($captured);
    }

    public function test_closure_receives_free_text_input_name_injection(): void
    {
        $captured = null;

        $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'pob',
                'cob',
                freeTextInputName: 'place_of_birth_text',
                closure: function ($freeTextInputName) use (&$captured) {
                    $captured = $freeTextInputName;
                },
            ),
            fn ($f) => 'Z336',
        );

        $this->assertEquals('place_of_birth_text', $captured);
    }

    public function test_closure_receives_country_field_depend_on_injection(): void
    {
        $captured = null;

        $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'pob',
                'birth_country',
                closure: function ($countryFieldDependOn) use (&$captured) {
                    $captured = $countryFieldDependOn;
                },
            ),
            fn ($f) => '*',
        );

        $this->assertEquals('birth_country', $captured);
    }

    public function test_closure_receives_free_text_name_defaults_to_main_name(): void
    {
        // When $freeTextInputName is not passed, it defaults to $name
        $captured = null;

        $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'my_field',
                'cob',
                closure: function ($freeTextInputName) use (&$captured) {
                    $captured = $freeTextInputName;
                },
            ),
            fn ($f) => 'Z336',
        );

        $this->assertEquals('my_field', $captured);
    }

    // -------------------------------------------------------------------------
    // $valueColumn integration with DB search results
    // -------------------------------------------------------------------------

    public function test_search_results_with_id_value_column_returns_correct_labels(): void
    {
        $comune = GeoLocation::create([
            'item_type'        => 'comune',
            'denominazione'    => 'Torino',
            'denominazione_en' => 'Turin',
            'codice_catastale' => 'L219',
            'is_foreign_state' => false,
        ]);

        // Default: keyed by codice_catastale
        $byCode = $this->trait::getGeoLocationSearchResults('Torino', 'comune', 5);
        $this->assertArrayHasKey('L219', $byCode);

        // With valueColumn=id: keyed by integer ID (locale='it' to avoid env-dependent label)
        $byId = $this->trait::getGeoLocationSearchResults('Torino', 'comune', 5, 'it', 'id');
        $this->assertArrayHasKey($comune->id, $byId);
        $this->assertEquals('Torino', $byId[$comune->id]);
    }

    public function test_search_results_with_locale_uses_localised_label(): void
    {
        GeoLocation::create([
            'item_type'        => 'comune',
            'denominazione'    => 'Bolzano',
            'denominazione_de' => 'Bozen',
            'codice_catastale' => 'A952',
            'is_foreign_state' => false,
        ]);

        $de = $this->trait::getGeoLocationSearchResults('Bolzano', 'comune', 5, 'de');
        $this->assertArrayHasKey('A952', $de);
        $this->assertEquals('Bozen', $de['A952']);

        $it = $this->trait::getGeoLocationSearchResults('Bolzano', 'comune', 5, 'it');
        $this->assertEquals('Bolzano', $it['A952']);
    }
}
