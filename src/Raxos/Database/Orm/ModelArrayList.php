<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use Raxos\Foundation\Collection\ArrayList;

/**
 * Class ModelArrayList
 *
 * @template-covariant T
 * @extends ArrayList<T>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.0
 */
class ModelArrayList extends ArrayList
{

    /**
     * Marks the given fields as hidden for every model in the list.
     *
     * @param string|array $fields
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function makeHidden(string|array $fields): static
    {
        return $this->mapTransform(fn(Model $model) => $model->makeHidden($fields));
    }

    /**
     * Marks the given fields as visible for every model in the list.
     *
     * @param string|array $fields
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function makeVisible(string|array $fields): static
    {
        return $this->mapTransform(fn(Model $model) => $model->makeVisible($fields));
    }

}
