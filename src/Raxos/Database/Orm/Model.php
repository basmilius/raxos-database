<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use Raxos\Database\Orm\Attribute\Cast;
use Raxos\Database\Orm\Attribute\Column;
use Raxos\Database\Orm\Attribute\PrimaryKey;
use Raxos\Foundation\Util\ReflectionUtil;
use ReflectionClass;
use ReflectionProperty;
use function array_key_exists;

/**
 * Class Model
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.0
 */
abstract class Model extends ModelBase
{

    public const ATTRIBUTES = [
        Cast::class,
        Column::class,
        PrimaryKey::class
    ];

    protected static array $fields = [];
    protected static array $fieldNames = [];
    protected static array $initialized = [];

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

        foreach (static::$fields[static::class] as $name => $field) {
            unset($this->{$name});

            $fieldName = static::$fieldNames[static::class][$name];

            if (isset($field['cast'])) {
                $this->data[$fieldName] = (new $field['cast'])->decode($this->data[$fieldName]);
            }
        }
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function getValue(string $field): mixed
    {
        $field = static::$fieldNames[static::class][$field] ?? $field;

        return parent::getValue($field);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function hasValue(string $field): bool
    {
        $field = static::$fieldNames[static::class][$field] ?? $field;

        return parent::hasValue($field);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function setValue(string $field, mixed $value): void
    {
        $field = static::$fieldNames[static::class][$field] ?? $field;

        parent::setValue($field, $value);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function unsetValue(string $field): void
    {
        $field = static::$fieldNames[static::class][$field] ?? $field;

        parent::unsetValue($field);
    }

    /**
     * Initializes the model.
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected static function initialize(): void
    {
        if (array_key_exists(static::class, static::$initialized)) {
            return;
        }

        static::$fields[static::class] = [];

        $class = new ReflectionClass(static::class);
        $properties = $class->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE);

        foreach ($properties as $property) {
            if ($property->getDeclaringClass()->getName() !== static::class) {
                continue;
            }

            $propertyAttributes = $property->getAttributes();
            $propertyName = $property->getName();
            $propertyType = $property->getType();
            $field = [];
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
                        if ($attr->getAlias() !== null) {
                            $fieldName = $attr->getAlias();
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

        static::$initialized[static::class] = true;
    }

}
