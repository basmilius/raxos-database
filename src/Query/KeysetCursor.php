<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use BackedEnum;
use JsonException;
use Raxos\Database\Query\Error\InvalidCursorException;
use Stringable;
use function array_is_list;
use function base64_decode;
use function base64_encode;
use function count;
use function is_array;
use function is_finite;
use function is_float;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function rtrim;
use function strlen;
use function strtr;
use const JSON_THROW_ON_ERROR;

/**
 * Class KeysetCursor
 *
 * Opaque position bound to the query and order. It is not an authorization token.
 *
 * @internal
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 3.3.0
 */
final class KeysetCursor
{
    /**
     * Validates the cursor version, size and query fingerprint before returning its boundary values.
     *
     * @param string $cursor
     * @param string $signature
     * @param int $size
     * @return list<string|int|float>
     * @throws InvalidCursorException
     * @author Bas Milius <bas@mili.us>
     * @since 3.3.0
     */
    public static function decode(
        string $cursor,
        string $signature,
        int $size
    ): array
    {
        if (strlen($cursor) > 8192) {
            throw new InvalidCursorException('The cursor is too large.');
        }

        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);

        try {
            $data = $decoded === false ? null : json_decode($decoded, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidCursorException('The cursor is malformed.');
        }

        $matchesQuery = is_array($data) && ($data['version'] ?? null) === 1 && ($data['query'] ?? null) === $signature;
        $values = is_array($data) ? ($data['values'] ?? null) : null;
        $matchesOrder = is_array($values) && array_is_list($values) && count($values) === $size;

        if (!$matchesQuery || !$matchesOrder) {
            throw new InvalidCursorException('The cursor does not match the query or order.');
        }

        foreach ($data['values'] as $value) {
            self::value($value);
        }

        return $data['values'];
    }

    /**
     * Encodes scalar boundary values with a query fingerprint; the cursor is not an authorization token.
     *
     * @param string $signature
     * @param list<string|int|float> $values
     * @return string
     * @throws InvalidCursorException|JsonException
     * @author Bas Milius <bas@mili.us>
     * @since 3.3.0
     */
    public static function encode(
        string $signature,
        array $values
    ): string
    {
        $payload = json_encode([
            'version' => 1,
            'query' => $signature,
            'values' => $values
        ], JSON_THROW_ON_ERROR);
        $cursor = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');

        if (strlen($cursor) > 8192) {
            throw new InvalidCursorException('The cursor is too large.');
        }

        return $cursor;
    }

    /**
     * Sort keys must be non-null scalar values; a trailing unique key is required.
     *
     * @param mixed $value
     * @return string|int|float
     * @throws InvalidCursorException
     * @author Bas Milius <bas@mili.us>
     * @since 3.3.0
     */
    public static function value(mixed $value): string|int|float
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        } elseif ($value instanceof Stringable) {
            $value = (string)$value;
        }

        if ((!is_string($value) && !is_int($value) && !is_float($value)) || (is_float($value) && !is_finite($value))) {
            throw new InvalidCursorException('Sort columns must be selected and contain non-null scalar values.');
        }

        return $value;
    }
}
