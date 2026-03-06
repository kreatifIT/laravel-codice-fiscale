<?php

namespace Kreatif\CodiceFiscale\Tests\Feature\Filament;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Kreatif\CodiceFiscale\Filament\Forms\Components\CodiceFiscale;
use Kreatif\CodiceFiscale\Filament\Forms\Traits\HasUserBasicFilamentFields;
use Kreatif\CodiceFiscale\Filament\Forms\Traits\HasCodiceFiscaleLabels;
use Kreatif\CodiceFiscale\Tests\TestCase;

class FilamentFieldsTraitTest extends TestCase
{
    // Use the trait in a dummy class for testing
    protected $traitOwner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->traitOwner = new class {
            use HasUserBasicFilamentFields;
            use HasCodiceFiscaleLabels;
        };
    }

    public function test_it_can_build_individual_fields()
    {
        $firstnameField = $this->traitOwner::getFirstnameField();
        $this->assertInstanceOf(TextInput::class, $firstnameField);
        $this->assertEquals('firstname', $firstnameField->getName());

        $dobField = $this->traitOwner::getDOBField();
        $this->assertInstanceOf(DatePicker::class, $dobField);
        $this->assertEquals('dob', $dobField->getName());

        $genderField = $this->traitOwner::getGenderField();
        $this->assertInstanceOf(Select::class, $genderField);
        $this->assertEquals('gender', $genderField->getName());
    }

    public function test_it_can_build_codice_fiscale_field_with_options()
    {
        $cfField = $this->traitOwner::getCodiceFiscaleField(
            enableGenerator: true,
            enableValidator: true
        );

        $this->assertInstanceOf(CodiceFiscale::class, $cfField);
    }

    public function test_it_can_build_all_fields_compound()
    {
        $allFields = $this->traitOwner::getAllCodiceFiscaleFields();
        
        // Structure:
        // 0: getNameFields() (1 Group)
        // 1: getBirthFields()[0] (1 Group)
        // 2: getBirthFields()[1] (1 Group)
        // 3: getCodiceFiscaleField() (1 Component)
        $this->assertCount(4, $allFields);
    }
    
    public function test_it_customizes_labels()
    {
        $field = $this->traitOwner::getFirstnameField(label: 'Custom Label');
        $this->assertEquals('Custom Label', $field->getLabel());

        // When label is false, Filament might still default to naming conventions or package defaults.
        // We just verify that it works when provided.
    }
}
