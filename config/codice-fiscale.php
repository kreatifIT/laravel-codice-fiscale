<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Geo-Locations Table Name
    |--------------------------------------------------------------------------
    |
    | The name of the table that stores both Italian municipalities (comuni)
    | and foreign states. This unified table contains all geographic data
    | needed for Codice Fiscale calculation.
    |
    */
    'geo_locations_table' => 'geo_locations',
    'geo_locations_model' => \Kreatif\CodiceFiscale\Models\GeoLocation::class,

    /*
    |--------------------------------------------------------------------------
    | Data Source URLs
    |--------------------------------------------------------------------------
    |
    | URLs for official Italian government data sources (ANPR - Anagrafe
    | Nazionale Popolazione Residente). These are used by sync commands to
    | update the database with the latest municipality and state data.
    |
    */
    'data_sources' => [
        // CSVs data from Pronotel
        'csv' => [
            '*' => [
                'driver' => 'csv',
                'source_type' => 'file',
                'source' => __DIR__ . '/../resources/data/csv/COL_VW_COMUNI_NAZIONI_ESTERE.csv',
                'options' => [
                    'delimiter' => ';',
                    'enclosure' => '"',
                    'escape' => '\\',
                    'header_row' => true,
                    'encoding' => 'ISO-8859-1',
                ],
                // Column mapping for Pronotel-like CSVs
                'mapping' => [
                    'type' => [
                        'column' => null,
                        'default' => null,
                        // If your unified CSV includes a discriminator column,
                        // set 'column' and 'values' to map it to item_types.
                        // 'values' => ['C' => 'comune', 'S' => 'stato']
                        'values' => [],
                    ],
                    'codice_catastale' => ['column' => 'CODICE'],
                    'denominazione' => ['column' => 'DESCR_I'],
                    'denominazione_de' => ['column' => 'DESCR_D'],
                    'cap' => ['column' => 'CAP'],
                    'sigla_provincia' => ['column' => 'SIGLA_PROVINCIA', 'fallback_columns' => ['SIGLA']],
                    'codice_istat' => ['column' => 'COM_CODICE'],
                    'valid_to' => ['column' => 'DATA_FINE', 'transform' => 'date_dmy_slash'],
                ],
                'defaults' => [
                    'fonte' => 'CSV',
                ],
            ],

            'comune' => [
                'driver' => 'csv',
                'source_type' => 'file',
                'source' => __DIR__ . '/../resources/data/csv/COL_VW_COMUNI.csv',
                'options' => [
                    'delimiter' => ';',
                    'enclosure' => '"',
                    'escape' => '\\',
                    'header_row' => true,
                    'encoding' => 'ISO-8859-1',
                ],
                'mapping' => [
                    'codice_catastale' => ['column' => 'CODICE'],
                    'denominazione' => ['column' => 'DESCR_I'],
                    'denominazione_de' => ['column' => 'DESCR_D'],
                    'cap' => ['column' => 'CAP'],
                    'sigla_provincia' => ['column' => 'SIGLA'],
                    'codice_istat' => ['column' => 'COM_CODICE'],
                    'valid_to' => ['column' => 'DATA_FINE', 'transform' => 'date_dmy_slash'],
                ],
                'defaults' => [
                    'item_type' => 'comune',
                    'stato' => 'IT',
                    'is_foreign_state' => false,
                    'fonte' => 'CSV',
                ],
            ],

            'stato' => [
                'driver' => 'csv',
                'source_type' => 'file',
                'source' => __DIR__ . '/../resources/data/csv/COL_VW_COMUNI_NAZIONI_ESTERE.csv',
                'options' => [
                    'delimiter' => ';',
                    'enclosure' => '"',
                    'escape' => '\\',
                    'header_row' => true,
                    'encoding' => 'ISO-8859-1',
                ],
                'mapping' => [
                    'codice_catastale' => ['column' => 'CODICE'],
                    'denominazione' => ['column' => 'DESCR_I'],
                    'denominazione_de' => ['column' => 'DESCR_D'],
                    'cap' => ['column' => 'CAP'],
                    'sigla_provincia' => ['column' => 'SIGLA_PROVINCIA', 'fallback_columns' => ['SIGLA']],
                    'valid_to' => ['column' => 'DATA_FINE', 'transform' => 'date_dmy_slash'],
                ],
                'defaults' => [
                    'item_type' => 'stato',
                    'is_foreign_state' => true,
                    'fonte' => 'CSV',
                ],
            ],
        ],

        'db' => [
            'comune' => [
                'driver' => 'csv',
                'source_type' => 'url',
                'source' => 'https://raw.githubusercontent.com/italia/anpr/master/src/archivi/ANPR_archivio_comuni.csv', // from ANPR GitHub
                'options' => [
                    // Remote file is comma-separated.
                    'delimiter' => ',',
                    'enclosure' => '"',
                    'escape' => '\\',
                    'header_row' => true,
                    'encoding' => 'UTF-8',
                ],
                // Header names from that feed are lowercased by the command.
                'mapping' => [
                    'codice_catastale' => ['column' => 'codcatastale'],
                    'denominazione' => ['column' => 'denominazione_it', 'fallback_columns' => ['denominazione']],
                    'altra_denominazione' => ['column' => 'altradenominazione'],
                    'sigla_provincia' => ['column' => 'siglaprovincia'],
                    'id_provincia' => ['column' => 'id_provincia'],
                    'id_regione' => ['column' => 'idregione'],
                    'stato' => ['column' => 'stato', 'default' => 'IT'],
                    'codice_istat' => ['column' => 'codistat'],
                    'cap' => ['column' => 'cap'],
                ],
                'defaults' => [
                    'item_type' => 'comune',
                    'is_foreign_state' => false,
                    'fonte' => 'DB',
                ],
            ],

            'stato' => [
                'driver' => 'rst',
                'source_type' => 'url',
                'source' => 'https://raw.githubusercontent.com/italia/anpr/refs/heads/master/src/tab/tab_stati_esteri.rst', // from ANPR GitHub
                'options' => [
                    'encoding' => 'UTF-8',
                ],
                // RST parsing produces normalized keys; map them to DB columns.
                'mapping' => [
                    'codice_catastale' => ['column' => 'CODAT'],
                    'denominazione' => ['column' => 'DENOMINAZIONEISTAT', 'fallback_columns' => ['denominazione']],
                    'denominazione_en' => ['column' => 'DENOMINAZIONEISTAT_EN'],
                    'codice_mae' => ['column' => 'CODMAE'],
                    'codice_min' => ['column' => 'CODMIN'],
                    'codice_istat' => ['column' => 'CODISTAT'],
                    'codice_iso3' => ['column' => 'CODISO3166_1_ALPHA3'],
                    'cittadinanza' => ['column' => 'CITTADINANZA', 'transform' => 'bool_s_n'],
                    'nascita' => ['column' => 'NASCITA', 'transform' => 'bool_s_n'],
                    'residenza' => ['column' => 'RESIDENZA', 'transform' => 'bool_s_n'],
                    'tipo' => ['column' => 'TIPO'],
                    'fonte' => ['column' => 'FONTE'],
                    'valid_from' => ['column' => 'DATAINIZIOVALIDITA', 'transform' => 'date_dmy_slash'],
                    'valid_to' => ['column' => 'DATAFINEVALIDITA', 'transform' => 'date_dmy_slash'],
                ],
                'defaults' => [
                    'item_type' => 'stato',
                    'is_foreign_state' => true,
                    'fonte' => 'DB',
                ],
            ],
        ],
    ],

    'lookup' => [
        'action' => \Kreatif\CodiceFiscale\Actions\FindBelfioreCode::class,
        'code_column' => 'codice_catastale',
        'search_all_languages' => true,
        'search_columns' => [
            'it' => 'denominazione',
            'de' => 'denominazione_de',
            'en' => 'denominazione_en',
        ],
        'fallback_language' => 'it',
    ],

    'item_types' => [
        'comune' => 'comune',           // Italian municipality
        'stato' => 'stato',             // Foreign state
        'territorio' => 'territorio',   // Territory
        'provincia' => 'provincia',     // Province
        'regione' => 'regione',         // Region
    ],

    'validation' => [
        'validate_checksum' => true,
        'validate_format' => true,
        'allow_empty' => false,
        'on_action_add_cf_if_empty_and_possible' => true,
    ],

    'sync' => [
        'chunk_size' => 500,
        'truncate_before_sync' => true,
        'http_timeout' => 60,
        // Upsert unique key and update columns
        'upsert' => [
            'unique_by' => ['codice_catastale'],
            'update' => [
                'item_type', 'denominazione', 'denominazione_de', 'denominazione_en', 'altra_denominazione',
                'sigla_provincia', 'id_provincia', 'id_regione', 'stato', 'cap',
                'codice', 'codice_mae', 'codice_min', 'codice_istat', 'codice_iso3',
                'is_foreign_state', 'cittadinanza', 'nascita', 'residenza', 'tipo', 'fonte',
                'valid_from', 'valid_to', 'last_change', 'updated_at'
            ],
        ],
    ],

    'cache' => [
        'enabled' => true,
        'prefix' => 'codice_fiscale',
        'ttl' => 86400,
    ],
];
