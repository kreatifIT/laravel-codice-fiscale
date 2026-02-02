<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Codice Fiscale Language Lines (Italiano)
    |--------------------------------------------------------------------------
    */

    'fields' => [
        'codice_fiscale' => 'Codice Fiscale',
        'first_name' => 'Nome',
        'firstname' => 'Nome',
        'last_name' => 'Cognome',
        'lastname' => 'Cognome',
        'dob' => 'Data di Nascita',
        'date_of_birth' => 'Data di Nascita',
        'gender' => 'Sesso',
        'country_of_birth' => 'Stato di Nascita',
        'cob' => 'Stato di Nascita',
        'place_of_birth' => 'Luogo di Nascita',
        'pob' => 'Luogo di Nascita',
    ],

    'options' => [
        'male' => 'Maschio',
        'female' => 'Femmina',
    ],

    'actions' => [
        'generate_tooltip' => 'Genera Codice Fiscale',
        'generate_title' => 'Genera Codice Fiscale Italiano',
        'generate_and_fill' => 'Genera e Compila',
        'validate_tooltip' => 'Valida Codice Fiscale',
    ],

    'helpers' => [
        'place_of_birth' => 'Inserisci il nome del comune o dello stato estero (es. Roma, Germania)',
    ],

    'validation' => [
        'empty_field' => 'Inserisci un Codice Fiscale da validare',
        'valid' => 'Codice Fiscale Valido',
        'valid_message' => 'Il formato e il checksum del Codice Fiscale sono corretti.',
        'invalid' => 'Codice Fiscale Non Valido',
        'invalid_message' => 'Il formato o il checksum del Codice Fiscale non sono corretti.',
        'valid_strict' => 'Valido e Verificato',
        'valid_strict_message' => 'Il Codice Fiscale è valido e corrisponde ai dati personali forniti.',
        'invalid_strict' => 'Non Corrisponde',
        'invalid_strict_message' => 'Il Codice Fiscale non corrisponde ai dati personali forniti.',
        'missing_data' => 'Informazioni Mancanti',
        'missing_data_message' => 'Compila tutti i campi obbligatori (nome, cognome, data di nascita, sesso, luogo di nascita) per eseguire la validazione rigorosa.',
        'place_not_found' => 'Luogo Non Trovato',
        'place_not_found_message' => 'Impossibile trovare il codice catastale per: :place',
        'generated_successfully' => 'Generato con Successo',
        'generation_failed' => 'Generazione Fallita',
        'invalid_codice_fiscale' => 'Il campo :attribute non è un Codice Fiscale italiano valido.',
        'codice_fiscale_mismatch' => 'Il campo :attribute non corrisponde ai dati personali forniti.',
    ],
];
