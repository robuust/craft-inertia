<?php

declare(strict_types=1);

namespace Robuust\CraftInertia\Content;

use InvalidArgumentException;
use JsonSerializable;
use stdClass;

/** Validates extension output before it reaches the adapter or SSR gateway. */
final class PublicValue
{
    /**
     * @param mixed $value Explicitly serialized public data.
     * @param string $path Diagnostic location for invalid values.
     * @param int $depth Current JSON nesting depth.
     * @return mixed Finite JSON-compatible data without implicit object dumping.
     */
    public static function normalize(mixed $value, string $path, int $depth = 0): mixed
    {
        if ($depth > 20) {
            throw new InvalidArgumentException("$path exceeds twenty nested JSON levels.");
        }
        if ($value instanceof JsonSerializable) {
            return self::normalize($value->jsonSerialize(), $path, $depth + 1);
        }
        if ($value instanceof stdClass) {
            return (object) self::normalize((array) $value, $path, $depth + 1);
        }
        if ((!is_null($value) && !is_scalar($value) && !is_array($value)) || (is_float($value) && !is_finite($value))) {
            throw new InvalidArgumentException("$path must contain finite JSON scalars, arrays, stdClass, JsonSerializable values or null; got ".get_debug_type($value).'.');
        }
        if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
            throw new InvalidArgumentException("$path contains invalid UTF-8.");
        }
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (is_string($key) && !mb_check_encoding($key, 'UTF-8')) {
                    throw new InvalidArgumentException("$path contains an invalid UTF-8 key.");
                }
                $value[$key] = self::normalize($item, "$path.$key", $depth + 1);
            }
        }
        return $value;
    }
}
