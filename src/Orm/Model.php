<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use JsonSerializable;
use Raxos\Contract\Collection\ArrayableInterface;
use Raxos\Contract\Database\DatabaseExceptionInterface;
use Raxos\Contract\Database\Orm\{AccessInterface, BackboneInterface, OrmExceptionInterface, QueryableInterface, VisibilityInterface};
use Raxos\Contract\Database\Query\{QueryExceptionInterface, QueryInterface};
use Raxos\Contract\{DebuggableInterface, ProxyableInterface};
use Raxos\Database\Orm\Definition\{EmbeddedDefinition, RelationDefinition};
use Raxos\Database\Orm\Error\MissingFunctionException;
use Raxos\Database\Orm\Structure\{StructureGenerator, StructureHelper};
use Raxos\Foundation\Access\{ArrayAccessible, ObjectAccessible};
use Stringable;
use function array_diff_key;
use function array_key_exists;
use function array_merge_recursive;
use function implode;
use function sprintf;

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
abstract class Model implements AccessInterface, ArrayableInterface, DebuggableInterface, JsonSerializable, ProxyableInterface, QueryableInterface, Stringable, VisibilityInterface
{

    use ArrayAccessible;
    use ObjectAccessible;
    use Queryable;

    public readonly BackboneInterface $backbone;

    private array $hidden = [];
    private array $only = [];
    private array $visible = [];

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
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function makeHidden(array|string $keys): static
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $keys = StructureHelper::normalizeKeys($keys, $this->backbone->structure);

        $clone = $this->backbone->createInstance();
        $clone->hidden = array_merge_recursive($this->hidden, $keys);
        $clone->visible = array_diff_key($this->visible, $clone->hidden);
        $clone->only = $this->only;

        return $clone;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function makeVisible(array|string $keys): static
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $keys = StructureHelper::normalizeKeys($keys, $this->backbone->structure);

        $clone = $this->backbone->createInstance();
        $clone->visible = array_merge_recursive($this->visible, $keys);
        $clone->hidden = array_diff_key($this->hidden, $clone->visible);
        $clone->only = $this->only;

        return $clone;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function only(array|string $keys): static
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $keys = StructureHelper::normalizeKeys($keys);

        $hidden = [];
        $only = [];
        $visible = [];

        foreach ($this->backbone->structure->properties as $property) {
            if (array_key_exists($property->name, $keys)) {
                // note: keep the property visible via the marker, but record a nested
                // sub-map in $only so it narrows the relation (restrictive), unlike a
                // nested makeVisible which reveals additively.
                $visible[$property->name] = null;

                if (is_array($keys[$property->name])) {
                    $only[$property->name] = $keys[$property->name];
                }
            } else {
                $hidden[$property->name] = null;
            }
        }

        $clone = $this->backbone->createInstance();
        $clone->hidden = $hidden;
        $clone->only = $only;
        $clone->visible = $visible;

        return $clone;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 2.4.0
     */
    public function proxy(): ModelProxy
    {
        return new ModelProxy($this);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function getValue(string $key): mixed
    {
        $previous = $this->backbone->currentInstance;
        $this->backbone->currentInstance = $this;

        try {
            return $this->backbone->getValue($key);
        } finally {
            $this->backbone->currentInstance = $previous;
        }
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function hasValue(string $key): bool
    {
        $previous = $this->backbone->currentInstance;
        $this->backbone->currentInstance = $this;

        try {
            return $this->backbone->hasValue($key);
        } finally {
            $this->backbone->currentInstance = $previous;
        }
    }

    /**
     * {@inheritdoc}
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
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * {@inheritdoc}
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function toArray(): array
    {
        $result = [];
        $previous = $this->backbone->currentInstance;
        $this->backbone->currentInstance = $this;
        $noVisibilityOverrides = empty($this->visible) && empty($this->hidden);

        try {
            foreach ($this->backbone->structure->properties as $property) {
                $key = $property->alias ?? $property->name;

                if ($noVisibilityOverrides) {
                    $visible = StructureHelper::isVisible($property, false, false);
                } else {
                    $visible = StructureHelper::isVisible(
                        $property,
                        array_key_exists($property->name, $this->visible),
                        array_key_exists($property->name, $this->hidden)
                    );
                }

                if (!$visible) {
                    continue;
                }

                if ($property instanceof EmbeddedDefinition) {
                    $value = $this->backbone->getValue($property->name);
                    $result[$key] = $value !== null ? self::embeddedToArray($property, $value) : null;

                    continue;
                }

                $whitelist = $property instanceof RelationDefinition ? $property->visibleOnly : null;
                $nestedOnly = $this->only[$property->name] ?? null;
                $nestedVisible = $this->visible[$property->name] ?? null;
                $nestedHidden = $this->hidden[$property->name] ?? null;
                $value = $this->backbone->getValue($property->name);

                // note: a #[Visible([...])] whitelist and a nested only() both narrow the
                // relation (restrictive); a nested makeVisible/makeHidden reveals or hides
                // individual fields on top of the relation's defaults. Applied in that order.
                if ($whitelist !== null || is_array($nestedOnly) || is_array($nestedVisible) || is_array($nestedHidden)) {
                    $apply = static function (self $model) use ($whitelist, $nestedOnly, $nestedVisible, $nestedHidden): array {
                        if ($whitelist !== null) {
                            $model = $model->only($whitelist);
                        }

                        if (is_array($nestedOnly)) {
                            $model = $model->only($nestedOnly);
                        }

                        if (is_array($nestedVisible)) {
                            $model = $model->makeVisible($nestedVisible);
                        }

                        if (is_array($nestedHidden)) {
                            $model = $model->makeHidden($nestedHidden);
                        }

                        return $model->toArray();
                    };

                    if ($value instanceof self) {
                        $value = $apply($value);
                    } elseif ($value instanceof ModelArrayList) {
                        $value = $value->map($apply);
                    }
                }

                $result[$key] = $value;
            }
        } finally {
            $this->backbone->currentInstance = $previous;
        }

        return $result;
    }

    /**
     * Converts an embedded value object to an associative array.
     *
     * @param EmbeddedDefinition $definition
     * @param object $value
     *
     * @return array
     * @author Bas Milius <bas@mili.us>
     * @since 2.2.0
     */
    private static function embeddedToArray(EmbeddedDefinition $definition, object $value): array
    {
        $result = [];

        foreach ($definition->columns as $column) {
            $columnKey = $column->alias ?? $column->name;
            $result[$columnKey] = $value->{$column->name};
        }

        foreach ($definition->embeddeds as $nested) {
            $nestedKey = $nested->alias ?? $nested->name;
            $nestedValue = $value->{$nested->name};
            $result[$nestedKey] = $nestedValue !== null ? self::embeddedToArray($nested, $nestedValue) : null;
        }

        return $result;
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
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __debugInfo(): array
    {
        return $this->toArray();
    }

    /**
     * {@inheritdoc}
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __toString(): string
    {
        $values = $this->backbone->getPrimaryKeyValues();

        if ($values === null) {
            return sprintf('%s(no_pk)', $this->backbone->class);
        }

        return sprintf('%s(%s)', $this->backbone->class, implode(', ', $values));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function getQueryableColumns(array $columns): array
    {
        return $columns;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function getQueryableJoins(QueryInterface $query): QueryInterface
    {
        return $query;
    }

}
