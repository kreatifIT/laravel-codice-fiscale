<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create(config('codice-fiscale.geo_locations_table', 'geo_locations'), function (Blueprint $table) {
            $table->id();

            // Item Type (comune, stato, provincia, regione, territorio, etc.)
            $table->string('item_type', 50)->index();

            // Multilingual Denomination (Name)
            $table->string('denominazione')->index();
            $table->string('denominazione_de')->nullable()->index();
            $table->string('denominazione_en')->nullable()->index();

            // Alternative denomination (former names, aliases)
            $table->string('altra_denominazione')->nullable();

            // Cadastral Code (Belfiore Code) - Primary identifier for Codice Fiscale
            $table->string('codice_catastale', 4)->unique()->index();

            // Province Information (for municipalities)
            $table->string('sigla_provincia', 4)->nullable()->index();
            $table->string('id_provincia', 10)->nullable()->index(); // Can be numeric or 'EX' for historical

            // Region Information
            $table->string('id_regione', 10)->nullable()->index(); // Can be numeric or 'EX' for historical

            // State Information
            $table->string('stato')->nullable();
            $table->boolean('is_foreign_state')->default(false)->index();

            // Various Official Codes
            $table->string('codice')->nullable(); // Generic code
            $table->string('codice_mae')->nullable(); // Ministero Affari Esteri code
            $table->string('codice_min')->nullable(); // Ministry code
            $table->string('codice_istat')->nullable()->index(); // ISTAT code
            $table->string('codice_iso3', 3)->nullable(); // ISO 3166-1 alpha-3 code

            // Foreign State Specific Fields
            $table->boolean('cittadinanza')->default(false); // For citizenship
            $table->boolean('nascita')->default(false); // For birth place
            $table->boolean('residenza')->default(false); // For residence

            // Type classification (for states: STATO / TERRITORIO / ALTRO)
            $table->string('tipo')->nullable();

            // Data Source / Origin
            $table->string('fonte')->nullable(); // Source: ANPR, Pronotel, etc.

            // Postal Code
            $table->string('cap', 5)->nullable();

            // Validity Period
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();

            // Last Change (when Italy updates the data)
            $table->timestamp('last_change')->nullable();

            // Laravel timestamps
            $table->timestamps();

            // Indexes for common queries
            $table->index(['item_type', 'is_foreign_state']);
            $table->index(['item_type', 'sigla_provincia']);
            $table->index(['valid_from', 'valid_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('codice-fiscale.geo_locations_table', 'geo_locations'));
    }
};
