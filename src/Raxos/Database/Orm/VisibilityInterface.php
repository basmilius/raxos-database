<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

/**
 * Interface VisibilityInterface
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.17
 */
interface VisibilityInterface
{

    /**
     * Marks the given keys as hidden.
     *
     * @param string[]|string $keys
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function makeHidden(array|string $keys): static;

    /**
     * Marks the given keys as visible.
     *
     * @param string[]|string $keys
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function makeVisible(array|string $keys): static;

    /**
     * Makes all properties hidden by default and marks only the given
     * properties as visible.
     *
     * @param string[]|string $keys
     *
     * @return static
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function only(array|string $keys): static;

}
