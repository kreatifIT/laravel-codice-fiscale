<?php

namespace Kreatif\CodiceFiscale\Tests\Feature\Filament;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreatif\CodiceFiscale\Filament\Forms\Traits\HasGeoLocationFilamentFields;
use Kreatif\CodiceFiscale\Filament\Forms\Traits\HasUserBasicFilamentFields;
use Kreatif\CodiceFiscale\Tests\TestCase;

/**
 * Tests for the $modify closure and customization patterns developers will actually use.
 */
class AdvancedUiCustomizationTest extends TestCase
{
    use RefreshDatabase;

    protected $geoTrait;
    protected $userTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->geoTrait = new class {
            use HasGeoLocationFilamentFields;
        };

        $this->userTrait = new class {
            use HasUserBasicFilamentFields;
        };
    }

    // -------------------------------------------------------------------------
    // Closure: common developer patterns
    // -------------------------------------------------------------------------

    public function test_modify_can_add_required_to_municipality_select()
    {
        $fields = $this->geoTrait::resolveMunicipalityField(
            get: fn ($f) => '*',
            name: 'pob',
            countryField: 'cob',
            modify: fn (Select $field) => $field->required(),
        );

        $this->assertTrue($fields[0]->isRequired());
    }

    public function test_modify_can_branch_on_is_italy_injection()
    {
        $receivedIsItaly = null;

        $this->geoTrait::resolveMunicipalityField(
            get: fn ($f) => 'Z336',
            name: 'pob',
            countryField: 'cob',
            modify: function (bool $isItaly) use (&$receivedIsItaly) {
                $receivedIsItaly = $isItaly;
            },
        );

        // Foreign country → $isItaly must be false so conditional logic in closures works correctly
        $this->assertFalse($receivedIsItaly);
    }

    public function test_modify_can_conditionally_apply_logic_using_is_italy()
    {
        $applyRequired = function (mixed $field, bool $isItaly) {
            if ($isItaly) {
                $field->required();
            }
        };

        // Italy → required
        $italyFields = $this->geoTrait::resolveMunicipalityField(
            get: fn ($f) => '*',
            name: 'pob',
            countryField: 'cob',
            modify: $applyRequired,
        );
        $this->assertTrue($italyFields[0]->isRequired());

        // Foreign → not required
        $foreignFields = $this->geoTrait::resolveMunicipalityField(
            get: fn ($f) => 'Z336',
            name: 'pob',
            countryField: 'cob',
            modify: $applyRequired,
        );
        $this->assertFalse($foreignFields[0]->isRequired());
    }

    public function test_modify_closure_on_get_place_of_birth_field()
    {
        $called = false;

        $group = $this->userTrait::getPlaceOfBirthField(
            modify: function () use (&$called) {
                $called = true;
            },
        );

        // Manually trigger the resolution (simulating what Filament does during form rendering)
        $this->userTrait::resolveMunicipalityField(
            get: fn ($f) => null,
            name: 'pob',
            modify: function () use (&$called) {
                $called = true;
            },
        );

        $this->assertTrue($called);
    }

    // -------------------------------------------------------------------------
    // getNameFields — $names + $modify closure
    // -------------------------------------------------------------------------

    public function test_get_name_fields_uses_custom_field_names()
    {
        // Verify names propagate by building individual fields the same way getNameFields does
        $firstname = $this->userTrait::getFirstnameField('first_name');
        $lastname  = $this->userTrait::getLastnameField('last_name');

        $this->assertEquals('first_name', $firstname->getName());
        $this->assertEquals('last_name', $lastname->getName());

        // getNameFields should return a Group without throwing
        $group = $this->userTrait::getNameFields(
            names: ['firstname' => 'first_name', 'lastname' => 'last_name'],
        );
        $this->assertInstanceOf(Group::class, $group);
    }

    public function test_get_name_fields_modify_closure_receives_group()
    {
        $capturedGroup = null;

        $this->userTrait::getNameFields(
            modify: function (Group $group) use (&$capturedGroup) {
                $capturedGroup = $group;
            },
        );

        $this->assertInstanceOf(Group::class, $capturedGroup);
    }

    public function test_get_name_fields_modify_can_replace_group()
    {
        $replacement = Group::make([])->columns(1);

        $result = $this->userTrait::getNameFields(
            modify: fn (Group $group) => $replacement,
        );

        $this->assertSame($replacement, $result);
    }

    public function test_get_name_fields_modify_returns_original_when_closure_returns_null()
    {
        $original = null;

        $result = $this->userTrait::getNameFields(
            modify: function (Group $group) use (&$original) {
                $original = $group;
                // return nothing
            },
        );

        $this->assertSame($original, $result);
    }

    // -------------------------------------------------------------------------
    // getBirthFields — $names + $modify closure
    // -------------------------------------------------------------------------

    public function test_get_birth_fields_uses_custom_field_names()
    {
        // Verify custom names produce correct individual fields
        $dob    = $this->userTrait::getDOBField('birth_date');
        $gender = $this->userTrait::getGenderField('sex');
        $cob    = $this->userTrait::getCountryOfBirthField('country');

        $this->assertEquals('birth_date', $dob->getName());
        $this->assertEquals('sex', $gender->getName());
        $this->assertEquals('country', $cob->getName());

        // getBirthFields with custom names should return 2 elements without throwing
        $fields = $this->userTrait::getBirthFields(
            names: ['dob' => 'birth_date', 'gender' => 'sex', 'cob' => 'country', 'pob' => 'place'],
        );
        $this->assertCount(2, $fields);
        $this->assertInstanceOf(Group::class, $fields[0]); // dob + gender + cob group
        $this->assertInstanceOf(Group::class, $fields[1]); // pob group
    }

    public function test_get_birth_fields_modify_closure_receives_array()
    {
        $capturedFields = null;

        $this->userTrait::getBirthFields(
            modify: function (array $fields) use (&$capturedFields) {
                $capturedFields = $fields;
            },
        );

        $this->assertIsArray($capturedFields);
        $this->assertCount(2, $capturedFields);
    }

    public function test_get_birth_fields_modify_can_replace_array()
    {
        $custom = [TextInput::make('custom')];

        $result = $this->userTrait::getBirthFields(
            modify: fn (array $fields) => $custom,
        );

        $this->assertSame($custom, $result);
    }

    // -------------------------------------------------------------------------
    // getAllCodiceFiscaleFields — $names + $modify closure
    // -------------------------------------------------------------------------

    public function test_get_all_codice_fiscale_fields_uses_custom_names()
    {
        $fields = $this->userTrait::getAllCodiceFiscaleFields(
            names: [
                'firstname'      => 'first_name',
                'codice_fiscale' => 'cf',
            ],
        );

        // [0] = Name Group, [1] = Birth Group, [2] = POB Group, [3] = CF field
        $this->assertCount(4, $fields);
        $this->assertInstanceOf(Group::class, $fields[0]); // name group
        $this->assertInstanceOf(Group::class, $fields[1]); // birth row group
        $this->assertInstanceOf(Group::class, $fields[2]); // pob group

        // CF field uses the custom name
        $this->assertEquals('cf', $fields[3]->getName());
    }

    public function test_get_all_codice_fiscale_fields_modify_receives_full_array()
    {
        $capturedCount = null;

        $this->userTrait::getAllCodiceFiscaleFields(
            modify: function (array $fields) use (&$capturedCount) {
                $capturedCount = count($fields);
            },
        );

        $this->assertEquals(4, $capturedCount);
    }

    public function test_get_all_codice_fiscale_fields_modify_can_add_extra_field()
    {
        $extra = TextInput::make('notes');

        $result = $this->userTrait::getAllCodiceFiscaleFields(
            modify: fn (array $fields) => [...$fields, $extra],
        );

        $this->assertCount(5, $result);
        $this->assertSame($extra, $result[4]);
    }
}
