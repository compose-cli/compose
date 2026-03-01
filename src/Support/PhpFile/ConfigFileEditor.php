<?php

declare(strict_types=1);

namespace Compose\Support\PhpFile;

use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\Token;
use RuntimeException;

class ConfigFileEditor
{
    /** @var Node\Stmt[] */
    private readonly array $oldStmts;

    /** @var Token[] */
    private readonly array $oldTokens;

    /** @var Node\Stmt[] */
    private readonly array $newStmts;

    private readonly Array_ $configArray;

    /** @var list<string> */
    private array $keysToComment = [];

    private function __construct(
        private readonly string $originalCode,
    ) {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();

        $this->oldStmts = $parser->parse($originalCode)
            ?? throw new RuntimeException('Failed to parse PHP code');
        $this->oldTokens = $parser->getTokens();

        $traverser = new NodeTraverser;
        $traverser->addVisitor(new CloningVisitor);
        $this->newStmts = $traverser->traverse($this->oldStmts);

        $this->configArray = $this->findReturnArray($this->newStmts);
    }

    public static function fromCode(string $code): self
    {
        return new self($code);
    }

    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $array = $this->configArray;

        for ($i = 0; $i < count($segments) - 1; $i++) {
            $item = $this->findItem($array, $segments[$i]);

            if ($item === null) {
                $newArray = new Array_([], ['kind' => Array_::KIND_SHORT]);
                $array->items[] = new ArrayItem($newArray, new String_($segments[$i]));
                $array = $newArray;
            } elseif ($item->value instanceof Array_) {
                $array = $item->value;
            } else {
                throw new RuntimeException("Cannot traverse into non-array key: {$segments[$i]}");
            }
        }

        $lastKey = end($segments);
        $item = $this->findItem($array, $lastKey);
        $valueNode = $this->valueToNode($value);

        if ($item !== null) {
            $item->value = $valueNode;
        } else {
            $array->items[] = new ArrayItem($valueNode, new String_($lastKey));
        }
    }

    public function remove(string $key): void
    {
        $segments = explode('.', $key);
        $array = $this->configArray;

        for ($i = 0; $i < count($segments) - 1; $i++) {
            $item = $this->findItem($array, $segments[$i]);

            if ($item === null || ! $item->value instanceof Array_) {
                return;
            }

            $array = $item->value;
        }

        $lastKey = end($segments);
        $array->items = array_values(array_filter(
            $array->items,
            fn (?ArrayItem $item) => $item !== null && ! $this->itemMatchesKey($item, $lastKey),
        ));
    }

    /**
     * @param  array<mixed>  $values
     */
    public function merge(string $key, array $values): void
    {
        $targetArray = $this->resolveArray($key);

        $existing = [];

        foreach ($targetArray->items as $item) {
            if ($item?->value instanceof String_) {
                $existing[] = $item->value->value;
            } elseif ($item?->value instanceof Int_) {
                $existing[] = $item->value->value;
            }
        }

        foreach ($values as $k => $v) {
            if ((is_string($v) || is_int($v)) && in_array($v, $existing, true)) {
                continue;
            }

            $valueNode = $this->valueToNode($v);

            if (is_int($k)) {
                $targetArray->items[] = new ArrayItem($valueNode);
            } else {
                $targetArray->items[] = new ArrayItem($valueNode, new String_($k));
            }
        }
    }

    public function push(string $key, mixed $value): void
    {
        $targetArray = $this->resolveArray($key);
        $targetArray->items[] = new ArrayItem($this->valueToNode($value));
    }

    /**
     * Mark a key to be commented out during render.
     *
     * Applied as string post-processing after format-preserving print.
     */
    public function comment(string $key): void
    {
        $this->keysToComment[] = $key;
    }

    public function has(string $key): bool
    {
        $segments = explode('.', $key);
        $array = $this->configArray;

        foreach ($segments as $i => $segment) {
            $item = $this->findItem($array, $segment);

            if ($item === null) {
                return false;
            }

            if ($i < count($segments) - 1) {
                if (! $item->value instanceof Array_) {
                    return false;
                }

                $array = $item->value;
            }
        }

        return true;
    }

    public function get(string $key): mixed
    {
        $segments = explode('.', $key);
        $array = $this->configArray;
        $item = null;

        foreach ($segments as $i => $segment) {
            $item = $this->findItem($array, $segment);

            if ($item === null) {
                return null;
            }

            if ($i < count($segments) - 1) {
                if (! $item->value instanceof Array_) {
                    return null;
                }

                $array = $item->value;
            }
        }

        return $item !== null ? $this->nodeToValue($item->value) : null;
    }

    public function render(): string
    {
        $printer = new Standard;
        $result = $printer->printFormatPreserving(
            $this->newStmts,
            $this->oldStmts,
            $this->oldTokens,
        );

        foreach ($this->keysToComment as $key) {
            $result = $this->commentOutKey($result, $key);
        }

        return $result;
    }

    private function findReturnArray(array $stmts): Array_
    {
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Return_ && $stmt->expr instanceof Array_) {
                return $stmt->expr;
            }
        }

        throw new RuntimeException('Config file must contain a return statement with an array');
    }

    private function findItem(Array_ $array, string $key): ?ArrayItem
    {
        foreach ($array->items as $item) {
            if ($item !== null && $this->itemMatchesKey($item, $key)) {
                return $item;
            }
        }

        return null;
    }

    private function itemMatchesKey(ArrayItem $item, string $key): bool
    {
        return $item->key instanceof String_ && $item->key->value === $key;
    }

    /**
     * Navigate to the Array_ at the given dot-notated key path,
     * creating intermediate arrays as needed.
     */
    private function resolveArray(string $key): Array_
    {
        $segments = explode('.', $key);
        $array = $this->configArray;

        foreach ($segments as $segment) {
            $item = $this->findItem($array, $segment);

            if ($item === null) {
                $newArray = new Array_([], ['kind' => Array_::KIND_SHORT]);
                $array->items[] = new ArrayItem($newArray, new String_($segment));
                $array = $newArray;
            } elseif ($item->value instanceof Array_) {
                $array = $item->value;
            } else {
                throw new RuntimeException(
                    "Expected array at key '{$segment}', got ".$item->value::class,
                );
            }
        }

        return $array;
    }

    private function valueToNode(mixed $value): Node\Expr
    {
        return match (true) {
            is_bool($value) => new ConstFetch(new Name($value ? 'true' : 'false')),
            is_null($value) => new ConstFetch(new Name('null')),
            is_int($value) => new Int_($value),
            is_float($value) => new Float_($value),
            is_string($value) => new String_($value),
            is_array($value) => $this->arrayToNode($value),
            default => throw new RuntimeException('Unsupported value type: '.get_debug_type($value)),
        };
    }

    private function arrayToNode(array $value): Array_
    {
        $items = [];
        $isList = array_is_list($value);

        foreach ($value as $k => $v) {
            $keyNode = $isList ? null : (is_int($k) ? new Int_($k) : new String_($k));
            $items[] = new ArrayItem($this->valueToNode($v), $keyNode);
        }

        return new Array_($items, ['kind' => Array_::KIND_SHORT]);
    }

    private function nodeToValue(Node\Expr $node): mixed
    {
        return match (true) {
            $node instanceof String_ => $node->value,
            $node instanceof Int_ => $node->value,
            $node instanceof Float_ => $node->value,
            $node instanceof ConstFetch => match (strtolower($node->name->toString())) {
                'true' => true,
                'false' => false,
                'null' => null,
                default => $node->name->toString(),
            },
            $node instanceof Array_ => $this->arrayNodeToValue($node),
            default => null,
        };
    }

    private function arrayNodeToValue(Array_ $node): array
    {
        $result = [];

        foreach ($node->items as $item) {
            if ($item === null) {
                continue;
            }

            $key = $item->key !== null ? $this->nodeToValue($item->key) : null;
            $val = $this->nodeToValue($item->value);

            if ($key !== null) {
                $result[$key] = $val;
            } else {
                $result[] = $val;
            }
        }

        return $result;
    }

    /**
     * Comment out a key-value pair in the rendered PHP code.
     *
     * Tracks bracket/parenthesis depth to correctly handle
     * multi-line array values.
     */
    private function commentOutKey(string $code, string $key): string
    {
        $segments = explode('.', $key);
        $targetKey = end($segments);
        $escaped = preg_quote($targetKey, '/');

        $pattern = '/^(\s*)[\'"]'.$escaped.'[\'"]\s*=>/m';

        if (! preg_match($pattern, $code, $matches, \PREG_OFFSET_CAPTURE)) {
            return $code;
        }

        /** @var int $startPos */
        $startPos = $matches[0][1];

        $lineStart = strrpos(substr($code, 0, $startPos), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;

        $depth = 0;
        $endPos = null;

        for ($i = $startPos; $i < strlen($code); $i++) {
            $char = $code[$i];

            if ($char === '[' || $char === '(') {
                $depth++;
            } elseif ($char === ']' || $char === ')') {
                if ($depth === 0) {
                    $endPos = $i - 1;
                    break;
                }

                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                $endPos = $i;
                break;
            }
        }

        if ($endPos === null) {
            $endPos = strlen($code) - 1;
        }

        $lineEnd = strpos($code, "\n", $endPos);

        if ($lineEnd === false) {
            $lineEnd = strlen($code);
        }

        $originalLines = substr($code, $lineStart, $lineEnd - $lineStart);
        $commentedLines = preg_replace('/^(\s*)/m', '$1// ', $originalLines);

        return substr($code, 0, $lineStart).$commentedLines.substr($code, $lineEnd);
    }
}
