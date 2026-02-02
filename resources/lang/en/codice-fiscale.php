<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Codice Fiscale Language Lines (English)
    |--------------------------------------------------------------------------
    */

    'fields' => [
        'codice_fiscale' => 'Tax Code',
        'first_name' => 'First Name',
        'firstname' => 'First Name',
        'last_name' => 'Last Name',
        'lastname' => 'Last Name',
        'date_of_birth' => 'Date of Birth',
        'dob' => 'Date of Birth',
        'gender' => 'Gender',
        'country_of_birth' => 'Country of Birth',
        'cob' => 'Country of Birth',
        'place_of_birth' => 'Place of Birth',
        'pob' => 'Place of Birth',
    ],

    'options' => [
        'male' => 'Male',
        'female' => 'Female',
    ],

    'actions' => [
        'generate_tooltip' => 'Generate Tax Code',
        'generate_title' => 'Generate Italian Tax Code',
        'generate_and_fill' => 'Generate & Fill',
        'validate_tooltip' => 'Validate Tax Code',
    ],

    'helpers' => [
        'place_of_birth' => 'Enter the municipality name or foreign state (e.g., Rome, Germany)',
    ],

    'validation' => [
        'empty_field' => 'Please enter a Tax Code to validate',
        'valid' => 'Valid Tax Code',
        'valid_message' => 'The Tax Code format and checksum are correct.',
        'invalid' => 'Invalid Tax Code',
        'invalid_message' => 'The Tax Code format or checksum is incorrect.',
        'valid_strict' => 'Valid & Verified',
        'valid_strict_message' => 'The Tax Code is valid and matches the provided personal data.',
        'invalid_strict' => 'Does Not Match',
        'invalid_strict_message' => 'The Tax Code does not match the provided personal data.',
        'missing_data' => 'Missing Information',
        'missing_data_message' => 'Please fill in all required fields (first name, last name, date of birth, gender, place of birth) to perform strict validation.',
        'place_not_found' => 'Place Not Found',
        'place_not_found_message' => 'Could not find cadastral code for: :place',
        'generated_successfully' => 'Generated Successfully',
        'generation_failed' => 'Generation Failed',
        'invalid_codice_fiscale' => 'The :attribute is not a valid Italian Tax Code.',
        'codice_fiscale_mismatch' => 'The :attribute does not match the provided personal data.',
    ],
];
