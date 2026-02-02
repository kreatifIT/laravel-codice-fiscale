<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Codice Fiscale Language Lines (Deutsch)
    |--------------------------------------------------------------------------
    */

    'fields' => [
        'codice_fiscale' => 'Steuernummer',
        'first_name' => 'Vorname',
        'firstname' => 'Vorname',
        'last_name' => 'Nachname',
        'lastname' => 'Nachname',
        'date_of_birth' => 'Geburtsdatum',
        'dob' => 'Geburtsdatum',
        'gender' => 'Geschlecht',
        'country_of_birth' => 'Geburtsland',
        'cob' => 'Geburtsland',
        'place_of_birth' => 'Geburtsort',
        'pob' => 'Geburtsort',
    ],

    'options' => [
        'male' => 'Männlich',
        'female' => 'Weiblich',
    ],

    'actions' => [
        'generate_tooltip' => 'Steuernummer Generieren',
        'generate_title' => 'Italienische Steuernummer Generieren',
        'generate_and_fill' => 'Generieren & Ausfüllen',
        'validate_tooltip' => 'Steuernummer Validieren',
    ],

    'helpers' => [
        'place_of_birth' => 'Geben Sie den Gemeindenamen oder den ausländischen Staat ein (z.B. Rom, Deutschland)',
    ],

    'validation' => [
        'empty_field' => 'Bitte geben Sie eine Steuernummer zur Validierung ein',
        'valid' => 'Gültige Steuernummer',
        'valid_message' => 'Das Format und die Prüfsumme der Steuernummer sind korrekt.',
        'invalid' => 'Ungültige Steuernummer',
        'invalid_message' => 'Das Format oder die Prüfsumme der Steuernummer ist nicht korrekt.',
        'valid_strict' => 'Gültig und Verifiziert',
        'valid_strict_message' => 'Die Steuernummer ist gültig und stimmt mit den angegebenen persönlichen Daten überein.',
        'invalid_strict' => 'Stimmt Nicht Überein',
        'invalid_strict_message' => 'Die Steuernummer stimmt nicht mit den angegebenen persönlichen Daten überein.',
        'missing_data' => 'Fehlende Informationen',
        'missing_data_message' => 'Bitte füllen Sie alle erforderlichen Felder aus (Vorname, Nachname, Geburtsdatum, Geschlecht, Geburtsort), um eine strenge Validierung durchzuführen.',
        'place_not_found' => 'Ort Nicht Gefunden',
        'place_not_found_message' => 'Katastercode für :place konnte nicht gefunden werden',
        'generated_successfully' => 'Erfolgreich Generiert',
        'generation_failed' => 'Generierung Fehlgeschlagen',
        'invalid_codice_fiscale' => 'Das Feld :attribute ist keine gültige italienische Steuernummer.',
        'codice_fiscale_mismatch' => 'Das Feld :attribute stimmt nicht mit den angegebenen persönlichen Daten überein.',
    ],
];
