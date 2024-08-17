<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use BackedEnum;
use JetBrains\PhpStorm\ExpectedValues;
use Raxos\Database\Error\{ConnectionException, ExecutionException, QueryException};
use Raxos\Database\Orm\Backpack\{Backpack, BackpackInterface};
use Raxos\Database\Orm\Definition\{ColumnDefinition, MacroDefinition, RelationDefinition};
use Raxos\Database\Orm\Error\{InstanceException, RelationException, StructureException};
use Raxos\Database\Orm\Relation\WritableRelationInterface;
use Raxos\Database\Orm\Structure\Structure;
use Raxos\Database\Query\QueryInterface;
use Raxos\Foundation\Util\Singleton;
use function array_map;
use function array_search;
use function array_shift;
use function in_array;
use function is_subclass_of;

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

    public const string RELATION_LINKING_KEY = '__linking_key';

    public readonly Structure $structure;

    public readonly BackpackInterface $data;
    public readonly BackpackInterface $castCache;
    public readonly BackpackInterface $macroCache;
    public readonly BackpackInterface $relationCache;

    public ?Model $currentInstance = null;

    private array $instances = [];
    private array $modified = [];
    private array $saveTasks = [];

    /**
     * Backbone constructor.
     *
     * @param class-string<TModel>|class-string<Model> $class
     * @param array $data
     * @param bool $isNew
     *
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public readonly string $class,
        array $data,
        public bool $isNew = false
    )
    {
        $this->structure = Structure::of($this->class);

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
        $this->instances[] = $instance;

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
    public function removeInstance(Model $instance): void
    {
        $index = array_search($instance, $this->instances, true);

        if ($index !== false) {
            unset($this->instances[$index]);
        }
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

            if (is_subclass_of($property->types[0], BackedEnum::class)) {
                return $property->types[0]::tryFrom($value);
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
        return $this->structure
            ->getRelation($property)
            ->fetch($this->currentInstance);
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
        if ($key === self::RELATION_LINKING_KEY) {
            return true;
        }

        return $this->structure->hasProperty($key);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function setValue(string $key, mixed $value): void
    {
        if ($key === self::RELATION_LINKING_KEY) {
            $this->data->setValue($key, $value);

            return;
        }

        $property = $this->structure->getProperty($key);

        try {
            match (true) {
                $property instanceof ColumnDefinition => $this->setColumnValue($property, $value),
                $property instanceof MacroDefinition => throw InstanceException::immutableMacro($this->class, $property->name),
                $property instanceof RelationDefinition => $this->setRelationValue($property, $value)
            };
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

}
