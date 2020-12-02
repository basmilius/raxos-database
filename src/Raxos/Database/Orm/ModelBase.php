<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use JsonSerializable;
use Serializable;
use function array_key_exists;

/**
 * Class ModelBase
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.0
 */
abstract class ModelBase implements JsonSerializable, Serializable
{

    /**
     * ModelBase constructor.
     *
     * @param array $data
     * @param static|null $master
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(protected array $data = [], protected ?self $master = null)
    {
    }

    /**
     * Gets the value of the given field.
     *
     * @param string $field
     *
     * @return mixed
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function getValue(string $field): mixed
    {
        return $this->data[$field] ?? null;
    }

    /**
     * Returns TRUE if the given field exists.
     *
     * @param string $field
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function hasValue(string $field): bool
    {
        return array_key_exists($field, $this->data);
    }

    /**
     * Sets the given field to the given value.
     *
     * @param string $field
     * @param mixed $value
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function setValue(string $field, mixed $value): void
    {
        $this->data[$field] = $value;
    }

    /**
     * Unsets the given field.
     *
     * @param string $field
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function unsetValue(string $field): void
    {
        unset($this->data[$field]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function jsonSerialize(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function serialize(): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function unserialize($serialized): void
    {
    }

    /**
     * @param string $name
     *
     * @return mixed
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see ModelBase::getValue()
     */
    public final function __get(string $name): mixed
    {
        return $this->getValue($name);
    }

    /**
     * @param string $name
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see ModelBase::hasValue()
     */
    public final function __isset(string $name): bool
    {
        return $this->hasValue($name);
    }

    /**
     * @param string $name
     * @param mixed $value
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see ModelBase::setValue()
     */
    public final function __set(string $name, mixed $value): void
    {
        $this->setValue($name, $value);
    }

    /**
     * @param string $name
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     * @see ModelBase::unsetValue()
     */
    public final function __unset(string $name): void
    {
        $this->unsetValue($name);
    }

}
