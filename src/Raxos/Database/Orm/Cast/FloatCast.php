<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Cast;

/**
 * Class FloatCast
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Cast
 * @since 1.0.0
 */
class FloatCast implements CastInterface
{

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function decode(string|float|int|null $value): ?float
    {
        if ($value === null) {
            return null;
        }

        return (float)$value;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function encode(mixed $value): string|float|int|null
    {
        if ($value === null) {
            return null;
        }

        return (string)$value;
    }

}
