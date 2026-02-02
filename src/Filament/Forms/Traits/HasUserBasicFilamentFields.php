<?php

namespace Kreatif\CodiceFiscale\Filament\Forms\Traits;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Kreatif\CodiceFiscale\Filament\Forms\Components\CodiceFiscale;
use Kreatif\CodiceFiscale\Models\GeoLocation;

trait HasUserBasicFilamentFields
{
    public static function getFirstnameLabel(): string
    {
        return trans('codice-fiscale::codice-fiscale.fields.first_name');
    }

    public static function getLastnameLabel(): string
    {
        return trans('codice-fiscale::codice-fiscale.fields.last_name');
    }

    public static function getDOBLabel(): string
    {
        return trans('codice-fiscale::codice-fiscale.fields.date_of_birth');
    }

    public static function getGenderLabel(): string
    {
        return trans('codice-fiscale::codice-fiscale.fields.gender');
    }

    public static function getFirstnameKey(): string
    {
        return "firstname";
    }

    public static function getCountryOfBirthLabel(): string
    {
        return trans('codice-fiscale::codice-fiscale.fields.country_of_birth');
    }

    public static function getPlaceOfBirthLabel(): string
    {
        return trans('codice-fiscale::codice-fiscale.fields.place_of_birth');
    }

    public static function getCodiceFiscaleLabel(): string
    {
        return trans('codice-fiscale::codice-fiscale.fields.codice_fiscale');
    }

    public static function getFirstnameField(
        string $name = 'firstname',
        bool $required = true,
        ?int $maxLength = 255,
        string|bool|null $label = null
    ): TextInput {
        $field = TextInput::make($name)
            ->label($label === false ? null : ($label ?: static::getFirstnameLabel()));

        if ($maxLength) {
            $field->maxLength($maxLength);
        }

        return $required ? $field->required() : $field;
    }

    public static function getLastnameField(
        string $name = 'lastname',
        bool $required = true,
        ?int $maxLength = 255,
        string|bool|null $label = null
    ): TextInput {
        $field = TextInput::make($name)
            ->label($label === false ? null : ($label ?: static::getLastnameLabel()));

        if ($maxLength) {
            $field->maxLength($maxLength);
        }

        return $required ? $field->required() : $field;
    }

    public static function getDOBField(
        string $name = 'dob',
        bool $required = true,
        ?string $displayFormat = null,
        string|bool|null $label = null
    ): DatePicker {
        $field = DatePicker::make($name)
            ->label($label === false ? null : ($label ?: static::getDOBLabel()))
            ->displayFormat($displayFormat ?? __('formats.date'))
            ->native(true);

        return $required ? $field->required() : $field;
    }

    public static function getGenderField(
        string $name = 'gender',
        bool $required = true,
        ?array $options = null,
        string|bool|null $label = null
    ): Select {
        $field = Select::make($name)
            ->label($label === false ? null : ($label ?: static::getGenderLabel()))
            ->options($options ?? [
                'M' => trans('codice-fiscale::codice-fiscale.options.male'),
                'F' => trans('codice-fiscale::codice-fiscale.options.female'),
            ])
            ->native(false);

        return $required ? $field->required() : $field;
    }


    public static function getCountryOfBirthField(
        string $name = 'cob',
        bool $required = true,
        bool $live = true,
        ?int $searchLimit = 30,
        string|bool|null $label = null
    ): Select {
        $field = Select::make($name)
            ->label($label === false ? null : ($label ?: static::getCountryOfBirthLabel()))
            ->searchable()
            ->getSearchResultsUsing(function (string $search) use ($searchLimit) {
                $options = static::getGeoLocationModel()::searchOptions(
                    $search,
                    config('codice-fiscale.item_types.stato'),
                    limit: $searchLimit
                )
                    ->mapWithKeys(fn($location) => [$location->codice_catastale => $location->getLabel()])
                    ->toArray();
                return $options;
            })
            ->getOptionLabelUsing(fn($value) => static::getGeoLocationModel()::findByBelfioreCode($value)?->getLabel());

        if ($required) {
            $field->required();
        }

        if ($live) {
            $field->live();
        }

        return $field;
    }

    public static function getPlaceOfBirthField(
        string $name = 'pob',
        bool $required = true,
        ?string $countryFieldName = 'cob',
        ?int $searchLimit = 15,
        string|bool|null $label = null
    ): Group {
        return static::buildDynamicMunicipalityField(
            $name,
            $countryFieldName,
            $required,
            $searchLimit,
            $label
        );
    }

    public static function getCodiceFiscaleField(
        string $name = 'codice_fiscale',
        bool $required = true,
        bool $enableGenerator = false,
        bool $enableValidator = false,
        bool $strictValidation = true,
        ?array $fieldMapping = null,
        string|bool|null $label = null
    ): CodiceFiscale {
        $field = CodiceFiscale::make($name);

        if ($label !== null) {
            $field->label($label === false ? null : ($label ?: static::getCodiceFiscaleLabel()));
        }

        if ($required) {
            $field->required();
        }

        if ($enableGenerator) {
            $field->generator();
        }

        if ($enableValidator) {
            $field->validator();
        }

        if ($strictValidation) {
            $field->strict($fieldMapping ?? [
                'firstname' => 'firstname',
                'lastname' => 'lastname',
                'dob' => 'dob',
                'gender' => 'gender',
                'pob' => 'pob',
            ]);
        }

        return $field;
    }

    public static function getNameFields(
        bool $required = true,
        ?int $maxLength = 255,
        ?array $columns = null
    ): Group {
        return Group::make([
            static::getFirstnameField('firstname', $required, $maxLength),
            static::getLastnameField('lastname', $required, $maxLength),
        ])->columns($columns ?? [
            'sm' => 1,
            'md' => 2,
        ])->columnSpanFull();
    }

    public static function getBirthFields(
        bool $required = true,
        array $columns = ['sm' => 1, 'md' => 3],
        int $searchLimit = 15
    ): array {
        return [
            Group::make([
                static::getDOBField('dob', $required),
                static::getGenderField('gender', $required),
                static::getCountryOfBirthField('cob', $required, true, $searchLimit),
            ])->columns($columns)->columnSpanFull(),
            static::getPlaceOfBirthField('pob', $required, 'cob', $searchLimit)
                ->columnSpanFull(),
        ];
    }

    public static function getAllCodiceFiscaleFields(
        bool $required = true,
        ?int $maxLength = 255,
        ?int $searchLimit = 15,
        bool $enableCFGenerator = false,
        bool $enableCFValidator = false,
        bool $strictCFValidation = true
    ): array {
        return [
            static::getNameFields($required, $maxLength),
            ...static::getBirthFields($required, searchLimit: $searchLimit),
            static::getCodiceFiscaleField(
                'codice_fiscale',
                $required,
                $enableCFGenerator,
                $enableCFValidator,
                $strictCFValidation
            ),
        ];
    }

    /**
     * @return class-string<\Kreatif\CodiceFiscale\Models\GeoLocation>
     */
    protected static function getGeoLocationModel(): string
    {
        return config(
            'codice-fiscale.geo_locations_model',
            \Kreatif\CodiceFiscale\Models\GeoLocation::class
        );
    }

    protected static function buildDynamicMunicipalityField(
        string $name,
        ?string $countryFieldName,
        bool $required,
        ?int $searchLimit,
        string|bool|null $label = null
    ): Group {
        return static::getFilamentDropdownForMunicipality(
            $name,
            $countryFieldName,
            $required,
            false,
            null,
            null,
            $searchLimit,
            $label
        );
    }

    protected static function getFilamentDropdownForCountry(
        string $name,
        bool|\Closure $required = true,
        ?string $locale = null,
        ?int $searchLimit = 50
    ): Select {
        $locale = $locale ?? app()->getLocale();
        $select = Select::make($name)
            ->searchable()
            ->required($required)
            ->live()
            ->getSearchResultsUsing(function (string $search) use ($locale, $searchLimit) {
                return static::getGeoLocationSearchResults(
                    $search,
                    config('codice-fiscale.item_types.stato'),
                    $searchLimit,
                    $locale
                );
            })
            ->getOptionLabelUsing(function ($value) use ($locale) {
                return static::getGeoLocationOptionLabelForFilament(
                    $value,
                    config('codice-fiscale.item_types.stato'),
                    $locale
                );
            })
            ->label(__('labels.'.$name));

        return $select;
    }

    protected static function getFilamentDropdownForMunicipality(
        string $name,
        ?string $countryFieldDependOn = null,
        bool $isRequired = true,
        bool $live = false,
        ?string $helperLabel = null,
        ?string $locale = null,
        ?int $searchLimit = 50,
        string|bool|null $label = null
    ): Group {
        $locale = $locale ?? app()->getLocale();
        return Group::make(function ($get) use ($name, $countryFieldDependOn, $isRequired, $helperLabel, $live, $locale, $searchLimit, $label) {
            $field = Select::make($name)
                ->searchable()
                ->getSearchResultsUsing(function (string $search) use ($locale, $searchLimit) {
                    return static::getGeoLocationSearchResults(
                        $search,
                        config('codice-fiscale.item_types.comune'),
                        $searchLimit,
                        $locale
                    );
                })
                ->getOptionLabelUsing(function ($value) use ($locale) {
                    return static::getGeoLocationOptionLabelForFilament(
                        $value,
                        config('codice-fiscale.item_types.comune'),
                        $locale
                    );
                });

            if ($countryFieldDependOn) {
                $countryValue = $get($countryFieldDependOn);
                if ($countryValue && $countryValue !== '*') {
                    $field = TextInput::make($name)->maxLength(150);
                }
            }
            if ($live) {
                $field->live();
            }
            if ($helperLabel) {
                $field->helperText($helperLabel);
            }

            $field
                ->label($label === false ? null : ($label ?: static::getPlaceOfBirthLabel()))
                ->required($isRequired);

            return [$field];
        });
    }

    protected static function getGeoLocationSearchResults(
        string $search,
        string $itemType,
        int $resultLimit = 50,
        ?string $locale = null
    ): array {
        $model = static::getGeoLocationModel();
        $locale = $locale ?? app()->getLocale();
        $labelColumn = 'denominazione';
        if (in_array($locale, ['de', 'en'])) {
            $labelColumn = 'denominazione_'.$locale;
        }
        $data = $model::searchOptions(
            $search,
            $itemType,
            $resultLimit
        )
            ->pluck($labelColumn, 'codice_catastale')
            ->toArray();

        foreach ($data as $key => $value) {
            if ($value == null && $key != null) {
                $result = GeoLocation::query()
                    ->where('codice_catastale', $key)->first();
                if ($result) {
                    $value = $result->getAttributeValue('denominazione') ??
                        $result->getAttributeValue('denominazione_de') ??
                        $result->getAttributeValue('denominazione_en') ??
                        $result->getAttributeValue('altra_denominazione') ??
                        $key;
                    $data[$key] = $value;
                }
            }
        }
        return $data;
    }

    protected static function getGeoLocationOptionLabelForFilament(
        string $value,
        ?string $itemType = null,
        ?string $locale = null
    ): string {
        $model = static::getGeoLocationModel();
        $locale = $locale ?? app()->getLocale();
        $labelColumn = 'denominazione';
        if (in_array($locale, ['de', 'en'])) {
            $labelColumn = 'denominazione_'.$locale;
        }

        $query = $model::query()
            ->where('codice_catastale', $value);

        if ($itemType) {
            $query->where('item_type', $itemType);
        }

        return $query->value($labelColumn) ?? $value;
    }
}
