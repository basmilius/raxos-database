<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use ArrayAccess;
use IteratorAggregate;
use Raxos\Foundation\Collection\ArrayList;

/**
 * Class ModelArrayList
 *
 * @template TModel of Model
 * @extends ArrayList<int, TModel>
 * @implements ArrayAccess<int, TModel>
 * @implements IteratorAggregate<int, TModel>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 13-08-2024
 */
class ModelArrayList extends ArrayList implements VisibilityInterface
{

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 13-08-2024
     */
    public function makeHidden(array|string $keys): static
    {
        return $this->mapTransform(static fn(Model $model) => $model->makeHidden($keys));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 13-08-2024
     */
    public function makeVisible(array|string $keys): static
    {
        return $this->mapTransform(static fn(Model $model) => $model->makeVisible($keys));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 13-08-2024
     */
    public function only(array|string $keys): static
    {
        return $this->mapTransform(static fn(Model $model) => $model->only($keys));
    }

}
