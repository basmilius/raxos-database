<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use BackedEnum;
use JetBrains\PhpStorm\{ArrayShape, Pure};
use Raxos\Database\Error\{DatabaseException, ModelException};
use Raxos\Database\Orm\Definition\{ColumnDefinition, MacroDefinition};
use Raxos\Database\Orm\Relation\{RelationInterface, WritableRelationInterface};
use Raxos\Database\Query\QueryInterface;
use Raxos\Foundation\Util\ArrayUtil;
use Stringable;
use function array_diff;
use function array_is_list;
use function array_key_exists;
use function array_map;
use function array_merge;
use function array_shift;
use function array_unique;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function is_subclass_of;
use function sprintf;
use function str_starts_with;

/**
 * Class Model
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.0
 */
abstract class Model extends ModelBase implements Stringable
{

    use ModelDatabaseAccess;

    private array $modified = [];
    private array $hidden = [];
    private array $visible = [];

    private array $castedFields = [];
    private array $macroCache = [];
    private bool $isMacroCall = false;

    /**
     * @var array<callable>
     * @internal
     * @private
     */
    public array $__saveTasks = [];

    /**
     * ModelBase constructor.
     *
     * @param array $data
     * @param bool $isNew
     * @param Model|null $master
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        array $data = [],
        protected bool $isNew = true,
        ?self $master = null
    )
    {
        parent::__construct($data, $master);

        InternalModelData::initialize(static::class);
        $this->prepareModel();

        if ($this->__master === null) {
            $this->onInitialize($this->__data);
        }
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function clone(): static
    {
        $clone = parent::clone();
        $clone->castedFields = &$this->castedFields;
        $clone->isNew = &$this->isNew;

        return $clone;
    }

    /**
     * Deletes the given model from the database.
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function destroy(): void
    {
        static::delete($this->getPrimaryKeyValues());
        static::cache()->remove($this);
    }

    /**
     * Gets an original data value.
     *
     * @param string $key
     *
     * @return mixed
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function getData(string $key): mixed
    {
        return $this->__data[$key] ?? null;
    }

    /**
     * Gets debug information about the model instance.
     *
     * @return array
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @internal
     * @private
     */
    #[ArrayShape([
        'type' => 'string',
        'data' => 'array',
        'fields' => 'array[]',
        'macros' => 'string[]',
        'modified' => 'string[]',
        'table' => 'string'
    ])]
    public function getDebugInformation(): array
    {
        $fields = [];
        $macros = [];

        foreach (InternalModelData::getColumns(static::class) as $column) {
            $column = $column->toArray();
            $column['types'] = implode('|', $column['types']);

            $fields[] = $column;
        }

        foreach (InternalModelData::getMacros(static::class) as $macro) {
            $callableName = $macro->callable;

            if (is_array($callableName)) {
                $callableName = sprintf('%s::%s(...)', $callableName[0], $callableName[1]);
            }

            $macros[$macro->name] = sprintf('%s [%s]', $callableName, $macro->isCacheable ? 'cacheable' : 'dynamic');
        }

        return [
            'type' => static::class,
            'data' => $this->__data,
            'fields' => $fields,
            'macros' => $macros,
            'modified' => $this->modified,
            'table' => InternalModelData::$table[static::class]
        ];
    }

    /**
     * Gets the value(s) of the primary key(s) of the model.
     *
     * @return array|string|int|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function getPrimaryKeyValues(): array|string|int|null
    {
        $keys = static::getPrimaryKey();

        if ($keys === null) {
            return null;
        }

        if (is_string($keys)) {
            $keys = [$keys];
        }

        $values = array_map($this->getValue(...), $keys);

        if (count($values) === 1) {
            return $values[0];
        }

        return $values;
    }

    /**
     * Returns TRUE if the model is modified.
     *
     * @param string|null $field
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    public function isModified(?string $field = null): bool
    {
        if (empty($this->modified)) {
            return false;
        }

        if ($field !== null && !in_array($field, $this->modified, true)) {
            return false;
        }

        return true;
    }

    /**
     * Returns TRUE if the given field is hidden.
     *
     * @param string $field
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    public function isHidden(string $field): bool
    {
        return in_array($field, $this->hidden, true);
    }

    /**
     * Returns TRUE if the given field is visible.
     *
     * @param string $field
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    public function isVisible(string $field): bool
    {
        return in_array($field, $this->visible, true);
    }

    /**
     * Marks the given fields as hidden.
     *
     * @param string[]|string $fields
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function makeHidden(array|string $fields): static
    {
        if (is_string($fields)) {
            $fields = [$fields];
        }

        $clone = $this->clone();
        $clone->hidden = array_unique([...$this->hidden, ...$fields]);
        $clone->visible = array_diff($this->visible, $clone->hidden);

        return $clone;
    }

    /**
     * Marks the given fields as visible.
     *
     * @param string[]|string $fields
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function makeVisible(array|string $fields): static
    {
        if (is_string($fields)) {
            $fields = [$fields];
        }

        $clone = $this->clone();
        $clone->visible = array_unique([...$this->visible, ...$fields]);
        $clone->hidden = array_diff($this->hidden, $clone->visible);

        return $clone;
    }

    /**
     * Marks all fields as hidden, except for the given fields.
     *
     * @param string[]|string $fields
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function only(array|string $fields): static
    {
        if (is_string($fields)) {
            $fields = [$fields];
        }

        $hidden = [];

        foreach (InternalModelData::getFields(static::class) as $field) {
            if (in_array($field->name, $fields, true)) {
                continue;
            }

            if (in_array($field->alias, $fields, true)) {
                $fields[] = $field->name;
                continue;
            }

            $hidden[] = $field->name;
        }

        $clone = $this->clone();
        $clone->hidden = $hidden;
        $clone->visible = $fields;

        return $clone;
    }

    /**
     * Queries the given relation.
     *
     * @param string $field
     *
     * @return QueryInterface
     * @throws DatabaseException
     * @throws ModelException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @no-named-arguments
     */
    public function queryRelation(string $field): QueryInterface
    {
        $def = InternalModelData::getField(static::class, $field);

        if ($def === null || !InternalModelData::isRelation($def)) {
            throw new ModelException(sprintf('Field %s is not a relation.', $field), ModelException::ERR_RELATION_NOT_FOUND);
        }

        return InternalModelData::getRelation(static::class, $def)
            ->query($this);
    }

    /**
     * Saves the model.
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function save(): void
    {
        while (($saveTask = array_shift($this->__saveTasks)) !== null) {
            $saveTask();
        }

        $pairs = [];

        foreach ($this->modified as $field) {
            $def = InternalModelData::getField(static::class, $field);
            $value = parent::getValue($def?->key);

            if ($def instanceof ColumnDefinition && $def->cast !== null) {
                $value = InternalModelData::cast($def->cast, 'encode', $value, $this);
            }

            $pairs[$def->key] = $value;
        }

        $primaryKey = static::getPrimaryKey();
        $primaryKey = is_array($primaryKey) ? $primaryKey : [$primaryKey];

        if ($this->isNew) {
            if (count($primaryKey) === 1) {
                $primaryKeyValue = static::query()
                    ->insertIntoValues(static::table(), $pairs)
                    ->runReturning($primaryKey[0]);

                // todo(Bas): this is probably not an auto increment field, figure out
                //  if we should have an AutoIncrement attribute or something.
                if ($primaryKeyValue === '0') {
                    $primaryKeyValue = $this->{$primaryKey[0]};
                }

                $query = static::select()
                    ->withoutModel();

                self::addPrimaryKeyClauses($query, $primaryKeyValue);

                /** @var array $data */
                $data = $query->single();
                $this->castedFields = [];
                $this->onInitialize($data);
                $this->__data = $data;
            } else {
                // todo(Bas): handle composite primary keys in the future.
                static::query()
                    ->insertIntoValues(static::table(), $pairs)
                    ->run();
            }

            $this->isNew = false;

            static::cache()->set($this);

            $this->macroCache = [];
        } else if (!empty($pairs)) {
            $primaryKey = array_map($this->getValue(...), $primaryKey);

            static::update($primaryKey, $pairs);
        }

        $this->modified = [];
    }

    /**
     * {@inheritdoc}
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function toArray(): array
    {
        $data = [];

        foreach (InternalModelData::getColumns(static::class) as $def) {
            $key = $def->alias ?? $def->name;

            if ($this->isHidden($def->name)) {
                continue;
            }

            if (InternalModelData::isRelation($def)) {
                if ($this->isVisible($def->name)) {
                    $relation = InternalModelData::getRelation(static::class, $def);

                    $data[$key] = $relation->fetch($this);
                }
            } else if ($this->isNew && $def->isPrimary) {
                $data[$key] = null;
            } else if ($this->hasValue($def->key)) {
                $data[$key] = $this->{$def->key};
            }

            if ($def->visibleOnly !== null && array_key_exists($def->name, $data)) {
                if ($data[$key] instanceof self) {
                    $data[$key] = $data[$key]->only($def->visibleOnly);
                } else if (is_array($data[$key])) {
                    $data[$key] = ArrayUtil::only($data[$key], $def->visibleOnly);
                }
            }
        }

        foreach (InternalModelData::getMacros(static::class) as $def) {
            if ($this->isHidden($def->name) || !$this->isVisible($def->name)) {
                continue;
            }

            $data[$def->alias ?? $def->name] = $this->callMacro($def);
        }

        $this->onPublish($data);

        return $data;
    }

    /**
     * Invoked when the model instance is initialized.
     *
     * @param array $data
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function onInitialize(array &$data): void
    {
        foreach (InternalModelData::getColumns(static::class) as $def) {
            $fieldExists = array_key_exists($def->key, $data);

            if (!$fieldExists && $this->isNew) {
                continue;
            }

            if (!$fieldExists && array_key_exists($def->name, $data)) {
                $data[$def->name] = $def->default;
            }
        }
    }

    /**
     * Invoked before the model is published to json_encode for example.
     *
     * @param array $data
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function onPublish(array &$data): void
    {
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function getValue(string $key): mixed
    {
        $def = InternalModelData::getField(static::class, $key);

        if (!$this->isMacroCall && $def instanceof MacroDefinition) {
            return $this->callMacro($def);
        }

        if (InternalModelData::isRelation($def)) {
            return InternalModelData::getRelation(static::class, $def)->fetch($this);
        }

        if ($def instanceof ColumnDefinition && $def->cast !== null && !in_array($def->name, $this->castedFields, true)) {
            $this->__data[$def->key] = InternalModelData::cast($def->cast, 'decode', $this->__data[$def->key], $this);
            $this->castedFields[] = $def->name;
        }

        if ($def instanceof ColumnDefinition && $def->default !== null && !array_key_exists($def->key, $this->__data)) {
            return $def->default;
        }

        $value = parent::getValue($def?->key ?? $key);

        // note: enum support.
        if ($def !== null && $value !== null && is_subclass_of($def->types[0], BackedEnum::class)) {
            return $def->types[0]::tryFrom($value);
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function hasValue(string $key): bool
    {
        $def = InternalModelData::getField(static::class, $key);

        if ($def instanceof ColumnDefinition || $def instanceof MacroDefinition) {
            return true;
        }

        return parent::hasValue($key);
    }

    /**
     * {@inheritdoc}
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function setValue(string $key, mixed $value): void
    {
        $def = InternalModelData::getField(static::class, $key);

        if ($def instanceof ColumnDefinition) {
            if (InternalModelData::isRelation($def)) {
                $this->setValueOfRelation($def, InternalModelData::getRelation(static::class, $def), $value);
            } else {
                if ($def->isPrimary && !$this->isNew) {
                    throw new ModelException(sprintf('Field "%s" is (part of) the primary key of model "%s" and is therefore not mutable.', $key, static::class), ModelException::ERR_IMMUTABLE);
                }

                if ($def->isImmutable && !$this->isNew) {
                    throw new ModelException(sprintf('Field "%s" in model "%s" is immutable.', $key, static::class), ModelException::ERR_IMMUTABLE);
                }

                // note: enum support.
                if (is_subclass_of($def->types[0], BackedEnum::class)) {
                    $value = $value?->value;
                }

                // note: assume that the data is valid and does not need casting.
                if ($def->cast !== null && !in_array($def->name, $this->castedFields, true)) {
                    $this->castedFields[] = $def->name;
                }

                $this->modified[] = $def->name;

                parent::setValue($def->key, $value);
            }
        } else if ($def instanceof MacroDefinition) {
            if (!static::connection()->tableColumnExists(static::table(), $def->alias ?? $def->name)) {
                throw new ModelException(sprintf('Field "%s" is a non-writable macro on model "%s".', $key, static::class), ModelException::ERR_IMMUTABLE);
            }

            $this->modified[] = $def->name;

            parent::setValue($def->name, $value);
        } else if (str_starts_with($key, '__')) {
            parent::setValue($key, $value);
        } else {
            throw new ModelException(sprintf('Field "%s" is not writable on model "%s".', $key, static::class), ModelException::ERR_IMMUTABLE);
        }
    }

    /**
     * Sets the value of a relation if possible.
     *
     * @param ColumnDefinition $def
     * @param RelationInterface $relation
     * @param mixed $value
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function setValueOfRelation(ColumnDefinition $def, RelationInterface $relation, mixed $value): void
    {
        if ($relation instanceof WritableRelationInterface) {
            $relation->write($this, $def, $value);

            return;
        }

        throw new ModelException(sprintf('Field "%s" on model "%s" is a relationship that is not writable.', $def->name, static::class), ModelException::ERR_IMMUTABLE);
    }

    /**
     * {@inheritdoc}
     * @throws ModelException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function unsetValue(string $key): void
    {
        $def = InternalModelData::getField(static::class, $key);

        if ($def instanceof MacroDefinition) {
            throw new ModelException(sprintf('Field "%s" is a macro and cannot be unset.', $key), ModelException::ERR_IMMUTABLE);
        }

        parent::unsetValue($def?->name ?? $key);
    }

    /**
     * Calls the given macro.
     *
     * @param MacroDefinition $macro
     *
     * @return mixed
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private function callMacro(MacroDefinition $macro): mixed
    {
        if (array_key_exists($macro->name, $this->macroCache)) {
            return $this->macroCache[$macro->name];
        }

        $this->isMacroCall = true;
        $result = $macro($this);
        $this->isMacroCall = false;

        if ($macro->isCacheable) {
            $this->macroCache[$macro->name] = $result;
        }

        return $result;
    }

    /**
     * Prepares the model for prime time.
     * - Removes all defined fields from the class that are known to
     *   be database related fields.
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private function prepareModel(): void
    {
        foreach (InternalModelData::getFields(static::class) as $def) {
            unset($this->{$def->name});

            if ($def->isHidden) {
                $this->hidden[] = $def->name;
            }

            if ($def->isVisible) {
                $this->visible[] = $def->name;
            }
        }
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
     * Ensures that the given fields are returned as array.
     *
     * @param array|string|int $fields
     *
     * @return array
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected static function ensureArrayFields(array|string|int $fields): array
    {
        if (!is_array($fields)) {
            $fields = [$fields];
        }

        if (array_is_list($fields)) {
            $fields = array_fill_keys($fields, true);
        }

        return $fields;
    }

    /**
     * Extends the given fields with the given extended fields.
     *
     * @param array|string|int $fields
     * @param array $extendedFields
     *
     * @return array
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected static function extendFields(array|string|int $fields, array $extendedFields): array
    {
        return array_merge(static::ensureArrayFields($fields), $extendedFields);
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

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __serialize(): array
    {
        $relations = [];

        foreach (InternalModelData::getColumns(static::class) as $field) {
            $name = $field->name;

            if ($this->isHidden($name) || !$this->isVisible($name)) {
                continue;
            }

            if ($this->{$field->key} === null) {
                continue;
            }

            $relations[$name] = $this->{$field->key};
        }

        return [
            $this->__data,
            $this->hidden,
            $this->visible,
            $this->isNew,
            $this->castedFields,
            $relations
        ];
    }

    /**
     * {@inheritdoc}
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __unserialize(array $data): void
    {
        InternalModelData::initialize(static::class);

        $this->prepareModel();

        [
            $this->__data,
            $this->hidden,
            $this->visible,
            $this->isNew,
            $this->castedFields,
            $relations
        ] = $data;

        $pk = $this->getPrimaryKeyValues();

        if (static::cache()->has(static::class, $pk)) {
            $this->__master = static::cache()->get(static::class, $pk);
            $this->castedFields = &$this->__master->castedFields;
            $this->__data = &$this->__master->__data;
            $this->isNew = &$this->__master->isNew;
        } else {
            $this->__master = null;
            static::cache()->set($this);
        }

        foreach ($relations as $relation) {
            if ($relation instanceof ModelArrayList) {
                foreach ($relation as $r) {
                    $r::cache()->set($r);
                }
            } else if (isset($relation->__data)) {
                $relation::cache()->set($relation);
            }
        }
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
            return sprintf('%s(%s)', static::class, implode(', ', $primaryKeyValues));
        }

        return sprintf('%s(%s)', static::class, $primaryKeyValues);
    }

}
