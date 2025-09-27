<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Caster;

use Raxos\Contract\Database\Orm\CasterInterface;
use Raxos\Database\Orm\Model;

/**
 * Class BooleanCaster
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Caster
 * @since 1.0.17
 */
final readonly class BooleanCaster implements CasterInterface
{

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function decode(float|int|string|null $value, Model $instance): bool
    {
        return $value === 1 || $value === '1';
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function encode(mixed $value, Model $instance): string|float|int|null
    {
        return $value ? 1 : 0;
    }

}
