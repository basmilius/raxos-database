<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use BackedEnum;
use Generator;
use JetBrains\PhpStorm\ExpectedValues;
use Raxos\Database\Contract\{ConnectionInterface, QueryInterface, StructureInterface};
use Raxos\Database\Error\{ConnectionException, ExecutionException, QueryException};
use Raxos\Database\Orm\Contract\{AccessInterface, BackboneInterface, BackpackInterface, CacheInterface, MutationListenerInterface, WritableRelationInterface};
use Raxos\Database\Orm\Definition\{ColumnDefinition, MacroDefinition, RelationDefinition};
use Raxos\Database\Orm\Error\{InstanceException, RelationException, StructureException};
use Raxos\Foundation\Util\Singleton;
use function array_column;
use function array_find_key;
use function array_map;
use function array_shift;
use function in_array;
use function is_subclass_of;
use function iterator_to_array;
use function Raxos\Database\Query\literal;

/**
 * Class Backbone
 *
 * @template TModel of Model
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.17
 */
final class Backbone implements AccessInterface, BackboneInterface
{

    public readonly CacheInterface $cache;
    public readonly ConnectionInterface $connection;
    public readonly string $class;

    public readonly BackpackInterface $data;
    public readonly BackpackInterface $castCache;
    public readonly BackpackInterface $macroCache;
    public readonly BackpackInterface $relationCache;

    public ?Model $currentInstance = null;

    private array $modified = [];
    private array $saveTasks = [];

    /**
     * Backbone constructor.
     *
     * @param StructureInterface $structure
     * @param array $data
     * @param bool $isNew
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public readonly StructureInterface $structure,
        array $data,
        public bool $isNew = false
    )
    {
        $this->class = $this->structure->class;
        $this->connection = $this->structure->connection;
        $this->cache = $this->connection->cache;

        $this->data = new Backpack($data);
        $this->castCache = new Backpack();
        $this->macroCache = new Backpack();
        $this->relationCache = new Backpack();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function addInstance(Model $instance): void
    {
        foreach ($this->structure->properties as $property) {
            unset($instance->{$property->name});
        }
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function createInstance(): Model
    {
        return new $this->class(backbone: $this);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function addSaveTask(callable $fn): void
    {
        $this->saveTasks[] = $fn;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function runSaveTasks(): void
    {
        while (($saveTask = array_shift($this->saveTasks)) !== null) {
            $saveTask();
        }
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function getCastedValue(string $caster, #[ExpectedValues(['decode', 'encode'])] string $mode, mixed $value): mixed
    {
        return Singleton::get($caster)->{$mode}($value, $this->currentInstance);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function getColumnValue(ColumnDefinition $property): mixed
    {
        if ($property->defaultValue !== null && !$this->data->hasValue($property->key)) {
            $value = $property->defaultValue;
        } else {
            $value = $this->data->getValue($property->key);

            if ($property->caster !== null) {
                if ($this->castCache->hasValue($property->name)) {
                    return $this->castCache->getValue($property->name);
                }

                $value = $this->getCastedValue($property->caster, 'decode', $value);
                $this->castCache->setValue($property->name, $value);
            }

            if ($value === null && $property->nullable) {
                return null;
            }

            if ($property->enumClass !== null) {
                return $property->enumClass::tryFrom($value);
            }
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function getMacroValue(MacroDefinition $property): mixed
    {
        if ($this->macroCache->hasValue($property->name)) {
            return $this->macroCache->getValue($property->name);
        }

        $callback = $property->callback;
        $result = $callback($this->currentInstance);

        if ($property->isCached) {
            $this->macroCache->setValue($property->name, $result);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function getRelationValue(RelationDefinition $property): Model|ModelArrayList|null
    {
        if ($this->relationCache->hasValue($property->name)) {
            return $this->relationCache->getValue($property->name);
        }

        $result = $this->structure
            ->getRelation($property)
            ->fetch($this->currentInstance);

        $this->relationCache->setValue($property->name, $result);

        return $result;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function setColumnValue(ColumnDefinition $property, mixed $value): void
    {
        if ($property->isPrimaryKey && !$this->isNew) {
            throw InstanceException::immutablePrimaryKey($this->class, $property->name);
        }

        if ($property->isImmutable && !$this->isNew) {
            throw InstanceException::immutable($this->class, $property->name);
        }

        if ($property->caster !== null) {
            $this->castCache->unsetValue($property->name);
            $value = $this->getCastedValue($property->caster, 'encode', $value);
        }

        if (is_subclass_of($property->types[0], BackedEnum::class)) {
            $value = $value?->value;
        }

        $this->modified[] = $property->name;
        $this->data->setValue($property->key, $value);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function setRelationValue(RelationDefinition $property, mixed $value): void
    {
        $relation = $this->structure->getRelation($property);

        if (!($relation instanceof WritableRelationInterface)) {
            throw InstanceException::immutableRelation($this->class, $property->name);
        }

        $relation->write($this->currentInstance, $property, $value);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function getPrimaryKeyValues(): array|null
    {
        $properties = $this->structure->primaryKey;

        if ($properties === null) {
            return null;
        }

        return array_map(fn(ColumnDefinition $property) => $this->getValue($property->name), $properties);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function isModified(?string $key = null): bool
    {
        if (empty($this->modified)) {
            return false;
        }

        if ($key !== null && !in_array($key, $this->modified, true)) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function queryRelation(Model $instance, string $key): QueryInterface
    {
        $property = $this->structure->getProperty($key);

        if (!($property instanceof RelationDefinition)) {
            throw StructureException::invalidRelation($this->class, $property->name);
        }

        return $this->structure
            ->getRelation($property)
            ->query($instance);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function reload(): void
    {
        $record = $this->class::select()
            ->withoutModel()
            ->wherePrimaryKey($this->class, $this->getPrimaryKeyValues())
            ->single();

        $this->data->replaceWith($record);

        $this->castCache->clear();
        $this->macroCache->clear();
        $this->relationCache->clear();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.19
     */
    public function save(): void
    {
        $primaryKey = $this->structure->primaryKey;
        $values = iterator_to_array($this->getSaveableValues());

        if (empty($values)) {
            return;
        }

        if ($this->isNew) {
            // note(Bas): saves a new record for the model.
            $primaryKeyValue = $this->class::query()
                ->insertIntoValues($this->structure->table, $values)
                ->conditional($this->structure->onDuplicateKeyUpdate !== null, fn(QueryInterface $query) => $query
                    ->onDuplicateKeyUpdate($this->structure->onDuplicateKeyUpdate))
                ->runReturning(array_column($primaryKey, 'key'));

            $record = $this->class::select()
                ->withoutModel()
                ->wherePrimaryKey($this->class, $primaryKeyValue)
                ->single();

            $this->data->replaceWith($record);
            $this->isNew = false;

            $this->cache->set($this->class, $primaryKeyValue, $this->currentInstance);
        } else {
            // note(Bas): saves the modified fields of an existing record.
            $primaryKey = array_map(fn(ColumnDefinition $property) => $this->getValue($property->name), $primaryKey);
            $this->class::update($primaryKey, $values);
        }

        $this->runSaveTasks();

        // todo(Bas): This should be improved. It would be nice to check if any
        //  properties that are related to these caches are updated and clear
        //  them only if needed. For example; we wouldn't want to clear the
        //  relation cache for a model when non of the relation related
        //  properties are updated.
        $this->castCache->clear();
        $this->macroCache->clear();
        $this->relationCache->clear();

        $this->modified = [];
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function getValue(string $key): mixed
    {
        $property = $this->structure->getProperty($key);

        try {
            return match (true) {
                $property instanceof ColumnDefinition => $this->getColumnValue($property),
                $property instanceof MacroDefinition => $this->getMacroValue($property),
                $property instanceof RelationDefinition => $this->getRelationValue($property)
            };
        } catch (ConnectionException|ExecutionException|QueryException|RelationException $err) {
            throw InstanceException::readFailed($this->class, $property->name, $err);
        }
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function hasValue(string $key): bool
    {
        return $this->structure->hasProperty($key);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function setValue(string $key, mixed $value): void
    {
        $property = $this->structure->getProperty($key);
        $oldValue = !$this->isNew && $this->currentInstance instanceof MutationListenerInterface
            ? $this->getValue($property->name)
            : null;

        try {
            match (true) {
                $property instanceof ColumnDefinition => $this->setColumnValue($property, $value),
                $property instanceof MacroDefinition => throw InstanceException::immutableMacro($this->class, $property->name),
                $property instanceof RelationDefinition => $this->setRelationValue($property, $value)
            };

            if (!$this->isNew && $this->currentInstance instanceof MutationListenerInterface) {
                $this->currentInstance->onMutation($property, $value, $oldValue);
            }
        } catch (QueryException|RelationException $err) {
            throw InstanceException::writeFailed($this->class, $property->name, $err);
        }
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function unsetValue(string $key): void
    {
        $property = $this->structure->getProperty($key);

        if ($property instanceof MacroDefinition) {
            throw InstanceException::immutableMacro($this->class, $property->name);
        }

        if ($property instanceof RelationDefinition) {
            throw InstanceException::immutableRelation($this->class, $property->name);
        }

        $this->data->unsetValue($key);
    }

    /**
     * Gets the saveable columns with their values.
     *
     * @return Generator<string, mixed>
     * @throws InstanceException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.19
     */
    private function getSaveableValues(): Generator
    {
        foreach ($this->structure->properties as $property) {
            if (!($property instanceof ColumnDefinition)) {
                continue;
            }

            if (!$this->isNew && !in_array($property->name, $this->modified, true)) {
                continue;
            }

            if ($this->data->hasValue($property->key)) {
                $value = $this->getValue($property->name);

                if ($value instanceof BackedEnum) {
                    $value = $value->value;
                }

                if ($property->caster !== null) {
                    $value = $this->getCastedValue($property->caster, 'encode', $value);
                }
            } elseif ($property->isComputed || $property->isPrimaryKey) {
                continue;
            } else {
                $value = literal('default');
            }

            yield $property->key => $value;
        }

        $polymorphic = $this->structure->parent?->polymorphic ?? $this->structure->polymorphic;

        if ($polymorphic === null || $this->data->hasValue($polymorphic->column)) {
            return;
        }

        yield $polymorphic->column => array_find_key($polymorphic->map, fn(string $class) => $this->class);
    }

}
