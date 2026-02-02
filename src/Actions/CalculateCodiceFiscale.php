<?php

namespace Kreatif\CodiceFiscale\Actions;

use CodiceFiscale\Calculator;
use CodiceFiscale\Subject;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 *
 * This action generates a valid Italian tax code based on:
 * - First name
 * - Last name
 * - Date of birth
 * - Gender (M/F)
 * - Place of birth (municipality or foreign state)
 *
 * Usage:
 *   $cf = app(CalculateCodiceFiscale::class)->execute([
 *       'firstName' => 'Mario',
 *       'lastName' => 'Rossi',
 *       'dob' => '1990-01-01',
 *       'gender' => 'M',
 *       'pob' => 'Roma', // of codice_catastale 'Z222'
 *   ]);
 *
 *   // Or use the static helper:
 *   $cf = CalculateCodiceFiscale::calculate([...]);
 */
class CalculateCodiceFiscale
{
    protected FindBelfioreCode $belfioreCodeFinder;

    public function __construct(?FindBelfioreCode $belfioreCodeFinder = null)
    {
        $this->belfioreCodeFinder = $belfioreCodeFinder ?? new FindBelfioreCode();
    }

    public static function calculate(array $data): ?string
    {
        return app(static::class)->execute($data);
    }

    /**
     * Execute the Codice Fiscale calculation.
     *
     * @param array $data Personal data with keys:
     *   - firstName: string
     *   - lastName: string
     *   - dob: string|Carbon instance
     *   - gender: string ('M' or 'F')
     *   - pob: string (municipality or foreign state name)
     *
     * @return string|null The calculated Codice Fiscale or null on failure
     */
    public function execute(array $data): ?string
    {
        try {
            // Validate required fields
            $required = ['firstname', 'lastname', 'dob', 'gender', 'pob'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new \InvalidArgumentException("Missing required field: {$field}");
                }
            }

            // Normalize date of birth
            $dateOfBirth = $this->normalizeDateOfBirth($data['dob']);

            // Normalize gender
            $gender = strtoupper(trim($data['gender']));
            if (!in_array($gender, ['M', 'F', 'MALE', 'FEMALE'])) {
                throw new \InvalidArgumentException("Gender must be 'M' or 'F', got: {$gender}");
            }
            if (strlen($gender) > 1) {
                $gender = $gender[0]; // Take first letter
            }

            // Find Belfiore code for place of birth
            $belfioreCode = $this->belfioreCodeFinder->execute($data['pob']);
            if ($belfioreCode == null && isset($data['pob']) && strlen($data['pob']) == 4 ) {
                $belfioreCode = $data['pob'];
            }
            if (!$belfioreCode) {
                throw new \RuntimeException(
                    "Could not find Belfiore code for place: {$data['pob']}"
                );
            }
            $prop = [
                'name' => trim($data['firstname']),
                'surname' => trim($data['lastname']),
                'birthDate' => $dateOfBirth,
                'gender' => $gender,
                'belfioreCode' => $belfioreCode
            ];

            $subject = new Subject($prop);
            $calculator = new Calculator($subject);
            $codiceFiscale = $calculator->calculate();

            return strtoupper($codiceFiscale);

        } catch (\Exception $e) {
            // Log the error if needed
            if (config('app.debug')) {
                logger()->error('Failed to calculate Codice Fiscale', [
                    'data' => $data,
                    'error' => $e->getMessage(),
                ]);
            }

            return null;
        }
    }

    public function executeOrFail(array $data): string
    {
        $result = $this->execute($data);

        if ($result === null) {
            throw new \RuntimeException('Failed to calculate Codice Fiscale');
        }

        return $result;
    }

    /**
     * Normalize date of birth to the format expected by the library.
     */
    protected function normalizeDateOfBirth(mixed $date): DateTimeInterface
    {
        if ($date instanceof Carbon) {
            return $date;
        }

        if (is_string($date)) {
            try {
                $carbon = Carbon::parse($date);
                return $carbon;
            } catch (\Exception $e) {
                throw new \InvalidArgumentException("Invalid date format: {$date}");
            }
        }

        throw new \InvalidArgumentException('Date of birth must be a string or Carbon instance');
    }

    public function calculateFromFields(
        string $firstName,
        string $lastName,
        string|Carbon $dateOfBirth,
        string $gender,
        string $placeOfBirth
    ): ?string {
        return $this->execute([
            'firstname' => $firstName,
            'lastname' => $lastName,
            'dob' => $dateOfBirth,
            'gender' => $gender,
            'pob' => $placeOfBirth,
        ]);
    }

    /**
     * Set a custom Belfiore code finder.
     */
    public function setBelfioreFinder(FindBelfioreCode $finder): self
    {
        $this->belfioreCodeFinder = $finder;
        return $this;
    }
}
