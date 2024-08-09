<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

use Raxos\Database\Error\DatabaseException;

/**
 * Interface ModelInterface
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.16
 */
interface ModelInterface extends MarkVisibilityInterface
{

    /**
     * Deletes the model instance from the database.
     *
     * @return void
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function destroy(): void;

    /**
     * Returns TRUE if the model is modified. If a key is given
     * only that key is checked.
     *
     * @param string|null $key
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function isModified(?string $key = null): bool;

    /**
     * Returns TRUE if the given key is hidden.
     *
     * @param string $key
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function isHidden(string $key): bool;

    /**
     * Returns TRUE if the given key is visible.
     *
     * @param string $key
     *
     * @return bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function isVisible(string $key): bool;

    /**
     * Marks all keys as hidden, except for the given ones.
     *
     * @param string[]|string $keys
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function only(array|string $keys): static;

    /**
     * Saves the model.
     *
     * If a model is marked as new, all set fields are treated as modified.
     * If a model is not new, only the modified fields are saved.
     *
     * @return void
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function save(): void;

}
