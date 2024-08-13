<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use BackedEnum;
use JetBrains\PhpStorm\Pure;
use Raxos\Database\Error\DatabaseException;
use Raxos\Database\Error\ModelException;
use Raxos\Database\Orm\Definition\ColumnDefinition;
use Raxos\Database\Orm\Definition\MacroDefinition;
use function array_key_exists;
use function array_map;
use function array_search;
use function array_shift;
use function in_array;
use function is_array;
use function is_subclass_of;
use function Raxos\Database\Query\literal;
use function sprintf;
use function str_starts_with;

/**
 * Class ModelBackbone
 *
 * @template TModel of Model
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.17
 * @internal
 * @private
 */
final class ModelBackbone implements ModelBackboneInterface
{

    private array $castCache = [];
    private array $instances = [];
    private bool $isDoingMacro = false;
    private array $macroCache = [];
    private array $modified = [];
    private array $saveTasks = [];

    /**
     * ModelBackbone constructor.
     *
     * @param class-string<TModel>|class-string<Model> $model
     * @param array<string, mixed> $data
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public readonly string $model,
        public array $data,
        public bool $isNew = false
    )
    {
        InternalStructure::initialize($this->model);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function addInstance(Model $instance): void
    {
        $this->instances[] = $instance;

        foreach (InternalStructure::getFields($this->model) as $definition) {
            unset($instance->{$definition->name});
        }
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function createInstance(): Model
    {
        return new $this->model(backbone: $this);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    #[Pure]
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
    public function removeInstance(Model $instance): void
    {
        $index = array_search($instance, $this->instances);

        if ($index !== false) {
            unset($this->instances[$index]);
        }
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function computeMacro(Model $instance, MacroDefinition $definition): mixed
    {
        if (array_key_exists($definition->name, $this->macroCache)) {
            return $this->macroCache[$definition->name];
        }

        $this->isDoingMacro = true;
        $result = $definition($instance);
        $this->isDoingMacro = false;

        if ($definition->isCacheable) {
            $this->macroCache[$definition->name] = $result;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function save(Model $instance): void
    {
        $this->saveTasks();

        $values = [];

        foreach ($this->modified as $key) {
            $definition = InternalStructure::getField($this->model, $key);
            $value = $this->getValue($instance, $definition->name);

            if ($value instanceof BackedEnum) {
                $value = $value->value;
            }

            $values[$definition->key] = $value;
        }

        $primaryKey = $instance::getPrimaryKey();
        $primaryKey = is_array($primaryKey) ? $primaryKey : [$primaryKey];

        if ($this->isNew) {
            foreach (InternalStructure::getColumns($this->model) as $definition) {
                if (isset($values[$definition->key]) || InternalStructure::isRelation($definition)) {
                    continue;
                }

                $values[$definition->key] = literal('default');
            }

            $primaryKeyValue = $instance::query()
                ->insertIntoValues($instance::table(), $values)
                ->runReturning($primaryKey);

            $data = $instance::select()
                ->withoutModel()
                ->wherePrimaryKey($this->model, $primaryKeyValue)
                ->single();

            $this->data = $data;
            $this->isNew = false;

            $this->castCache = [];
            $this->macroCache = [];

            $instance::cache()->set($instance);
        } elseif (!empty($values)) {
            $primaryKey = array_map($this->getValue(...), $primaryKey);
            $instance::update($primaryKey, $values);
        }

        $this->modified = [];
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function saveTasks(): void
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
    public function getValue(Model $instance, string $key): mixed
    {
        $definition = InternalStructure::getField($this->model, $key);

        if (!$this->isDoingMacro && $definition instanceof MacroDefinition) {
            return $this->computeMacro($instance, $definition);
        }

        if (InternalStructure::isRelation($definition)) {
            return InternalStructure::getRelation($this->model, $definition)
                ->fetch($instance);
        }

        if ($definition instanceof ColumnDefinition) {
            if ($definition->cast !== null) {
                return $this->castCache[$definition->name] ??= InternalStructure::cast($definition->cast, 'decode', $this->data[$definition->key], $instance);
            }

            if ($definition->default !== null && !array_key_exists($definition->name, $this->data)) {
                return $definition->default;
            }
        }

        $fieldKey = $definition?->key ?? $key;

        if (!array_key_exists($fieldKey, $this->data)) {
            throw new ModelException(sprintf('Column "%s" does not exist and does not have a default value in "%s".', $fieldKey, $this->model), ModelException::ERR_FIELD_NOT_FOUND);
        }

        $value = $this->data[$fieldKey];

        if ($definition !== null && $value !== null && is_subclass_of($definition->types[0], BackedEnum::class)) {
            return $definition->types[0]::tryFrom($value);
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function hasValue(Model $instance, string $key): bool
    {
        $definition = InternalStructure::getField($this->model, $key);

        return $definition instanceof ColumnDefinition
            || $definition instanceof MacroDefinition
            || array_key_exists($definition?->key ?? $key, $this->data);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function setValue(Model $instance, string $key, mixed $value): void
    {
        $definition = InternalStructure::getField($this->model, $key);

        if ($definition instanceof ColumnDefinition) {
            if (InternalStructure::isRelation($definition)) {
                InternalStructure::setRelationValue($instance, $definition, InternalStructure::getRelation($this->model, $definition), $value);

                return;
            }

            if ($definition->isPrimary && !$this->isNew) {
                throw new ModelException(sprintf('Field "%s" is (part of) the primary key of model "%s" and is therefore not mutable.', $key, $this->model), ModelException::ERR_IMMUTABLE);
            }

            if ($definition->isImmutable && !$this->isNew) {
                throw new ModelException(sprintf('Field "%s" in model "%s" is immutable.', $key, $this->model), ModelException::ERR_IMMUTABLE);
            }

            if ($definition->cast !== null) {
                unset($this->castCache[$definition->name]);
                $value = InternalStructure::cast($definition->cast, 'encode', $value, $instance);
            }

            if (is_subclass_of($definition->types[0], BackedEnum::class)) {
                $value = $value?->value;
            }

            $this->modified[] = $definition->name;
            $this->data[$definition->key] = $value;

            return;
        }

        if ($definition instanceof MacroDefinition) {
            if ($this->model::connection()->tableColumnExists($this->model::table(), $definition->key)) {
                throw new ModelException(sprintf('Field "%s" of model "%s" is a non-writable macro.', $key, $this->model), ModelException::ERR_IMMUTABLE);
            }

            $this->modified[] = $definition->name;
            $this->data[$definition->key] = $value;

            return;
        }

        if (!str_starts_with($key, '__')) {
            throw new ModelException(sprintf('Field "%s" is not writable on model "%s".', $key, $this->model), ModelException::ERR_IMMUTABLE);
        }

        $this->data[$key] = $value;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function unsetValue(Model $instance, string $key): void
    {
        $definition = InternalStructure::getField($this->model, $key);

        if ($definition instanceof MacroDefinition) {
            throw new ModelException(sprintf('Field "%s" of model "%s" is a macro and cannot be unset.', $key, $this->model), ModelException::ERR_IMMUTABLE);
        }

        if (!isset($this->data[$definition->name])) {
            return;
        }

        unset($this->data[$definition->name]);
    }

}
