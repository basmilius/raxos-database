<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use Raxos\Database\Error\ModelException;
use Raxos\Foundation\Access\{ArrayAccessible, ObjectAccessible};
use function extension_loaded;
use function sprintf;

/**
 * Class ModelBase
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.0
 */
abstract class ModelBase implements ModelBaseInterface
{

    use ArrayAccessible;
    use ObjectAccessible;

    /**
     * @var array<string, mixed>
     * @internal
     * @private
     */
    public array $__data;

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
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function clone(): static
    {
        if ($this->__master !== null) {
            return new static(master: $this->__master);
        }

        return new static(master: $this);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function getValue(string $key): mixed
    {
        if (array_key_exists($key, $this->__data)) {
            return $this->__data[$key];
        }

        throw new ModelException(sprintf('Column "%s" does not exist and does not have a default value in "%s".', $key, static::class), ModelException::ERR_FIELD_NOT_FOUND);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function hasValue(string $key): bool
    {
        return array_key_exists($key, $this->__data);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function setValue(string $key, mixed $value): void
    {
        $this->__data[$key] = $value;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function unsetValue(string $key): void
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
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

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
     * @since 1.0.16
     */
    public function __toString(): string
    {
        return static::class;
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
