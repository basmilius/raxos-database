<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use ArrayAccess;
use JsonSerializable;
use Raxos\Database\Error\DatabaseException;
use Raxos\Database\Error\ModelException;
use Raxos\Foundation\Access\ArrayAccessible;
use Raxos\Foundation\Access\ObjectAccessible;
use Serializable;
use stdClass;
use function array_key_exists;
use function sprintf;

/**
 * Class ModelBase
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.0
 */
abstract class ModelBase extends stdClass implements ArrayAccess, JsonSerializable, Serializable
{

    use ArrayAccessible;
    use ObjectAccessible;

    /**
     * ModelBase constructor.
     *
     * @param array $__data
     * @param static|null $__master
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(protected array $__data = [], protected ?self $__master = null)
    {
        if ($this->__master !== null) {
            $this->__data = &$this->__master->__data;
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

        if ($this->__master !== null) {
            $master = $this->__master;
        }

        return new static(__master: $master);
    }

    /**
     * Gets the master model instance.
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public final function getModelMaster(): static
    {
        return $this->__master ?? $this;
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
        if (array_key_exists($field, $this->__data)) {
            return $this->__data[$field];
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
        return array_key_exists($field, $this->__data);
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
        $this->__data[$field] = $value;
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
        unset($this->__data[$field]);
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
        return $this->__data;
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
    public abstract function unserialize(mixed $data): void;

}
