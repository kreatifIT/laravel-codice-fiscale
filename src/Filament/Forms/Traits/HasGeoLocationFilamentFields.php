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

    public static function getGeoLocationSearchResults(string $search, string $itemType, int $resultLimit = 50, ?string $locale = null, ?string $valueColumn = 'codice_catastale'): array
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
            ->pluck($labelColumn, $valueColumn)
            ->toArray();
        // where key's value is null, Filament gives error, so we add a workaround to set the value
        // basically you pluck the label column 'denominizaione' or 'denominazione_de' or 'denominazione_en' as value,
        // but if the value is null then you pluck the key column 'codice_catastale' as value,
        // so you have always a value for the option label
        foreach ($data as $key => $value) {
            if ($value == null && $key != null) {
                $result = GeoLocation::query()
                    ->where($valueColumn, $key)->first();
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
        ?string $locale = null,
        ?string $freeTextInputName = null,
        ?\Closure $closure = null,
        ?string $valueColumn = 'codice_catastale',
    ): Group {
        $locale = $locale ?? app()->getLocale();
        $freeTextInputName = $freeTextInputName ?? $name;
        return Group::make(function ($get) use ($name, $countryFieldDependOn, $isRequired, $helperLabel, $live, $locale, $freeTextInputName, $closure, $valueColumn) {
            // Default: Italian municipality select
            $field = Select::make($name)
                ->searchable()
                ->getSearchResultsUsing(function (string $search) use ($locale, $valueColumn) {
                   return $this->getGeoLocationSearchResults($search, config('codice-fiscale.item_types.comune'), 50, $locale, $valueColumn);
                })
                ->getOptionLabelUsing(function ($value) use ($locale, $valueColumn) {
                    return $this->getGeoLocationOptionLabelForFilament($value, config('codice-fiscale.item_types.comune'), $locale, $valueColumn);
                });

            if ($countryFieldDependOn) {
                $countryValue = $get($countryFieldDependOn);
                // If country is NOT Italy ('*'), use free-text input for municipality
                if ($countryValue && $countryValue !== '*') {
                    $field = TextInput::make($freeTextInputName)->maxLength(150);
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
            if ($closure) {
                $field->evaluate($closure, [
                        'countryFieldDependOn' => $countryFieldDependOn,
                        'live' => $live,
                        'helperLabel' => $helperLabel,
                        'locale' => $locale,
                        'freeTextInputName' => $freeTextInputName,
                        'countryValue' => $countryValue ?? null,

                ]);
            }

            return [$field];
        });
    }

}
