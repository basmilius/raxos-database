<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use ArrayAccess;
use JsonSerializable;
use Raxos\Database\Error\DatabaseException;
use Raxos\Foundation\Collection\Arrayable;
use Raxos\Foundation\PHP\MagicMethods\{DebugInfoInterface, SerializableInterface};
use Stringable;

/**
 * Interface ModelBaseInterface
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.16
 */
interface ModelBaseInterface extends Arrayable, ArrayAccess, DebugInfoInterface, JsonSerializable, SerializableInterface, Stringable
{

    /**
     * Clones the model.
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function clone(): static;

    /**
     * Returns the value at the given key.
     *
     * @param string $key
     *
     * @return mixed
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function getValue(string $key): mixed;

    /**
     * Returns TRUE if the given key exists.
     *
     * @param string $key
     *
     * @return bool
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function hasValue(string $key): bool;

    /**
     * Sets the given key to the given value.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return void
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function setValue(string $key, mixed $value): void;

    /**
     * Unsets the given key.
     *
     * @param string $key
     *
     * @return void
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function unsetValue(string $key): void;

}
