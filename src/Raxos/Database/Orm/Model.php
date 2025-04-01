<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use JsonSerializable;
use Raxos\Database\Contract\QueryInterface;
use Raxos\Database\Error\{ConnectionException, ExecutionException, QueryException};
use Raxos\Database\Orm\Contract\{AccessInterface, BackboneInterface, QueryableInterface, VisibilityInterface};
use Raxos\Database\Orm\Definition\RelationDefinition;
use Raxos\Database\Orm\Error\{InstanceException, RelationException, StructureException};
use Raxos\Database\Orm\Structure\StructureHelper;
use Raxos\Database\Query\Select;
use Raxos\Foundation\Access\{ArrayAccessible, ObjectAccessible};
use Raxos\Foundation\Contract\{ArrayableInterface, DebuggableInterface};
use Stringable;
use function array_diff_key;
use function array_key_exists;
use function array_merge_recursive;
use function implode;
use function Raxos\Database\Query\literal;
use function sprintf;

/**
 * Class Model
 *
 * @mixin Queryable<static>
 *
 * @property BackboneInterface<static> $backbone
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.0
 */
abstract class Model implements AccessInterface, ArrayableInterface, DebuggableInterface, JsonSerializable, QueryableInterface, Stringable, VisibilityInterface
{

    use ArrayAccessible;
    use ObjectAccessible;
    use Queryable;

    public readonly BackboneInterface $backbone;

    private array $hidden = [];
    private array $visible = [];

    /**
     * Model constructor.
     *
     * @param BackboneInterface<static>|null $backbone
     *
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        ?BackboneInterface $backbone = null
    )
    {
        $this->backbone = $backbone ?? new Backbone(static::class, [], true);
        $this->backbone->addInstance($this);
    }

    /**
     * Model destructor.
     */
    public function __destruct()
    {
        $this->backbone->removeInstance($this);
    }

    /**
     * Deletes the model record from the database.
     *
     * @return void
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws InstanceException
     * @throws QueryException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function destroy(): void
    {
        $primaryKey = $this->backbone->getPrimaryKeyValues();

        $cache = $this->backbone->cache;
        $cache->unset(static::class, $primaryKey);

        if ($this->backbone->structure->softDeleteColumn !== null) {
            self::update($primaryKey, [
                $this->backbone->structure->softDeleteColumn => literal('now()')
            ]);
        } else {
            self::delete($primaryKey);
        }
    }

    /**
     * Saves the model.
     *
     * @return void
     * @throws ConnectionException
     * @throws ExecutionException
     * @throws InstanceException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function save(): void
    {
        $this->backbone->currentInstance = $this;
        $this->backbone->save();
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
        $visible = [];

        foreach ($this->backbone->structure->properties as $property) {
            if (array_key_exists($property->name, $keys)) {
                $visible[$property->name] = $keys[$property->name];
            } else {
                $hidden[$property->name] = null;
            }
        }

        $clone = $this->backbone->createInstance();
        $clone->hidden = $hidden;
        $clone->visible = $visible;

        return $clone;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function getValue(string $key): mixed
    {
        $this->backbone->currentInstance = $this;

        return $this->backbone->getValue($key);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function hasValue(string $key): bool
    {
        $this->backbone->currentInstance = $this;

        return $this->backbone->hasValue($key);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function setValue(string $key, mixed $value): void
    {
        $this->backbone->currentInstance = $this;
        $this->backbone->setValue($key, $value);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function unsetValue(string $key): void
    {
        $this->backbone->currentInstance = $this;
        $this->backbone->unsetValue($key);
    }

    /**
     * {@inheritdoc}
     * @throws InstanceException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * {@inheritdoc}
     * @throws InstanceException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function toArray(): array
    {
        $result = [];

        foreach ($this->backbone->structure->properties as $property) {
            $key = $property->alias ?? $property->name;
            $visible = StructureHelper::isVisible(
                $property,
                array_key_exists($property->name, $this->visible),
                array_key_exists($property->name, $this->hidden)
            );

            if (!$visible) {
                continue;
            }

            $only = $this->visible[$property->name] ?? null;
            $value = $this->getValue($property->name);

            if ($property instanceof RelationDefinition && $property->visibleOnly !== null) {
                $only = array_merge_recursive($property->visibleOnly, $only ?? []);
            }

            if ($only !== null) {
                if ($value instanceof self) {
                    $value = $value->only($only)->toArray();
                } else {
                    if ($value instanceof ModelArrayList) {
                        $value = $value->map(static fn(self $model) => $model->only($only)->toArray());
                    }
                }
            }

            $result[$key] = $value;
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
     * @throws ConnectionException
     * @throws InstanceException
     * @throws QueryException
     * @throws RelationException
     * @throws StructureException
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

        throw InstanceException::missingFunction(static::class, $name);
    }

    /**
     * {@inheritdoc}
     * @throws InstanceException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __debugInfo(): ?array
    {
        return $this->toArray();
    }

    /**
     * {@inheritdoc}
     * @throws InstanceException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __toString(): string
    {
        $values = $this->backbone->getPrimaryKeyValues();
        $values = implode(', ', $values);

        return sprintf('%s(%s)', $this->backbone->class, $values);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function getQueryableColumns(Select $select): Select
    {
        return $select;
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
