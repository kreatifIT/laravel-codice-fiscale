<?php

namespace Kreatif\CodiceFiscale\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class GeoLocation extends Model
{

    protected $fillable = [
        'item_type',
        'denominazione',
        'denominazione_de',
        'denominazione_en',
        'altra_denominazione',
        'codice_catastale',
        'sigla_provincia',
        'id_provincia',
        'id_regione',
        'stato',
        'is_foreign_state',
        'codice',
        'codice_mae',
        'codice_min',
        'codice_istat',
        'codice_iso3',
        'cittadinanza',
        'nascita',
        'residenza',
        'tipo',
        'fonte',
        'cap',
        'valid_from',
        'valid_to',
        'last_change',
    ];

    protected $casts = [
        'is_foreign_state' => 'boolean',
        'cittadinanza' => 'boolean',
        'nascita' => 'boolean',
        'residenza' => 'boolean',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'last_change' => 'datetime',
    ];

    public function getTable()
    {
        return config('codice-fiscale.geo_locations_table', parent::getTable());
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('item_type', $type);
    }

    public function scopeComuni(Builder $query): Builder
    {
        return $query->where('item_type', config('codice-fiscale.item_types.comune', 'comune'));
    }

    public function scopeForeignStates(Builder $query): Builder
    {
        return $query->where('is_foreign_state', true)
            ->where('item_type', config('codice-fiscale.item_types.stato', 'stato'));
    }

    public function scopeValid(Builder $query, ?Carbon $date = null): Builder
    {
        $date = ($date ?? now())->toDateString();

        return $query
            ->where(function (Builder $q) use ($date) {
                $q->whereNull('valid_from')
                  ->orWhereDate('valid_from', '<=', $date);
            })
            ->where(function (Builder $q) use ($date) {
                $q->whereNull('valid_to')
                  ->orWhereDate('valid_to', '>=', $date);
            });
    }

    public function scopeSearchByName(Builder $query, string $name): Builder
    {
        $likeOperator = $this->getLikeOperator();
        return $query->where(function (Builder $q) use ($name, $likeOperator) {
            $q->where('denominazione', $likeOperator, $name)
                ->orWhere('denominazione_de', $likeOperator, $name)
                ->orWhere('denominazione_en', $likeOperator, $name)
                ->orWhere('altra_denominazione', $likeOperator, $name);
        });
    }

    public static function findByBelfioreCode(?string $code): ?self
    {
        if ($code == null) {
            return null;
        }
        return static::where('codice_catastale', strtoupper($code))
            ->valid()
            ->first();
    }

    public function isItaly(): bool
    {
        return $this->codice_catastale === '*';
    }

    public function getLabel(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        if (in_array($locale, ['de', 'en'])) {
            $nameField = "denominazione_{$locale}";
            if (!empty($this->{$nameField})) {
                return $this->{$nameField};
            }
        }
        return $this->denominazione ?? '';
    }

    public function isValid(?Carbon $date = null): bool
    {
        $date = $date ?? now();
        $fromValid = is_null($this->valid_from) || $this->valid_from->lte($date);
        $toValid = is_null($this->valid_to) || $this->valid_to->gte($date);

        return $fromValid && $toValid;
    }

    public function isComune(): bool
    {
        return $this->item_type === config('codice-fiscale.item_types.comune', 'comune');
    }

    public function isForeignState(): bool
    {
        return $this->is_foreign_state &&
               $this->item_type === config('codice-fiscale.item_types.stato', 'stato');
    }

    protected function getLikeOperator(): string
    {
        $driver = $this->getConnection()->getDriverName();

        return match ($driver) {
            'pgsql' => 'ilike',
            default => 'LIKE',
        };
    }

    public static function getMunicipalityOptions(?string $locale = null, bool $onlyValid = true, int $limit = 10000): array
    {
        $query = static::comuni();
        if ($onlyValid) {
            $query->valid();
        }
        return $query->get()
            ->limit($limit)
            ->toArray();
    }

    public static function getForeignStateOptions(?string $locale = null, bool $onlyValid = true, int $limit = 300): array
    {
        $query = static::foreignStates();

        if ($onlyValid) {
            $query->valid();
        }
        return $query->get()
            ->limit($limit)
            ->toArray();
    }

    public static function searchOptions(
        string $search,
        ?string $type = null,
        int $limit = 50,
        ?string $locale = null
    ): \Illuminate\Database\Eloquent\Collection
    {
        $textSearchableColumns = [
            'denominazione',
            'denominazione_de',
            'denominazione_en',
            'altra_denominazione',
        ];

        $operator = (new static)->getLikeOperator(); // Handles LIKE vs ILIKE (Postgres)

        $query = static::query()->valid();

        if ($type) {
            $query->ofType($type);
        }

        // Filter rows where *any* column contains the string
        $query->where(function ($q) use ($textSearchableColumns, $search, $operator) {
            foreach ($textSearchableColumns as $column) {
                $q->orWhere($column, $operator, "%{$search}%");
            }
        });

        // RANKING: Build dynamic SQL for "Exact" vs "Starts With" vs "Contains"
        // We prefer exact matches (0), then 'starts with' (1), then others (2)
        $exactCases = [];
        $startsWithCases = [];
        $bindings = [];

        foreach ($textSearchableColumns as $column) {
            // Priority 0: Exact Match
            $exactCases[] = "{$column} {$operator} ?";
            $bindings[] = $search;

            // Priority 1: Starts With
            $startsWithCases[] = "{$column} {$operator} ?";
            $bindings[] = "{$search}%";
        }

        // Join the conditions with OR
        $sqlExact = implode(' OR ', $exactCases);
        $sqlStartsWith = implode(' OR ', $startsWithCases);

        // Apply Ordering
        return $query
            ->orderByRaw("
            CASE
                WHEN ({$sqlExact}) THEN 0
                WHEN ({$sqlStartsWith}) THEN 1
                ELSE 2
            END
        ", $bindings)
            // Tie-breaker: Shortest strings appear first (e.g., 'Rome' before 'Rome City')
            ->orderByRaw("LENGTH(denominazione) ASC")
            ->limit($limit)
            ->get();
    }

}
