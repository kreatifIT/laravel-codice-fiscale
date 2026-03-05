<?php

namespace Kreatif\CodiceFiscale\Filament\Forms\Traits;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;

trait HasGeoLocationFilamentFields
{
    use HasCodiceFiscaleLabels;

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

    /**
     * Search geo-locations and return [$value => $label] pairs.
     *
     * @param  string  $valueColumn  'codice_catastale' (default) or 'id'
     */
    protected static function geoLocationSearchResults(
        string $search,
        string $itemType,
        int $limit = 50,
        string $valueColumn = 'codice_catastale',
    ): array {
        $locale = app()->getLocale();
        $labelColumn = in_array($locale, ['de', 'en']) ? 'denominazione_' . $locale : 'denominazione';

        return static::getGeoLocationModel()::searchOptions($search, $itemType, $limit)
            ->mapWithKeys(function ($location) use ($valueColumn, $labelColumn) {
                $label = $location->getAttributeValue($labelColumn)
                    ?? $location->getAttributeValue('denominazione')
                    ?? $location->getAttributeValue('denominazione_de')
                    ?? $location->getAttributeValue('denominazione_en')
                    ?? (string) $location->getAttributeValue($valueColumn);

                return [$location->getAttributeValue($valueColumn) => $label];
            })
            ->toArray();
    }

    /**
     * Resolve the display label for a stored geo-location value.
     *
     * @param  string  $valueColumn  'codice_catastale' (default) or 'id'
     */
    protected static function geoLocationOptionLabel(
        mixed $value,
        string $itemType,
        string $valueColumn = 'codice_catastale',
        string $fallbackColumn = 'denominazione',
    ): string {
        $locale = app()->getLocale();
        $labelColumn = in_array($locale, ['de', 'en']) ? 'denominazione_' . $locale : $fallbackColumn;

        $record = static::getGeoLocationModel()::query()
            ->where($valueColumn, $value)
            ->where('item_type', $itemType)
            ->first();

        if (! $record) {
            return (string) $value;
        }

        return $record->getAttributeValue($labelColumn)
            ?? $record->getAttributeValue($fallbackColumn)
            ?? (string) $value;
    }

    /**
     * Searchable country/state Select backed by geo_locations.
     *
     * @param  string  $valueColumn  'codice_catastale' (default) or 'id'
     */
    public static function getFilamentDropdownForCountry(
        string $name,
        string $valueColumn = 'codice_catastale',
        int $searchLimit = 50,
    ): Select {
        return Select::make($name)
            ->label(static::getCountryOfBirthLabel())
            ->searchable()
            ->live()
            ->getSearchResultsUsing(fn (string $search) => static::geoLocationSearchResults(
                $search,
                config('codice-fiscale.item_types.stato'),
                $searchLimit,
                $valueColumn,
            ))
            ->getOptionLabelUsing(fn ($value) => static::geoLocationOptionLabel(
                $value,
                config('codice-fiscale.item_types.stato'),
                $valueColumn,
            ));
    }

    /**
     * Dynamic place-of-birth field.
     * - Municipality Select when country value matches $italyValue
     * - Free TextInput for any other country
     *
     * @param  string|int  $italyValue       '*' when valueColumn='codice_catastale', or Italy's numeric ID
     * @param  string      $valueColumn      'codice_catastale' (default) or 'id'
     * @param  \Closure|null $modify         Receives the built field via named injections:
     *                                       $field, $countryValue, $italyValue, $isItaly
     *                                       Return a component to replace it, or void to mutate in place.
     */
    public static function getFilamentDropdownForMunicipality(
        string $name,
        ?string $countryField = null,
        string|int $italyValue = '*',
        ?string $label = null,
        string $valueColumn = 'codice_catastale',
        int $searchLimit = 50,
        ?string $freeTextInputName = null,
        ?\Closure $modify = null,
    ): Group {
        $freeTextInputName = $freeTextInputName ?? $name;

        return Group::make(function ($get) use ($name, $countryField, $italyValue, $label, $valueColumn, $searchLimit, $freeTextInputName, $modify) {
            return static::resolveMunicipalityField($get, $name, $countryField, $italyValue, $label, $valueColumn, $searchLimit, $freeTextInputName, $modify);
        });
    }

    /**
     * Resolve the concrete field(s) for the municipality Group.
     * Extracted for testability — call this directly in tests instead of going through the Filament schema lifecycle.
     *
     * @param  callable    $get              Filament Get helper (or any callable in tests)
     * @param  string|int  $italyValue       '*' for codice_catastale, or Italy's actual value for other columns
     * @param  \Closure|null $modify         Receives named injections: field, countryValue, italyValue, isItaly.
     *                                       Return a component to replace the default, or void to mutate in place.
     * @return array<int, \Filament\Forms\Components\Field>
     */
    public static function resolveMunicipalityField(
        callable $get,
        string $name,
        ?string $countryField = null,
        string|int $italyValue = '*',
        ?string $label = null,
        string $valueColumn = 'codice_catastale',
        int $searchLimit = 50,
        string $freeTextInputName = '',
        ?\Closure $modify = null,
    ): array {
        $freeTextInputName = $freeTextInputName ?: $name;
        $resolvedLabel = $label ?? static::getPlaceOfBirthLabel();
        $countryValue = null;

        if ($countryField) {
            $countryValue = $get($countryField);

            // When using a non-catastale value column and the caller left $italyValue as '*',
            // we cannot rely on '*' — resolve Italy's actual stored value from the database.
            if ($valueColumn !== 'codice_catastale' && $italyValue === '*') {
                $italyRecord = static::getGeoLocationModel()::query()
                    ->where('item_type', config('codice-fiscale.item_types.stato'))
                    ->where(fn ($q) => $q
                        ->where('denominazione', 'Italia')
                        ->orWhere('denominazione_en', 'Italy')
                        ->orWhere('denominazione_de', 'Italien')
                    )
                    ->first();

                if ($italyRecord) {
                    $italyValue = $italyRecord->getAttributeValue($valueColumn);
                }
            }

            if ($countryValue && (string) $countryValue !== (string) $italyValue) {
                $field = TextInput::make($freeTextInputName)
                    ->label($resolvedLabel)
                    ->maxLength(150);

                return [static::applyModify($field, $modify, $countryValue, $italyValue, false)];
            }
        }

        $field = Select::make($name)
            ->label($resolvedLabel)
            ->searchable()
            ->getSearchResultsUsing(fn (string $search) => static::geoLocationSearchResults(
                $search,
                config('codice-fiscale.item_types.comune'),
                $searchLimit,
                $valueColumn,
            ))
            ->getOptionLabelUsing(fn ($value) => static::geoLocationOptionLabel(
                $value,
                config('codice-fiscale.item_types.comune'),
                $valueColumn,
            ));

        return [static::applyModify($field, $modify, $countryValue, $italyValue, $countryValue !== null)];
    }

    /**
     * Apply the $modify closure to a field.
     * Supports both mutation (void return) and replacement (return a new component).
     *
     * @param  \Filament\Forms\Components\Field  $field
     * @param  mixed  $countryValue  The current country field value (null if no country dependency)
     * @param  string|int  $italyValue  The resolved Italy value
     * @param  bool  $isItaly  Whether the current country is Italy
     * @return \Filament\Forms\Components\Field
     */
    protected static function applyModify(
        mixed $field,
        ?\Closure $modify,
        mixed $countryValue,
        string|int $italyValue,
        bool $isItaly,
    ): mixed {
        if (! $modify) {
            return $field;
        }

        $result = $field->evaluate($modify, [
            'field'        => $field,
            'countryValue' => $countryValue,
            'italyValue'   => $italyValue,
            'isItaly'      => $isItaly,
        ]);

        // If the closure returned a replacement component, use it.
        // Otherwise, mutations on $field (object reference) are already applied.
        return ($result instanceof \Filament\Forms\Components\Field) ? $result : $field;
    }

    // -------------------------------------------------------------------------
    // Backward-compatibility aliases
    // -------------------------------------------------------------------------

    /** @deprecated Use getGeoLocationModel() */
    protected static function getCodiceFiscaleGeoLocationModel(): string
    {
        return static::getGeoLocationModel();
    }

    /** @deprecated Use geoLocationSearchResults() */
    public static function getGeoLocationSearchResults(string $search, string $itemType, int $resultLimit = 50, ?string $locale = null): array
    {
        return static::geoLocationSearchResults($search, $itemType, $resultLimit);
    }

    /** @deprecated Use geoLocationOptionLabel() */
    public static function getGeoLocationOptionLabelForFilament(string $value, ?string $itemType = null, ?string $locale = null): string
    {
        return static::geoLocationOptionLabel(
            $value,
            $itemType ?? config('codice-fiscale.item_types.stato'),
        );
    }
}
