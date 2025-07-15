<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Contract;

/**
 * Interface RelationAttributeInterface
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Contract
 * @since 1.1.0
 */
interface RelationAttributeInterface
{

    public bool $eagerLoad {
        get;
    }

}
