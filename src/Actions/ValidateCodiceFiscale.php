<?php

namespace Kreatif\CodiceFiscale\Actions;


class ValidateCodiceFiscale
{
    protected bool $validateFormat;
    protected bool $validateChecksum;
    protected bool $allowEmpty;

    public function __construct()
    {
        $this->validateFormat = config('codice-fiscale.validation.validate_format', true);
        $this->validateChecksum = config('codice-fiscale.validation.validate_checksum', true);
        $this->allowEmpty = config('codice-fiscale.validation.allow_empty', false);
    }

    public static function isValid(?string $codiceFiscale): bool
    {
        return app(static::class)->execute($codiceFiscale);
    }

    public static function isValidStrict(?string $codiceFiscale, array $personalData): bool
    {
        return app(static::class)->executeStrict($codiceFiscale, $personalData);
    }

    /**
     * Execute basic validation (format and checksum).
     *
     * @param string|null $codiceFiscale The Codice Fiscale to validate
     * @return bool True if valid, false otherwise
     */
    public function execute(?string $codiceFiscale): bool
    {
        if (empty($codiceFiscale)) {
            return $this->allowEmpty;
        }

        $cf = strtoupper(trim($codiceFiscale));

        if ($this->validateFormat && !$this->hasValidFormat($cf)) {
            return false;
        }

        if ($this->validateChecksum && !$this->hasValidChecksum($cf)) {
            return false;
        }

        return true;
    }

    /**
     * Execute strict validation by comparing against personal data.
     *
     * This recalculates the Codice Fiscale from the provided personal data
     * and compares it with the given Codice Fiscale.
     *
     * @param string|null $codiceFiscale The Codice Fiscale to validate
     * @param array $personalData Personal data (firstName, lastName, etc.)
     * @return bool True if valid and matches, false otherwise
     */
    public function executeStrict(?string $codiceFiscale, array $personalData): bool
    {
        if (!$this->execute($codiceFiscale)) {
            return false;
        }

        $calculator = app(CalculateCodiceFiscale::class);
        $calculatedCf = $calculator->execute($personalData);
        if (!$calculatedCf) {
            return false;
        }

        // Compare (case-insensitive)
        return strtoupper(trim($codiceFiscale)) === strtoupper($calculatedCf);
    }

    /**
     * Validate the format of a Codice Fiscale.
     *
     * The format should be:
     * - 6 letters (surname and name)
     * - 2 digits/letters for year
     * - 1 letter for month
     * - 2 digits/letters for day and gender
     * - 1 letter for place
     * - 3 digits/letters for place code
     * - 1 letter for checksum
     *
     * @param string $cf The Codice Fiscale
     * @return bool
     */
    protected function hasValidFormat(string $cf): bool
    {
        // Must be exactly 16 characters
        if (strlen($cf) !== 16) {
            return false;
        }

        // Validate pattern:
        // ^[A-Z]{6}          - 6 letters for surname and name
        // [0-9LMNPQRSTUV]{2} - 2 chars for year (digits or letters for omocodia)
        // [ABCDEHLMPRST]{1}  - 1 letter for month
        // [0-9LMNPQRSTUV]{2} - 2 chars for day/gender
        // [A-Z]{1}           - 1 letter
        // [0-9LMNPQRSTUV]{3} - 3 chars for place code
        // [A-Z]{1}$          - 1 letter for checksum
        $pattern = '/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[ABCDEHLMPRST]{1}[0-9LMNPQRSTUV]{2}[A-Z]{1}[0-9LMNPQRSTUV]{3}[A-Z]{1}$/';

        return preg_match($pattern, $cf) === 1;
    }

    /**
     * Validate the checksum (last character) of a Codice Fiscale.
     *
     * @param string $cf The Codice Fiscale
     * @return bool
     */
    protected function hasValidChecksum(string $cf): bool
    {
        if (strlen($cf) !== 16) {
            return false;
        }

        // Odd position values (1-based index, so positions 1,3,5,7,9,11,13,15)
        $oddMap = [
            '0' => 1, '1' => 0, '2' => 5, '3' => 7, '4' => 9, '5' => 13,
            '6' => 15, '7' => 17, '8' => 19, '9' => 21, 'A' => 1, 'B' => 0,
            'C' => 5, 'D' => 7, 'E' => 9, 'F' => 13, 'G' => 15, 'H' => 17,
            'I' => 19, 'J' => 21, 'K' => 2, 'L' => 4, 'M' => 18, 'N' => 20,
            'O' => 11, 'P' => 3, 'Q' => 6, 'R' => 8, 'S' => 12, 'T' => 14,
            'U' => 16, 'V' => 10, 'W' => 22, 'X' => 25, 'Y' => 24, 'Z' => 23,
        ];

        $sum = 0;

        // Process odd-positioned characters (0-indexed: 0,2,4,6,8,10,12,14)
        for ($i = 0; $i < 15; $i += 2) {
            $char = $cf[$i];
            if (!isset($oddMap[$char])) {
                return false;
            }
            $sum += $oddMap[$char];
        }

        // Process even-positioned characters (0-indexed: 1,3,5,7,9,11,13)
        for ($i = 1; $i < 15; $i += 2) {
            $char = $cf[$i];
            if (is_numeric($char)) {
                $sum += (int) $char;
            } else {
                $sum += ord($char) - ord('A');
            }
        }

        // Calculate expected checksum character
        $remainder = $sum % 26;
        $expectedCheckChar = chr(ord('A') + $remainder);

        // Compare with actual checksum (last character)
        return $expectedCheckChar === $cf[15];
    }

    /**
     * Get validation result with detailed information.
     *
     * @param string|null $codiceFiscale
     * @return array [
     *   'valid' => bool,
     *   'format_valid' => bool,
     *   'checksum_valid' => bool,
     *   'errors' => array
     * ]
     */
    public function getValidationDetails(?string $codiceFiscale): array
    {
        $errors = [];
        $formatValid = true;
        $checksumValid = true;

        if (empty($codiceFiscale)) {
            if (!$this->allowEmpty) {
                $errors[] = 'Codice Fiscale is required';
            }
            return [
                'valid' => $this->allowEmpty,
                'format_valid' => false,
                'checksum_valid' => false,
                'errors' => $errors,
            ];
        }

        $cf = strtoupper(trim($codiceFiscale));

        if ($this->validateFormat) {
            $formatValid = $this->hasValidFormat($cf);
            if (!$formatValid) {
                $errors[] = 'Invalid format';
            }
        }

        if ($this->validateChecksum) {
            $checksumValid = $this->hasValidChecksum($cf);
            if (!$checksumValid) {
                $errors[] = 'Invalid checksum';
            }
        }

        return [
            'valid' => empty($errors),
            'format_valid' => $formatValid,
            'checksum_valid' => $checksumValid,
            'errors' => $errors,
        ];
    }

    public function setValidateFormat(bool $validate): self
    {
        $this->validateFormat = $validate;
        return $this;
    }

    public function setValidateChecksum(bool $validate): self
    {
        $this->validateChecksum = $validate;
        return $this;
    }

    public function setAllowEmpty(bool $allow): self
    {
        $this->allowEmpty = $allow;
        return $this;
    }
}
