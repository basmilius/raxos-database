<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use JsonSerializable;
use Raxos\Database\Error\DatabaseException;
use Raxos\Database\Error\ModelException;
use Serializable;
use function array_key_exists;
use function sprintf;

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
        if ($this->master !== null) {
            $this->data = &$this->master->data;
        }
    }

    /**
     * Clones the model.
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function clone(): static
    {
        $master = $this;

        if ($this->master !== null) {
            $master = $this->master;
        }

        return new static(master: $master);
    }

    /**
     * Gets the value of the given field.
     *
     * @param string $field
     *
     * @return mixed
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function getValue(string $field): mixed
    {
        if (array_key_exists($field, $this->data)) {
            return $this->data[$field];
        }

        throw new ModelException(sprintf('Field "%s" does not exists in "%s".', $field, static::class), ModelException::ERR_FIELD_NOT_FOUND);
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
     * Converts the model to an array.
     *
     * @return array
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public abstract function jsonSerialize(): array;

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public abstract function serialize(): string;

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public abstract function unserialize($serialized): void;

    /**
     * @param string $name
     *
     * @return mixed
     * @throws DatabaseException
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
