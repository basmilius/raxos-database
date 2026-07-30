<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use Raxos\Collection\ArrayList;
use Raxos\Contract\Collection\ArrayListInterface;
use Raxos\Contract\Database\Orm\{ModelInterface, VisibilityInterface};

/**
 * Class ModelArrayList
 *
 * @template TKey of array-key
 * @template TValue of ModelInterface
 * @implements ArrayListInterface<TKey, TValue>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.17
 */
class ModelArrayList extends ArrayList implements VisibilityInterface
{

    /**
     * Returns a list of read-only views of the models, sharing their backbones.
     * Safe to expose to untrusted consumers such as template engines.
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    public function readonly(): static
    {
        return $this->map(static fn(ModelInterface $model) => $model->readonly());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function makeHidden(array|string $keys): static
    {
        return $this->map(static fn(ModelInterface $model) => $model->makeHidden($keys));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function makeVisible(array|string $keys): static
    {
        return $this->map(static fn(ModelInterface $model) => $model->makeVisible($keys));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function only(array|string $keys): static
    {
        return $this->map(static fn(ModelInterface $model) => $model->only($keys));
    }

}
