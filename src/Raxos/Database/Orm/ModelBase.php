<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use ArrayAccess;
use JsonSerializable;
use Raxos\Database\Error\{DatabaseException, ModelException};
use Raxos\Foundation\Access\{ArrayAccessible, ObjectAccessible};
use Raxos\Foundation\Collection\Arrayable;
use Raxos\Foundation\PHP\MagicMethods\{DebugInfoInterface, SerializableInterface};
use function extension_loaded;
use function sprintf;

/**
 * Class ModelBase
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.0
 */
abstract class ModelBase implements Arrayable, ArrayAccess, DebugInfoInterface, JsonSerializable, SerializableInterface
{

    use ArrayAccessible;
    use ObjectAccessible;

    /** @var array<string, mixed> */
    protected array $__data;

    /**
     * @internal
     * @private
     */
    public ?self $__master;

    /**
     * ModelBase constructor.
     *
     * @param array<string, mixed> $data
     * @param static|null $master
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        array $data = [],
        ?self $master = null
    )
    {
        if ($master !== null) {
            $this->__data = &$master->__data;
            $this->__master = $master;
        } else {
            $this->__data = $data;
            $this->__master = $this;
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
        if ($this->__master !== null) {
            return new static(master: $this->__master);
        }

        return new static(master: $this);
    }

    /**
     * Gets the value of the given field.
     *
     * @param string $key
     *
     * @return mixed
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function getValue(string $key): mixed
    {
        if (array_key_exists($key, $this->__data)) {
            return $this->__data[$key];
        }

        throw new ModelException(sprintf('Column "%s" does not exist and does not have a default value in "%s".', $key, static::class), ModelException::ERR_FIELD_NOT_FOUND);
    }

    /**
     * Returns TRUE if the given field exists.
     *
     * @param string $key
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function hasValue(string $key): bool
    {
        return array_key_exists($key, $this->__data);
    }

    /**
     * Sets the given field to the given value.
     *
     * @param string $key
     * @param mixed $value
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function setValue(string $key, mixed $value): void
    {
        $this->__data[$key] = $value;
    }

    /**
     * Unsets the given field.
     *
     * @param string $key
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected function unsetValue(string $key): void
    {
        unset($this->__data[$key]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
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
     * @since 1.0.16
     */
    public function __debugInfo(): ?array
    {
        if (extension_loaded('xdebug')) {
            return $this->__data;
        }

        return $this->toArray();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public abstract function __serialize(): array;

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public abstract function __unserialize(array $data): void;

}
