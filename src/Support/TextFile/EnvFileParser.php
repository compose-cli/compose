<?php

declare(strict_types=1);

namespace Compose\Support\TextFile;

class EnvFileParser
{
    /** @var list<array{type: string, ...}> */
    protected array $entries = [];

    /** @var array<string, int> key -> index into for fast lookup */
    protected array $keyIndex = [];

    protected function __construct() {}

    public static function parse(string $contents): self
    {
        $parser = new self;

        if ($contents === '') {
            return $parser;
        }

        $lines = explode("\n", $contents);

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                $parser->entries[] = ['type' => 'blank'];

                continue;
            }

            if (str_starts_with($trimmed, '#')) {
                $parser->entries[] = ['type' => 'comment', 'raw' => $line];

                continue;
            }

            $export = false;

            if (str_starts_with($trimmed, 'export ')) {
                $export = true;
                $trimmed = substr($trimmed, 7);
            }

            $eqPos = strpos($trimmed, '=');

            if ($eqPos === false) {
                $key = $trimmed;
                $parser->addEntry($key, null, '', $export, $line);

                continue;
            }

            $key = substr($trimmed, 0, $eqPos);
            $rawValue = substr($trimmed, $eqPos + 1);
            [$value, $quote] = self::parseValue($rawValue);

            $parser->addEntry($key, $value, $quote, $export, $line);
        }

        return $parser;
    }

    public function get(string $key): ?string
    {
        if (! isset($this->keyIndex[$key])) {
            return null;
        }

        return $this->entries[$this->keyIndex[$key]]['value'];
    }

    public function has(string $key): bool
    {
        return isset($this->keyIndex[$key]);
    }

    public function set(string $key, string $value, ?string $afterKey = null): self
    {
        if (isset($this->keyIndex[$key])) {
            $index = $this->keyIndex[$key];
            $this->entries[$index]['value'] = $value;
            $this->entries[$index]['raw'] = null;

            return $this;
        }

        $entry = [
            'type' => 'pair',
            'key' => $key,
            'value' => $value,
            'quote' => '',
            'export' => false,
            'raw' => null,
        ];

        if ($afterKey !== null && isset($this->keyIndex[$afterKey])) {
            $insertAt = $this->keyIndex[$afterKey] + 1;
            array_splice($this->entries, $insertAt, 0, [$entry]);
            $this->rebuildKeyIndex();
        } else {
            $this->keyIndex[$key] = count($this->entries);
            $this->entries[] = $entry;
        }

        return $this;
    }

    public function remove(string $key): self
    {
        if (! isset($this->keyIndex[$key])) {
            return $this;
        }

        $index = $this->keyIndex[$key];
        array_splice($this->entries, $index, 1);
        $this->rebuildKeyIndex();

        return $this;
    }

    /**
     * Comment out a key by prefixing its line with `# `.
     */
    public function comment(string $key): self
    {
        if (! isset($this->keyIndex[$key])) {
            return $this;
        }

        $index = $this->keyIndex[$key];
        $entry = $this->entries[$index];

        $serialized = $this->serializeEntry($entry);
        $this->entries[$index] = ['type' => 'comment', 'raw' => '# '.$serialized];

        unset($this->keyIndex[$key]);

        return $this;
    }

    /**
     * Uncomment a previously commented-out key.
     *
     * Scans comment lines for one that, when stripped of the `# ` prefix,
     * parses as the given key.
     */
    public function uncomment(string $key): self
    {
        foreach ($this->entries as $index => $entry) {
            if ($entry['type'] !== 'comment') {
                continue;
            }

            $raw = $entry['raw'];
            $stripped = preg_replace('/^#\s*/', '', (string) $raw);

            $eqPos = strpos((string) $stripped, '=');
            $commentKey = $eqPos !== false ? substr((string) $stripped, 0, $eqPos) : trim((string) $stripped);

            $export = false;

            if (str_starts_with($commentKey, 'export ')) {
                $export = true;
                $commentKey = substr($commentKey, 7);
            }

            $commentKey = trim($commentKey);

            if ($commentKey !== $key) {
                continue;
            }

            if ($eqPos !== false) {
                $rawValue = substr((string) $stripped, $eqPos + 1);
                [$value, $quote] = self::parseValue($rawValue);
            } else {
                $value = null;
                $quote = '';
            }

            $this->entries[$index] = [
                'type' => 'pair',
                'key' => $key,
                'value' => $value,
                'quote' => $quote,
                'export' => $export,
                'raw' => null,
            ];

            $this->keyIndex[$key] = $index;

            return $this;
        }

        return $this;
    }

    /**
     * Add a section: blank line + comment header + key-value pairs.
     */
    public function addSection(string $header, array $values, ?string $afterKey = null): self
    {
        $newEntries = [];
        $newEntries[] = ['type' => 'blank'];
        $newEntries[] = ['type' => 'comment', 'raw' => $header];

        foreach ($values as $key => $value) {
            $newEntries[] = [
                'type' => 'pair',
                'key' => $key,
                'value' => $value,
                'quote' => '',
                'export' => false,
                'raw' => null,
            ];
        }

        if ($afterKey !== null && isset($this->keyIndex[$afterKey])) {
            $insertAt = $this->keyIndex[$afterKey] + 1;
            array_splice($this->entries, $insertAt, 0, $newEntries);
        } else {
            array_push($this->entries, ...$newEntries);
        }

        $this->rebuildKeyIndex();

        return $this;
    }

    public function toString(): string
    {
        $lines = [];

        foreach ($this->entries as $entry) {
            $lines[] = match ($entry['type']) {
                'blank' => '',
                'comment' => $entry['raw'],
                'pair' => $entry['raw'] ?? $this->serializeEntry($entry),
            };
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{0: string, 1: string} [value, quoteStyle]
     */
    private static function parseValue(string $raw): array
    {
        if ($raw === '') {
            return ['', ''];
        }

        if (str_starts_with($raw, '"')) {
            $end = self::findClosingQuote($raw, '"');
            $inner = substr($raw, 1, $end - 1);
            $value = stripcslashes($inner);

            return [$value, '"'];
        }

        if (str_starts_with($raw, "'")) {
            $end = self::findClosingQuote($raw, "'");
            $inner = substr($raw, 1, $end - 1);

            return [$inner, "'"];
        }

        $value = $raw;
        $commentPos = strpos($value, ' #');

        if ($commentPos !== false) {
            $value = substr($value, 0, $commentPos);
        }

        return [rtrim($value), ''];
    }

    private static function findClosingQuote(string $raw, string $quote): int
    {
        $length = strlen($raw);

        for ($i = 1; $i < $length; $i++) {
            if ($raw[$i] === '\\' && $quote === '"') {
                $i++;

                continue;
            }

            if ($raw[$i] === $quote) {
                return $i;
            }
        }

        return $length;
    }

    private function addEntry(string $key, ?string $value, string $quote, bool $export, string $raw): void
    {
        $index = count($this->entries);
        $this->entries[] = [
            'type' => 'pair',
            'key' => $key,
            'value' => $value,
            'quote' => $quote,
            'export' => $export,
            'raw' => $raw,
        ];
        $this->keyIndex[$key] = $index;
    }

    private function serializeEntry(array $entry): string
    {
        $line = '';

        if ($entry['export']) {
            $line .= 'export ';
        }

        $line .= $entry['key'];

        if ($entry['value'] === null) {
            return $line;
        }

        $line .= '=';

        $line .= match ($entry['quote']) {
            '"' => '"'.addcslashes($entry['value'], '"\\').'"',
            "'" => "'".$entry['value']."'",
            default => $entry['value'],
        };

        return $line;
    }

    private function rebuildKeyIndex(): void
    {
        $this->keyIndex = [];

        foreach ($this->entries as $index => $entry) {
            if ($entry['type'] === 'pair') {
                $this->keyIndex[$entry['key']] = $index;
            }
        }
    }
}
