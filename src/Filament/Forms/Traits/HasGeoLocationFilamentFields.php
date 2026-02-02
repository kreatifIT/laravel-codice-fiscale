<?php

namespace Kreatif\CodiceFiscale\Filament\Forms\Traits;


use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Kreatif\CodiceFiscale\Models\GeoLocation;

trait HasGeoLocationFilamentFields
{

    /**
     * @return class-string<\Kreatif\CodiceFiscale\Models\GeoLocation>
     */
    protected static function getCodiceFiscaleGeoLocationModel(): string
    {
        return config(
            'codice-fiscale.geo_locations_model',
            \Kreatif\CodiceFiscale\Models\GeoLocation::class
        );
    }

    public function getFilamentDropdownForCountry(string $name, bool $isRequired = true, ?string $locale = null): Select
    {
        $locale = $locale ?? app()->getLocale();
        $select = Select::make($name)
            ->searchable()
            ->live()
            ->getSearchResultsUsing(function (string $search) use ($locale) {
                return $this->getGeoLocationSearchResults(
                    $search,
                    config('codice-fiscale.item_types.stato'),
                    50,
                    $locale
                );
            })
            ->getOptionLabelUsing(function ($value) use ($locale) {
                // if ($value === '*') {
                //     return __('labels.Italy');
                // }
                return $this->getGeoLocationOptionLabelForFilament($value, config('codice-fiscale.item_types.stato'), $locale);
            })
            ->label(__('labels.'.$name));

        if ($isRequired) {
            $select->required();
        }

        return $select;
    }

    public static function getGeoLocationSearchResults(string $search, string $itemType, int $resultLimit = 50, ?string $locale = null): array
    {
        $model = self::getCodiceFiscaleGeoLocationModel();
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
        // where key is null, Filament gives error, so we replace the key with value
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
//        Log::warning('GeoLocationSearchResults: '.$search.' - '.$itemType.' - '.$resultLimit.' - '.$locale.' - '.json_encode($data));
        return $data;
    }

    public static function getGeoLocationOptionLabelForFilament(string $value, ?string $itemType = null, ?string $locale = null): string
    {
        $model = self::getCodiceFiscaleGeoLocationModel();
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

    /**
     * @param  string  $name  Field name
     * @param  string|null  $countryFieldDependOn  Name of the country field to depend on:
     *                                          - Italy ('*')  => use select of comuni
     *                                          - Other        => use free text input
     */
    public function getFilamentDropdownForMunicipality(
        string $name,
        ?string $countryFieldDependOn = null,
        bool $isRequired = true,
        bool $live = false,
        ?string $helperLabel = null,
        ?string $locale = null
    ): Group {
        $locale = $locale ?? app()->getLocale();
        return Group::make(function ($get) use ($name, $countryFieldDependOn, $isRequired, $helperLabel, $live, $locale) {
            // Default: Italian municipality select
            $field = Select::make($name)
                ->searchable()
                ->getSearchResultsUsing(function (string $search) use ($locale) {
                   return $this->getGeoLocationSearchResults($search, config('codice-fiscale.item_types.comune'), 50, $locale);
                })
                ->getOptionLabelUsing(function ($value) use ($locale) {
                    return $this->getGeoLocationOptionLabelForFilament($value, config('codice-fiscale.item_types.comune'), $locale);
                });

            if ($countryFieldDependOn) {
                $countryValue = $get($countryFieldDependOn);
                // If country is NOT Italy ('*'), use free-text input for municipality
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
                ->label(__('labels.'.$name))
                ->required($isRequired);

            return [$field];
        });
    }

}
