<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\ExpectedValues;
use JetBrains\PhpStorm\Pure;
use Raxos\Database\Error\DatabaseException;
use Raxos\Database\Error\ModelException;
use Raxos\Database\Orm\Attribute\{Column, HasMany, HasOne, Macro, PrimaryKey, RelationAttribute, Table};
use Raxos\Database\Orm\Cast\CastInterface;
use Raxos\Database\Orm\Defenition\FieldDefinition;
use Raxos\Database\Orm\Defenition\MacroDefinition;
use Raxos\Database\Orm\Relation\Relation;
use Raxos\Database\Query\Query;
use Raxos\Foundation\Event\Emitter;
use Raxos\Foundation\PHP\MagicMethods\DebugInfoInterface;
use Raxos\Foundation\Util\ReflectionUtil;
use Raxos\Foundation\Util\Singleton;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;
use function array_diff;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_unique;
use function class_exists;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function is_subclass_of;
use function serialize;
use function sprintf;
use function str_starts_with;
use function ucfirst;
use function unserialize;

/**
 * Class Model
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.0
 */
abstract class Model extends ModelBase implements DebugInfoInterface
{

    use Emitter;
    use ModelDatabaseAccess;

    public const EVENT_CREATE = 'create';
    public const EVENT_DELETE = 'delete';
    public const EVENT_UPDATE = 'update';

    protected const ATTRIBUTES = [
        Column::class,
        Macro::class,
        HasMany::class,
        HasOne::class,
        PrimaryKey::class,
        Table::class
    ];

    protected static string $connectionId = 'default';

    private static array $fields = [];
    private static array $initialized = [];
    private static array $macros = [];
    private static array $relations = [];
    private static array $tables = [];

    protected array $modified = [];
    protected array $hidden = [];
    protected array $visible = [];

    private array $macroCache = [];
    private bool $isMacroCall = false;

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
    public function __construct(array $data = [], protected bool $isNew = true, ?self $master = null)
    {
        parent::__construct($data, $master);

        self::initialize();
        $this->prepareModel();

        if ($this->master === null) {
            $this->onInitialize($this->data);
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
        $clone->isNew = &$this->isNew;

        return $clone;
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
        $fields = static::getFields();
        $macros = static::getMacros();

        foreach ($fields as &$field) {
            $field = $field->toArray();
            $field['types'] = implode('|', $field['types']);
        }

        foreach ($macros as &$macro) {
            $macro = sprintf('%s::%s() [%s]', static::class, $macro->method, $macro->isCacheable ? 'cacheable' : 'dynamic');
        }

        return [
            'type' => static::class,
            'data' => $this->data,
            'fields' => $fields,
            'macros' => $macros,
            'table' => static::$tables[static::class]
        ];
    }

    /**
     * Gets the value(s) of the primary key(s) of the model.
     *
     * @return array|string|int|null
     * @throws DatabaseException
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

        $values = array_map(fn(string $key) => $this->getValue($key), $keys);

        if (count($values) === 1) {
            return $values[0];
        } else {
            return $values;
        }
    }

    /**
     * Returns TRUE if the model is modified.
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function isModified(): bool
    {
        return !empty($this->modified);
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

        foreach (array_keys(static::getFields()) as $fieldName) {
            $fieldName = static::getFieldName($fieldName);

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
     * @return Query
     * @throws DatabaseException
     * @throws ModelException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @no-named-arguments
     */
    public function queryRelation(string $field): Query
    {
        if (!static::isRelation($field)) {
            throw new ModelException(sprintf('Field %s is not a relation.', $field), ModelException::ERR_RELATION_NOT_FOUND);
        }

        $relation = static::getRelation($field);

        return $relation->getQuery($this);
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
            $fieldName = static::getFieldName($field);
            $fieldDefinition = static::getField($field);
            $value = parent::getValue($fieldName);

            if ($fieldDefinition->cast !== null) {
                $value = static::castField($fieldDefinition->cast, 'encode', $value);
            }

            $pairs[$fieldName] = $value;
        }

        $primaryKey = static::getPrimaryKey();
        $primaryKey = is_array($primaryKey) ? $primaryKey : [$primaryKey];

        if ($this->isNew) {
            static::query()
                ->insertIntoValues(static::getTable(), $pairs)
                ->run();

            $this->isNew = false;

            if (count($primaryKey) === 1) {
                $primaryKey = static::connection()->lastInsertId();

                $query = static::select()
                    ->withoutModel();

                self::addPrimaryKeyClauses($query, $primaryKey);

                /** @var array $data */
                $data = $query->single();
                $this->onInitialize($data);
                $this->data = $data;
            }

            static::cache()->set($this);

            $this->macroCache = [];
            $this->emit(static::EVENT_CREATE);
        } else {
            $primaryKey = array_map(fn(string $key) => $this->getValue($key), $primaryKey);

            static::update($primaryKey, $pairs);

            $this->emit(static::EVENT_UPDATE);
        }
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

        foreach (static::getFields() as $fieldName => $fieldDefinition) {
            $alias = $fieldDefinition->alias ?? $fieldName;

            if ($this->isHidden($alias)) {
                continue;
            }

            if (static::isRelation($fieldName)) {
                if ($this->isVisible($fieldName)) {
                    $relation = static::getRelation($fieldName);

                    $data[$fieldName] = $relation->get($this);
                }
            } else if ($this->isNew && $fieldDefinition->isPrimary) {
                $data[$alias] = null;
            } else if ($this->hasValue($fieldName)) {
                $data[$alias] = $this->{$fieldName};
            }
        }

        foreach (static::getMacros() as $name => $macro) {
            $macroName = $macro->alias ?? $name;

            if ($this->isHidden($macroName) || !$this->isVisible($macroName)) {
                continue;
            }

            $data[$macroName] = $this->callMacro($name);
        }

        $this->onPublish($data);

        return $data;
    }

    /**
     * Invoked when the model instance is initialized.
     *
     * @param array $data
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function onInitialize(array &$data): void
    {
        foreach (static::getFields() as $fieldName => $fieldDefinition) {
            $fieldName = static::getFieldName($fieldName);
            $fieldExists = array_key_exists($fieldName, $data);

            if (!$fieldExists && $this->isNew) {
                continue;
            }

            if (!$fieldExists && array_key_exists($fieldName, $data)) {
                $data[$fieldName] = $fieldDefinition->default;
            }

            if ($fieldDefinition->cast !== null) {
                $data[$fieldName] = static::castField($fieldDefinition->cast, 'decode', $data[$fieldName]);
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
        if (!$this->isMacroCall && static::getMacro($field) !== null) {
            return $this->callMacro($field);
        }

        if (static::isRelation($field)) {
            $relation = static::getRelation($field);

            return $relation->get($this);
        }

        $field = static::getFieldName($field);

        return parent::getValue($field);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function hasValue(string $field): bool
    {
        if (!$this->isMacroCall && static::getMacro($field) !== null) {
            return true;
        }

        if (array_key_exists($field, static::$relations[static::class]) || static::isRelation($field)) {
            return true;
        }

        $field = static::getFieldName($field);

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
        $fieldName = static::getFieldName($field);
        $fieldDefinition = static::getField($field);

        if ($fieldDefinition !== null) {
            if ($fieldDefinition->isPrimary) {
                throw new ModelException(sprintf('Field "%s" is part of the primary key of model "%s" and is therefore not writable.', $field, static::class), ModelException::ERR_IMMUTABLE);
            }

            $this->modified[] = $field;

            parent::setValue($fieldName, $value);
        } else {
            throw new ModelException(sprintf('Field "%s" is not writable on model "%s".', $field, static::class), ModelException::ERR_IMMUTABLE);
        }
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function unsetValue(string $field): void
    {
        $field = static::getFieldName($field);

        parent::unsetValue($field);
    }

    /**
     * Calls the given macro.
     *
     * @param string $name
     *
     * @return mixed
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private function callMacro(string $name): mixed
    {
        $macro = static::getMacro($name);

        if ($macro === null) {
            throw new ModelException(sprintf('Macro "%s" not found in model "%s".', $name, static::class), ModelException::ERR_MACRO_NOT_FOUND);
        }

        if (array_key_exists($name, $this->macroCache)) {
            return $this->macroCache[$name];
        }

        $this->isMacroCall = true;
        $result = $this->{$macro->method}($this);
        $this->isMacroCall = false;

        if ($macro->isCacheable) {
            $this->macroCache[$name] = $result;
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
        foreach (static::$fields[static::class] as $name => $field) {
            unset($this->{$name});
        }

        foreach (static::$macros[static::class] as $name => $macro) {
            unset($this->{$name});
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
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function serialize(): string
    {
        return serialize([
            $this->data,
            $this->hidden,
            $this->visible
        ]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function unserialize(mixed $data): void
    {
        [
            $this->data,
            $this->hidden,
            $this->visible
        ] = unserialize($data);

        $this->prepareModel();
    }

    /**
     * Gets a defined field.
     *
     * @param string $field
     *
     * @return FieldDefinition|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function getField(string $field): ?FieldDefinition
    {
        return static::$fields[static::class][$field] ?? null;
    }

    /**
     * Gets the real name of a field.
     *
     * @param string $field
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function getFieldName(string $field): string
    {
        $f = static::getField($field);

        return $f->alias ?? $field;
    }

    /**
     * Gets all defined fields.
     *
     * @return FieldDefinition[]
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function getFields(): array
    {
        return static::$fields[static::class] ?? [];
    }

    /**
     * Gets the given macro.
     *
     * @param string $name
     *
     * @return MacroDefinition|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function getMacro(string $name): ?MacroDefinition
    {
        return static::$macros[static::class][$name] ?? null;
    }

    /**
     * Gets all defined macros.
     *
     * @return MacroDefinition[]
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function getMacros(): array
    {
        return static::$macros[static::class] ?? [];
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
        $fields = [];

        foreach (static::getFields() as $fieldName => $field) {
            if ($field->isPrimary) {
                $fields[] = static::getFieldName($fieldName);
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
    }

    /**
     * Ensures a relation with the given name.
     *
     * @param string $field
     *
     * @return Relation
     * @throws DatabaseException
     * @throws ModelException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function getRelation(string $field): Relation
    {
        if (array_key_exists($field, static::$relations[static::class])) {
            return static::$relations[static::class][$field];
        }

        $definition = static::getField($field);
        $relationType = $definition->relation;

        if ($relationType === null) {
            throw new ModelException(sprintf('Model %s does not have a relation named %s.', static::class, $field), ModelException::ERR_RELATION_NOT_FOUND);
        }

        $relation = $relationType->create(static::connection(), static::class, $definition);

        static::$relations[static::class][$field] = $relation;

        return $relation;
    }

    /**
     * Gets all relations of the model.
     *
     * @return Relation[]
     * @throws DatabaseException
     * @throws ModelException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function getRelations(): array
    {
        $relations = [];

        foreach (static::getFields() as $fieldName => $field) {
            if ($field->relation === null) {
                continue;
            }

            $relations[] = static::getRelation($fieldName);
        }

        return $relations;
    }

    /**
     * Gets the table of the model.
     *
     * @return string
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function getTable(): string
    {
        return static::$tables[static::class] ?? throw new ModelException(sprintf('Model "%s" does not have a table assigned.', static::class), ModelException::ERR_NO_TABLE_ASSIGNED);
    }

    /**
     * Returns TRUE if the given field is a relation.
     *
     * @param string $field
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function isRelation(string $field): bool
    {
        $field = static::getField($field);

        return $field !== null && $field->relation !== null;
    }

    /**
     * Casts the given value using the given caster class.
     *
     * @param string $casterClass
     * @param string $mode
     * @param mixed $value
     *
     * @return mixed
     * @throws ModelException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private static function castField(string $casterClass, #[ExpectedValues(['decode', 'encode'])] string $mode, mixed $value): mixed
    {
        if (!class_exists($casterClass)) {
            throw new ModelException(sprintf('Caster "%s" not found.', $casterClass), ModelException::ERR_CASTER_NOT_FOUND);
        }

        if (!is_subclass_of($casterClass, CastInterface::class)) {
            throw new ModelException(sprintf('Class "%s" is not a valid caster class.', $casterClass), ModelException::ERR_CASTER_NOT_FOUND);
        }

        /** @var CastInterface $caster */
        $caster = Singleton::get($casterClass);

        return $caster->{$mode}($value);
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
        if (array_key_exists(static::class, static::$initialized)) {
            return;
        }

        static::$fields[static::class] = [];
        static::$macros[static::class] = [];
        static::$relations[static::class] = [];

        $class = new ReflectionClass(static::class);

        $attributes = $class->getAttributes();

        foreach ($attributes as $attribute) {
            if (!in_array($attribute->getName(), self::ATTRIBUTES)) {
                continue;
            }

            $attr = $attribute->newInstance();

            switch (true) {
                case $attr instanceof Table:
                    static::$tables[static::class] = $attr->getName();
                    break;

                default:
                    continue 2;
            }
        }

        static::initializeFields($class);
        static::$initialized[static::class] = true;
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
        $properties = $class->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE);

        foreach ($properties as $property) {
            if ($property->getDeclaringClass()->getName() !== $class->getName()) {
                continue;
            }

            if (!empty($macro = $property->getAttributes(Macro::class))) {
                static::initializeMacro($class, $property, $macro[0]);
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
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private static function initializeField(ReflectionProperty $property): void
    {
        $attributes = $property->getAttributes();

        $alias = null;
        $cast = null;
        $classProperty = $property->getName();
        $default = null;
        $isPrimary = false;
        $relation = null;
        $types = ReflectionUtil::getTypes($property->getType()) ?? [];
        $validField = false;

        foreach ($attributes as $attribute) {
            if (!in_array($attribute->getName(), self::ATTRIBUTES)) {
                continue;
            }

            $attr = $attribute->newInstance();

            switch (true) {
                case $attr instanceof Column:
                    $alias = $attr->getAlias();
                    $cast = $attr->getCaster();
                    $default = $attr->getDefault();
                    $validField = true;
                    break;

                case $attr instanceof RelationAttribute:
                    $relation = $attr;

                    $validField = true;
                    break;

                case $attr instanceof PrimaryKey:
                    $isPrimary = true;
                    break;
            }
        }

        if (!$validField) {
            return;
        }

        static::$fields[static::class][$classProperty] = new FieldDefinition(
            $alias,
            $cast,
            $default,
            $isPrimary,
            $classProperty,
            $relation,
            $types
        );
    }

    /**
     * Initializes a macro of the model.
     *
     * @param ReflectionClass $class
     * @param ReflectionProperty $property
     * @param ReflectionAttribute $macroAttribute
     *
     * @throws ModelException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private static function initializeMacro(ReflectionClass $class, ReflectionProperty $property, ReflectionAttribute $macroAttribute): void
    {
        $propertyName = $property->getName();
        $methodName = str_starts_with($propertyName, 'is') ? $propertyName : 'get' . ucfirst($propertyName);

        if (!$class->hasMethod($methodName)) {
            throw new ModelException(sprintf('Macro %s in model %s should have a callback method named %s.', $propertyName, static::class, $methodName), ModelException::ERR_MACRO_METHOD_NOT_FOUND);
        }

        /** @var Macro $macro */
        $macro = $macroAttribute->newInstance();

        static::$macros[static::class][$propertyName] = new MacroDefinition(
            $macro->getAlias(),
            $macro->isCacheable(),
            $methodName
        );
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
        return $this->toArray();
    }

}
