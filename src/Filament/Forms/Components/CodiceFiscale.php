<?php

namespace Kreatif\CodiceFiscale\Filament\Forms\Components;

use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Log;
use Kreatif\CodiceFiscale\Actions\CalculateCodiceFiscale;
use Kreatif\CodiceFiscale\Actions\FindBelfioreCode;
use Kreatif\CodiceFiscale\Actions\ValidateCodiceFiscale;
use Kreatif\CodiceFiscale\Rules\CodiceFiscaleMatchesData;
use Kreatif\CodiceFiscale\Rules\ValidCodiceFiscale as ValidCodiceFiscaleRule;

/**
 * Filament CodiceFiscale Field Component
 *
 * A comprehensive Filament field for Italian Codice Fiscale with:
 * - Generator modal with auto-fill from form
 * - Validator button with visual feedback
 * - Strict validation against other form fields
 * - Flexible and overrideable action classes
 *
 * Usage:
 *   CodiceFiscale::make('codice_fiscale')
 *       ->generator()
 *       ->validator()
 *       ->strict(['firstName' => 'first_name', 'lastName' => 'last_name', 'dob' => 'dob','gender' => 'gender', 'pob' => 'pob'])
 *        // or
 *        ->strict(function() { // for strict validating
 *          // Dynamic field mapping
 *          return ['firstName' => 'first_name', 'lastName' => 'last_name','dob' => 'dob','gender' => 'gender','pob' => 'pob'];
 *        })
 *       ->usingFinder(MyCustomFinder::class)
 *       ->usingCalculator(MyCustomCalculator::class)
 *       ->usingValidator(MyCustomValidator::class);
 */
class CodiceFiscale extends TextInput
{
    protected bool|\Closure $hasGenerator = false;
    protected bool|\Closure $hasValidator = false;
    protected array|\Closure|null $strictFields = null;
    protected string|\Closure|null $locale = null;

    // Overrideable action classes or closures
    protected string|\Closure|null $finderActionClass = null;
    protected string|\Closure|null $calculatorActionClass = null;
    protected string|\Closure|null $validatorActionClass = null;


    public static function make(?string $name = 'codice_fiscale'): static
    {
        $instance = parent::make($name);
        $instance->label(__('codice-fiscale::codice-fiscale.fields.codice_fiscale'));
        return $instance;
    }

    public function usingLocale(string|\Closure|null $locale): static
    {
        $this->locale = $locale;
        return $this;
    }

    public function getComponentLocale(): string
    {
        return $this->evaluate($this->locale) ?? app()->getLocale();
    }

    /**
     * Enable the generator button with modal.
     * @param  bool|\Closure  $enabled  True/false or closure for conditional enabling
     */
    public function generator(bool|\Closure $enabled = true): static
    {
        $this->hasGenerator = $enabled;
        $this->prefixActions([
            $this->makeGeneratorAction(),
        ]);
        return $this;
    }

    /**
     * Enable the validator button.
     *
     * @param  bool|\Closure  $enabled  True/false or closure for conditional enabling
     *
     */
    public function validator(bool|\Closure $enabled = true): static
    {
        $this->hasValidator = $enabled;
        $this->suffixActions([
            $this->makeValidatorAction(),
        ]);

        return $this;
    }

    /**
     * Enable strict validation against form fields.
     *
     * @param  array|\Closure  $fields  Field mapping or closure returning field mapping
     *                               Closure receives: ($record, $get)
     *
     * Examples:
     *   ->strict(['firstName' => 'first_name', ...])
     *   ->strict(fn() => ['firstName' => 'name']) // Dynamic mapping
     *   ->strict(fn($record, $get) => $record->type === 'person'
     *       ? ['firstName' => 'first_name', ...]
     *       : null
     *   )
     */
    public function strict(array|\Closure $fields): static
    {
        $this->strictFields = $fields;
        return $this;
    }

    /**
     * Alias for strict() with a more descriptive name.
     *
     * @param  array|\Closure  $fields  Field mapping for strict validation
     */
    public function verifyAgainst(array|\Closure $fields): static
    {
        return $this->strict($fields);
    }

    /**
     * Alias for strict() with another descriptive name.
     *
     * @param  array|\Closure  $fields  Field mapping for strict validation
     */
    public function validateAgainstFields(array|\Closure $fields): static
    {
        return $this->strict($fields);
    }

    /**
     * Override the default Belfiore code finder action.
     *
     * @param  string|\Closure  $action  Class that extends FindBelfioreCode or closure
     *                                Closure receives: ($placeName, $record, $get, $set)
     *                                Must return: string|null (Belfiore code)
     *
     * Example with class:
     *   ->usingFinder(MyCustomFinder::class)
     *
     * Example with closure:
     *   ->usingFinder(function ($placeName, $record, $get, $set) {
     *       // Your custom logic
     *       return 'H501';
     *   })
     */
    public function usingFinder(string|\Closure $action): static
    {
        $this->finderActionClass = $action;
        return $this;
    }

    /**
     * Override the default Codice Fiscale calculator action.
     *
     * @param  string|\Closure  $action  Class that extends CalculateCodiceFiscale or closure
     *                                Closure receives: ($data, $record, $get, $set)
     *                                Must return: string|null (Codice Fiscale)
     *
     * Example with class:
     *   ->usingCalculator(MyCustomCalculator::class)
     *
     * Example with closure:
     *   ->usingCalculator(function ($data, $record, $get, $set) {
     *       // Your custom logic
     *       return 'RSSMRA90A01H501Z';
     *   })
     */
    public function usingCalculator(string|\Closure $action): static
    {
        $this->calculatorActionClass = $action;
        return $this;
    }

    /**
     * Override the default Codice Fiscale validator action.
     *
     * @param  string|\Closure  $action  Class that extends ValidateCodiceFiscale or closure
     *                                Closure receives: ($codiceFiscale, $data, $record, $get, $set)
     *                                Must return: bool (is valid)
     */
    public function usingValidator(string|\Closure $action): static
    {
        $this->validatorActionClass = $action;
        return $this;
    }

    public function using(string|\Closure $action): static
    {
        $this->finderActionClass = $action;
        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->placeholder('RSSMRA90A01H501Z')
            ->mask('aaaaaa99a99a999a')
            ->minLength(16)
            ->maxLength(16)
            ->rules(function () {
                $rules = [new ValidCodiceFiscaleRule];

                $strictFields = $this->evaluateStrictFields();
                Log::debug('CodiceFiscale Field - Strict Fields: ', ['fields' => $strictFields]);
                if ($strictFields) {
                    $rules[] = CodiceFiscaleMatchesData::strict(fieldMapping: $strictFields);
                }
                Log::debug('Total Rules for CodiceFiscale Field: ', ['rules_count' => count($rules)]);
                return $rules;
            })
            ->dehydrateStateUsing(fn(?string $state): ?string => $state ? strtoupper($state) : null)
            ->formatStateUsing(fn(?string $state): ?string => $state ? strtoupper($state) : null);
    }

    protected function makeGeneratorAction(): Action
    {
        return Action::make('generate_cf')
                     ->icon('heroicon-o-sparkles')
                     ->tooltip(fn() => trans('codice-fiscale::codice-fiscale.actions.generate_tooltip', [], $this->getComponentLocale()))
                     ->color('primary')
                     ->modalWidth('2xl')
                     ->modalHeading(fn() => trans('codice-fiscale::codice-fiscale.actions.generate_title', [], $this->getComponentLocale()))
                     ->modalSubmitActionLabel(fn() => trans('codice-fiscale::codice-fiscale.actions.generate_and_fill', [], $this->getComponentLocale()))
                     ->visible(fn() => $this->evaluateGeneratorVisibility())
                     ->fillForm(fn(Get $get): array => $this->getGeneratorFormData($get))
                     ->schema([
                         Group::make([
                             TextInput::make('firstname')
                                      ->label(fn() => trans('codice-fiscale::codice-fiscale.fields.first_name', [], $this->getComponentLocale()))
                                      ->required()
                                      ->maxLength(255),
                             TextInput::make('lastname')
                                      ->label(fn() => trans('codice-fiscale::codice-fiscale.fields.last_name', [], $this->getComponentLocale()))
                                      ->required()
                                      ->maxLength(255),
                         ])->columns([
                             'sm' => 1,
                             'md' => 2,
                         ])->columnSpanFull(),

                         Group::make([
                             DatePicker::make('dob')
                                       ->label(fn() => trans('codice-fiscale::codice-fiscale.fields.date_of_birth', [], $this->getComponentLocale()))
                                       ->required()
                                       ->displayFormat('d/m/Y')
                                       ->native(false),
                             Select::make('gender')
                                   ->label(fn() => trans('codice-fiscale::codice-fiscale.fields.gender', [], $this->getComponentLocale()))
                                   ->options([
                                       'M' => trans('codice-fiscale::codice-fiscale.options.male', [], $this->getComponentLocale()),
                                       'F' => trans('codice-fiscale::codice-fiscale.options.female', [], $this->getComponentLocale()),
                                   ])
                                   ->required()
                                   ->native(false),
                             Select::make('place_of_birth')
                                      ->label(fn() => trans('codice-fiscale::codice-fiscale.fields.place_of_birth', [], $this->getComponentLocale()))
                                      ->required()
                                      ->helperText(fn() => trans('codice-fiscale::codice-fiscale.helpers.place_of_birth', [], $this->getComponentLocale()))
                                      ->searchable()
                                      ->getSearchResultsUsing(fn(string $search) => \Kreatif\CodiceFiscale\Models\GeoLocation::searchOptions($search, limit: 15, locale: $this->getComponentLocale())
                                          ->mapWithKeys(fn($location) => [$location->codice_catastale => $location->getLabel($this->getComponentLocale())])
                                          ->toArray()
                                      )
                                      ->getOptionLabelUsing(fn($value) => \Kreatif\CodiceFiscale\Models\GeoLocation::findByBelfioreCode($value)?->getLabel($this->getComponentLocale())),
                         ])->columnSpanFull()->columns([
                             'sm' => 1,
                             'md' => 3,
                         ]),
                     ])
                     ->action(function (Set $set, array $data) {
                         $val = $this->handleGenerate($data);
                         if ($val) {
                             $set($this->getName(), $val);
                         }
                     });
    }

    protected function makeValidatorAction(): Action
    {
        return Action::make('validate_cf')
                     ->icon('heroicon-o-arrow-path')
                     ->tooltip(fn() => trans('codice-fiscale::codice-fiscale.actions.validate_tooltip', [], $this->getComponentLocale()))
                     ->color('primary')
                     ->visible(fn() => $this->evaluateValidatorVisibility())
                     ->action(function (Get $get, Set $set, ?Action $action) {
                         $cf = $get($this->getName());

                         $strictFields = $this->evaluateStrictFields();
                         if (!$cf) {
                             // if validate, we calculate and set the value
                             if (config('codice-fiscale.validation.on_action_add_cf_if_empty_and_possible') && $strictFields && count($strictFields) >= 5) {
                                 $cf = $this->handleGenerate($this->getGeneratorFormData($get));
                                 if ($cf) {
                                     $set($this->getName(), $cf);
                                 }
                             }
                             Notification::make()
                                         ->title(trans('codice-fiscale::codice-fiscale.validation.empty_field', [], $this->getComponentLocale()))
                                         ->warning()
                                         ->send();
                             return;
                         }

                         if ($strictFields) {
                             $isValid = $this->performStrictValidation($get, $cf);
                         }
                         else {
                             $isValid = $this->performBasicValidation($cf);
                         }
                         if ($isValid && $action && method_exists($action, 'color')) {
                             $action->color('success');
                         }
                     });
    }

    protected function evaluateGeneratorVisibility(): bool
    {
        if ($this->hasGenerator instanceof \Closure) {
            return $this->evaluate($this->hasGenerator);
        }

        return (bool)$this->hasGenerator;
    }

    protected function evaluateValidatorVisibility(): bool
    {
        if ($this->hasValidator instanceof \Closure) {
            return $this->evaluate($this->hasValidator);
        }

        return (bool)$this->hasValidator;
    }

    protected function evaluateStrictFields(): ?array
    {
        if ($this->strictFields instanceof \Closure) {
            return $this->evaluate($this->strictFields);
        }

        return $this->strictFields;
    }

    protected function getGeneratorFormData(Get $get): array
    {
        $strictFields = $this->evaluateStrictFields();

        if (!$strictFields) {
            return [];
        }

        return [
            'firstname' => $get($strictFields['firstname'] ?? null),
            'lastname' => $get($strictFields['lastname'] ?? null),
            'dob' => $get($strictFields['dob'] ?? null),
            'gender' => $get($strictFields['gender'] ?? null),
            'pob' => $this->resolvePlaceOfBirth($get),
        ];
    }

    protected function resolvePlaceOfBirth(Get $get): ?string
    {
        $strictFields = $this->evaluateStrictFields();

        if (!$strictFields || !isset($strictFields['pob'])) {
            return null;
        }

        $placeValue = $get($strictFields['pob']);

        if (is_string($placeValue)) {
            return $placeValue;
        }

        if (is_numeric($placeValue)) {
            $location = \Kreatif\CodiceFiscale\Models\GeoLocation::find($placeValue);
            return $location?->getLabel($this->getComponentLocale());
        }

        return null;
    }

    protected function handleGenerate(array $data): ?string
    {
        $pob = $data['pob'] ?? $data['place_of_birth'];
        if ($this->finderActionClass instanceof \Closure) {  // Execute closure
            $belfioreCode = $this->evaluate($this->finderActionClass, [
                'placeName' => $pob, // will be codice_catastale in most cases(100%)
            ]);
        } elseif ($this->finderActionClass) { // Use custom class
            $finder = app($this->finderActionClass);
            $belfioreCode = $finder->execute($pob);
        } else { // Use default
            $finder = app(FindBelfioreCode::class);
            $belfioreCode = $finder->execute($pob);
        }

        if (empty($belfioreCode) && (empty($pob) || strlen($pob) != 4)) {
            Notification::make()
                        ->title(__('codice-fiscale::codice-fiscale.validation.place_not_found'))
                        ->body(
                            __('codice-fiscale::codice-fiscale.validation.place_not_found_message', [
                                'place' => $pob
                            ])
                        )
                        ->danger()
                        ->send();
            return null;
        }
        // Use custom or default calculator
        $calculationData = [
            'firstname' => $data['firstname'],
            'lastname' => $data['lastname'],
            'dob' => $data['dob'],
            'gender' => $data['gender'],
            'pob' => $pob, //$data['pob'], // Place of Birth
        ];

        if ($this->calculatorActionClass instanceof \Closure) {
            $cf = $this->evaluate($this->calculatorActionClass, [
                'data' => $calculationData,
            ]);
        } elseif ($this->calculatorActionClass) {
            $calculator = app($this->calculatorActionClass);
            $cf = $calculator->execute($calculationData);
        } else {
            $calculator = app(CalculateCodiceFiscale::class);
            $cf = $calculator->execute($calculationData);
        }

        if ($cf && strlen($cf) == 16) {
            Notification::make()
                        ->title(__('codice-fiscale::codice-fiscale.validation.generated_successfully'))
                        ->body($cf)
                        ->success()
                        ->send();
            return $cf;
        } else {
            Notification::make()
                        ->title(__('codice-fiscale::codice-fiscale.validation.generation_failed'))
                        ->danger()
                        ->send();
        }
        return null;
    }

    protected function performBasicValidation(string $cf): bool
    {
        $record = $this->getRecord();
        $get = fn($field) => $this->evaluate(fn(Get $get) => $get($field));

        // Use custom or default validator
        if ($this->validatorActionClass instanceof \Closure) {
            // Execute closure
            $isValid = $this->evaluate($this->validatorActionClass, [
                'codiceFiscale' => $cf,
                'data' => [],
                'record' => $record,
                'get' => $get,
                'set' => fn() => null,
            ]);
        } elseif ($this->validatorActionClass) {  // Use custom class
            $validator = app($this->validatorActionClass);
            $isValid = $validator->execute($cf);
        } else {
            $validator = app(ValidateCodiceFiscale::class);
            $isValid = $validator->execute($cf);
        }

        if ($isValid) {
            Notification::make()
                        ->title(__('codice-fiscale:codice-fiscale.:validation.valid'))
                        ->body(__('codice-fiscale::codice-fiscale.validation.valid_message'))
                        ->success()
                        ->send();
        } else {
            Notification::make()
                        ->title(__('codice-fiscale::codice-fiscale.validation.invalid'))
                        ->body(__('codice-fiscale::codice-fiscale.validation.invalid_message'))
                        ->danger()
                        ->send();
        }
        return $isValid;
    }

    protected function performStrictValidation(Get $get, string $cf): bool
    {
        $strictFields = $this->evaluateStrictFields();

        if (!$strictFields) {
            return $this->performBasicValidation($cf);
        }

        $firstName = $get($strictFields['firstname'] ?? $strictFields['firstname'] ?? null);
        $lastName = $get($strictFields['lastname'] ?? $strictFields['lastname'] ?? null);
        $dob = $get($strictFields['dob'] ?? null);
        $gender = $get($strictFields['gender'] ?? null);
        $placeOfBirth = $get($strictFields['pob'] ?? null);

        if (!$firstName || !$lastName || !$dob || !$gender || !$placeOfBirth) {
            Notification::make()
                        ->title(__('codice-fiscale::codice-fiscale.validation.missing_data'))
                        ->body(__('codice-fiscale::codice-fiscale.validation.missing_data_message'))
                        ->warning()
                        ->send();
            return false;
        }

        // Resolve place name if needed
        $placeName = is_numeric($placeOfBirth)
            ? $this->resolvePlaceOfBirth($get)
            : $placeOfBirth;

        $record = $this->getRecord();

        $validationData = [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'dob' => $dob,
            'gender' => $gender,
            'pob' => $placeName,
        ];

        if ($this->validatorActionClass instanceof \Closure) {
            $isValid = $this->evaluate($this->validatorActionClass, [
                'codiceFiscale' => $cf,
                'data' => $validationData,
                'record' => $record,
                'get' => $get,
            ]);
        } elseif ($this->validatorActionClass) {
            $validator = app($this->validatorActionClass);
            $isValid = $validator->executeStrict($cf, $validationData);
        } else {
            $validator = app(ValidateCodiceFiscale::class);
            $isValid = $validator->executeStrict($cf, $validationData);
        }

        if ($isValid) {
            Notification::make()
                        ->title(__('codice-fiscale::codice-fiscale.validation.valid_strict'))
                        ->body(__('codice-fiscale::codice-fiscale.validation.valid_strict_message'))
                        ->success()
                        ->send();
            return true;
        } else {
            Notification::make()
                        ->title(__('codice-fiscale:codice-fiscale.:validation.invalid_strict'))
                        ->body(__('codice-fiscale::codice-fiscale.validation.invalid_strict_message'))
                        ->danger()
                        ->send();
            return false;
        }
    }
}
