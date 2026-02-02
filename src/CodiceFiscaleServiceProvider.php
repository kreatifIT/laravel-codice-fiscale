<?php

namespace Kreatif\CodiceFiscale;

use Illuminate\Support\Facades\Validator;
use Kreatif\CodiceFiscale\Actions\CalculateCodiceFiscale;
use Kreatif\CodiceFiscale\Actions\FindBelfioreCode;
use Kreatif\CodiceFiscale\Actions\ValidateCodiceFiscale;
use Kreatif\CodiceFiscale\Commands\SyncGeoLocationsCommand;
use Kreatif\CodiceFiscale\Rules\CodiceFiscaleMatchesData;
use Kreatif\CodiceFiscale\Rules\ValidCodiceFiscale;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CodiceFiscaleServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('codice-fiscale')
            ->hasConfigFile('codice-fiscale')
            ->hasMigration('create_geo_locations_table')
            ->hasTranslations()
            ->hasCommands([
                SyncGeoLocationsCommand::class,
            ])
            ->discoversMigrations()
            ->runsMigrations();

    }

    public function packageRegistered(): void
    {
        $this->app->singleton(
            FindBelfioreCode::class,
            function ($app) {
                return new FindBelfioreCode();
            }
        );

        $this->app->singleton(
            CalculateCodiceFiscale::class,
            function ($app) {
                return new CalculateCodiceFiscale();
            }
        );

        $this->app->singleton(
            ValidateCodiceFiscale::class,
            function ($app) {
                return new ValidateCodiceFiscale();
            }
        );
    }

    public function packageBooted(): void
    {
        // Register custom validation rules
        $this->registerValidationRules();

        // Publish data files
        $this->publishes([
            __DIR__ . '/../resources/data' => resource_path('data'),
        ], 'codice-fiscale-data');
    }

    protected function registerValidationRules(): void
    {
        // Register string-based validation rule for ValidCodiceFiscale
        Validator::extend('codice_fiscale', function ($attribute, $value, $parameters, $validator) {
            $rule = new ValidCodiceFiscale();
            $passes = true;
            $rule->validate($attribute, $value, function ($message) use (&$passes) {
                $passes = false;
            });

            return $passes;
        }, trans('codice-fiscale::codice-fiscale.validation.invalid_codice_fiscale'));

        // Register string-based validation rule for CodiceFiscaleMatchesData
        Validator::extend('codice_fiscale_matches', function ($attribute, $value, $parameters, $validator) {
            $fieldMapping = $this->parseFieldMapping($parameters);
            $rule = CodiceFiscaleMatchesData::strict($fieldMapping)
                ->setData($validator->getData());
            $passes = true;
            $rule->validate($attribute, $value, function ($message) use (&$passes) {
                $passes = false;
            });

            return $passes;
        }, trans('codice-fiscale::codice-fiscale.validation.codice_fiscale_mismatch'));
    }

    /**
     * Parse field mapping from validation rule parameters.
     *
     * Converts: 'firstName:first_name,lastName:last_name'
     * To: ['firstName' => 'first_name', 'lastName' => 'last_name']
     */
    protected function parseFieldMapping(array $parameters): array
    {
        if (empty($parameters)) {
            return [];
        }

        $mapping = [];

        foreach ($parameters as $parameter) {
            if (str_contains($parameter, ':')) {
                [$key, $value] = explode(':', $parameter, 2);
                $mapping[trim($key)] = trim($value);
            }
        }

        return $mapping;
    }

    /**
     * Get the validation error message.
     */
    protected function getValidationMessage(string $key): string
    {
        $messages = [
            'codice_fiscale' => 'The :attribute is not a valid Italian Codice Fiscale.',
            'codice_fiscale_matches' => 'The :attribute does not match the provided personal data.',
        ];
        return $messages[$key] ?? $messages['codice_fiscale'];
    }
}
