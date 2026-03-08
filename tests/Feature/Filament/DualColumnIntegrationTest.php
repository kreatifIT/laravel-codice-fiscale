<?php

namespace Kreatif\CodiceFiscale\Tests\Feature\Filament;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreatif\CodiceFiscale\Filament\Forms\Traits\HasGeoLocationFilamentFields;
use Kreatif\CodiceFiscale\Models\GeoLocation;
use Kreatif\CodiceFiscale\Tests\TestCase;

/**
 * Integration tests for the dual-column pattern using real database data.
 *
 * Dual-column pattern:
 *   - Italian municipality → Select stores the geo_location value (id or codice_catastale)
 *   - Foreign municipality → TextInput stores a free-text name in a SEPARATE column
 *
 * Example schema:
 *   birth_municipality_id   INTEGER  (FK to geo_locations.id — used when Italy)
 *   birth_municipality_name VARCHAR  (free text — used when foreign country)
 */
class DualColumnIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $trait;

    // ── seeded records ────────────────────────────────────────────────────────
    protected GeoLocation $italy;
    protected GeoLocation $germany;
    protected GeoLocation $france;
    protected GeoLocation $roma;
    protected GeoLocation $milano;
    protected GeoLocation $bolzano;   // has DE label, no EN label
    protected GeoLocation $romagna;   // shares prefix with Roma

    protected function setUp(): void
    {
        parent::setUp();

        $this->trait = new class {
            use HasGeoLocationFilamentFields;
        };

        $this->seedGeoLocations();
    }

    private function seedGeoLocations(): void
    {
        $this->italy = GeoLocation::create([
            'item_type'        => config('codice-fiscale.item_types.stato'),
            'denominazione'    => 'Italia',
            'denominazione_en' => 'Italy',
            'denominazione_de' => 'Italien',
            'codice_catastale' => '*',
            'is_foreign_state' => false,
        ]);

        $this->germany = GeoLocation::create([
            'item_type'        => config('codice-fiscale.item_types.stato'),
            'denominazione'    => 'Germania',
            'denominazione_en' => 'Germany',
            'denominazione_de' => 'Deutschland',
            'codice_catastale' => 'Z336',
            'is_foreign_state' => true,
        ]);

        $this->france = GeoLocation::create([
            'item_type'        => config('codice-fiscale.item_types.stato'),
            'denominazione'    => 'Francia',
            'denominazione_en' => 'France',
            'denominazione_de' => 'Frankreich',
            'codice_catastale' => 'Z110',
            'is_foreign_state' => true,
        ]);

        $this->roma = GeoLocation::create([
            'item_type'        => config('codice-fiscale.item_types.comune'),
            'denominazione'    => 'Roma',
            'denominazione_en' => 'Rome',
            'denominazione_de' => 'Rom',
            'codice_catastale' => 'H501',
            'is_foreign_state' => false,
            'sigla_provincia'  => 'RM',
        ]);

        $this->milano = GeoLocation::create([
            'item_type'        => config('codice-fiscale.item_types.comune'),
            'denominazione'    => 'Milano',
            'denominazione_en' => 'Milan',
            'denominazione_de' => 'Mailand',
            'codice_catastale' => 'F205',
            'is_foreign_state' => false,
            'sigla_provincia'  => 'MI',
        ]);

        $this->bolzano = GeoLocation::create([
            'item_type'        => config('codice-fiscale.item_types.comune'),
            'denominazione'    => 'Bolzano',
            'denominazione_de' => 'Bozen',
            // intentionally no denominazione_en → tests null-label fallback
            'codice_catastale' => 'A952',
            'is_foreign_state' => false,
            'sigla_provincia'  => 'BZ',
        ]);

        $this->romagna = GeoLocation::create([
            'item_type'        => config('codice-fiscale.item_types.comune'),
            'denominazione'    => 'Romagna Longa',
            'codice_catastale' => 'H999',
            'is_foreign_state' => false,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────────────────

    private function resolveGroupFields(Group $group, callable $get): array
    {
        $ref = new \ReflectionProperty($group, 'childComponents');
        $ref->setAccessible(true);
        $closure = $ref->getValue($group)['default'];

        return $closure($get);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // A — Search results with real data, default valueColumn='codice_catastale'
    // ─────────────────────────────────────────────────────────────────────────

    public function test_search_comuni_keyed_by_codice_catastale(): void
    {
        $results = $this->trait::getGeoLocationSearchResults('Roma', 'comune', 10, 'it');

        $this->assertArrayHasKey('H501', $results);
        $this->assertEquals('Roma', $results['H501']);
    }

    public function test_search_comuni_returns_correct_italian_label(): void
    {
        $results = $this->trait::getGeoLocationSearchResults('Milano', 'comune', 10, 'it');

        $this->assertEquals('Milano', $results['F205']);
    }

    public function test_search_comuni_returns_german_label_for_de_locale(): void
    {
        $results = $this->trait::getGeoLocationSearchResults('Bolzano', 'comune', 10, 'de');

        // Bolzano has denominazione_de='Bozen'
        $this->assertEquals('Bozen', $results['A952']);
    }

    public function test_search_comuni_returns_english_label_for_en_locale(): void
    {
        $results = $this->trait::getGeoLocationSearchResults('Roma', 'comune', 10, 'en');

        $this->assertEquals('Rome', $results['H501']);
    }

    public function test_search_comuni_falls_back_to_denominazione_when_locale_label_is_null(): void
    {
        // Bolzano has no denominazione_en → must fall back to denominazione ('Bolzano')
        $results = $this->trait::getGeoLocationSearchResults('Bolzano', 'comune', 10, 'en');

        $this->assertArrayHasKey('A952', $results);
        $this->assertEquals('Bolzano', $results['A952']);
    }

    public function test_search_prefix_match_returns_multiple_comuni(): void
    {
        // Both 'Roma' and 'Romagna Longa' start with 'Rom'
        $results = $this->trait::getGeoLocationSearchResults('Rom', 'comune', 10, 'it');

        $this->assertArrayHasKey('H501', $results); // Roma
        $this->assertArrayHasKey('H999', $results); // Romagna Longa
    }

    public function test_search_respects_limit(): void
    {
        $results = $this->trait::getGeoLocationSearchResults('Rom', 'comune', 1, 'it');

        $this->assertCount(1, $results);
    }

    public function test_search_exact_match_comes_before_prefix_match(): void
    {
        // 'Roma' should rank above 'Romagna Longa'
        $results = $this->trait::getGeoLocationSearchResults('Roma', 'comune', 10, 'it');

        $keys = array_keys($results);
        $this->assertEquals('H501', $keys[0], 'Roma (exact match) should rank before Romagna Longa');
    }

    public function test_search_type_filter_isolates_comuni_from_stati(): void
    {
        $comuni  = $this->trait::getGeoLocationSearchResults('Roma', 'comune', 10, 'it');
        $stati   = $this->trait::getGeoLocationSearchResults('Roma', 'stato',  10, 'it');

        $this->assertArrayHasKey('H501', $comuni);
        $this->assertArrayNotHasKey('H501', $stati);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // B — Search results with valueColumn='id' and real data
    // ─────────────────────────────────────────────────────────────────────────

    public function test_search_keyed_by_id_returns_roma_with_integer_key(): void
    {
        $results = $this->trait::getGeoLocationSearchResults('Roma', 'comune', 10, 'it', 'id');

        $this->assertArrayHasKey($this->roma->id, $results);
        $this->assertEquals('Roma', $results[$this->roma->id]);
        $this->assertArrayNotHasKey('H501', $results); // no codice_catastale as key
    }

    public function test_search_keyed_by_id_finds_multiple_records_with_correct_ids(): void
    {
        $results = $this->trait::getGeoLocationSearchResults('Rom', 'comune', 10, 'it', 'id');

        $this->assertArrayHasKey($this->roma->id,    $results);
        $this->assertArrayHasKey($this->romagna->id, $results);
    }

    public function test_search_keyed_by_id_uses_localised_label(): void
    {
        $results = $this->trait::getGeoLocationSearchResults('Rom', 'comune', 10, 'de', 'id');

        // Roma has denominazione_de='Rom'
        $this->assertEquals('Rom', $results[$this->roma->id]);
    }

    public function test_search_keyed_by_id_falls_back_to_denominazione_when_locale_null(): void
    {
        // Bolzano has no denominazione_en
        $results = $this->trait::getGeoLocationSearchResults('Bolzano', 'comune', 10, 'en', 'id');

        $this->assertArrayHasKey($this->bolzano->id, $results);
        $this->assertEquals('Bolzano', $results[$this->bolzano->id]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // C — Dual-column: field switching with real country values in DB
    // ─────────────────────────────────────────────────────────────────────────

    public function test_italy_codice_catastale_selects_municipality_select(): void
    {
        // Country stored as codice_catastale '*' (Italy)
        $fields = $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'birth_municipality_id',
                'country_cob',
                freeTextInputName: 'birth_municipality_name',
            ),
            fn ($field) => $this->italy->codice_catastale, // '*'
        );

        $this->assertInstanceOf(Select::class, $fields[0]);
        $this->assertEquals('birth_municipality_id', $fields[0]->getName());
    }

    public function test_germany_codice_catastale_selects_text_input(): void
    {
        // Country stored as codice_catastale 'Z336' (Germany)
        $fields = $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'birth_municipality_id',
                'country_cob',
                freeTextInputName: 'birth_municipality_name',
            ),
            fn ($field) => $this->germany->codice_catastale, // 'Z336'
        );

        $this->assertInstanceOf(TextInput::class, $fields[0]);
        $this->assertEquals('birth_municipality_name', $fields[0]->getName());
    }

    public function test_france_codice_catastale_selects_text_input(): void
    {
        $fields = $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'birth_municipality_id',
                'country_cob',
                freeTextInputName: 'birth_municipality_name',
            ),
            fn ($field) => $this->france->codice_catastale, // 'Z110'
        );

        $this->assertInstanceOf(TextInput::class, $fields[0]);
        $this->assertEquals('birth_municipality_name', $fields[0]->getName());
    }

    public function test_no_country_selected_defaults_to_select(): void
    {
        // User hasn't chosen a country yet
        $fields = $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'birth_municipality_id',
                'country_cob',
                freeTextInputName: 'birth_municipality_name',
            ),
            fn ($field) => null,
        );

        $this->assertInstanceOf(Select::class, $fields[0]);
        $this->assertEquals('birth_municipality_id', $fields[0]->getName());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // D — Dual-column with valueColumn='id': Select uses integer ID as value
    // ─────────────────────────────────────────────────────────────────────────

    public function test_search_with_id_column_matches_record_id_in_db(): void
    {
        // Simulates the case where birth_municipality_id is a FK to geo_locations.id
        $results = $this->trait::getGeoLocationSearchResults(
            'Roma',
            config('codice-fiscale.item_types.comune'),
            10,
            'it',
            'id',
        );

        // The key must be the actual PK of the Roma record
        $this->assertArrayHasKey($this->roma->id, $results);

        // The stored integer ID can be used directly as FK
        $resolved = GeoLocation::find($this->roma->id);
        $this->assertEquals('Roma', $resolved->denominazione);
    }

    public function test_search_with_id_column_and_milan(): void
    {
        $results = $this->trait::getGeoLocationSearchResults(
            'Milano',
            config('codice-fiscale.item_types.comune'),
            10,
            'it',
            'id',
        );

        $this->assertArrayHasKey($this->milano->id, $results);
        $this->assertEquals('Milano', $results[$this->milano->id]);
    }

    public function test_search_with_id_column_respects_item_type_for_states(): void
    {
        // When searching states by id, municipalities must NOT appear
        $statiResults = $this->trait::getGeoLocationSearchResults(
            'Germ',
            config('codice-fiscale.item_types.stato'),
            10,
            'en',
            'id',
        );

        $this->assertArrayHasKey($this->germany->id, $statiResults);
        $this->assertEquals('Germany', $statiResults[$this->germany->id]);

        // Milan (comune) must not appear in stati results
        $this->assertArrayNotHasKey($this->milano->id, $statiResults);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // E — closure receives live data about the active field
    // ─────────────────────────────────────────────────────────────────────────

    public function test_closure_knows_italy_is_selected_with_real_codice_catastale(): void
    {
        $receivedCountryValue = null;

        $fields = $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'birth_municipality_id',
                'country_cob',
                freeTextInputName: 'birth_municipality_name',
                closure: function ($countryValue) use (&$receivedCountryValue) {
                    $receivedCountryValue = $countryValue;
                },
            ),
            fn ($f) => $this->italy->codice_catastale, // '*'
        );

        $this->assertEquals('*', $receivedCountryValue);
        $this->assertInstanceOf(Select::class, $fields[0]); // Italy → Select
    }

    public function test_closure_knows_foreign_country_is_selected(): void
    {
        $receivedCountryValue = null;

        $fields = $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'birth_municipality_id',
                'country_cob',
                freeTextInputName: 'birth_municipality_name',
                closure: function ($countryValue) use (&$receivedCountryValue) {
                    $receivedCountryValue = $countryValue;
                },
            ),
            fn ($f) => $this->germany->codice_catastale, // 'Z336'
        );

        $this->assertEquals('Z336', $receivedCountryValue);
        $this->assertInstanceOf(TextInput::class, $fields[0]); // Foreign → TextInput
    }

    public function test_closure_receives_correct_free_text_name_for_different_configurations(): void
    {
        // Pattern A: birth_municipality_id / birth_municipality_name
        $capturedA = null;
        $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'birth_municipality_id',
                'country_cob',
                freeTextInputName: 'birth_municipality_name',
                closure: function ($freeTextInputName) use (&$capturedA) {
                    $capturedA = $freeTextInputName;
                },
            ),
            fn ($f) => 'Z336',
        );

        // Pattern B: address_city_id / address_city_name
        $capturedB = null;
        $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'address_city_id',
                'address_country',
                freeTextInputName: 'address_city_name',
                closure: function ($freeTextInputName) use (&$capturedB) {
                    $capturedB = $freeTextInputName;
                },
            ),
            fn ($f) => 'Z110',
        );

        $this->assertEquals('birth_municipality_name', $capturedA);
        $this->assertEquals('address_city_name',       $capturedB);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // F — address fields: same dual-column pattern for a different use case
    // ─────────────────────────────────────────────────────────────────────────

    public function test_address_city_with_italy_returns_select(): void
    {
        $fields = $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'address_city_id',
                'address_country',
                freeTextInputName: 'address_city_name',
            ),
            fn ($f) => '*', // Italy
        );

        $this->assertInstanceOf(Select::class, $fields[0]);
        $this->assertEquals('address_city_id', $fields[0]->getName());
    }

    public function test_address_city_with_foreign_country_returns_text_input(): void
    {
        $fields = $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'address_city_id',
                'address_country',
                freeTextInputName: 'address_city_name',
            ),
            fn ($f) => $this->france->codice_catastale, // 'Z110'
        );

        $this->assertInstanceOf(TextInput::class, $fields[0]);
        $this->assertEquals('address_city_name', $fields[0]->getName());
    }

    public function test_address_city_switches_correctly_between_italy_and_foreign(): void
    {
        $group = $this->trait->getFilamentDropdownForMunicipality(
            'address_city_id',
            'address_country',
            freeTextInputName: 'address_city_name',
        );

        // Italy
        $italy = $this->resolveGroupFields($group, fn ($f) => '*');
        $this->assertInstanceOf(Select::class, $italy[0]);
        $this->assertEquals('address_city_id', $italy[0]->getName());

        // Germany
        $foreign = $this->resolveGroupFields($group, fn ($f) => 'Z336');
        $this->assertInstanceOf(TextInput::class, $foreign[0]);
        $this->assertEquals('address_city_name', $foreign[0]->getName());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // G — isRequired propagates correctly to both field types
    // ─────────────────────────────────────────────────────────────────────────

    public function test_required_is_set_on_select_for_italy(): void
    {
        $fields = $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'birth_municipality_id',
                'country_cob',
                isRequired: true,
                freeTextInputName: 'birth_municipality_name',
            ),
            fn ($f) => '*',
        );

        $this->assertTrue($fields[0]->isRequired());
    }

    public function test_required_is_set_on_text_input_for_foreign(): void
    {
        $fields = $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'birth_municipality_id',
                'country_cob',
                isRequired: true,
                freeTextInputName: 'birth_municipality_name',
            ),
            fn ($f) => 'Z336',
        );

        $this->assertTrue($fields[0]->isRequired());
    }

    public function test_not_required_when_is_required_false(): void
    {
        // Italy
        $italyFields = $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'birth_municipality_id',
                'country_cob',
                isRequired: false,
                freeTextInputName: 'birth_municipality_name',
            ),
            fn ($f) => '*',
        );
        $this->assertFalse($italyFields[0]->isRequired());

        // Foreign
        $foreignFields = $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'birth_municipality_id',
                'country_cob',
                isRequired: false,
                freeTextInputName: 'birth_municipality_name',
            ),
            fn ($f) => 'Z336',
        );
        $this->assertFalse($foreignFields[0]->isRequired());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // H — valueColumn='id' passed directly to getFilamentDropdownForMunicipality
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Extracts the stored search closure from a Select and calls it with a search term.
     * This lets us verify that valueColumn is actually wired into the Select's search.
     */
    private function callSelectSearchClosure(Select $select, string $search): array
    {
        $ref = new \ReflectionProperty($select, 'getSearchResultsUsing');
        $ref->setAccessible(true);
        $closure = $ref->getValue($select);

        return $select->evaluate($closure, ['search' => $search]);
    }

    public function test_select_search_keyed_by_id_when_value_column_is_id(): void
    {
        $fields = $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'birth_municipality_id',
                'cob',
                freeTextInputName: 'birth_municipality_name',
                valueColumn: 'id',
            ),
            fn ($f) => '*', // Italy → Select
        );

        $select = $fields[0];
        $this->assertInstanceOf(Select::class, $select);

        $results = $this->callSelectSearchClosure($select, 'Roma');

        $this->assertArrayHasKey($this->roma->id, $results);
        $this->assertNotEmpty($results[$this->roma->id]);     // has a label (locale-dependent)
        $this->assertArrayNotHasKey('H501', $results);        // NOT keyed by codice_catastale
    }

    public function test_select_search_keyed_by_codice_catastale_by_default(): void
    {
        // Control: without valueColumn, search results use codice_catastale
        $fields = $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'birth_municipality_id',
                'cob',
                freeTextInputName: 'birth_municipality_name',
            ),
            fn ($f) => '*',
        );

        $select = $fields[0];
        $results = $this->callSelectSearchClosure($select, 'Roma');

        $this->assertArrayHasKey('H501', $results);
        $this->assertArrayNotHasKey($this->roma->id, $results);
    }

    public function test_cob_option_search_keyed_by_id_and_value(): void
    {
        // Control: without valueColumn, search results use codice_catastale
        $fields = $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'birth_municipality_id',
                'cob',
                freeTextInputName: 'birth_municipality_name',
                valueColumn: 'id',
            ),
            fn ($f) => '4',
        );
        $textSearchInput = $fields[0];
        $this->assertInstanceOf(TextInput::class, $textSearchInput);
        $this->assertEquals('birth_municipality_name', $textSearchInput->getName());
    }

    public function test_select_search_with_id_column_finds_multiple_comuni(): void
    {
        $fields = $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'birth_municipality_id',
                'cob',
                freeTextInputName: 'birth_municipality_name',
                valueColumn: 'id',
            ),
            fn ($f) => '*',
        );

        $results = $this->callSelectSearchClosure($fields[0], 'Rom');

        $this->assertArrayHasKey($this->roma->id,    $results); // Roma
        $this->assertArrayHasKey($this->romagna->id, $results); // Romagna Longa
    }

    public function test_field_switching_works_correctly_with_id_value_column(): void
    {
        $group = $this->trait->getFilamentDropdownForMunicipality(
            'birth_municipality_id',
            'cob',
            freeTextInputName: 'birth_municipality_name',
            valueColumn: 'id',
        );

        // Italy → Select, name = 'birth_municipality_id'
        $italy = $this->resolveGroupFields($group, fn ($f) => '*');
        $this->assertInstanceOf(Select::class, $italy[0]);
        $this->assertEquals('birth_municipality_id', $italy[0]->getName());

        // Foreign → TextInput, name = 'birth_municipality_name'
        $foreign = $this->resolveGroupFields($group, fn ($f) => 'Z336');
        $this->assertInstanceOf(TextInput::class, $foreign[0]);
        $this->assertEquals('birth_municipality_name', $foreign[0]->getName());
    }

    public function test_text_input_unaffected_by_value_column(): void
    {
        // valueColumn is irrelevant for the TextInput (no search/option logic there)
        // but freeTextInputName must still be used as the field name
        $fields = $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'birth_municipality_id',
                'cob',
                freeTextInputName: 'birth_municipality_name',
                valueColumn: 'id',
            ),
            fn ($f) => $this->germany->codice_catastale, // 'Z336'
        );

        $this->assertInstanceOf(TextInput::class, $fields[0]);
        $this->assertEquals('birth_municipality_name', $fields[0]->getName());
        $this->assertEquals(150, $fields[0]->getMaxLength());
    }

    public function test_select_search_with_id_column_respects_search_locale(): void
    {
        $fields = $this->resolveGroupFields(
            $this->trait->getFilamentDropdownForMunicipality(
                'birth_municipality_id',
                'cob',
                freeTextInputName: 'birth_municipality_name',
                valueColumn: 'id',
            ),
            fn ($f) => '*',
        );

        // The locale defaults to app()->getLocale() inside the closure.
        // We seed Roma with denominazione_en='Rome' so searching in 'en' locale should give 'Rome'.
        app()->setLocale('en');

        $results = $this->callSelectSearchClosure($fields[0], 'Roma');

        $this->assertArrayHasKey($this->roma->id, $results);
        $this->assertEquals('Rome', $results[$this->roma->id]);

        app()->setLocale('it'); // restore
    }

    // ─────────────────────────────────────────────────────────────────────────
    // I — Country + Municipality combined end-to-end
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Full flow with valueColumn='codice_catastale' (default):
     *   - Country select stores codice_catastale ('*' / 'Z336')
     *   - Municipality Select (Italy) keyed by codice_catastale
     *   - Municipality TextInput (foreign) uses freeTextInputName
     */
    public function test_combined_country_and_municipality_with_codice_catastale_value_column(): void
    {
        // ── Country select ────────────────────────────────────────────────────
        $countrySelect = $this->trait->getFilamentDropdownForCountry('cob', locale: 'it');

        $italyCountry = $this->callSelectSearchClosure($countrySelect, 'Italia');
        $this->assertArrayHasKey('*', $italyCountry, 'Italy must be keyed by codice_catastale "*"');

        $germanyCountry = $this->callSelectSearchClosure($countrySelect, 'Germania');
        $this->assertArrayHasKey('Z336', $germanyCountry, 'Germany must be keyed by codice_catastale "Z336"');

        // ── Municipality group ─────────────────────────────────────────────────
        $muniGroup = $this->trait->getFilamentDropdownForMunicipality(
            'pob',
            'cob',
            freeTextInputName: 'pob_text',
            locale: 'it',
            // valueColumn defaults to 'codice_catastale'
        );

        // Italy ('*') → Select with name 'pob'
        $italyFields = $this->resolveGroupFields($muniGroup, fn ($f) => '*');
        $this->assertInstanceOf(Select::class, $italyFields[0]);
        $this->assertEquals('pob', $italyFields[0]->getName());

        // Municipality search keyed by codice_catastale
        $muniResults = $this->callSelectSearchClosure($italyFields[0], 'Roma');
        $this->assertArrayHasKey('H501', $muniResults);              // keyed by codice_catastale
        $this->assertArrayNotHasKey($this->roma->id, $muniResults);  // NOT keyed by id
        $this->assertEquals('Roma', $muniResults['H501']);

        // Germany ('Z336') → TextInput with freeTextInputName
        $foreignFields = $this->resolveGroupFields($muniGroup, fn ($f) => 'Z336');
        $this->assertInstanceOf(TextInput::class, $foreignFields[0]);
        $this->assertEquals('pob_text', $foreignFields[0]->getName());
        $this->assertEquals(150, $foreignFields[0]->getMaxLength());
    }

    /**
     * Full flow with valueColumn='id':
     *   - Country select still stores codice_catastale ('*' / 'Z336') — unchanged
     *   - Municipality Select (Italy) keyed by geo_locations.id (integer FK)
     *   - Municipality TextInput (foreign) uses freeTextInputName; valueColumn irrelevant
     */
    public function test_combined_country_and_municipality_with_id_value_column(): void
    {
        // ── Country select ─────────────────────────────────────────────────────
        // Country always uses codice_catastale regardless of municipality's valueColumn
        $countrySelect = $this->trait->getFilamentDropdownForCountry('cob', locale: 'it');

        $italyCountry = $this->callSelectSearchClosure($countrySelect, 'Italia');
        $this->assertArrayHasKey('*', $italyCountry);

        $germanyCountry = $this->callSelectSearchClosure($countrySelect, 'Germania');
        $this->assertArrayHasKey('Z336', $germanyCountry);

        // ── Municipality group with valueColumn='id' ───────────────────────────
        $muniGroup = $this->trait->getFilamentDropdownForMunicipality(
            'birth_municipality_id',
            'cob',
            freeTextInputName: 'birth_municipality_name',
            valueColumn: 'id',
        );

        // Italy ('*') → Select with name 'birth_municipality_id'
        $italyFields = $this->resolveGroupFields($muniGroup, fn ($f) => '*');
        $this->assertInstanceOf(Select::class, $italyFields[0]);
        $this->assertEquals('birth_municipality_id', $italyFields[0]->getName());

        // Municipality search keyed by integer ID (FK to geo_locations.id)
        $muniResults = $this->callSelectSearchClosure($italyFields[0], 'Roma');
        $this->assertArrayHasKey($this->roma->id, $muniResults);     // keyed by integer id
        $this->assertArrayNotHasKey('H501', $muniResults);           // NOT keyed by codice_catastale
        $this->assertNotEmpty($muniResults[$this->roma->id]);         // has a label

        // The stored id resolves back to the correct record (FK integrity)
        $resolved = GeoLocation::find($this->roma->id);
        $this->assertEquals('Roma', $resolved->denominazione);

        // Germany ('Z336') → TextInput; valueColumn plays no role here
        $foreignFields = $this->resolveGroupFields($muniGroup, fn ($f) => 'Z336');
        $this->assertInstanceOf(TextInput::class, $foreignFields[0]);
        $this->assertEquals('birth_municipality_name', $foreignFields[0]->getName());
        $this->assertEquals(150, $foreignFields[0]->getMaxLength());
    }
}
