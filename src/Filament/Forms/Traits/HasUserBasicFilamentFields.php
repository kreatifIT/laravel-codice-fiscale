<?php

namespace Kreatif\CodiceFiscale\Filament\Forms\Traits;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Kreatif\CodiceFiscale\Filament\Forms\Components\CodiceFiscale;

trait HasUserBasicFilamentFields
{
    use HasCodiceFiscaleLabels;
    use HasGeoLocationFilamentFields;

    // -------------------------------------------------------------------------
    // Individual field builders
    // -------------------------------------------------------------------------

    public static function getFirstnameField(
        string $name = 'firstname',
        string|false|null $label = null,
    ): TextInput {
        return TextInput::make($name)
            ->label($label === false ? null : ($label ?: static::getFirstnameLabel()))
            ->maxLength(255);
    }

    public static function getLastnameField(
        string $name = 'lastname',
        string|false|null $label = null,
    ): TextInput {
        return TextInput::make($name)
            ->label($label === false ? null : ($label ?: static::getLastnameLabel()))
            ->maxLength(255);
    }

    public static function getDOBField(
        string $name = 'dob',
        string|false|null $label = null,
    ): DatePicker {
        return DatePicker::make($name)
            ->label($label === false ? null : ($label ?: static::getDOBLabel()))
            ->displayFormat(__('formats.date'))
            ->native(true);
    }

    public static function getGenderField(
        string $name = 'gender',
        string|false|null $label = null,
    ): Select {
        return Select::make($name)
            ->label($label === false ? null : ($label ?: static::getGenderLabel()))
            ->options(static::getGenderOptions())
            ->native(false);
    }

    /**
     * Searchable country-of-birth Select backed by geo_locations.
     *
     * @param  string  $valueColumn  'codice_catastale' (default) or 'id'
     */
    public static function getCountryOfBirthField(
        string $name = 'cob',
        string $valueColumn = 'codice_catastale',
        string|false|null $label = null,
        int $searchLimit = 30,
    ): Select {
        return static::getFilamentDropdownForCountry(
            $name,
            $valueColumn,
            $searchLimit,
        )
            ->label($label === false ? null : ($label ?: static::getCountryOfBirthLabel()));
    }

    /**
     * Dynamic place-of-birth field.
     * Shows a municipality Select when country is Italy, free TextInput otherwise.
     *
     * @param  string|int  $italyValue  '*' when valueColumn='codice_catastale', or Italy's numeric ID
     * @param  string      $valueColumn  'codice_catastale' (default) or 'id'
     */
    public static function getPlaceOfBirthField(
        string $name = 'pob',
        string $countryField = 'cob',
        string|int $italyValue = '*',
        string|false|null $label = null,
        string $valueColumn = 'codice_catastale',
        int $searchLimit = 15,
        ?string $freeTextInputNameForMunicipality = null,
        ?\Closure $modify = null,
    ): Group {
        return static::getFilamentDropdownForMunicipality(
            $name,
            $countryField,
            $italyValue,
            $label === false ? null : ($label ?: static::getPlaceOfBirthLabel()),
            $valueColumn,
            $searchLimit,
            $freeTextInputNameForMunicipality,
            $modify,
        );
    }

    public static function getCodiceFiscaleField(
        string $name = 'codice_fiscale',
        bool $enableGenerator = false,
        bool $enableValidator = false,
        bool $strictValidation = true,
        ?array $fieldMapping = null,
        string|false|null $label = null,
    ): CodiceFiscale {
        $field = CodiceFiscale::make($name)
            ->label($label === false ? null : ($label ?: static::getCodiceFiscaleLabel()));

        if ($enableGenerator) {
            $field->generator();
        }

        if ($enableValidator) {
            $field->validator();
        }

        if ($strictValidation) {
            $field->strict($fieldMapping ?? [
                'firstname' => 'firstname',
                'lastname'  => 'lastname',
                'dob'       => 'dob',
                'gender'    => 'gender',
                'pob'       => 'pob',
            ]);
        }

        return $field;
    }

    // -------------------------------------------------------------------------
    // Compound field builders
    // -------------------------------------------------------------------------

    /**
     * First + last name fields in a 2-column Group.
     *
     * @param  array<string, string>  $names   Override field names, e.g. ['firstname' => 'first_name', 'lastname' => 'last_name']
     * @param  \Closure|null          $modify  Receives the built Group; return a modified Group (or null to keep original)
     */
    public static function getNameFields(array $names = [], ?\Closure $modify = null): Group
    {
        $group = Group::make([
            static::getFirstnameField($names['firstname'] ?? 'firstname'),
            static::getLastnameField($names['lastname'] ?? 'lastname'),
        ])->columns(['sm' => 1, 'md' => 2])->columnSpanFull();

        return $modify ? ($modify($group) ?? $group) : $group;
    }

    /**
     * DOB + gender + country-of-birth + place-of-birth fields.
     *
     * @param  array<string, string>  $names   Override field names, e.g. ['dob' => 'birth_date', 'cob' => 'country', 'pob' => 'place']
     * @param  \Closure|null          $modify  Receives the built array; return a modified array (or null to keep original)
     */
    public static function getBirthFields(array $names = [], ?\Closure $modify = null): array
    {
        $cobKey = $names['cob'] ?? 'cob';

        $fields = [
            Group::make([
                static::getDOBField($names['dob'] ?? 'dob'),
                static::getGenderField($names['gender'] ?? 'gender'),
                static::getCountryOfBirthField($cobKey),
            ])->columns(['sm' => 1, 'md' => 3])->columnSpanFull(),
            static::getPlaceOfBirthField(
                $names['pob'] ?? 'pob',
                $cobKey,
            )->columnSpanFull(),
        ];

        return $modify ? ($modify($fields) ?? $fields) : $fields;
    }

    /**
     * All codice fiscale form fields: name + birth data + CF field.
     *
     * @param  array<string, string>  $names   Override any field name, e.g. ['firstname' => 'first_name', 'codice_fiscale' => 'cf']
     * @param  \Closure|null          $modify  Receives the built array; return a modified array (or null to keep original)
     */
    public static function getAllCodiceFiscaleFields(array $names = [], ?\Closure $modify = null): array
    {
        $fields = [
            static::getNameFields($names),
            ...static::getBirthFields($names),
            static::getCodiceFiscaleField($names['codice_fiscale'] ?? 'codice_fiscale'),
        ];

        return $modify ? ($modify($fields) ?? $fields) : $fields;
    }

    // -------------------------------------------------------------------------
    // Backward-compatibility aliases
    // -------------------------------------------------------------------------

    /**
     * @deprecated Use getNameFields(array $names, ?\Closure $modify) instead
     * Old signature: getNameFields(bool $required, ?int $maxLength, ?array $columns)
     */
    protected static function buildDynamicMunicipalityField(
        string $name,
        ?string $countryFieldName,
        bool $required,
        ?int $searchLimit,
        string|false|null $label = null,
    ): Group {
        return static::getPlaceOfBirthField($name, $countryFieldName ?? 'cob', '*', $label, 'codice_catastale', $searchLimit ?? 15);
    }
}
