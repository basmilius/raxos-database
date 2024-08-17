<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use ArrayAccess;
use IteratorAggregate;
use Raxos\Foundation\Collection\ArrayList;

/**
 * Class ModelArrayList
 *
 * @template TKey of array-key
 * @template TModel of Model
 * @extends ArrayList<TKey, TModel>
 * @implements ArrayAccess<TKey, TModel>
 * @implements IteratorAggregate<TKey, TModel>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.17
 */
class ModelArrayList extends ArrayList implements VisibilityInterface
{

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function makeHidden(array|string $keys): static
    {
        return $this->mapTransform(static fn(Model $model) => $model->makeHidden($keys));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function makeVisible(array|string $keys): static
    {
        return $this->mapTransform(static fn(Model $model) => $model->makeVisible($keys));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function only(array|string $keys): static
    {
        return $this->mapTransform(static fn(Model $model) => $model->only($keys));
    }

}
