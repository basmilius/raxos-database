<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Raxos\Database\Error\DatabaseException;

/**
 * Interface RelationAttributeInterface
 *
 * @property bool $eagerLoad
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.16
 */
interface RelationAttributeInterface
{

    /**
     * Restores the relation attribute from saved state.
     *
     * @param array $state
     *
     * @return self
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public static function __set_state(array $state): self;

}
