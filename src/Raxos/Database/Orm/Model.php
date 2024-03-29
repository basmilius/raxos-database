<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace Raxos\Database\Orm;

use BackedEnum;
use Generator;
use JetBrains\PhpStorm\{ArrayShape, ExpectedValues, Pure};
use Raxos\Database\Error\{DatabaseException, ModelException};
use Raxos\Database\Orm\Attribute\{Alias, Caster, Column, CustomRelation, DataKey, HasLinkedMany, HasMany, HasManyThrough, HasOne, Hidden, Immutable, Macro, Polymorphic, PrimaryKey, RelationAttribute, Table, Visible};
use Raxos\Database\Orm\Cast\CastInterface;
use Raxos\Database\Orm\Definition\{FieldDefinition, MacroDefinition};
use Raxos\Database\Orm\Relation\{HasLinkedManyRelation, HasOneRelation, LazyRelation, Relation};
use Raxos\Database\Query\{Query, QueryInterface};
use Raxos\Foundation\Event\Emitter;
use Raxos\Foundation\PHP\MagicMethods\DebugInfoInterface;
use Raxos\Foundation\Util\{ArrayUtil, ReflectionUtil, Singleton};
use ReflectionClass;
use ReflectionProperty;
use Stringable;
use WeakMap;
use function array_diff;
use function array_is_list;
use function array_key_exists;
use function array_map;
use function array_merge;
use function array_shift;
use function array_unique;
use function class_exists;
use function count;
use function end;
use function explode;
use function extension_loaded;
use function implode;
use function in_array;
use function intval;
use function is_array;
use function is_numeric;
use function is_string;
use function is_subclass_of;
use function json_encode;
use function sprintf;
use function str_starts_with;
use function trim;
use function ucfirst;

/**
 * Class Model
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.0
 */
abstract class Model extends ModelBase implements DebugInfoInterface, Stringable
{

    use Emitter;
    use ModelDatabaseAccess;

    protected final const array ATTRIBUTES = [
        Alias::class,
        Caster::class,
        Column::class,
        CustomRelation::class,
        DataKey::class,
        Macro::class,
        HasLinkedMany::class,
        HasMany::class,
        HasManyThrough::class,
        HasOne::class,
        Hidden::class,
        Immutable::class,
        Polymorphic::class,
        PrimaryKey::class,
        Table::class,
        Visible::class
    ];

    protected static string $connectionId = 'default';

    private static array $__alias = [];
    private static array $__initialized = [];
    private static array $__polymorphicClassMap = [];
    private static array $__polymorphicColumn = [];
    private static array $__relations = [];
    private static array $__tables = [];

    /** @var FieldDefinition[][]|MacroDefinition[][] */
    private static array $__fields = [];

    private array $modified = [];
    private array $hidden = [];
    private array $visible = [];

    private array $castedFields = [];
    private array $macroCache = [];
    private bool $isMacroCall = false;

    /**
     * ModelBase constructor.
     *
     * @param array $__data
     * @param bool $isNew
     * @param Model|null $__master
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(array $__data = [], protected bool $isNew = true, ?self $__master = null)
    {
        parent::__construct($__data, $__master);

        self::initialize();
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
        static::emit(ModelEvent::DELETE, $this);
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

        foreach (static::getFields() as $field) {
            $field = $field->toArray();
            $field['types'] = implode('|', $field['types']);

            $fields[] = $field;
        }

        foreach (static::getMacros() as $macro) {
            $macros[] = sprintf('%s::%s() [%s]', static::class, $macro->method, $macro->isCacheable ? 'cacheable' : 'dynamic');
        }

        return [
            'type' => static::class,
            'data' => $this->__data,
            'fields' => $fields,
            'macros' => $macros,
            'modified' => $this->modified,
            'table' => static::$__tables[static::class]
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
        } else {
            return $values;
        }
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

        if ($field !== null && !in_array($field, $this->modified)) {
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
        return in_array($field, $this->hidden);
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
        return in_array($field, $this->visible);
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

        foreach (static::getFields() as $field) {
            $fieldName = $field->name;

            if (in_array($fieldName, $fields)) {
                continue;
            }

            $hidden[] = $fieldName;
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
        $def = static::getField($field);

        if (!static::isRelation($def)) {
            throw new ModelException(sprintf('Field %s is not a relation.', $field), ModelException::ERR_RELATION_NOT_FOUND);
        }

        return static::getRelation($def)->getQuery($this);
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
        $pairs = [];

        foreach ($this->modified as $field) {
            $def = static::getField($field);
            $fieldName = $def?->name ?? $field;
            $value = parent::getValue($fieldName);

            if ($def instanceof FieldDefinition && $def->cast !== null) {
                $value = static::castField($def->cast, 'encode', $value);
            }

            $pairs[$fieldName] = $value;
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
            $this->emit(ModelEvent::CREATE);
        } else {
            $primaryKey = array_map($this->getValue(...), $primaryKey);

            static::update($primaryKey, $pairs);

            $this->emit(ModelEvent::UPDATE);
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

        foreach (static::getFields() as $def) {
            if ($this->isHidden($def->name)) {
                continue;
            }

            if (static::isRelation($def)) {
                if ($this->isVisible($def->name)) {
                    $relation = static::getRelation($def);

                    $data[$def->name] = $relation->get($this);
                }
            } else if ($this->isNew && $def->isPrimary) {
                $data[$def->name] = null;
            } else if ($this->hasValue($def->property)) {
                $data[$def->name] = $this->{$def->property};
            }

            if ($def->visibleOnly !== null && array_key_exists($def->name, $data)) {
                if ($data[$def->name] instanceof self) {
                    $data[$def->name] = $data[$def->name]->only($def->visibleOnly);
                } else if (is_array($data[$def->name])) {
                    $data[$def->name] = ArrayUtil::only($data[$def->name], $def->visibleOnly);
                }
            }
        }

        foreach (static::getMacros() as $def) {
            if ($this->isHidden($def->name) || !$this->isVisible($def->name)) {
                continue;
            }

            $data[$def->name] = $this->callMacro($def);
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
        foreach (static::getFields() as $field) {
            $fieldName = $field->name;
            $fieldExists = array_key_exists($fieldName, $data);

            if (!$fieldExists && $this->isNew) {
                continue;
            }

            if (!$fieldExists && array_key_exists($fieldName, $data)) {
                $data[$fieldName] = $field->default;
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
    protected function getValue(string $field): mixed
    {
        $def = static::getField(static::$__alias[static::class][$field] ?? $field);

        if (!$this->isMacroCall && $def instanceof MacroDefinition) {
            return $this->callMacro($def);
        }

        if (static::isRelation($def)) {
            return static::getRelation($def)->get($this);
        }

        if ($def instanceof FieldDefinition && $def->cast !== null && !in_array($def->property, $this->castedFields)) {
            $this->__data[$def->name] = static::castField($def->cast, 'decode', $this->__data[$def->key]);
            $this->castedFields[] = $def->property;
        }

        if ($def instanceof FieldDefinition && $def->default !== null && !array_key_exists($def->key, $this->__data)) {
            return $def->default;
        }

        $value = parent::getValue($def?->key ?? $field);

        // note: enum support.
        if ($value !== null && is_subclass_of($def->types[0], BackedEnum::class)) {
            return $def->types[0]::tryFrom($value);
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function hasValue(string $field): bool
    {
        $def = static::getField(static::$__alias[static::class][$field] ?? $field);

        if ($def instanceof FieldDefinition || $def instanceof MacroDefinition) {
            return true;
        }

        return parent::hasValue($field);
    }

    /**
     * {@inheritdoc}
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function setValue(string $field, mixed $value): void
    {
        $def = static::getField(static::$__alias[static::class][$field] ?? $field);

        if ($def instanceof FieldDefinition) {
            if (static::isRelation($def)) {
                $this->setValueOfRelation($def, static::getRelation($def), $value);
            } else {
                if ($def->isPrimary && !$this->isNew) {
                    throw new ModelException(sprintf('Field "%s" is (part of) the primary key of model "%s" and is therefore not mutable.', $field, static::class), ModelException::ERR_IMMUTABLE);
                } else if ($def->isImmutable && !$this->isNew) {
                    throw new ModelException(sprintf('Field "%s" in model "%s" is immutable.', $field, static::class), ModelException::ERR_IMMUTABLE);
                }

                // note: enum support.
                if (is_subclass_of($def->types[0], BackedEnum::class)) {
                    $value = $value?->value;
                }

                // note: assume that the data is valid and does not need casting.
                if ($def->cast !== null && !in_array($def->property, $this->castedFields)) {
                    $this->castedFields[] = $def->property;
                }

                $this->modified[] = $def->property;

                parent::setValue($def->name, $value);
            }
        } else if ($def instanceof MacroDefinition) {
            if (!self::connection()->tableColumnExists(static::table(), $def->name)) {
                throw new ModelException(sprintf('Field "%s" is a non-writable macro on model "%s".', $field, static::class), ModelException::ERR_IMMUTABLE);
            }

            $this->modified[] = $def->property;

            parent::setValue($def->name, $value);
        } else if (str_starts_with($field, '__')) {
            parent::setValue($field, $value);
        } else {
            throw new ModelException(sprintf('Field "%s" is not writable on model "%s".', $field, static::class), ModelException::ERR_IMMUTABLE);
        }
    }

    /**
     * Sets the value of a relation if possible.
     *
     * @param FieldDefinition $field
     * @param Relation $relation
     * @param mixed $value
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function setValueOfRelation(FieldDefinition $field, Relation $relation, mixed $value): void
    {
        if ($relation instanceof LazyRelation) {
            $relation = $relation->getRelation();
        }

        switch (true) {
            case $relation instanceof HasOneRelation:
                $column = $relation->key;
                $referenceColumn = $relation->referenceKey;

                /** @var self $referenceModel */
                $referenceModel = $relation->referenceModel;

                if (!($value instanceof $referenceModel)) {
                    throw new ModelException(sprintf('%s is not assignable to type %s.', $value::class, $referenceModel), ModelException::ERR_INVALID_TYPE);
                }

                $column = explode('.', $column);
                $column = end($column);
                $column = trim($column, '`');

                $referenceColumn = explode('.', $referenceColumn);
                $referenceColumn = end($referenceColumn);
                $referenceColumn = trim($referenceColumn, '`');

                parent::setValue($column, $value->{$referenceColumn});
                parent::setValue($relation->fieldName, $value);

                $this->modified[] = static::$__alias[static::class][$column] ?? $column;
                break;

            default:
                throw new ModelException(sprintf('Field "%s" is a relationship that has no setters on model "%s".', $field->name, static::class), ModelException::ERR_IMMUTABLE);
        }
    }

    /**
     * {@inheritdoc}
     * @throws ModelException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function unsetValue(string $field): void
    {
        $def = static::getField(static::$__alias[static::class][$field] ?? $field);

        if ($def instanceof MacroDefinition) {
            throw new ModelException(sprintf('Field "%s" is a macro and cannot be unset.', $field), ModelException::ERR_IMMUTABLE);
        }

        parent::unsetValue($def?->name ?? $field);
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
        $result = static::{$macro->method}($this);
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
        foreach (static::$__fields[static::class] as $field) {
            unset($this->{$field->property});

            if ($field->isHidden) {
                $this->hidden[] = $field->name;
            }

            if ($field->isVisible) {
                $this->visible[] = $field->name;
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
     * Gets a defined field.
     *
     * @param string $field
     *
     * @return FieldDefinition|MacroDefinition|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function getField(string $field): FieldDefinition|MacroDefinition|null
    {
        return static::$__fields[static::class][$field] ?? null;
    }

    /**
     * Gets all defined fields.
     *
     * @return Generator<FieldDefinition>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function getFields(): Generator
    {
        foreach (static::$__fields[static::class] ?? [] as $def) {
            if ($def instanceof FieldDefinition) {
                yield $def;
            }
        }
    }

    /**
     * Gets all defined macros.
     *
     * @return Generator<MacroDefinition>
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function getMacros(): Generator
    {
        foreach (static::$__fields[static::class] ?? [] as $def) {
            if ($def instanceof MacroDefinition) {
                yield $def;
            }
        }
    }

    /**
     * Gets the primary key(s) of the model.
     *
     * @return string[]|string|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function getPrimaryKey(): array|string|null
    {
        static $knownPrimaryKey = [];

        return $knownPrimaryKey[static::class] ??= (function (): array|string|null {
            $fields = [];

            foreach (static::getFields() as $field) {
                if ($field->isPrimary) {
                    $fields[] = $field->name;
                }
            }

            $length = count($fields);

            if ($length === 0) {
                return null;
            } else if ($length === 1) {
                return $fields[0];
            } else {
                return $fields;
            }
        })();
    }

    /**
     * Ensures a relation with the given name.
     *
     * @param FieldDefinition $field
     *
     * @return Relation
     * @throws DatabaseException
     * @throws ModelException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function getRelation(FieldDefinition $field): Relation
    {
        if (array_key_exists($field->property, static::$__relations[static::class])) {
            return static::$__relations[static::class][$field->property];
        }

        if ($field->relation === null) {
            throw new ModelException(sprintf('Model %s does not have a relation named %s.', static::class, $field->name), ModelException::ERR_RELATION_NOT_FOUND);
        }

        return static::$__relations[static::class][$field->property] = new LazyRelation(
            $field->relation,
            static::class,
            $field,
            static::connection()
        );
    }

    /**
     * Gets all relations of the model.
     *
     * @return Generator<Relation>
     * @throws DatabaseException
     * @throws ModelException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function getRelations(): Generator
    {
        foreach (static::getFields() as $def) {
            if ($def->relation !== null) {
                yield static::getRelation($def);
            }
        }
    }

    /**
     * Gets the table of the model.
     *
     * @return string
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function table(): string
    {
        if (array_key_exists(static::class, static::$__tables)) {
            return static::$__tables[static::class];
        }

        static::initialize();

        return static::$__tables[static::class] ?? throw new ModelException(sprintf('Model "%s" does not have a table assigned.', static::class), ModelException::ERR_NO_TABLE_ASSIGNED);
    }

    /**
     * Returns TRUE if the given field is a non-relation field.
     *
     * @param FieldDefinition|MacroDefinition|null $field
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function isField(FieldDefinition|MacroDefinition|null $field): bool
    {
        return $field instanceof FieldDefinition && $field->relation === null;
    }

    /**
     * Returns TRUE if the given field is a macro.
     *
     * @param FieldDefinition|MacroDefinition|null $field
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function isMacro(FieldDefinition|MacroDefinition|null $field): bool
    {
        return $field instanceof MacroDefinition;
    }

    /**
     * Returns TRUE if the given field is a relation.
     *
     * @param FieldDefinition|MacroDefinition|null $field
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function isRelation(FieldDefinition|MacroDefinition|null $field): bool
    {
        return $field instanceof FieldDefinition && $field->relation !== null;
    }

    /**
     * Restores the settings of the model from the given settings.
     *
     * @param array $modelSettings
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function restoreModelSettings(array $modelSettings): void
    {
        [
            static::$__alias[static::class],
            static::$__fields[static::class],
            static::$__polymorphicClassMap[static::class],
            static::$__polymorphicColumn[static::class],
            static::$__tables[static::class]
        ] = $modelSettings;

        static::$__initialized[static::class] = true;
        static::$__relations[static::class] = [];
    }

    /**
     * Returns the settings of the model.
     *
     * @return array
     * @throws ModelException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function saveModelSettings(): array
    {
        static::initialize();

        return [
            static::$__alias[static::class],
            static::$__fields[static::class],
            static::$__polymorphicClassMap[static::class],
            static::$__polymorphicColumn[static::class],
            static::$__tables[static::class]
        ];
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
     */
    protected static function getDefaultFields(array|string|int $fields): array|string|int
    {
        return $fields;
    }

    /**
     * Gets the joins that should be added to every select query.
     *
     * @param Query $query
     *
     * @return QueryInterface
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected static function getDefaultJoins(Query $query): QueryInterface
    {
        return $query;
    }

    /**
     * Casts the given value using the given caster class.
     *
     * @param string $casterClass
     * @param string $mode
     * @param mixed $value
     *
     * @return mixed
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private static function castField(string $casterClass, #[ExpectedValues(['decode', 'encode'])] string $mode, mixed $value): mixed
    {
        /** @var CastInterface $caster */
        $caster = Singleton::get($casterClass);

        return $caster->{$mode}($value);
    }

    /**
     * Copies various settings from the given master model.
     *
     * @param string $masterModel
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private static function copySettings(string $masterModel): void
    {
        static::$__tables[static::class] = static::$__tables[$masterModel] ?? null;
    }

    /**
     * Initializes the model.
     *
     * @throws ModelException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private static function initialize(): void
    {
        if (isset(static::$__initialized[static::class])) {
            return;
        }

        static::$__alias[static::class] = [];
        static::$__fields[static::class] = [];
        static::$__polymorphicColumn[static::class] = null;
        static::$__polymorphicClassMap[static::class] = [];
        static::$__relations[static::class] = [];

        $class = new ReflectionClass(static::class);
        $attributes = $class->getAttributes();

        foreach ($attributes as $attribute) {
            $attributeName = $attribute->getName();

            if (!in_array($attributeName, self::ATTRIBUTES)) {
                continue;
            }

            switch (true) {
                case $attributeName === Polymorphic::class:
                    $p = $attribute->newInstance();
                    static::$__polymorphicColumn[static::class] = $p->column;
                    static::$__polymorphicClassMap[static::class] = $p->map;
                    break;

                case $attributeName === Table::class:
                    static::$__tables[static::class] = $attribute->getArguments()[0];
                    break;

                default:
                    continue 2;
            }
        }

        // note: This will make models based on another model possible.
        if (($parentClass = $class->getParentClass())->name !== self::class) {
            /** @var self&string $parentModel */
            $parentModel = $parentClass->name;
            $parentModel::initialize();

            static::copySettings($parentClass->name);
            static::initializeFields($parentClass);
        }

        static::initializeFields($class);

        static::$__initialized[static::class] = true;
    }

    /**
     * Initializes the fields of the model, based on the properties of the
     * model class.
     *
     * @param ReflectionClass $class
     *
     * @throws ModelException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private static function initializeFields(ReflectionClass $class): void
    {
        $className = $class->name;
        $properties = $class->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE);

        foreach ($properties as $property) {
            if ($property->class !== $className) {
                continue;
            }

            if (!empty($property->getAttributes(Macro::class))) {
                static::initializeMacro($class, $property);
            } else {
                static::initializeField($property);
            }
        }
    }

    /**
     * Initializes a single field of the model.
     *
     * @param ReflectionProperty $property
     *
     * @throws ModelException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private static function initializeField(ReflectionProperty $property): void
    {
        static $knownCasters = [];

        $attributes = $property->getAttributes();

        $alias = null;
        $cast = null;
        $dataKey = null;
        $default = null;
        $isImmutable = false;
        $isPrimary = false;
        $isHidden = false;
        $isVisible = false;
        $relation = null;
        $types = ReflectionUtil::getTypes($property->getType()) ?? [];
        $validField = false;
        $visibleOnly = null;

        foreach ($attributes as $attribute) {
            $attr = $attribute->newInstance();

            switch (true) {
                case $attr instanceof Alias:
                    $alias = $attr->alias;
                    break;

                case $attr instanceof Caster:
                    $cast = $attr->caster;

                    if (!isset($knownCasters[$cast])) {
                        if (!class_exists($cast)) {
                            throw new ModelException(sprintf('Caster "%s" not found.', $cast), ModelException::ERR_CASTER_NOT_FOUND);
                        }

                        if (!is_subclass_of($cast, CastInterface::class)) {
                            throw new ModelException(sprintf('Class "%s" is not a valid caster class.', $cast), ModelException::ERR_CASTER_NOT_FOUND);
                        }

                        $knownCasters[$cast] = true;
                    }
                    break;

                case $attr instanceof Column:
                    $default = $attr->default;
                    $validField = true;
                    break;

                case $attr instanceof DataKey:
                    $dataKey = $attr->key;
                    break;

                case $attr instanceof RelationAttribute:
                    $relation = $attr;
                    $validField = true;
                    break;

                case $attr instanceof Immutable:
                    $isImmutable = true;
                    break;

                case $attr instanceof PrimaryKey:
                    $isImmutable = true;
                    $isPrimary = true;
                    $validField = true;
                    break;

                case $attr instanceof Hidden:
                    $isHidden = true;
                    break;

                case $attr instanceof Visible:
                    $isVisible = true;
                    $visibleOnly = is_string($attr->only) ? [$attr->only] : $attr->only;
                    break;
            }
        }

        if (!$validField) {
            return;
        }

        if ($alias !== null) {
            static::$__alias[static::class][$alias] = $property->name;
        }

        static::$__fields[static::class][$property->name] = new FieldDefinition(
            $alias,
            $cast,
            $dataKey,
            $default,
            $isImmutable,
            $isPrimary,
            $isHidden,
            $isVisible,
            $property->name,
            $relation,
            $types,
            $visibleOnly
        );
    }

    /**
     * Initializes a macro of the model.
     *
     * @param ReflectionClass $class
     * @param ReflectionProperty $property
     *
     * @throws ModelException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private static function initializeMacro(ReflectionClass $class, ReflectionProperty $property): void
    {
        $attributes = $property->getAttributes();
        $methodName = str_starts_with($property->name, 'is') ? $property->name : 'get' . ucfirst($property->name);

        if (!$class->hasMethod($methodName)) {
            throw new ModelException(sprintf('Macro %s in model %s should have a static macro method named %s.', $property->name, static::class, $methodName), ModelException::ERR_MACRO_METHOD_NOT_FOUND);
        }

        $alias = null;
        $isCacheable = false;
        $isHidden = false;
        $isVisible = false;

        foreach ($attributes as $attribute) {
            $attr = $attribute->newInstance();

            switch (true) {
                case $attr instanceof Alias:
                    $alias = $attr->alias;
                    break;

                case $attr instanceof Macro:
                    $isCacheable = $attr->isCacheable;
                    break;

                case $attr instanceof Hidden:
                    $isHidden = true;
                    break;

                case $attr instanceof Visible:
                    $isVisible = true;
                    break;
            }
        }

        if ($alias !== null) {
            static::$__alias[static::class][$alias] = $property->name;
        }

        static::$__fields[static::class][$property->name] = new MacroDefinition(
            $alias,
            $isCacheable,
            $isHidden,
            $isVisible,
            $methodName,
            $property->name
        );
    }

    /**
     * Creates a new instance of the current model class with the given
     * column attributes.
     *
     * @param mixed $result
     * @param string|null $masterModel
     *
     * @return static
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @access private
     * @internal
     * @private
     */
    static function createInstance(mixed $result, string $masterModel = null): static
    {
        if ($masterModel !== null) {
            static::copySettings($masterModel);

            static::$__polymorphicClassMap[static::class] = [];
            static::$__polymorphicColumn[static::class] = null;
        }

        if (($typeColumn = static::$__polymorphicColumn[static::class]) !== null) {
            /** @var self&string $polymorphicClassName */
            $polymorphicClassName = static::$__polymorphicClassMap[static::class][$result[$typeColumn]] ?? null;

            if ($polymorphicClassName !== null) {
                return $polymorphicClassName::createInstance($result, static::class);
            }
        }

        $primaryKey = static::getPrimaryKey();

        if (is_array($primaryKey)) {
            $primaryKeyValue = array_map(fn(string $key) => $result[$key], $primaryKey);
        } else if (!empty($primaryKey)) {
            $primaryKeyValue = $result[$primaryKey];
        } else {
            $primaryKeyValue = null;
        }

        if ($primaryKeyValue !== null && static::cache()->has(static::class, $primaryKeyValue)) {
            $instance = static::cache()->get(static::class, $primaryKeyValue);
        } else {
            $instance = new static($result, false);
            $instance::cache()->set($instance, $masterModel);
        }

        if (array_key_exists('__linking_key', $result)) {
            $keys = explode(',', $result['__linking_key']);
            $keys = array_map(fn(string $key) => is_numeric($key) ? intval($key) : $key, $keys);

            HasLinkedManyRelation::$linkingKeys ??= new WeakMap();
            HasLinkedManyRelation::$linkingKeys[$instance] = $keys;
        }

        return $instance;
    }

    /**
     * Eager loads the relationships of the given models.
     *
     * @param Model[] $models
     * @param string[] $forceEagerLoad
     * @param string[] $eagerLoadDisable
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @access private
     * @internal
     * @private
     */
    static function eagerLoadRelationships(array $models, array $forceEagerLoad = [], array $eagerLoadDisable = []): void
    {
        /** @var Relation[] $relations */
        $relations = static::getRelations();
        $didRelations = [];

        // note: first we need to determine which relations are in the current model,
        //  for polymorphic relations it's more performant to combine the relations
        //  of the underlying types into one big query.
        foreach ($relations as $relation) {
            $fieldName = $relation->fieldName;

            if ((!$relation->eagerLoad && !in_array($fieldName, $forceEagerLoad)) || in_array($fieldName, $eagerLoadDisable)) {
                continue;
            }

            $relation->eagerLoad($models);
            $didRelations[] = $fieldName;
        }

        $classGroups = [];

        while (!empty($models)) {
            $model = array_shift($models);

            $classGroups[$model::class] ??= [];
            $classGroups[$model::class][] = $model;
        }

        /**
         * @var self&string $modelClass
         * @var self[] $models
         */
        foreach ($classGroups as $modelClass => $models) {
            /** @var Relation[] $relations */
            $relations = $modelClass::getRelations();

            foreach ($relations as $relation) {
                $fieldName = $relation->fieldName;

                if (in_array($fieldName, $didRelations) || (!$relation->eagerLoad && !in_array($fieldName, $forceEagerLoad)) || in_array($fieldName, $eagerLoadDisable)) {
                    continue;
                }

                $relation->eagerLoad($models);
            }
        }
    }

    /**
     * Initializes the model from a relation.
     *
     * @throws ModelException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @access private
     * @internal
     * @private
     */
    static function initializeFromRelation(): void
    {
        static::initialize();
    }

    /**
     * {@inheritdoc}
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __debugInfo(): ?array
    {
        if (extension_loaded('xdebug')) {
            // note: debugInfo gets called by xdebug many times and that
            //  breaks our code.
            return $this->__data;
        }

        return $this->toArray();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __serialize(): array
    {
        $relations = [];

        foreach (static::getFields() as $field) {
            $name = $field->name;

            if ($this->isHidden($name) || !$this->isVisible($name)) {
                continue;
            }

            if ($this->{$field->property} === null) {
                continue;
            }

            $relations[$name] = $this->{$field->property};
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
        self::initialize();
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
            /** @var ModelArrayList|self $relation */
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
            return sprintf('%s(%s)', static::class, json_encode($primaryKeyValues));
        } else {
            return sprintf('%s(%s)', static::class, $primaryKeyValues);
        }
    }

}
