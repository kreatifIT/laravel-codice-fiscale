<?php

namespace Kreatif\CodiceFiscale\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Kreatif\CodiceFiscale\Parsers\RstGridTableParser;

class SyncGeoLocationsCommand extends Command
{
    protected $signature = 'codice-fiscale:sync-geo-locations
        {--type="*" : comune|stato|"*" (default: "*")}
        {--source=csv : csv|db (default: csv)}
        {--profile= : Override data_sources.source.profile (default: if type=* then *, else type)}
        {--no-truncate : Do not truncate existing rows for the selected type(s)}
        {--dry-run : Do not write to DB, only parse and show counts}';

    protected $description = 'Sync geo locations (comuni/stati) from configured CSV/DB sources.';

    protected string $table;
    protected int $chunkSize;

    public function __construct()
    {
        parent::__construct();

        $this->table = config('codice-fiscale.geo_locations_table', 'geo_locations');
        $this->chunkSize = (int)config('codice-fiscale.sync.chunk_size', 500);
    }

    public function handle(): int
    {
        $type = (string)$this->option('type');
        $source = (string)$this->option('source');
        $profileOpt = $this->option('profile');
        $dryRun = (bool)$this->option('dry-run');

        if (!in_array($source, ['csv', 'db'], true)) {
            $this->error("Invalid --source={$source}. Allowed: csv, db");
            return self::FAILURE;
        }

        $types = $this->resolveTypes($type);
        if ($types === []) {
            $this->error("Invalid --type={$type}. Allowed: comune, stato, *");
            return self::FAILURE;
        }

        $this->info('Starting geo-locations synchronization...');
        $this->comment('Table: ' . $this->table . "\t Source: " . $source . "\t Type(s): " . implode(', ', $types));

        if ($dryRun) {
            $this->warn('DRY RUN enabled: no DB writes will occur.');
        }

        $totalInserted = 0;
        $countriesUpdated = false;
        foreach ($types as $t) {
            $profile = $this->resolveProfile($profileOpt, $type, $t);
            $cfg = config("codice-fiscale.data_sources.{$source}.{$profile}")
                ?? config("codice-fiscale.data_sources.{$source}.{$t}")
                ?? config("codice-fiscale.data_sources.{$source}.*");

            if (!$cfg) {
                $this->error("Missing config for data_sources.{$source}.{$profile} (or {$t} or *).");
                return self::FAILURE;
            }

            $count = $this->syncOne($t, $cfg, $dryRun);
            if ($count < 0) {
                return self::FAILURE;
            }
            $totalInserted += $count;
            if ($t === config("codice-fiscale.item_types.stato", 'stato')) {
                $countriesUpdated = true;
            }
        }

        if (!$dryRun && $countriesUpdated) {
            // we need to insert italy as country with codice_catastale='*' if not present
            self::getGeoLocationModel()::updateOrCreate(
                ['codice_catastale' => '*'],
                [
                    'denominazione' => 'Italia',
                    'denominazione_en' => 'Italy',
                    'denominazione_de' => 'Italien',
                    'is_foreign_state' => false,
                    'cittadinanza' => true,
                    'nascita' => true,
                    'residenza' => true,
                    'tipo' => 'Nazione',
                    'item_type' => config("codice-fiscale.item_types.stato", 'stato'),
                ]);
            $this->info("✓ Upsert country: Italia with codice_catastale='*'.");
            $totalInserted += 1;
        }

        $this->info("✓ Completed. Upserted {$totalInserted} records.");
        return self::SUCCESS;
    }

    protected function resolveTypes(string $type): array
    {
        $type = strtolower(trim($type));
        return match ($type) {
            '*', '"*"', 'all' => ['comune', 'stato'],
            'comune', 'comuni', 'municipalities' => ['comune'],
            'stato', 'stati', 'states' => ['stato'],
            default => [],
        };
    }

    protected function resolveProfile($profileOpt, string $rawType, string $resolvedType): string
    {
        if (is_string($profileOpt) && trim($profileOpt) !== '') {
            return trim($profileOpt);
        }

        $rawType = strtolower(trim($rawType));
        if ($rawType === '*' || $rawType === 'all' || $rawType === '"*"') {
            // If user asked for all, default to '*' profile when present.
            return '*';
        }

        return $resolvedType;
    }

    protected function syncOne(string $type, array $cfg, bool $dryRun): int
    {
        $driver = $cfg['driver'] ?? null;
        $sourceType = $cfg['source_type'] ?? null;
        $source = $cfg['source'] ?? null;
        $options = $cfg['options'] ?? [];
        $mapping = $cfg['mapping'] ?? [];
        $defaults = $cfg['defaults'] ?? [];

        if (!$driver || !$sourceType || !$source) {
            $this->error("Invalid source config: driver/source_type/source are required.");
            return -1;
        }

        $this->newLine();
        $this->info("Syncing type={$type} using driver={$driver} from {$sourceType}...");

        $raw = null;
        if ($sourceType === 'file') {
            if (!is_string($source) || !file_exists($source)) {
                $this->error("File not found: {$source}");
                return -1;
            }
            $raw = file_get_contents($source);
            if ($raw === false) {
                $this->error("Failed to read file: {$source}");
                return -1;
            }
        } elseif ($sourceType === 'url') {
            try {
                $timeout = (int)config('codice-fiscale.sync.http_timeout', 60);
                $resp = Http::timeout($timeout)->get($source);
                if ($resp->failed()) {
                    $this->error('Failed to download data. HTTP status: ' . $resp->status());
                    return -1;
                }
                $raw = $resp->body();
            } catch (\Throwable $e) {
                $this->error('Download error: ' . $e->getMessage());
                return -1;
            }
        } else {
            $this->error("Invalid source_type={$sourceType}. Allowed: file, url");
            return -1;
        }


        $records = match ($driver) {
            'csv' => $this->parseCsv($raw, $options),
            'rst' => $this->parseRstStates($raw),
            default => null,
        };

        if (!is_array($records)) {
            $this->error("Unsupported driver={$driver}.");
            return -1;
        }

        if ($records === []) {
            $this->warn('No records parsed.');
            return 0;
        }

        $this->comment('Parsed records: ' . count($records));

        $now = now();
        $upserts = [];

        foreach ($records as $r) {
            if (!is_array($r)) {
                continue;
            }
            if ($sourceType == 'file') {
                $guessRecordItemType = strlen($r['sigla_provincia']) == 2
                    ? config('codice-fiscale.item_types.comune', 'comune')
                    : config('codice-fiscale.item_types.stato', 'stato');
                if ($type !== $guessRecordItemType) {
                    continue;
                }

            }

            if (!isset($r['DENOMINAZIONE']) || !isset($r['CODAT']) || !isset($r['NASCITA']) || $r['NASCITA'] == 'N') {
                if (!isset($r['descr_i']) || !isset($r['codice']))
                    continue;
            }

            $mapped = $this->mapRecord($r, $mapping, $defaults, $type);
            if (!$mapped) {
                continue;
            }

            if (empty($mapped['codice_catastale'])) {
                continue;
            }

            $mapped['created_at'] ??= $now;
            $mapped['updated_at'] ??= $now;
            $mapped['item_type'] ??= config("codice-fiscale.item_types.{$type}", $type);

            if (!array_key_exists('is_foreign_state', $mapped)) {
                $mapped['is_foreign_state'] = ($type === 'stato');
            }

            // Special case: ITALIA (keep your old behavior).
            if (($type === 'stato') && isset($mapped['denominazione']) && strtoupper((string)$mapped['denominazione']) === 'ITALIA') {
                $mapped['codice_catastale'] = '*';
                $mapped['is_foreign_state'] = false;
                $mapped['cittadinanza'] = true;
                $mapped['nascita'] = true;
                $mapped['residenza'] = true;
                $mapped['tipo'] = $mapped['tipo'] ?? 'Nazione';
                $mapped['denominazione_de'] = $mapped['denominazione_de'] ?? 'Italien';
                $mapped['denominazione_en'] = $mapped['denominazione_en'] ?? 'Italy';
            }
            $upserts[] = $mapped;
        }

        $upserts = collect($upserts)
            ->filter(fn($x) => is_array($x) && !empty($x['codice_catastale']))
            ->unique('codice_catastale')
            ->values()
            ->all();

        if ($upserts === []) {
            $this->warn('No valid rows after mapping/filtering.');
            return 0;
        }

        $this->comment('Prepared for upsert: ' . count($upserts));

        if ($dryRun) {
            $this->line('Dry-run sample row:');
            $this->line(json_encode($upserts[0], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            return count($upserts);
        }

        $uniqueBy = (array)config('codice-fiscale.sync.upsert.unique_by', ['codice_catastale']);
        $updateCols = (array)config('codice-fiscale.sync.upsert.update', []);

        DB::transaction(function () use ($type, $upserts, $uniqueBy, $updateCols) {
            if (config('codice-fiscale.sync.truncate_before_sync', true) && !$this->option('no-truncate')) {
                $this->comment('Deleting existing rows for selected type...');
                $q = DB::table($this->table)->where('item_type', config("codice-fiscale.item_types.{$type}", $type));
                if ($type === 'comune') {
                    $q->where('is_foreign_state', false);
                }
                if ($type === 'stato') {
                    // keep consistent with your schema usage
                    $q->where('is_foreign_state', true);
                }
                $q->delete();
            }

            $bar = $this->output->createProgressBar(count($upserts));
            $bar->start();

            foreach (array_chunk($upserts, $this->chunkSize) as $kiy => $chunk) {
                DB::table($this->table)->upsert($chunk, $uniqueBy, $updateCols);
                $bar->advance(count($chunk));
            }

            $bar->finish();
            $this->newLine();
        });

        $this->info('✓ Upserted ' . count($upserts) . ' rows for type=' . $type);
        return count($upserts);
    }

    protected function parseCsv(string $content, array $options): array
    {
        $delimiter = $options['delimiter'] ?? ',';
        $enclosure = $options['enclosure'] ?? '"';
        $escape = $options['escape'] ?? '\\';
        $headerRow = (bool)($options['header_row'] ?? true);
        $encoding = $options['encoding'] ?? 'UTF-8';

        if ($encoding && strtoupper($encoding) !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        $lines = preg_split('/\r\n|\r|\n/', $content);
        $lines = array_values(array_filter($lines, fn($l) => trim((string)$l) !== ''));

        if ($lines === []) {
            return [];
        }

        $header = [];
        $start = 0;

        if ($headerRow) {
            $header = str_getcsv($lines[0], $delimiter, $enclosure, $escape);
            $header = array_map(fn($h) => strtolower(trim((string)$h)), $header);
            $start = 1;
        }

        $out = [];
        for ($i = $start; $i < count($lines); $i++) {
            $row = str_getcsv($lines[$i], $delimiter, $enclosure, $escape);
            if ($headerRow) {
                if (count($row) !== count($header)) {
                    continue;
                }
                $out[] = array_combine($header, $row);
            } else {
                $out[] = $row;
            }
        }

        return $out;
    }

    protected function parseRstStates(string $content): array
    {
        $parser = new RstGridTableParser()->parseContent($content);
        $states = collect($parser->rows)
            ->filter(fn($x) => is_array($x) && (isset($x['CODAT']) || (isset($x['denominazione']) && strtoupper((string)$x['denominazione']) === 'ITALIA')))
            ->values()
            ->all();
        return $states;
    }

    protected function mapRecord(array $record, array $mapping, array $defaults, string $type): ?array
    {
        $row = [];

        foreach ($defaults as $k => $v) {
            if ($k === 'item_type') {
                $row[$k] = config("codice-fiscale.item_types.{$v}", $v);
            } else {
                $row[$k] = str($v)->value();
            }
        }

        if (isset($mapping['type']) && is_array($mapping['type'])) {
            $typeCfg = $mapping['type'];
            $col = $typeCfg['column'] ?? null;
            $vals = $typeCfg['values'] ?? [];
            if ($col && isset($record[strtolower($col)]) || isset($record[$col])) {
                $rawVal = $record[strtolower($col)] ?? $record[$col] ?? null;
                if ($rawVal !== null && $vals) {
                    $mappedType = $vals[(string)$rawVal] ?? null;
                    if ($mappedType) {
                        $row['item_type'] = config("codice-fiscale.item_types.{$mappedType}", $mappedType);
                        $row['is_foreign_state'] = ($mappedType === 'stato');
                    }
                }
            }
        }
        foreach ($mapping as $dbField => $cfg) {
            if ($dbField === 'type') {
                continue;
            }
            if (!is_array($cfg)) {
                continue;
            }

            $col = $cfg['column'] ?? null;
            $fallback = $cfg['fallback_columns'] ?? [];
            $default = $cfg['default'] ?? null;
            $transform = $cfg['transform'] ?? null;

            $val = null;
            if (is_string($col) && $col !== '') {
                $val = $record[strtolower($col)] ?? $record[$col] ?? null;
            }

            if (($val === null || $val === '') && is_array($fallback)) {
                foreach ($fallback as $fb) {
                    $v = $record[strtolower((string)$fb)] ?? $record[$fb] ?? null;
                    if ($v !== null && $v !== '') {
                        $val = $v;
                        break;
                    }
                }
            }

            if (($val === null || $val === '') && $default !== null) {
                $val = $default;
            }

            if ($val === null) {
                continue;
            }

            // Trim strings.
            if (is_string($val)) {
                $val = trim($val);
                $val = str($val)->value();
                $val = addslashes($val);
                $val = str_replace(',', "", $val);
            }

            // Apply transforms.
            if (is_string($transform) && $transform !== '') {
                $val = $this->applyTransform($transform, $val);
            }

            // Some mild normalization.
            if (in_array($dbField, ['denominazione', 'denominazione_de', 'denominazione_en'], true) && is_string($val)) {
                $val = Str::title($val);
            }

            $row[$dbField] = $val;
        }

        $row['item_type'] ??= config("codice-fiscale.item_types.{$type}", $type);

        // Ensure booleans are booleans when present.
        foreach (['is_foreign_state', 'cittadinanza', 'nascita', 'residenza'] as $b) {
            if (array_key_exists($b, $row)) {
                $row[$b] = (bool)$row[$b];
            }
        }

        // Normalize dates for DB.
        foreach (['valid_from', 'valid_to'] as $d) {
            if (isset($row[$d]) && is_string($row[$d]) && $row[$d] === '') {
                $row[$d] = null;
            }
        }

        if (!array_key_exists('is_foreign_state', $row)) {
            $row['is_foreign_state'] = ($type === 'stato');
        }

        return $row;
    }

    protected function applyTransform(string $transform, $val)
    {
        return match ($transform) {
            'date_dmy_slash' => $this->transformDateDmySlash($val),
            'bool_s_n' => $this->transformBoolSN($val),
            default => $val,
        };
    }

    protected function transformBoolSN($val): bool
    {
        return strtoupper((string)$val) === 'S';
    }

    protected function transformDateDmySlash($val): ?string
    {
        $v = trim((string)$val);
        if ($v === '') {
            return null;
        }
        if (!preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $v)) {
            return null;
        }
        [$d, $m, $y] = explode('/', $v);
        return "{$y}-{$m}-{$d}";
    }

    /**
     * @return class-string<\Kreatif\CodiceFiscale\Models\GeoLocation>
     */
    protected static function getGeoLocationModel(): string
    {
        return config(
            'codice-fiscale.geo_locations_model',
            \Kreatif\CodiceFiscale\Models\GeoLocation::class
        );
    }

}


