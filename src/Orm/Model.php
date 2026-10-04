<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use ArrayAccess;
use Raxos\Contract\Database\DatabaseExceptionInterface;
use Raxos\Contract\Database\Orm\{BackboneInterface, MutableModelInterface, OrmExceptionInterface, QueryableInterface};
use Raxos\Contract\Database\Query\{QueryExceptionInterface, QueryInterface};
use Raxos\Contract\DebuggableInterface;
use Raxos\Database\Orm\Definition\RelationDefinition;
use Raxos\Database\Orm\Error\MissingFunctionException;
use Raxos\Database\Orm\Structure\StructureGenerator;
use Raxos\Foundation\Access\{ArrayAccessible, ObjectAccessible};

/**
 * Class Model
 *
 * @mixin Queryable<static>
 * @property BackboneInterface<static> $backbone
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.0
 */
abstract class Model implements ArrayAccess, DebuggableInterface, MutableModelInterface, QueryableInterface
{

    use ArrayAccessible;
    use ObjectAccessible;
    use ModelReadable;
    use Queryable;

    /**
     * Model constructor.
     *
     * @param BackboneInterface<static>|null $backbone
     *
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        ?BackboneInterface $backbone = null
    )
    {
        $this->backbone = $backbone ?? new Backbone(StructureGenerator::for(static::class), [], true);
        $this->backbone->addInstance($this);
    }

    /**
     * Deletes the model record from the database.
     *
     * @return void
     * @throws DatabaseExceptionInterface
     * @throws OrmExceptionInterface
     * @throws QueryExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function destroy(): void
    {
        $previous = $this->backbone->currentInstance;
        $this->backbone->currentInstance = $this;

        try {
            $this->backbone->destroy();
        } finally {
            $this->backbone->currentInstance = $previous;
        }
    }

    /**
     * Saves the model.
     *
     * @return void
     * @throws DatabaseExceptionInterface
     * @throws OrmExceptionInterface
     * @throws QueryExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function save(): void
    {
        $previous = $this->backbone->currentInstance;
        $this->backbone->currentInstance = $this;

        try {
            $this->backbone->save();
        } finally {
            $this->backbone->currentInstance = $previous;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    public function readonly(): ReadonlyModel
    {
        return new ReadonlyModel($this->backbone, $this->hidden, $this->only, $this->visible);
    }

    /**
     * {@inheritdoc}
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function setValue(string $key, mixed $value): void
    {
        $previous = $this->backbone->currentInstance;
        $this->backbone->currentInstance = $this;

        try {
            $this->backbone->setValue($key, $value);
        } finally {
            $this->backbone->currentInstance = $previous;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function unsetValue(string $key): void
    {
        $previous = $this->backbone->currentInstance;
        $this->backbone->currentInstance = $this;

        try {
            $this->backbone->unsetValue($key);
        } finally {
            $this->backbone->currentInstance = $previous;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @author Bas Milius <bas@mili.us>
     * @since 3.1.0
     */
    protected function createVisibilityClone(): static
    {
        return $this->backbone->createInstance();
    }

    /**
     * Queries a relation.
     *
     * @param string $name
     * @param array $arguments
     *
     * @return QueryInterface
     * @throws DatabaseExceptionInterface
     * @throws OrmExceptionInterface
     * @throws QueryExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __call(string $name, array $arguments): QueryInterface
    {
        $property = $this->backbone->structure->getProperty($name);

        if ($property instanceof RelationDefinition) {
            $relation = $this->backbone->structure->getRelation($property);

            return $relation->query($this);
        }

        throw new MissingFunctionException(static::class, $name);
    }

    /**
     * {@inheritdoc}
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function getQueryableColumns(array $columns): array
    {
        return $columns;
    }

    /**
     * {@inheritdoc}
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function getQueryableJoins(QueryInterface $query): QueryInterface
    {
        return $query;
    }

}
