<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use Raxos\Collection\ArrayList;
use Raxos\Contract\Collection\ArrayListInterface;
use Raxos\Contract\Database\Orm\VisibilityInterface;
use Raxos\Contract\ProxyableInterface;

/**
 * Class ModelArrayList
 *
 * @template TKey of array-key
 * @template TValue of Model
 * @implements ArrayListInterface<TKey, TValue>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.17
 */
class ModelArrayList extends ArrayList implements ProxyableInterface, VisibilityInterface
{

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.4.0
     */
    public function proxy(): ModelArrayListProxy
    {
        return new ModelArrayListProxy($this);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function makeHidden(array|string $keys): static
    {
        return $this->map(static fn(Model $model) => $model->makeHidden($keys));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function makeVisible(array|string $keys): static
    {
        return $this->map(static fn(Model $model) => $model->makeVisible($keys));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function only(array|string $keys): static
    {
        return $this->map(static fn(Model $model) => $model->only($keys));
    }

}
