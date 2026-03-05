<?php

namespace Kreatif\CodiceFiscale\Filament\Forms\Traits;

trait HasCodiceFiscaleLabels
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

    public static function getGenderOptions(): array
    {
        return [
            'M' => trans('codice-fiscale::codice-fiscale.options.male'),
            'F' => trans('codice-fiscale::codice-fiscale.options.female'),
        ];
    }
}
