<?php

namespace Kreatif\CodiceFiscale\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Log;
use Kreatif\CodiceFiscale\Actions\ValidateCodiceFiscale;

/**
 * Laravel validation rule for strict Codice Fiscale validation against personal data.
 *
 * This rule not only validates the format and checksum, but also recalculates
 * the Codice Fiscale from the provided personal data and compares them.
 *
 * IMPORTANT: Must include 'countryOfBirth' in field mapping for proper codice catastale resolution:
 * - If cob='*' (Italy): uses pob as the Italian commune code
 * - If cob starts with 'Z' (4 chars): uses cob as the foreign country code
 *
 * Usage:
 *   use Kreatif\CodiceFiscale\Rules\CodiceFiscaleMatchesData;
 *
 *   $validator = Validator::make($data, [
 *       'codice_fiscale' => [
 *           'required',
 *           CodiceFiscaleMatchesData::strict([
 *               'firstname' => 'first_name',
 *               'lastname' => 'last_name',
 *               'dob' => 'dob',
 *               'gender' => 'gender',
 *               'pob' => 'pob',
 *               'countryOfBirth' => 'cob',  // Required for proper validation
 *           ])
 *       ],
 *   ]);
 */
class CodiceFiscaleMatchesData implements ValidationRule, DataAwareRule
{
    protected array $data = [];
    protected array $fieldMapping;
    protected bool $requireAllFields;

    /**
     * Private constructor - use ::strict() factory method instead.
     *
     * @param array $fieldMapping Maps CF fields to your data fields.
     * @param bool $requireAllFields If true, all fields must be present
     */
    private function __construct(
        array $fieldMapping = [],
        bool $requireAllFields = false
    ) {
        $this->fieldMapping = empty($fieldMapping)
            ? $this->getDefaultFieldMapping()
            : $fieldMapping;

        $this->requireAllFields = $requireAllFields;
    }

    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$this->passes($value)) {
            $fail($this->message());
        }
    }

    protected function passes(mixed $value): bool
    {
        if (!is_string($value) || empty($value)) {
            return false;
        }

        $personalData = $this->extractPersonalData();
        if ($this->requireAllFields && !$this->hasAllRequiredFields($personalData)) {
            return false;
        }

        // If we don't have enough data, skip strict validation
        if (empty(array_filter($personalData))) {
            // Fall back to basic validation
            $validator = new ValidateCodiceFiscale();
            return $validator->execute($value);
        }

        // Perform strict validation
        $validator = new ValidateCodiceFiscale();
        return $validator->executeStrict($value, $personalData);
    }

    protected function extractPersonalData(): array
    {
        $personalData = [];

        foreach ($this->fieldMapping as $cfField => $dataField) {
            if (isset($this->data[$dataField])) {
                $personalData[$cfField] = $this->data[$dataField];
            } elseif (isset($this->data['data'][$dataField])) {
                $personalData[$cfField] = $this->data['data'][$dataField];
            }
        }

        // Resolve the correct place code (codice catastale) for validation
        $personalData = $this->resolveCodiceCatastale($personalData);

        return $personalData;
    }

    /**
     * Resolve the correct codice catastale (place code) based on country of birth.
     *
     * Logic:
     * - If cob is '*' (Italy): use pob as the codice catastale (Italian commune code)
     * - If cob is 4 chars starting with 'Z': use cob as the codice catastale (foreign country code)
     * - Otherwise: use pob as-is
     *
     * @param array $personalData
     * @return array
     */
    protected function resolveCodiceCatastale(array $personalData): array
    {
        // Get COB value from data
        $cobField = $this->fieldMapping['countryOfBirth'] ?? 'cob';
        $cobValue = null;

        if (isset($this->data[$cobField])) {
            $cobValue = $this->data[$cobField];
        } elseif (isset($this->data['data'][$cobField])) {
            $cobValue = $this->data['data'][$cobField];
        }

        // If COB exists and we have a pob field in personalData
        if ($cobValue && isset($personalData['pob'])) {
            // If born in Italy (cob = '*'), use pob as the codice catastale
           if (is_string($cobValue) && strlen($cobValue) === 4 && strtoupper($cobValue)[0] === 'Z') {
                $personalData['pob'] = $cobValue;
            }
        }

        return $personalData;
    }

    protected function hasAllRequiredFields(array $personalData): bool
    {
        $required = ['firstname', 'lastname', 'dob', 'gender', 'pob'];

        foreach ($required as $field) {
            if (empty($personalData[$field])) {
                return false;
            }
        }

        return true;
    }

    protected function message(): string
    {
        return trans('codice-fiscale::codice-fiscale.validation.codice_fiscale_mismatch')
            ?: 'The :attribute does not match the provided personal data.';
    }

    /**
     * Get default field mapping.
     *
     * @return array
     */
    protected function getDefaultFieldMapping(): array
    {
        return [
            'firstName' => 'first_name',
            'lastName' => 'last_name',
            'dob' => 'dob',
            'gender' => 'gender',
            'pob' => 'pob',
            'countryOfBirth' => 'cob',
        ];
    }

    /**
     * Create instance that requires all fields with strict validation.
     * This is the recommended way to create instances of this rule.
     *
     * @param array $fieldMapping
     * @return static
     */
    public static function strict(array $fieldMapping = []): static
    {
        return new static($fieldMapping, requireAllFields: true);
    }
}
