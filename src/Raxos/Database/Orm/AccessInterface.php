<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use Raxos\Database\Orm\Error\{InstanceException, StructureException};

/**
 * Interface AccessInterface
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 13-08-2024
 */
interface AccessInterface
{

    /**
     * Gets the value at the given key.
     *
     * @param string $key
     *
     * @return mixed
     * @throws InstanceException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 13-08-2024
     */
    public function getValue(string $key): mixed;

    /**
     * Returns TRUE if a value exists at the given key.
     *
     * @param string $key
     *
     * @return bool
     * @throws InstanceException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 13-08-2024
     */
    public function hasValue(string $key): bool;

    /**
     * Sets the value at the given key.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return void
     * @throws InstanceException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 13-08-2024
     */
    public function setValue(string $key, mixed $value): void;

    /**
     * Unsets the value at the given key.
     *
     * @param string $key
     *
     * @return void
     * @throws InstanceException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 13-08-2024
     */
    public function unsetValue(string $key): void;

}
