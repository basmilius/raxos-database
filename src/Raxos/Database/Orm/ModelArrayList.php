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
 * @template TValue of Model
 * @extends ArrayList<TKey, TValue>
 * @implements ArrayAccess<TKey, TValue>
 * @implements IteratorAggregate<TKey, TValue>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.0
 */
class ModelArrayList extends ArrayList implements MarkVisibilityInterface
{

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function makeHidden(string|array $keys): static
    {
        return $this->mapTransform(static fn(Model $model) => $model->makeHidden($keys));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function makeVisible(string|array $keys): static
    {
        return $this->mapTransform(static fn(Model $model) => $model->makeVisible($keys));
    }

}
