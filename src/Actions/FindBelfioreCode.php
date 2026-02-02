<?php

namespace Kreatif\CodiceFiscale\Actions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Kreatif\CodiceFiscale\Models\GeoLocation;

/**
 * Action to find the Belfiore (cadastral) code for a given place name.
 *
 * This action searches both Italian municipalities and foreign states
 * in all configured language columns (IT, DE, EN) and returns the
 * corresponding codice_catastale (Belfiore code) used in Codice Fiscale.
 *
 * Usage:
 *   $code = app(FindBelfioreCode::class)->execute('Roma');
 *   $code = FindBelfioreCode::find('Berlin');
 */
class FindBelfioreCode
{
    protected string $table;
    protected string $codeColumn;
    protected array $searchColumns;
    protected bool $searchAllLanguages;
    protected ?string $locale = null;
    protected bool $useCache;
    protected string $cachePrefix;
    protected int $cacheTtl;

    public function __construct()
    {
        $this->table = config('codice-fiscale.geo_locations_table', 'geo_locations');
        $this->codeColumn = config('codice-fiscale.lookup.code_column', 'codice_catastale');
        $this->searchColumns = config('codice-fiscale.lookup.search_columns', [
            'it' => 'denominazione',
            'de' => 'denominazione_de',
            'en' => 'denominazione_en',
        ]);
        $this->searchAllLanguages = config('codice-fiscale.lookup.search_all_languages', true);
        $this->locale = app()->getLocale();
        $this->useCache = config('codice-fiscale.cache.enabled', true);
        $this->cachePrefix = config('codice-fiscale.cache.prefix', 'codice_fiscale');
        $this->cacheTtl = config('codice-fiscale.cache.ttl', 86400);
    }

    public static function find(string $placeName): ?string
    {
        return app(static::class)->execute($placeName);
    }

    public function setLocale(?string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    public function setTable(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    public function setCodeColumn(string $column): self
    {
        $this->codeColumn = $column;
        return $this;
    }

    public function setSearchColumns(array $columns): self
    {
        $this->searchColumns = $columns;
        return $this;
    }

    public function searchAllLanguages(bool $enabled): self
    {
        $this->searchAllLanguages = $enabled;
        return $this;
    }
    public function withoutCache(): self
    {
        $this->useCache = false;
        return $this;
    }

    public function execute(string $placeName): ?string
    {
        if (empty(trim($placeName))) {
            return null;
        }

        $placeName = trim($placeName);
        if ($this->useCache) {
            $cacheKey = $this->getCacheKey($placeName);
            $cached = Cache::get($cacheKey);

            if ($cached !== null) {
                return $cached;
            }
        }

        // Check if it is a code directly
        $byCode = DB::table($this->table)->where($this->codeColumn, $placeName)->value($this->codeColumn);
        if ($byCode) {
            return $byCode;
        }

        $code = $this->searchInDatabase($placeName);
        if ($this->useCache && $code !== null) {
            Cache::put($this->getCacheKey($placeName), $code, $this->cacheTtl);
        }

        return $code;
    }

    protected function searchInDatabase(string $placeName): ?string
    {
        $columns = $this->getSearchColumns();

        if (empty($columns)) {
            return null;
        }

        $query = DB::table($this->table)
            ->where(function ($q) use ($columns, $placeName) {
                $operator = $this->getLikeOperator();

                foreach ($columns as $column) {
                    $q->orWhere($column, $operator, $placeName);
                }
            })
            ->where(function ($q) {
                $q->where(function ($q) {
                    $q->whereNull('valid_from')
                        ->orWhere('valid_from', '<=', now());
                })
                ->where(function ($q) {
                    $q->whereNull('valid_to')
                        ->orWhere('valid_to', '>=', now());
                });
            });

        return $query->value($this->codeColumn);
    }

    protected function getSearchColumns(): array
    {
        if ($this->searchAllLanguages) {
            return array_values($this->searchColumns);
        }

        if (empty($this->searchColumns)) {
            return [];
        }
        if (isset($this->searchColumns[$this->locale])) {
            return [$this->searchColumns[$this->locale]];
        }
        $fallbackLang = config('codice-fiscale.lookup.fallback_language', 'it');
        if (isset($this->searchColumns[$fallbackLang])) {
            return [$this->searchColumns[$fallbackLang]];
        }
        return array_values($this->searchColumns);
    }

    protected function getLikeOperator(): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'pgsql' => 'ilike',
            default => 'LIKE',
        };
    }

    protected function getCacheKey(string $placeName): string
    {
        $key = strtolower(trim($placeName));
        return "{$this->cachePrefix}:belfiore:" . md5($key);
    }

    public function clearCache(string $placeName): void
    {
        Cache::forget($this->getCacheKey($placeName));
    }

    public static function clearAllCaches(): void
    {
        $prefix = config('codice-fiscale.cache.prefix', 'codice_fiscale');
        Cache::forget("{$prefix}:belfiore:*");
    }
}
