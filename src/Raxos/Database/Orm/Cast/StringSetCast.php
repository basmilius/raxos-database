<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Cast;

use function explode;
use function implode;
use function is_array;
use function is_string;

/**
 * Class StringSetCast
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Cast
 * @since 1.0.0
 */
class StringSetCast implements CastInterface
{

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function decode(int|string|null $value): array
    {
        if (!is_string($value)) {
            return [];
        }

        return explode(',', $value);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function encode(mixed $value): string|int|null
    {
        if (!is_array($value)) {
            return null;
        }

        return implode(',', $value);
    }

}
