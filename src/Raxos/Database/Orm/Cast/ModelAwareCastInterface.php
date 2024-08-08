<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Cast;

use Raxos\Database\Orm\Model;

/**
 * Interface ModelAwareCastInterface
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Cast
 * @since 1.0.16
 */
interface ModelAwareCastInterface extends CastInterface
{

    /**
     * {@inheritdoc}
     *
     * @param Model|null $model
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function decode(float|int|string|null $value, ?Model $model = null): mixed;

    /**
     * {@inheritdoc}
     *
     * @param Model|null $model
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function encode(mixed $value, ?Model $model = null): string|float|int|null;

}
