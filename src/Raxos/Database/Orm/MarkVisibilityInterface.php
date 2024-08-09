<?php
declare(strict_types=1);

namespace Raxos\Database\Orm;

/**
 * Interface MarkVisibilityInterface
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm
 * @since 1.0.16
 */
interface MarkVisibilityInterface
{

    /**
     * Marks the given keys as hidden.
     *
     * @param string[]|string $keys
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function makeHidden(array|string $keys): static;

    /**
     * Marks the given keys as visible.
     *
     * @param string[]|string $keys
     *
     * @return $this
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function makeVisible(array|string $keys): static;

}
