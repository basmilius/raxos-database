<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use Raxos\Database\Error\DatabaseException;
use Raxos\Database\Error\ModelException;
use Raxos\Database\Orm\Attribute\{Cast, Column, Macro, PrimaryKey, Table};
use Raxos\Database\Orm\Cast\CastInterface;
use Raxos\Foundation\PHP\MagicMethods\DebugInfoInterface;
use Raxos\Foundation\Util\ReflectionUtil;
use Raxos\Foundation\Util\Singleton;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use function array_diff;
use function array_filter;
use function array_key_exists;
use function array_map;
use function array_unique;
use function Columba\Util\pre;
use function count;
use function in_array;
use function is_string;
use function serialize;
use function sprintf;
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

    protected const ATTRIBUTES = [
        Cast::class,
        Column::class,
        Macro::class,
        PrimaryKey::class,
        Table::class
    ];

    private static array $fields = [];
    private static array $fieldNames = [];
    private static array $initialized = [];
    private static array $macros = [];
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
        'fields' => 'array',
        'macros' => 'array',
        'modified' => 'string[]',
        'table' => 'string'
    ])]
    public function getDebugInformation(): array
    {
        return [
            'type' => static::class,
            'data' => $this->data,
            'fields' => static::getFields(),
            'macros' => static::getMacros(),
            'modified' => $this->modified,
            'table' => static::$tables[static::class]
        ];
    }

    /**
     * Gets the primary key(s) of the model.
     *
     * @return array|string|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function getPrimaryKey(): array|string|null
    {
        $fields = [];

        foreach (static::getFields() as $fieldName => $field) {
            if ($field['is_primary']) {
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
     * Saves the model.
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function save(): void
    {
        pre(__METHOD__, $this->modified);
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

        foreach (static::getFieldNames() as $field => $alias) {
            if ($this->isHidden($alias)) {
                continue;
            }

            $data[$alias] = $this->{$field};
        }

        foreach (static::getMacros() as $name => $macro) {
            if ($this->isHidden($name) || !$this->isVisible($name)) {
                continue;
            }

            $data[$name] = $this->callMacro($name);
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
        foreach (static::getFields() as $fieldName => $field) {
            $fieldName = static::getFieldName($fieldName);

            if (isset($field['cast'])) {
                /** @var CastInterface $caster */
                $caster = Singleton::get($field['cast']);

                $data[$fieldName] = $caster->decode($data[$fieldName]);
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

        $field = static::getFieldName($field);

        return parent::hasValue($field);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function setValue(string $field, mixed $value): void
    {
        $field = static::getFieldName($field);

        if ($field !== null) {
            $this->modified[] = $field;

            parent::setValue($field, $value);
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
        $result = $this->{$macro['method']}();
        $this->isMacroCall = false;

        if ($macro['is_cacheable']) {
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
    public function unserialize($serialized): void
    {
        [
            $this->data,
            $this->hidden,
            $this->visible
        ] = unserialize($serialized);

        $this->prepareModel();
    }

    /**
     * Gets a defined field.
     *
     * @param string $field
     *
     * @return array|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function getField(string $field): ?array
    {
        return static::$fields[static::class][$field] ?? null;
    }

    /**
     * Gets a field name.
     *
     * @param string $field
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function getFieldName(string $field): string
    {
        return static::$fieldNames[static::class][$field] ?? $field;
    }

    /**
     * Gets all defined field names.
     *
     * @return array
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function getFieldNames(): array
    {
        return static::$fieldNames[static::class] ?? [];
    }

    /**
     * Gets all defined fields.
     *
     * @return array
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
     * @return array|null
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function getMacro(string $name): ?array
    {
        return static::$macros[static::class][$name] ?? null;
    }

    /**
     * Gets all defined macros.
     *
     * @return array
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public static function getMacros(): array
    {
        return static::$macros[static::class] ?? [];
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
     * Initializes the model.
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private static function initialize(): void
    {
        if (array_key_exists(static::class, static::$initialized)) {
            return;
        }

        static::$fields[static::class] = [];

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
        static::initializeMethods($class);

        static::$initialized[static::class] = true;
    }

    /**
     * Initializes the fields of the model, based on the properties of the
     * model class.
     *
     * @param ReflectionClass $class
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private static function initializeFields(ReflectionClass $class): void
    {
        $properties = $class->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE);

        foreach ($properties as $property) {
            if ($property->getDeclaringClass()->getName() !== static::class) {
                continue;
            }

            $propertyAttributes = $property->getAttributes();
            $propertyName = $property->getName();
            $propertyType = $property->getType();
            $field = [];
            $field['is_primary'] = false;
            $field['types'] = ReflectionUtil::getTypes($propertyType) ?? [];
            $fieldName = $propertyName;
            $validField = false;

            foreach ($propertyAttributes as $attribute) {
                if (!in_array($attribute->getName(), self::ATTRIBUTES)) {
                    continue;
                }

                $attr = $attribute->newInstance();

                switch (true) {
                    case $attr instanceof Cast:
                        $field['cast'] = $attr->getClass();
                        break;

                    case $attr instanceof Column:
                        if (($alias = $attr->getAlias()) !== null) {
                            $fieldName = $alias;
                        }

                        $validField = true;
                        break;

                    case $attr instanceof PrimaryKey:
                        $field['is_primary'] = true;
                        break;
                }
            }

            if (!$validField) {
                continue;
            }

            static::$fields[static::class][$propertyName] = $field;
            static::$fieldNames[static::class][$propertyName] = $fieldName;
        }
    }

    /**
     * Initializes macros and relations.
     *
     * @param ReflectionClass $class
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private static function initializeMethods(ReflectionClass $class): void
    {
        $methods = $class->getMethods();

        foreach ($methods as $method) {
            if ($method->getDeclaringClass()->getName() !== static::class) {
                continue;
            }

            $attributes = $method->getAttributes();
            $attributeNames = array_map(fn(ReflectionAttribute $attr) => $attr->getName(), $attributes);

            if (in_array(Macro::class, $attributeNames)) {
                static::initializeMethodMacro($method, $attributes);
            }

            // todo(Bas): Relation methods.
        }
    }

    /**
     * Initializes a macro method.
     *
     * @param ReflectionMethod $method
     * @param ReflectionAttribute[] $attributes
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    private static function initializeMethodMacro(ReflectionMethod $method, array $attributes): void
    {
        $attributes = array_filter($attributes, fn(ReflectionAttribute $attr) => in_array($attr->getName(), self::ATTRIBUTES));

        $macroName = $method->getName();
        $macro = [
            'method' => $method->getName(),
            'is_cacheable' => false
        ];

        foreach ($attributes as $attribute) {
            $attribute = $attribute->newInstance();

            switch (true) {
                case $attribute instanceof Macro:
                    $macroName = $attribute->getName();
                    $macro['is_cacheable'] = $attribute->isCacheable();
                    break;

                default:
                    continue 2;
            }
        }

        static::$macros[static::class][$macroName] = $macro;
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
