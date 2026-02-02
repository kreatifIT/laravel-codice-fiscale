<?php

namespace Kreatif\CodiceFiscale\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Kreatif\CodiceFiscale\Actions\ValidateCodiceFiscale as ValidateAction;

/**
 * Laravel validation rule for Codice Fiscale format and checksum validation.
 *
 * This rule validates:
 * - Correct length (16 characters)
 * - Correct format (pattern matching)
 * - Valid checksum (last character)
 *
 * Usage:
 *   use Kreatif\CodiceFiscale\Rules\ValidCodiceFiscale;
 *
 *   $validator = Validator::make($data, [
 *       'codice_fiscale' => ['required', 'string', new ValidCodiceFiscale],
 *   ]);
 *
 * Or register in AppServiceProvider to use as string:
 *   Validator::extend('codice_fiscale', ValidCodiceFiscale::class);
 *
 *   Then use:
 *   'codice_fiscale' => 'required|codice_fiscale'
 */
class ValidCodiceFiscale implements ValidationRule
{
    protected ValidateAction $validator;

    public function __construct()
    {
        $this->validator = new ValidateAction();
    }

    /**
     * Run the validation rule.
     *
     * @param string $attribute
     * @param mixed $value
     * @param Closure $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$this->passes($value)) {
            $fail($this->message());
        }
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param mixed $value
     * @return bool
     */
    protected function passes(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return $this->validator->execute($value);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    protected function message(): string
    {
        return trans('codice-fiscale::codice-fiscale.validation.invalid_codice_fiscale')
            ?: 'The :attribute is not a valid Italian Codice Fiscale.';
    }
}
