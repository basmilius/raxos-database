<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Cast;

/**
 * Class BooleanCast
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Cast
 * @since 1.0.0
 */
class BooleanCast implements CastInterface
{

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function decode(int|string|null $value): bool
    {
        return $value === 1 || $value === '1';
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function encode(mixed $value): string|int|null
    {
        return $value ? 1 : 0;
    }
}
