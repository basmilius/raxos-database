<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Caster;

use Raxos\Database\Orm\Model;

/**
 * Class FloatCaster
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Caster
 * @since 15-08-2024
 */
final readonly class FloatCaster implements CasterInterface
{

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 15-08-2024
     */
    public function decode(float|int|string|null $value, Model $instance): ?float
    {
        if ($value === null) {
            return null;
        }

        return (float)$value;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 15-08-2024
     */
    public function encode(mixed $value, Model $instance): string|float|int|null
    {
        if ($value === null) {
            return null;
        }

        return (string)$value;
    }

}
