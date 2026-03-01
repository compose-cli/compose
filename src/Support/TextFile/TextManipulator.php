<?php

declare(strict_types=1);

namespace Compose\Support\TextFile;

use RuntimeException;

class TextManipulator
{
    public static function replace(string $content, string $search, string $replace): string
    {
        return str_replace($search, $replace, $content);
    }

    public static function replaceRegex(string $content, string $pattern, string $replace): string
    {
        $result = @preg_replace($pattern, $replace, $content);

        if ($result === null) {
            throw new RuntimeException("Invalid regex pattern: {$pattern}");
        }

        return $result;
    }

    public static function prepend(string $content, string $text): string
    {
        return $text.$content;
    }

    public static function append(string $content, string $text): string
    {
        return $content.$text;
    }

    public static function insertAfter(string $content, string $marker, string $text): string
    {
        $pos = strpos($content, $marker);

        if ($pos === false) {
            return $content;
        }

        $insertAt = $pos + strlen($marker);

        return substr($content, 0, $insertAt).$text.substr($content, $insertAt);
    }

    public static function insertBefore(string $content, string $marker, string $text): string
    {
        $pos = strpos($content, $marker);

        if ($pos === false) {
            return $content;
        }

        return substr($content, 0, $pos).$text.substr($content, $pos);
    }
}
