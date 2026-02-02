<?php

declare(strict_types=1);

namespace Kreatif\CodiceFiscale\Parsers;

use RuntimeException;

final class RstGridTableParser
{
    /** @var list<string> */
    public array $headers = [];

    /** @var list<array<string,mixed>> */
    public array $rows = [];

    public function isEmpty(): bool
    {
        return $this->headers === [] && $this->rows === [];
    }

    public function parseFile(string $filePath): self
    {
        if (trim($filePath) === '') {
            throw new RuntimeException('filePath cannot be empty.');
        }

        if (!is_file($filePath)) {
            throw new RuntimeException("File not found: {$filePath}");
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException("Unable to read file: {$filePath}");
        }

        $this->parseLines($lines);

        return $this;
    }

    public function parseContent(string $content): self
    {
        $lines = preg_split('/\r\n|\r|\n/', $content);
        if ($lines === false) {
            throw new RuntimeException('Unable to split content into lines.');
        }

        $this->parseLines($lines);

        return $this;
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    /**
     * @param list<string> $lines
     */
    private function parseLines(array $lines): void
    {
        $this->headers = [];
        $this->rows = [];
        $boundaries = [];
        $buffer = [];
        $parsingStarted = false;

        foreach ($lines as $rawLine) {
            $line = rtrim((string) $rawLine, "\r\n");

            if ($this->isBorderLine($line)) {
                // First border initializes boundaries
                if (!$parsingStarted) {
                    $boundaries = $this->detectBoundaries($line);
                    if ($boundaries === []) {
                        continue;
                    }
                    $parsingStarted = true;
                    $buffer = [];
                    continue;
                }

                // Flush row buffer when a border line appears
                if ($buffer !== []) {
                    $row = $this->finalizeRow($buffer);

                    if ($this->isHeaderSeparator($line) && $this->headers === []) {
                        $this->headers = $row;
                    } else {
                        // 1. Map to headers
                        $assocRow = ($this->headers !== [])
                            ? $this->combineWithHeaders($row)
                            : $row;

                        // 2. Expand nested ANPR attributes (The Fix)
                        $this->rows[] = $this->expandRowAttributes($assocRow);
                    }
                    $buffer = [];
                }
                continue;
            }

            if ($parsingStarted && $this->isContentLine($line)) {
                $cells = $this->extractCells($line, $boundaries);

                if ($buffer === []) {
                    $buffer = $cells;
                    continue;
                }

                foreach ($cells as $i => $cell) {
                    if (!isset($buffer[$i])) {
                        $buffer[$i] = $cell;
                        continue;
                    }
                    if ($cell === '') {
                        continue;
                    }
                    // Preserve \n for later splitting
                    $buffer[$i] = rtrim((string) $buffer[$i]) . "\n" . $cell;
                }
            }
        }

        // Final flush if needed
        if ($parsingStarted && $buffer !== []) {
            $row = $this->finalizeRow($buffer);
            $assocRow = ($this->headers !== []) ? $this->combineWithHeaders($row) : $row;
            $this->rows[] = $this->expandRowAttributes($assocRow);
        }

        $this->cleanupData();
    }

    /**
     * Parses nested lists inside cells (e.g., "- KEY: Value") and flattens them into the row.
     */
    private function expandRowAttributes(array $row): array
    {
        $newRow = [];

        foreach ($row as $key => $value) {
            if (!is_string($value) || !str_contains($value, "\n - ")) {
                $newRow[$key] = $value;
                continue;
            }

            // Split: "MAIN VALUE\n - KEY: Val\n - KEY2: Val"
            $parts = explode("\n - ", $value);

            // The first part is the main value (e.g., "AFGHANISTAN")
            $mainValue = trim(array_shift($parts));
            $newRow[$key] = $mainValue;

            // Parse the attributes
            foreach ($parts as $part) {
                // Split only on the first colon
                $p = explode(':', $part, 2);
                if (count($p) === 2) {
                    $subKey = trim($p[0]);
                    $subVal = trim($p[1]);
                    $newRow[$subKey] = $subVal;
                }
            }
        }

        return $newRow;
    }

    private function isBorderLine(string $line): bool
    {
        $t = ltrim($line);
        return $t !== ''
            && $t[0] === '+'
            && (str_contains($t, '-') || str_contains($t, '='))
            && preg_match('/^\+[-=\+]+$/', trim($t)) === 1;
    }

    private function isHeaderSeparator(string $line): bool
    {
        return str_contains($line, '=');
    }

    private function isContentLine(string $line): bool
    {
        $t = ltrim($line);
        return $t !== '' && $t[0] === '|';
    }

    private function detectBoundaries(string $borderLine): array
    {
        $positions = [];
        $len = strlen($borderLine);
        for ($i = 0; $i < $len; $i++) {
            if ($borderLine[$i] === '+') {
                $positions[] = $i;
            }
        }
        if (count($positions) < 2) {
            return [];
        }

        $out = [];
        for ($i = 0; $i < count($positions) - 1; $i++) {
            $start = $positions[$i] + 1;
            $length = $positions[$i + 1] - $start;
            if ($length < 0) {
                continue;
            }
            $out[] = ['start' => $start, 'length' => $length];
        }
        return $out;
    }

    private function extractCells(string $contentLine, array $boundaries): array
    {
        $cells = [];
        $lineLen = strlen($contentLine);
        foreach ($boundaries as $b) {
            if ($b['start'] >= $lineLen) {
                $cells[] = '';
                continue;
            }
            $cells[] = substr($contentLine, $b['start'], $b['length']);
        }
        return $cells;
    }

    private function finalizeRow(array $rawCells): array
    {
        $out = [];
        foreach ($rawCells as $cell) {
            $v = (string) $cell;
            // Clean tabs/spaces but PRESERVE newlines (\n is not in the regex class)
            $v = preg_replace("/[\t\x0B\f\r ]+/u", ' ', $v) ?? $v;
            $v = trim($v);
            $out[] = $v;
        }
        return $out;
    }

    private function combineWithHeaders(array $row): array
    {
        if (count($row) !== count($this->headers)) {
            return $row;
        }
        $headers = array_map(fn($h) => trim((string) $h), $this->headers);
        $assoc = array_combine($headers, $row);
        return is_array($assoc) ? $assoc : $row;
    }

    private function cleanupData(): void
    {
        // Drop empty header artifacts
        $this->headers = array_values(array_filter($this->headers, fn($h) => (string) $h !== ''));

        // Drop completely empty rows
        $this->rows = array_values(array_filter($this->rows, function ($r) {
            if (!is_array($r)) return false;
            foreach ($r as $v) {
                if (trim((string) $v) !== '') return true;
            }
            return false;
        }));
    }
}
