<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Cast;

use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

/**
 * Class JsonCast
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Cast
 * @since 1.0.2
 */
final class JsonCast implements CastInterface
{

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function decode(string|float|int|null $value): mixed
    {
        if (!is_string($value) || (!str_starts_with($value, '{') && !str_starts_with($value, '['))) {
            return null;
        }

        return json_decode($value, true);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.2
     */
    public function encode(mixed $value): string|float|int|null
    {
        if (!is_array($value)) {
            return null;
        }

        return json_encode($value);
    }

}
