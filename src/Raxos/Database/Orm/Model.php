<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use Raxos\Database\Error\DatabaseException;
use Raxos\Database\Orm\Definition\ColumnDefinition;
use Raxos\Database\Query\QueryInterface;
use Raxos\Foundation\Access\{ArrayAccessible, ObjectAccessible};
use function array_diff_key;
use function array_key_exists;
use function array_merge_recursive;
use function implode;
use function is_array;
use function sprintf;

/**
 * Class Model
 *
 * @implements ModelInterface<static>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.0
 */
abstract class Model implements ModelInterface
{

    use ArrayAccessible;
    use ObjectAccessible;
    use ModelDatabaseAccess;

    /**
     * @var ModelBackbone<static>
     * @internal
     * @private
     */
    public readonly ModelBackbone $backbone;

    private array $hidden = [];
    private array $visible = [];

    /**
     * ModelBase constructor.
     *
     * @param ModelBackbone|null $backbone
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        ?ModelBackbone $backbone = null
    )
    {
        $this->backbone = $backbone ?? new ModelBackbone(static::class, [], true);
        $this->backbone->addInstance($this);
    }

    /**
     * ModelBase destructor.
     */
    public function __destruct()
    {
        $this->backbone->removeInstance($this);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function clone(): static
    {
        return $this->backbone->createInstance();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function destroy(): void
    {
        static::delete($this->getPrimaryKeyValues());
        static::cache()->remove($this);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function save(): void
    {
        $this->backbone->save($this);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function makeHidden(array|string $keys): static
    {
        $keys = InternalHelper::normalizeFieldsArray($keys);

        $clone = $this->clone();
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
        $keys = InternalHelper::normalizeFieldsArray($keys);

        $clone = $this->clone();
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
        $keys = InternalHelper::normalizeFieldsArray($keys);
        $visible = [];
        $hidden = [];

        foreach (InternalModelData::getFields(static::class) as $definition) {
            if (array_key_exists($definition->key, $keys)) {
                $visible[$definition->key] = $keys[$definition->key];
            } else {
                $hidden[$definition->key] = null;
            }
        }

        $clone = $this->clone();
        $clone->hidden = $hidden;
        $clone->visible = $visible;

        return $clone;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function getValue(string $key): mixed
    {
        return $this->backbone->getValue($this, $key);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function hasValue(string $key): bool
    {
        return $this->backbone->hasValue($this, $key);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function setValue(string $key, mixed $value): void
    {
        $this->backbone->setValue($this, $key, $value);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function unsetValue(string $key): void
    {
        $this->backbone->unsetValue($this, $key);
    }

    /**
     * {@inheritdoc}
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function toArray(): array
    {
        $result = [];

        foreach (InternalModelData::getFields(static::class) as $definition) {
            $fieldKey = $definition->alias ?? $definition->name;
            $isVisible = InternalHelper::isVisible(
                $definition,
                array_key_exists($definition->key, $this->visible),
                array_key_exists($definition->key, $this->hidden)
            );

            if (!$isVisible) {
                continue;
            }

            $value = $this->getValue($fieldKey);
            $only = $this->visible[$definition->key] ?? null;

            if ($definition instanceof ColumnDefinition && $definition->visibleOnly !== null) {
                $only = array_merge_recursive($definition->visibleOnly, $this->visible[$fieldKey] ?? []);
            }

            if ($only !== null) {
                if ($value instanceof self) {
                    $value = $value->only($only)->toArray();
                } elseif ($value instanceof ModelArrayList) {
                    $value = $value->map(fn(self $v) => $v->only($only)->toArray());
                }
            }

            $result[$fieldKey] = $value;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __call(string $name, array $arguments): QueryInterface
    {
        return $this->queryRelation($name);
    }

    /**
     * {@inheritdoc}
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __debugInfo(): array
    {
        return $this->toArray();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __toString(): string
    {
        $primaryKeyValues = $this->getPrimaryKeyValues();

        if (is_array($primaryKeyValues)) {
            $primaryKeyValues = implode(', ', $primaryKeyValues);
        }

        return sprintf('%s(%s)', static::class, $primaryKeyValues);
    }

    /**
     * Gets the fields that should be selected by default.
     *
     * @param array|string|int $fields
     *
     * @return array|string|int
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @noinspection PhpDocRedundantThrowsInspection
     */
    protected static function getDefaultFields(array|string|int $fields): array|string|int
    {
        return $fields;
    }

    /**
     * Gets the joins that should be added to every select query.
     *
     * @param QueryInterface $query
     *
     * @return QueryInterface
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @noinspection PhpDocRedundantThrowsInspection
     */
    protected static function getDefaultJoins(QueryInterface $query): QueryInterface
    {
        return $query;
    }

}

//$data = [];
//$instance = $this;
//
//if ($only !== null) {
//    $instance = $this->only($only);
//}
//
//foreach (InternalModelData::getColumns(static::class) as $def) {
//    $key = $def->alias ?? $def->name;
//
//    if ($instance->isHidden($def->name)) {
//        continue;
//    }
//
//    if (InternalModelData::isRelation($def)) {
//        if ($instance->isVisible($def->name)) {
//            $data[$key] = InternalModelData::getRelation(static::class, $def)
//                ->fetch($instance);
//        }
//    } elseif ($instance->isNew && $def->isPrimary) {
//        $data[$key] = null;
//    } elseif ($instance->hasValue($def->key)) {
//        $data[$key] = $instance->{$def->key};
//    }
//
//    if ($def->visibleOnly !== null && array_key_exists($def->name, $data)) {
//        if ($data[$key] instanceof self) {
//            $data[$key] = $data[$key]->toArray($def->visibleOnly);
//        } elseif ($data[$key] instanceof ModelArrayList) {
//            $data[$key] = $data[$key]->map(fn(self $model) => $model->toArray($def->visibleOnly));
//        } elseif (is_array($data[$key])) {
//            $data[$key] = ArrayUtil::only($data[$key], $def->visibleOnly);
//        }
//    }
//
//    if ($only !== null && array_key_exists($key, $only) && $data[$key] instanceof self) {
//        $data[$key] = $data[$key]->toArray($only[$key]);
//    }
//}
//
//foreach (InternalModelData::getMacros(static::class) as $def) {
//    if ($instance->isHidden($def->name) || !$instance->isVisible($def->name)) {
//        continue;
//    }
//
//    $data[$def->alias ?? $def->name] = $instance->callMacro($def);
//}
//
//$instance->onPublish($data);
//
//return $data;

//    /**
//     * {@inheritdoc}
//     * @author Bas Milius <bas@mili.us>
//     * @since 1.0.0
//     */
//    public function __serialize(): array
//    {
//        $relations = [];
//
//        foreach (InternalModelData::getColumns(static::class) as $field) {
//            $name = $field->name;
//
//            if ($this->isHidden($name) || !$this->isVisible($name)) {
//                continue;
//            }
//
//            if ($this->{$field->key} === null) {
//                continue;
//            }
//
//            $relations[$name] = $this->{$field->key};
//        }
//
//        return [
//            $this->__data,
//            $this->hidden,
//            $this->visible,
//            $this->isNew,
//            $this->castedFields,
//            $relations
//        ];
//    }
//
//    /**
//     * {@inheritdoc}
//     * @throws DatabaseException
//     * @author Bas Milius <bas@mili.us>
//     * @since 1.0.0
//     */
//    public function __unserialize(array $data): void
//    {
//        InternalModelData::initialize(static::class);
//
//        [
//            $this->__data,
//            $this->hidden,
//            $this->visible,
//            $this->isNew,
//            $this->castedFields,
//            $relations
//        ] = $data;
//
//        $pk = $this->getPrimaryKeyValues();
//
//        if (static::cache()->has(static::class, $pk)) {
//            $this->__master = static::cache()->get(static::class, $pk);
//            $this->castedFields = &$this->__master->castedFields;
//            $this->__data = &$this->__master->__data;
//            $this->isNew = &$this->__master->isNew;
//        } else {
//            $this->__master = null;
//            static::cache()->set($this);
//        }
//
//        foreach ($relations as $relation) {
//            if ($relation instanceof ModelArrayList) {
//                foreach ($relation as $r) {
//                    $r::cache()->set($r);
//                }
//            } elseif (isset($relation->__data)) {
//                $relation::cache()->set($relation);
//            }
//        }
//    }
