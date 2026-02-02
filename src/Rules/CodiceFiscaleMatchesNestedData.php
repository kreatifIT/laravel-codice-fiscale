<?php

namespace Kreatif\CodiceFiscale\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Log;
use Kreatif\CodiceFiscale\Actions\ValidateCodiceFiscale;

/**
 * Laravel validation rule for strict Codice Fiscale validation in nested/repeater data.
 *
 * This rule is designed for validating fiscal codes within repeater fields where
 * the personal data is nested (e.g., children.*.fiscal_code).
 *
 * IMPORTANT: Must include 'countryOfBirth' in field mapping for proper codice catastale resolution:
 * - If cob='*' (Italy): uses pob as the Italian commune code
 * - If cob starts with 'Z' (4 chars): uses cob as the foreign country code
 *
 * Usage:
 *   'children.*.fiscal_code' => [
 *       'required',
 *       CodiceFiscaleMatchesNestedData::strict([
 *           'firstname' => 'first_name',
 *           'lastname' => 'last_name',
 *           'dob' => 'dob',
 *           'gender' => 'gender',
 *           'pob' => 'pob',
 *           'countryOfBirth' => 'cob',  // Required for proper validation
 *       ])
 *   ],
 */
class CodiceFiscaleMatchesNestedData implements ValidationRule, DataAwareRule
{
    protected array $data = [];
    protected array $fieldMapping;
    protected bool $requireAllFields;

    /**
     * Private constructor - use ::strict() factory method instead.
     *
     * @param array $fieldMapping Maps CF fields to sibling field names in the same repeater item
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
        Log::info('=== CodiceFiscaleMatchesNestedData: __construct() called ===', [
            'fieldMapping' => $this->fieldMapping,
            'requireAllFields' => $this->requireAllFields,
        ]);
    }

    public function setData(array $data): static
    {
        $this->data = $data;
        Log::info('=== CodiceFiscaleMatchesNestedData: setData() called ===', [
            'data_keys' => array_keys($data),
        ]);
        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        Log::info('=== CodiceFiscaleMatchesNestedData: validate() called ===', [
            'attribute' => $attribute,
            'value' => $value,
        ]);

        if (!$this->passes($attribute, $value)) {
            $fail($this->message());
        }
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        Log::info('=== CodiceFiscaleMatchesNestedData: passes() starting ===', [
            'attribute' => $attribute,
            'value' => $value,
        ]);

        if (!is_string($value) || empty($value)) {
            Log::info('CodiceFiscaleMatchesNestedData: Value is not string or empty');
            return false;
        }

        // Extract the parent path and index from attribute
        // e.g., "children.0.fiscal_code" -> parent: "children.0"
        $parts = explode('.', $attribute);
        array_pop($parts); // Remove the field name (fiscal_code)
        $parentPath = implode('.', $parts);

        Log::info('CodiceFiscaleMatchesNestedData: Extracted parent path', [
            'parentPath' => $parentPath,
        ]);

        // Get the sibling data from the same repeater item
        $personalData = $this->extractPersonalDataFromPath($parentPath);

        Log::info('CodiceFiscaleMatchesNestedData: Extracted personal data', [
            'personalData' => $personalData,
        ]);

        if ($this->requireAllFields && !$this->hasAllRequiredFields($personalData)) {
            Log::info('CodiceFiscaleMatchesNestedData: Missing required fields');
            return false;
        }

        // If we don't have enough data, skip strict validation
        if (empty(array_filter($personalData))) {
            Log::info('CodiceFiscaleMatchesNestedData: No personal data, falling back to basic validation');
            $validator = new ValidateCodiceFiscale();
            return $validator->execute($value);
        }

        // Perform strict validation
        Log::info('CodiceFiscaleMatchesNestedData: Performing strict validation', [
            'personalData' => $personalData,
        ]);
        $validator = new ValidateCodiceFiscale();
        $result = $validator->executeStrict($value, $personalData);
        Log::info('CodiceFiscaleMatchesNestedData: Strict validation result', [
            'result' => $result,
        ]);
        return $result;
    }

    /**
     * Extract personal data from the nested path.
     *
     * @param string $parentPath The parent path (e.g., "children.0")
     * @return array
     */
    protected function extractPersonalDataFromPath(string $parentPath): array
    {
        $personalData = [];

        // Navigate to the parent item using the path
        $pathParts = explode('.', $parentPath);
        $currentData = $this->data;

        foreach ($pathParts as $part) {
            if (!isset($currentData[$part])) {
                Log::warning('CodiceFiscaleMatchesNestedData: Path not found', [
                    'part' => $part,
                    'parentPath' => $parentPath,
                ]);
                return [];
            }
            $currentData = $currentData[$part];
        }

        Log::info('CodiceFiscaleMatchesNestedData: Found item data', [
            'item_keys' => array_keys($currentData),
        ]);

        // Extract the mapped fields from the current item
        foreach ($this->fieldMapping as $cfField => $dataField) {
            if (isset($currentData[$dataField])) {
                $personalData[$cfField] = $currentData[$dataField];
            }
        }

        // Resolve the correct place code (codice catastale) for validation
        $personalData = $this->resolveCodiceCatastale($personalData, $currentData);

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
     * @param array $itemData The current repeater item data
     * @return array
     */
    protected function resolveCodiceCatastale(array $personalData, array $itemData): array
    {
        // Get COB value from the current item
        $cobField = $this->fieldMapping['countryOfBirth'] ?? 'cob';
        $cobValue = $itemData[$cobField] ?? null;

        Log::info('CodiceFiscaleMatchesNestedData: Resolving codice catastale', [
            'pob_value' => $personalData['pob'] ?? null,
            'cob_value' => $cobValue,
        ]);

        // If COB exists and we have a pob field in personalData
        if ($cobValue && isset($personalData['pob'])) {
            // If born in Italy (cob = '*'), use pob as the codice catastale
            if ($cobValue === '*') {
                Log::info('CodiceFiscaleMatchesNestedData: Italian birth detected, using pob as codice catastale', [
                    'pob' => $personalData['pob'],
                ]);
                // pob already contains the Italian commune code, keep it as-is
            }
            // If born abroad (cob starts with 'Z' and length is 4), use cob as the codice catastale
            elseif (is_string($cobValue) && strlen($cobValue) === 4 && strtoupper($cobValue)[0] === 'Z') {
                Log::info('CodiceFiscaleMatchesNestedData: Foreign birth detected, using cob as codice catastale', [
                    'original_pob' => $personalData['pob'],
                    'cob_value' => $cobValue,
                ]);
                $personalData['pob'] = $cobValue;
            }
            // Otherwise, use pob as-is
            else {
                Log::info('CodiceFiscaleMatchesNestedData: Using pob as-is', [
                    'pob' => $personalData['pob'],
                    'cob' => $cobValue,
                ]);
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

    protected function getDefaultFieldMapping(): array
    {
        return [
            'firstname' => 'first_name',
            'lastname' => 'last_name',
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
     * @param array $fieldMapping Field mapping array. Must include 'countryOfBirth' for proper codice catastale resolution.
     * @return static
     */
    public static function strict(array $fieldMapping = []): static
    {
        Log::info('=== CodiceFiscaleMatchesNestedData: strict() static method called ===', [
            'fieldMapping' => $fieldMapping,
        ]);
        return new static($fieldMapping, requireAllFields: true);
    }
}
