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
 * End-to-end tests for country + municipality used together.
 *
 * Two scenarios:
 *   1. valueColumn='codice_catastale' (default) — municipality Select keyed by codice_catastale
 *   2. valueColumn='id'               — municipality Select keyed by geo_locations.id (FK)
 *
 * The country Select always stores codice_catastale ('*' = Italy, 'Z112' = Germany).
 */
class CountryMunicipalityIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $trait;

    protected GeoLocation $italy;
    protected GeoLocation $germany;
    protected GeoLocation $roma;

    protected function setUp(): void
    {
        parent::setUp();

        $this->trait = new class {
            use HasGeoLocationFilamentFields;
        };

        $this->italy = GeoLocation::create([
            'denominazione'    => 'Italia',
            'codice_catastale' => '*',
            'item_type'        => config('codice-fiscale.item_types.stato'),
            'is_foreign_state' => false,
        ]);

        $this->germany = GeoLocation::create([
            'denominazione'    => 'Germania',
            'codice_catastale' => 'Z112',
            'item_type'        => config('codice-fiscale.item_types.stato'),
            'is_foreign_state' => true,
        ]);

        $this->roma = GeoLocation::create([
            'denominazione'    => 'Roma',
            'codice_catastale' => 'H501',
            'item_type'        => config('codice-fiscale.item_types.comune'),
            'is_foreign_state' => false,
        ]);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function resolveGroupFields(Group $group, callable $get): array
    {
        $ref = new \ReflectionProperty($group, 'childComponents');
        $ref->setAccessible(true);
        $closure = $ref->getValue($group)['default'];

        return $closure($get);
    }

    private function callSelectSearchClosure(Select $select, string $search): array
    {
        $ref = new \ReflectionProperty($select, 'getSearchResultsUsing');
        $ref->setAccessible(true);
        $closure = $ref->getValue($select);

        return $select->evaluate($closure, ['search' => $search]);
    }

    // ── Country select ─────────────────────────────────────────────────────────

    public function test_country_select_is_built_correctly(): void
    {
        $select = $this->trait->getFilamentDropdownForCountry('cob');

        $this->assertInstanceOf(Select::class, $select);
        $this->assertEquals('cob', $select->getName());
    }

    public function test_country_search_returns_italy_keyed_by_codice_catastale(): void
    {
        $select  = $this->trait->getFilamentDropdownForCountry('cob', locale: 'it');
        $results = $this->callSelectSearchClosure($select, 'Italia');

        $this->assertArrayHasKey('*', $results, 'Italy must be keyed by "*"');
    }

    public function test_country_search_returns_germany_keyed_by_codice_catastale(): void
    {
        $select  = $this->trait->getFilamentDropdownForCountry('cob', locale: 'it');
        $results = $this->callSelectSearchClosure($select, 'Germania');

        $this->assertArrayHasKey('Z112', $results, 'Germany must be keyed by "Z112"');
    }

    // ── Combined: valueColumn='codice_catastale' ───────────────────────────────

    public function test_combined_with_codice_catastale_italy_shows_select(): void
    {
        // Country select stores '*' when Italy is chosen
        $countrySelect   = $this->trait->getFilamentDropdownForCountry('cob', locale: 'it');
        $countryResults  = $this->callSelectSearchClosure($countrySelect, 'Italia');
        $selectedCountry = array_key_first($countryResults); // '*'

        $this->assertEquals('*', $selectedCountry);

        // Municipality group: default valueColumn='codice_catastale'
        $muniGroup  = $this->trait->getFilamentDropdownForMunicipality(
            'pob',
            'cob',
            freeTextInputName: 'pob_text',
            locale: 'it',
        );
        $fields = $this->resolveGroupFields($muniGroup, fn ($f) => $selectedCountry);

        $this->assertInstanceOf(Select::class, $fields[0]);
        $this->assertEquals('pob', $fields[0]->getName());

        // Search results are keyed by codice_catastale
        $muniResults = $this->callSelectSearchClosure($fields[0], 'Roma');
        $this->assertArrayHasKey('H501', $muniResults);
        $this->assertArrayNotHasKey($this->roma->id, $muniResults);
        $this->assertEquals('Roma', $muniResults['H501']);
    }

    public function test_combined_with_codice_catastale_foreign_shows_text_input(): void
    {
        // Country select stores 'Z112' when Germany is chosen
        $countrySelect   = $this->trait->getFilamentDropdownForCountry('cob', locale: 'it');
        $countryResults  = $this->callSelectSearchClosure($countrySelect, 'Germania');
        $selectedCountry = array_key_first($countryResults); // 'Z112'

        $this->assertEquals('Z112', $selectedCountry);

        // Municipality group: default valueColumn='codice_catastale'
        $muniGroup = $this->trait->getFilamentDropdownForMunicipality(
            'pob',
            'cob',
            freeTextInputName: 'pob_text',
            valueColumn: 'id'
        );
        $fields = $this->resolveGroupFields($muniGroup, fn ($f) => $selectedCountry);

        $this->assertInstanceOf(TextInput::class, $fields[0]);
        $textField = $fields[0];
        $this->assertEquals('pob_text', $textField->getName());
        $this->assertEquals(150, $textField->getMaxLength());

    }

    // ── Combined: valueColumn='id' ─────────────────────────────────────────────

    public function test_combined_with_id_value_column_italy_shows_select_keyed_by_id(): void
    {
        // Country select still stores codice_catastale ('*') — unchanged
        $countrySelect   = $this->trait->getFilamentDropdownForCountry('cob', locale: 'it');
        $countryResults  = $this->callSelectSearchClosure($countrySelect, 'Italia');
        $selectedCountry = array_key_first($countryResults); // '*'

        $this->assertEquals('*', $selectedCountry);

        // Municipality group: valueColumn='id' → Select keyed by integer PK
        $muniGroup = $this->trait->getFilamentDropdownForMunicipality(
            'birth_municipality_id',
            'cob',
            freeTextInputName: 'birth_municipality_name',
            valueColumn: 'id',
        );
        $fields = $this->resolveGroupFields($muniGroup, fn ($f) => $selectedCountry);

        $this->assertInstanceOf(Select::class, $fields[0]);
        $this->assertEquals('birth_municipality_id', $fields[0]->getName());

        // Search results keyed by integer ID (FK to geo_locations.id)
        $muniResults = $this->callSelectSearchClosure($fields[0], 'Roma');
        $this->assertArrayHasKey($this->roma->id, $muniResults);
        $this->assertArrayNotHasKey('H501', $muniResults);

        // ID can be used to look up the record
        $resolved = GeoLocation::find($this->roma->id);
        $this->assertEquals('Roma', $resolved->denominazione);
    }

    public function test_combined_with_id_value_column_foreign_shows_text_input(): void
    {
        // Country select stores 'Z112' for Germany
        $countrySelect   = $this->trait->getFilamentDropdownForCountry('cob', locale: 'it');
        $countryResults  = $this->callSelectSearchClosure($countrySelect, 'Germania');
        $selectedCountry = array_key_first($countryResults); // 'Z112'

        $this->assertEquals('Z112', $selectedCountry);

        // Municipality group: valueColumn='id' — foreign still uses TextInput
        $muniGroup = $this->trait->getFilamentDropdownForMunicipality(
            'birth_municipality_id',
            'cob',
            freeTextInputName: 'birth_municipality_name',
            valueColumn: 'id',
        );
        $fields = $this->resolveGroupFields($muniGroup, fn ($f) => $selectedCountry);

        // TextInput — valueColumn plays no role here
        $this->assertInstanceOf(TextInput::class, $fields[0]);
        $this->assertEquals('birth_municipality_name', $fields[0]->getName());
        $this->assertEquals(150, $fields[0]->getMaxLength());
    }

    // ── Edge cases: what line 143 ($countryValue !== '*') does with integer IDs ──
    //
    // The switching logic in getFilamentDropdownForMunicipality (line 143) checks:
    //
    //   if ($countryValue && $countryValue !== '*') → TextInput (foreign)
    //
    // This works ONLY when the country field stores codice_catastale.
    // If the country field stores integer IDs (e.g. both fields use valueColumn='id'),
    // any integer — including Italy's ID — satisfies `int !== '*'`, so the trait
    // cannot distinguish Italy from a foreign country: both get TextInput.
    //
    // Tests below document this behaviour so the limitation is explicit.
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * SUPPORTED: country stores '*' (codice_catastale), municipality uses valueColumn='id'.
     * Italy codice_catastale '*' correctly triggers Select.
     */
    public function test_italy_by_codice_catastale_with_municipality_id_column_shows_select(): void
    {
        $group  = $this->trait->getFilamentDropdownForMunicipality(
            'birth_municipality_id', 'cob',
            freeTextInputName: 'birth_municipality_name',
            valueColumn: 'id',
        );
        $fields = $this->resolveGroupFields($group, fn ($f) => '*');

        $this->assertInstanceOf(Select::class, $fields[0],
            "Italy ('*') must produce a Select even when valueColumn='id'");
    }

    /**
     * SUPPORTED: country stores 'Z112' (codice_catastale), municipality uses valueColumn='id'.
     * Foreign codice_catastale correctly triggers TextInput.
     */
    public function test_foreign_country_by_codice_catastale_with_municipality_id_column_shows_text_input(): void
    {
        $group  = $this->trait->getFilamentDropdownForMunicipality(
            'birth_municipality_id', 'cob',
            freeTextInputName: 'birth_municipality_name',
            valueColumn: 'id',
        );
        $fields = $this->resolveGroupFields($group, fn ($f) => 'Z112');

        $this->assertInstanceOf(TextInput::class, $fields[0],
            "Foreign codice_catastale must produce a TextInput when valueColumn='id'");
        $this->assertEquals('birth_municipality_name', $fields[0]->getName());
    }

    /**
     * Country field stores Italy's integer ID — the trait resolves it to codice_catastale
     * via DB lookup, finds '*', and correctly shows Select.
     */
    public function test_italy_by_integer_id_correctly_shows_select(): void
    {
        $group  = $this->trait->getFilamentDropdownForMunicipality(
            'birth_municipality_id', 'cob',
            freeTextInputName: 'birth_municipality_name',
            valueColumn: 'id',
        );

        // Simulate: country field stores Italy's integer PK
        $fields = $this->resolveGroupFields($group, fn ($f) => $this->italy->id);

        $this->assertInstanceOf(Select::class, $fields[0],
            'Italy by integer ID must resolve to codice_catastale "*" and show Select.');
    }

    /**
     * KNOWN LIMITATION: a foreign country by integer ID also triggers TextInput,
     * which is the correct outcome — but only because any integer satisfies !== '*'.
     */
    public function test_foreign_country_by_integer_id_shows_text_input(): void
    {
        $group  = $this->trait->getFilamentDropdownForMunicipality(
            'birth_municipality_id', 'cob',
            freeTextInputName: 'birth_municipality_name',
            valueColumn: 'id',
        );

        // Simulate: country field stores Germany's integer PK
        $fields = $this->resolveGroupFields($group, fn ($f) => $this->germany->id);

        $this->assertInstanceOf(TextInput::class, $fields[0],
            'A foreign country by integer ID correctly shows TextInput '
            .'(int !== "*" is always true).');
        $this->assertEquals('birth_municipality_name', $fields[0]->getName());
    }

    /**
     * SAFE CASE: null country value (nothing selected yet) always falls through
     * the `if ($countryValue && ...)` guard → Select is shown regardless of valueColumn.
     */
    public function test_null_country_value_always_shows_select(): void
    {
        $group  = $this->trait->getFilamentDropdownForMunicipality(
            'birth_municipality_id', 'cob',
            freeTextInputName: 'birth_municipality_name',
            valueColumn: 'id',
        );
        $fields = $this->resolveGroupFields($group, fn ($f) => null);

        $this->assertInstanceOf(Select::class, $fields[0],
            'null country value must always fall back to Select (no country chosen yet).');
    }
}
